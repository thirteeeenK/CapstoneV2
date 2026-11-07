<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StorePromotionRequest;
use App\Http\Requests\Admin\UpdatePromotionRequest;
use App\Models\DestinationModel;
use App\Models\LuckyOffer;
use App\Models\Promotion;
use App\Models\PromotionRedemption;
use App\Models\User;
use App\Services\AdminAuditService;
use App\Services\LuckyOfferService;
use App\Services\SystemSettingsService;
use Illuminate\Http\Request;

class AdminPromotionController extends Controller
{
    /**
     * Lucky promotion campaigns with live search and status filters.
     */
    public function index(Request $request, SystemSettingsService $settings, LuckyOfferService $offers)
    {
        $search = $request->query('search');
        $visibility = $request->query('visibility');

        $promotions = Promotion::query()
            ->withCount(Promotion::usageCounts())
            ->when($search, fn ($q) => $q->where('name', 'ilike', "%{$search}%"))
            ->when($visibility === 'active', fn ($q) => $q->where('is_active', true))
            ->when($visibility === 'inactive', fn ($q) => $q->where('is_active', false))
            ->orderByDesc('is_active')->orderByDesc('id')->paginate(15)->withQueryString();

        if ($request->ajax()) {
            return view('admin.promotions._results', compact('promotions'));
        }

        $stats = [
            'active' => Promotion::where('is_active', true)->count(),
            'wins' => LuckyOffer::whereNotNull('promotion_id')->count(),
            'reserved' => PromotionRedemption::where('status', PromotionRedemption::STATUS_RESERVED)->count(),
            'redeemed' => PromotionRedemption::where('status', PromotionRedemption::STATUS_REDEEMED)->count(),
        ];

        return view('admin.promotions.index', compact('promotions', 'search', 'visibility', 'stats') + [
            'dailyAttempts' => $settings->section('lucky')['daily_attempts'], 'attemptsRevision' => $settings->revision('lucky'),
            'exhausted' => $offers->exhaustedTravelers(),
        ]);
    }

    public function create()
    {
        return view('admin.promotions.create', ['promotion' => new Promotion([
            'is_active' => false, 'win_percent' => Promotion::DEFAULT_WIN_PERCENT, 'discount_type' => Promotion::TYPE_PERCENT, ...StorePromotionRequest::LIMIT_DEFAULTS,
        ]), 'destinations' => DestinationModel::orderBy('name')->get(['id', 'name'])]);
    }

    public function store(StorePromotionRequest $request)
    {
        $promotion = Promotion::create($request->promotionAttributes() + StorePromotionRequest::LIMIT_DEFAULTS + [
            'created_by_admin_id' => auth('admin')->id(), 'updated_by_admin_id' => auth('admin')->id(),
        ]);
        AdminAuditService::log($promotion);

        return redirect()->route('admin.promotions.index')->with('success', "Promotion '{$promotion->name}' saved".($promotion->is_active ? ' and active.' : ' as inactive. Enable it when its terms are final.'));
    }

    public function edit(Promotion $promotion)
    {
        return view('admin.promotions.edit', ['promotion' => $promotion->loadCount(Promotion::usageCounts()), 'destinations' => DestinationModel::orderBy('name')->get(['id', 'name'])]);
    }

    /**
     * Rule changes raise the revision and apply only to discounts won afterwards.
     */
    public function update(UpdatePromotionRequest $request, Promotion $promotion)
    {
        $oldValues = $promotion->getOriginal();
        $promotion->fill($request->promotionAttributes());
        if ($promotion->isDirty(Promotion::TERM_FIELDS)) {
            $promotion->revision++;
        }
        $promotion->updated_by_admin_id = auth('admin')->id();
        $promotion->save();
        AdminAuditService::log($promotion, $oldValues);

        return redirect()->route('admin.promotions.index')->with('success', "Promotion '{$promotion->name}' updated. Discounts already won keep their original terms.");
    }

    /**
     * Give a traveler the full daily Lucky limit back.
     */
    public function resetAttempts(User $user, LuckyOfferService $offers)
    {
        $before = $offers->attempts($user);
        $offers->resetAttempts($user);
        AdminAuditService::recordAction($user, 'lucky_attempts_reset', ['attempts_left' => $before['left']], ['attempts_left' => $before['limit']], auth('admin')->user());

        return redirect()->route('admin.promotions.index')->with('success', "{$user->name}'s Lucky attempts were reset.");
    }

    /**
     * Pausing stops new codes and new redemptions; bookings that already
     * reserved a code keep their recorded discount.
     */
    public function toggle(Request $request, Promotion $promotion)
    {
        $oldValues = ['is_active' => $promotion->is_active];
        $promotion->forceFill(['is_active' => ! $promotion->is_active, 'updated_by_admin_id' => auth('admin')->id()])->save();
        AdminAuditService::log($promotion, $oldValues);
        $message = "Promotion '{$promotion->name}' is now ".($promotion->is_active ? 'active.' : 'paused. Existing bookings keep their recorded discount.');

        if ($request->wantsJson() || $request->ajax()) {
            return response()->json(['success' => true, 'is_active' => $promotion->is_active, 'message' => $message]);
        }

        return back()->with('success', $message);
    }
}
