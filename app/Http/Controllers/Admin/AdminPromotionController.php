<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StorePromotionRequest;
use App\Http\Requests\Admin\UpdatePromotionRequest;
use App\Models\DestinationModel;
use App\Models\LuckyOffer;
use App\Models\Promotion;
use App\Models\PromotionRedemption;
use App\Services\AdminAuditService;
use Illuminate\Http\Request;

class AdminPromotionController extends Controller
{
    /**
     * Lucky promotion campaigns with live search and status filters.
     */
    public function index(Request $request)
    {
        $search = $request->query('search');
        $visibility = $request->query('visibility');

        $promotions = Promotion::query()
            ->withCount(['offers as codes_issued_count' => fn ($q) => $q->whereNotNull('code')])
            ->withCount(['redemptions as reserved_count' => fn ($q) => $q->where('status', PromotionRedemption::STATUS_RESERVED)])
            ->withCount(['redemptions as redeemed_count' => fn ($q) => $q->where('status', PromotionRedemption::STATUS_REDEEMED)])
            ->when($search, fn ($q) => $q->where(fn ($inner) => $inner->where('name', 'ilike', "%{$search}%")->orWhere('code_prefix', 'ilike', "%{$search}%")))
            ->when($visibility === 'active', fn ($q) => $q->where('is_active', true))
            ->when($visibility === 'inactive', fn ($q) => $q->where('is_active', false))
            ->orderByDesc('is_active')->orderByDesc('id')->paginate(15)->withQueryString();

        if ($request->ajax()) {
            return view('admin.promotions._results', compact('promotions'));
        }

        $stats = [
            'active' => Promotion::where('is_active', true)->count(),
            'codes' => LuckyOffer::whereNotNull('code')->count(),
            'reserved' => PromotionRedemption::where('status', PromotionRedemption::STATUS_RESERVED)->count(),
            'redeemed' => PromotionRedemption::where('status', PromotionRedemption::STATUS_REDEEMED)->count(),
        ];

        return view('admin.promotions.index', compact('promotions', 'search', 'visibility', 'stats'));
    }

    public function create()
    {
        return view('admin.promotions.create', ['promotion' => new Promotion([
            'is_active' => false, 'discount_type' => Promotion::TYPE_PERCENT, 'per_traveler_limit' => 1,
            'codes_per_traveler_per_day' => 3, 'offer_valid_hours' => 72, 'award_weight' => 1,
        ]), 'destinations' => DestinationModel::orderBy('name')->get(['id', 'name'])]);
    }

    public function store(StorePromotionRequest $request)
    {
        $promotion = Promotion::create($request->promotionAttributes() + [
            'created_by_admin_id' => auth('admin')->id(), 'updated_by_admin_id' => auth('admin')->id(),
        ]);
        AdminAuditService::log($promotion);

        return redirect()->route('admin.promotions.index')->with('success', "Promotion '{$promotion->name}' saved".($promotion->is_active ? ' and active.' : ' as inactive. Enable it when its terms are final.'));
    }

    public function edit(Promotion $promotion)
    {
        return view('admin.promotions.edit', ['promotion' => $promotion, 'destinations' => DestinationModel::orderBy('name')->get(['id', 'name'])]);
    }

    /**
     * Rule changes raise the revision and apply only to codes issued afterwards.
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

        return redirect()->route('admin.promotions.index')->with('success', "Promotion '{$promotion->name}' updated. Codes issued earlier keep their original terms.");
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
