<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ActivityModel;
use App\Models\AddOnModel;
use App\Models\Booking;
use App\Models\BookingItem;
use App\Models\ChatbotAbuseReport;
use App\Models\HotelModel;
use App\Models\Package;
use App\Models\Review;
use App\Models\ReviewSummary;
use App\Models\RoomType;
use App\Models\SupportInquiry;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function index(): View
    {
        // Plain arrays only: hydrated models break unserialize on serializing cache drivers.
        $data = Cache::remember('admin.dashboard.v2', 300, fn () => $this->dashboardData());

        return view('admin.dashboard', $data);
    }

    /**
     * All aggregates the dashboard needs in one cached pass.
     *
     * @return array<string, mixed>
     */
    protected function dashboardData(): array
    {
        $now = now();
        $todayStart = $now->copy()->startOfDay();
        $weekStart = $now->copy()->subDays(7)->startOfDay();
        $monthStart = $now->copy()->subDays(30)->startOfDay();

        // --- Action queue ---
        $pendingCount = Booking::where('status', Booking::STATUS_PENDING)->count();
        $stalePending = Booking::where('status', Booking::STATUS_PENDING)
            ->where('created_at', '<', $now->copy()->subHours(48));
        $staleCount = (clone $stalePending)->count();
        $oldestStale = (clone $stalePending)->orderBy('created_at')->value('created_at');

        $approvedQuery = Booking::where('status', Booking::STATUS_APPROVED);
        $approvedCount = (clone $approvedQuery)->count();
        $pipelineValue = (float) (clone $approvedQuery)->sum('net_amount');
        $deadlineRisk = (clone $approvedQuery)
            ->whereNotNull('payment_deadline')
            ->where('payment_deadline', '<=', $now->copy()->addDay())
            ->count();

        $cancelRequests = Booking::where('status', Booking::STATUS_CANCELLATION_REQUESTED)->count();

        $supportOpen = SupportInquiry::whereIn('status', [
            SupportInquiry::STATUS_PENDING,
            SupportInquiry::STATUS_HUMAN_ACTIVE,
        ])->count();
        $supportUnassigned = SupportInquiry::where('status', SupportInquiry::STATUS_PENDING)
            ->whereNull('assigned_admin_id')
            ->count();
        $oldestTicket = SupportInquiry::whereIn('status', [
            SupportInquiry::STATUS_PENDING,
            SupportInquiry::STATUS_HUMAN_ACTIVE,
        ])->orderBy('requested_at')->value('requested_at');

        $abusePending = ChatbotAbuseReport::where('status', 'pending')->count();
        $expiredWeek = Booking::where('status', Booking::STATUS_EXPIRED)
            ->where('created_at', '>=', $weekStart)
            ->count();

        // --- Business KPIs ---
        $kpi = [];
        foreach (['today' => $todayStart, 'week' => $weekStart, 'month' => $monthStart] as $key => $from) {
            $scope = Booking::where('created_at', '>=', $from);
            $kpi[$key] = [
                'bookings' => (clone $scope)->count(),
                'collected' => (float) (clone $scope)->where('payment_status', Booking::PAYMENT_PAID)->sum('net_amount'),
            ];
        }
        $monthScope = Booking::where('created_at', '>=', $monthStart);
        $kpi['month']['pipeline'] = (float) (clone $monthScope)->where('status', Booking::STATUS_APPROVED)->sum('net_amount');
        $kpi['month']['avg'] = $kpi['month']['bookings'] > 0
            ? round((float) (clone $monthScope)->avg('net_amount'), 2)
            : 0.00;

        // --- 30-day trend (bookings + collected revenue per day) ---
        $rows = Booking::where('created_at', '>=', $monthStart)
            ->selectRaw('DATE(created_at) as day, COUNT(*) as bookings')
            ->selectRaw("COALESCE(SUM(CASE WHEN payment_status = 'paid' THEN net_amount ELSE 0 END), 0) as collected")
            ->groupBy('day')
            ->orderBy('day')
            ->get()
            ->keyBy('day');

        $trendLabels = [];
        $trendBookings = [];
        $trendRevenue = [];
        $peakDay = null;
        for ($i = 29; $i >= 0; $i--) {
            $day = $now->copy()->subDays($i)->format('Y-m-d');
            $row = $rows->get($day);
            $count = $row ? (int) $row->bookings : 0;
            $trendLabels[] = Carbon::parse($day)->format('M j');
            $trendBookings[] = $count;
            $trendRevenue[] = $row ? round((float) $row->collected, 2) : 0;
            if ($peakDay === null || $count > $peakDay['bookings']) {
                $peakDay = ['date' => $day, 'bookings' => $count];
            }
        }

        // --- Status funnel (30d) ---
        $statusCounts = Booking::where('created_at', '>=', $monthStart)
            ->selectRaw('status, COUNT(*) as c')
            ->groupBy('status')
            ->pluck('c', 'status')
            ->toArray();

        // --- Top sellers (30d, real demand only) ---
        $topSellers = BookingItem::query()
            ->join('bookings', 'bookings.id', '=', 'booking_items.booking_id')
            ->where('bookings.created_at', '>=', $monthStart)
            ->whereNotIn('bookings.status', [Booking::STATUS_REJECTED, Booking::STATUS_CANCELLED, Booking::STATUS_EXPIRED])
            ->select('booking_items.item_title', DB::raw('COUNT(*) as bookings'), DB::raw('COALESCE(SUM(booking_items.subtotal), 0) as revenue'))
            ->groupBy('booking_items.item_title')
            ->orderByDesc('bookings')
            ->limit(5)
            ->get();

        // --- Satisfaction + catalog + growth ---
        $platformSummary = ReviewSummary::where('summarizable_type', ReviewSummary::PLATFORM_OVERALL_TYPE)->first();
        $lowReviewsWeek = Review::where('rating', '<=', 2)->where('created_at', '>=', $weekStart)->count();
        $topNegative = $platformSummary?->top_negative_highlights ?? [];
        $topKeyword = is_array($topNegative) && $topNegative !== [] ? (string) reset($topNegative) : null;

        $hiddenCounts = [
            'hotels' => HotelModel::where('is_shown', false)->count(),
            'rooms' => RoomType::where('is_shown', false)->count(),
            'activities' => ActivityModel::where('is_shown', false)->count(),
            'addons' => AddOnModel::where('is_shown', false)->count(),
        ];
        $packagesExpiring = Package::whereNotNull('valid_to')
            ->whereDate('valid_to', '>=', $todayStart->toDateString())
            ->whereDate('valid_to', '<=', $now->copy()->addDays(14)->toDateString())
            ->count();
        $newUsersWeek = User::where('created_at', '>=', $weekStart)->count();

        // --- Recent activity ---
        $recentBookings = Booking::with('user')->withCount('items')
            ->orderByDesc('created_at')->limit(6)->get();
        $recentTickets = SupportInquiry::with('user')
            ->orderByDesc('requested_at')->limit(5)->get();
        $recentLowReviews = Review::with('user')
            ->where('rating', '<=', 2)->orderByDesc('created_at')->limit(5)->get();

        $insights = $this->buildInsights([
            'staleCount' => $staleCount,
            'oldestStale' => $oldestStale,
            'approvedCount' => $approvedCount,
            'pipelineValue' => $pipelineValue,
            'deadlineRisk' => $deadlineRisk,
            'expiredWeek' => $expiredWeek,
            'peakDay' => $peakDay,
            'topSellers' => $topSellers,
            'platformSummary' => $platformSummary,
            'lowReviewsWeek' => $lowReviewsWeek,
            'topKeyword' => $topKeyword,
            'supportOpen' => $supportOpen,
            'supportUnassigned' => $supportUnassigned,
            'oldestTicket' => $oldestTicket,
            'abusePending' => $abusePending,
            'packagesExpiring' => $packagesExpiring,
            'hiddenCounts' => $hiddenCounts,
        ]);

        return [
            'pendingCount' => $pendingCount,
            'staleCount' => $staleCount,
            'oldestStale' => $oldestStale,
            'approvedCount' => $approvedCount,
            'pipelineValue' => $pipelineValue,
            'deadlineRisk' => $deadlineRisk,
            'cancelRequests' => $cancelRequests,
            'supportOpen' => $supportOpen,
            'supportUnassigned' => $supportUnassigned,
            'abusePending' => $abusePending,
            'expiredWeek' => $expiredWeek,
            'kpi' => $kpi,
            'statusCounts' => $statusCounts,
            'platformSummary' => $platformSummary ? [
                'average_rating' => (float) $platformSummary->average_rating,
                'total_reviews' => (int) $platformSummary->total_reviews,
                'negative_percentage' => (float) $platformSummary->negative_percentage,
            ] : null,
            'lowReviewsWeek' => $lowReviewsWeek,
            'hiddenCounts' => $hiddenCounts,
            'packagesExpiring' => $packagesExpiring,
            'newUsersWeek' => $newUsersWeek,
            'recentBookings' => $recentBookings->map(fn (Booking $b) => [
                'id' => $b->id,
                'booking_code' => $b->booking_code,
                'contact_name' => $b->contact_name,
                'items_count' => $b->items_count,
                'net_amount' => (float) $b->net_amount,
                'status' => $b->status,
            ])->all(),
            'recentTickets' => $recentTickets->map(fn (SupportInquiry $t) => [
                'ticket_number' => $t->ticket_number,
                'user_name' => $t->user?->name ?? 'Guest',
                'status' => $t->status,
            ])->all(),
            'recentLowReviews' => $recentLowReviews->map(fn (Review $r) => [
                'reviewer_name' => $r->reviewer_name ?? $r->user?->name ?? 'Guest',
                'rating' => (int) $r->rating,
                'comment' => (string) $r->comment,
                'time_ago' => $r->created_at?->diffForHumans() ?? '',
            ])->all(),
            'insights' => $insights,
            'chartData' => [
                'trend' => ['labels' => $trendLabels, 'bookings' => $trendBookings, 'revenue' => $trendRevenue],
                'funnel' => $this->funnelSeries($statusCounts),
                'topSellers' => [
                    'labels' => $topSellers->map(fn ($r) => mb_strimwidth((string) $r->item_title, 0, 28, '…'))->values()->all(),
                    'data' => $topSellers->map(fn ($r) => (int) $r->bookings)->values()->all(),
                ],
            ],
        ];
    }

    /**
     * @return array{labels: list<string>, series: list<int>}
     */
    protected function funnelSeries(array $statusCounts): array
    {
        $groups = [
            'Pending' => ['pending'],
            'Approved' => ['approved'],
            'Paid' => ['paid'],
            'Completed' => ['completed'],
            'Closed' => ['rejected', 'cancelled', 'expired', 'cancellation_denied'],
        ];
        $labels = [];
        $series = [];
        foreach ($groups as $label => $keys) {
            $total = 0;
            foreach ($keys as $key) {
                $total += (int) ($statusCounts[$key] ?? 0);
            }
            if ($total > 0) {
                $labels[] = $label;
                $series[] = $total;
            }
        }

        return ['labels' => $labels, 'series' => $series];
    }

    /**
     * Deterministic rule-based insights; only genuinely useful ones fire.
     *
     * @param  array<string, mixed>  $s
     * @return list<array{level: string, icon: string, text: string, route: string, params: array<string, string>}>
     */
    protected function buildInsights(array $s): array
    {
        $insights = [];

        if ($s['staleCount'] > 0) {
            $oldest = $s['oldestStale'] ? Carbon::parse($s['oldestStale'])->format('M j, g:i A') : 'unknown date';
            $insights[] = [
                'level' => 'danger',
                'icon' => 'hourglass_empty',
                'text' => "{$s['staleCount']} pending booking(s) stale over 48h — oldest from {$oldest}.",
                'route' => 'admin.bookings.index',
                'params' => ['status' => 'pending'],
            ];
        }

        if ($s['deadlineRisk'] > 0) {
            $insights[] = [
                'level' => 'warn',
                'icon' => 'payments',
                'text' => '₱'.number_format($s['pipelineValue'], 2)." awaiting payment across {$s['approvedCount']} approved booking(s); {$s['deadlineRisk']} hit payment deadline within 24h.",
                'route' => 'admin.bookings.index',
                'params' => ['status' => 'approved'],
            ];
        } elseif ($s['pipelineValue'] > 0) {
            $insights[] = [
                'level' => 'info',
                'icon' => 'payments',
                'text' => '₱'.number_format($s['pipelineValue'], 2)." pipeline awaiting payment across {$s['approvedCount']} approved booking(s).",
                'route' => 'admin.bookings.index',
                'params' => ['status' => 'approved'],
            ];
        }

        if ($s['expiredWeek'] > 0) {
            $insights[] = [
                'level' => 'warn',
                'icon' => 'event_busy',
                'text' => "{$s['expiredWeek']} booking(s) expired unpaid in the last 7 days — consider deadline or follow-up review.",
                'route' => 'admin.bookings.index',
                'params' => ['status' => 'expired'],
            ];
        }

        if (($s['peakDay']['bookings'] ?? 0) > 0) {
            $top = $s['topSellers']->first();
            $seller = $top ? " Top seller: {$top->item_title} ({$top->bookings} booking(s), 30d)." : '';
            $insights[] = [
                'level' => 'info',
                'icon' => 'trending_up',
                'text' => "Peak day {$s['peakDay']['date']} with {$s['peakDay']['bookings']} booking(s).{$seller}",
                'route' => 'admin.reports.index',
                'params' => [],
            ];
        }

        if ($s['lowReviewsWeek'] > 0) {
            $keyword = $s['topKeyword'] ? " Top complaint theme: “{$s['topKeyword']}”." : '';
            $insights[] = [
                'level' => 'warn',
                'icon' => 'reviews',
                'text' => "{$s['lowReviewsWeek']} low rating review(s) (1–2★) in the last 7 days.{$keyword}",
                'route' => 'admin.reviews.index',
                'params' => [],
            ];
        }

        if ($s['supportOpen'] > 0) {
            $waiting = $s['oldestTicket']
                ? ' Oldest waiting since '.Carbon::parse($s['oldestTicket'])->diffForHumans().'.'
                : '';
            $insights[] = [
                'level' => $s['supportUnassigned'] > 0 ? 'warn' : 'info',
                'icon' => 'support_agent',
                'text' => "{$s['supportOpen']} open support ticket(s) ({$s['supportUnassigned']} unassigned).{$waiting}",
                'route' => 'admin.support.index',
                'params' => [],
            ];
        }

        if ($s['abusePending'] > 0) {
            $insights[] = [
                'level' => 'warn',
                'icon' => 'shield',
                'text' => "{$s['abusePending']} chatbot abuse report(s) awaiting review.",
                'route' => 'admin.users.index',
                'params' => [],
            ];
        }

        if ($s['packagesExpiring'] > 0) {
            $insights[] = [
                'level' => 'info',
                'icon' => 'event_upcoming',
                'text' => "{$s['packagesExpiring']} package(s) expire within 14 days — review validity dates.",
                'route' => 'admin.packages.index',
                'params' => [],
            ];
        }

        return $insights;
    }
}
