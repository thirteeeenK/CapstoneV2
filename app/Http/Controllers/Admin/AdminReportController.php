<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Services\GeminiService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class AdminReportController extends Controller
{
    protected GeminiService $gemini;

    public function __construct(GeminiService $gemini)
    {
        $this->gemini = $gemini;
    }

    /**
     * Report page: KPI cards, daily breakdown, detail table.
     */
    public function index(Request $request)
    {
        $data = $this->reportData($request);
        $data['analysis'] = session('analysis');

        return view('admin.reports.index', $data);
    }

    /**
     * Download the report as a PDF.
     */
    public function exportPdf(Request $request)
    {
        $data = $this->reportData($request);

        $pdf = Pdf::loadView('admin.reports.pdf', $data);

        return $pdf->download('sunnytrip-bookings-report-' . now()->format('Y-m-d') . '.pdf');
    }

    /**
     * On-demand AI analysis of the current report filters.
     */
    public function analyze(Request $request)
    {
        $data = $this->reportData($request);
        $payload = $this->buildAnalysisPayload($data);
        $analysis = $this->gemini->analyzeBookingReport($payload);

        if ($request->ajax()) {
            return view('admin.reports._analysis', compact('analysis'));
        }

        return redirect()
            ->route('admin.reports.index', $request->only(['from', 'to', 'status']))
            ->with('analysis', $analysis);
    }

    /**
     * Gather every metric the report needs for the given filters.
     */
    protected function reportData(Request $request): array
    {
        $from = $request->query('from') ? Carbon::parse($request->query('from'))->startOfDay() : now()->subDays(30)->startOfDay();
        $to = $request->query('to') ? Carbon::parse($request->query('to'))->endOfDay() : now()->endOfDay();
        $status = $request->query('status');

        $fromLabel = $from->format('Y-m-d');
        $toLabel = $to->format('Y-m-d');

        $query = Booking::query()
            ->where('created_at', '>=', $from)
            ->where('created_at', '<=', $to);

        if ($status === 'closed') {
            $query->whereIn('status', ['rejected', 'cancelled', 'expired', 'completed']);
        } elseif ($status) {
            $query->where('status', $status);
        }

        $daily = $query->clone()
            ->selectRaw(
                "DATE(created_at) as day, COUNT(*) as bookings, "
                . "COALESCE(SUM(CASE WHEN payment_status = 'paid' THEN net_amount ELSE 0 END), 0) as collected, "
                . "COALESCE(SUM(CASE WHEN status = 'approved' THEN net_amount ELSE 0 END), 0) as estimated"
            )
            ->groupBy('day')
            ->orderByDesc('day')
            ->get();

        $totalBookings = $query->clone()->count();
        $collected = (float) $query->clone()->where('payment_status', Booking::PAYMENT_PAID)->sum('net_amount');
        $estimated = (float) $query->clone()->where('status', Booking::STATUS_APPROVED)->sum('net_amount');
        $avgValue = $totalBookings > 0 ? round((float) $query->clone()->avg('net_amount'), 2) : 0.00;

        $statusCounts = $query->clone()
            ->selectRaw('status, COUNT(*) as c')
            ->groupBy('status')
            ->pluck('c', 'status')
            ->toArray();

        $bookings = $query->clone()
            ->with('user')
            ->withCount('items')
            ->orderByDesc('created_at')
            ->limit(150)
            ->get();

        return compact(
            'from', 'to', 'fromLabel', 'toLabel', 'status',
            'daily', 'totalBookings', 'collected', 'estimated', 'avgValue', 'statusCounts', 'bookings',
        );
    }

    /**
     * Compact, token-efficient stats payload for the AI analysis.
     */
    protected function buildAnalysisPayload(array $data): array
    {
        $peak = $data['daily']->sortByDesc('bookings')->first();

        return [
            'range' => "{$data['fromLabel']} to {$data['toLabel']}",
            'status_filter' => $data['status'] ?: 'all statuses',
            'total_bookings' => $data['totalBookings'],
            'revenue' => [
                'collected' => round($data['collected'], 2),
                'estimated_awaiting_payment' => round($data['estimated'], 2),
            ],
            'average_booking_value' => $data['avgValue'],
            'status_breakdown' => $data['statusCounts'],
            'peak_day' => $peak ? ['date' => $peak['day'], 'bookings' => (int) $peak['bookings']] : null,
            'stale_pending_count' => Booking::where('status', Booking::STATUS_PENDING)
                ->where('created_at', '<', now()->subHours(48))
                ->count(),
            'refunded_count' => Booking::where('payment_status', Booking::PAYMENT_REFUNDED)->count(),
            'admin_price_adjustments' => Booking::where(function ($q) {
                $q->where('admin_discount_amount', '>', 0)->orWhere('admin_surcharge_amount', '>', 0);
            })->count(),
            'expired_count' => Booking::where('status', Booking::STATUS_EXPIRED)->count(),
        ];
    }
}
