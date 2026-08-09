<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\BookingItem;
use App\Notifications\BookingApproved;
use App\Notifications\BookingCancelled;
use App\Notifications\BookingNotification;
use App\Notifications\BookingPaid;
use App\Notifications\BookingRejected;
use App\Services\BookingRequestService;
use App\Services\RoomAvailabilityService;
use Illuminate\Http\Request;

class AdminBookingController extends Controller
{
    protected RoomAvailabilityService $availabilityService;
    protected BookingRequestService $bookingRequestService;

    public function __construct(RoomAvailabilityService $availabilityService, BookingRequestService $bookingRequestService)
    {
        $this->availabilityService = $availabilityService;
        $this->bookingRequestService = $bookingRequestService;
    }

    /**
     * List bookings with filters (pending queue first).
     */
    public function index(Request $request)
    {
        $search = $request->query('search');
        $status = $request->query('status');

        $query = Booking::with('user')->withCount('items');

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('booking_code', 'ilike', "%{$search}%")
                    ->orWhere('contact_name', 'ilike', "%{$search}%")
                    ->orWhere('contact_email', 'ilike', "%{$search}%");
            });
        }

        $allStatuses = ['pending', 'approved', 'paid', 'completed', 'rejected', 'cancelled', 'expired'];

        if ($status) {
            if ($status === 'closed') {
                $query->whereIn('status', ['rejected', 'cancelled', 'expired', 'completed']);
            } elseif (in_array($status, $allStatuses, true)) {
                $query->where('status', $status);
            }
        }

        $bookings = $query->orderByRaw(
            "CASE status
                WHEN 'pending' THEN 0
                WHEN 'approved' THEN 1
                ELSE 2
             END, created_at desc"
        )->paginate(15)->withQueryString();

        $stats = [
            'pending' => Booking::where('status', Booking::STATUS_PENDING)->count(),
            'approved' => Booking::where('status', Booking::STATUS_APPROVED)->count(),
            'paid' => Booking::where('status', Booking::STATUS_PAID)->count(),
            'total' => Booking::count(),
        ];

        if ($request->ajax()) {
            return view('admin.bookings._table', compact('bookings'));
        }

        return view('admin.bookings.index', compact('bookings', 'search', 'status', 'stats'));
    }

    /**
     * Booking detail with per-item availability auto-check.
     */
    public function show($id)
    {
        $booking = Booking::with(['items', 'user', 'reviewer', 'history'])
            ->findOrFail($id);

        $roomAvailability = [];
        foreach ($booking->items as $item) {
            if ($item->item_type === 'room' && $item->check_in_date && $item->check_out_date && $item->itemable) {
                $roomAvailability[$item->id] = $this->availabilityService->check(
                    $item->itemable,
                    $item->check_in_date->copy()->startOfDay(),
                    $item->check_out_date->copy()->startOfDay(),
                    $booking->id
                );
            }
        }

        return view('admin.bookings.show', compact('booking', 'roomAvailability'));
    }

    /**
     * Approve (possibly partially) the booking and open the payment window.
     */
    public function approve(Request $request, $id)
    {
        $booking = Booking::with('items')->findOrFail($id);

        if ($booking->status !== Booking::STATUS_PENDING) {
            return back()->with('error', 'Only pending bookings can be approved.');
        }

        $validated = $request->validate([
            'items' => 'nullable|array',
            'items.*.include' => 'nullable|boolean',
            'items.*.quantity' => 'nullable|integer|min:1|max:50',
            'items.*.admin_note' => 'nullable|string|max:500',
            'admin_notes' => 'nullable|string|max:2000',
            'admin_discount_amount' => 'nullable|numeric|min:0|max:999999',
            'admin_surcharge_amount' => 'nullable|numeric|min:0|max:999999',
            'price_adjustment_reason' => 'nullable|string|max:500',
        ]);

        $adminDiscount = (float) ($validated['admin_discount_amount'] ?? 0);
        $adminSurcharge = (float) ($validated['admin_surcharge_amount'] ?? 0);

        if ($adminDiscount > 0 || $adminSurcharge > 0) {
            if (empty(trim($validated['price_adjustment_reason'] ?? ''))) {
                return back()->withInput()->with('error', 'Please provide a reason for the price adjustment.');
            }

            $booking->admin_discount_amount = $adminDiscount;
            $booking->admin_surcharge_amount = $adminSurcharge;
            $booking->price_adjustment_reason = trim($validated['price_adjustment_reason']);
            $booking->price_adjusted_at = now();
        }

        $adjustments = [];
        foreach ($booking->items as $item) {
            $raw = $validated['items'][$item->id] ?? null;
            if ($raw) {
                $adjustments[$item->id] = [
                    'include' => !empty($raw['include']),
                    'quantity' => (int) ($raw['quantity'] ?? $item->quantity),
                    'admin_note' => $raw['admin_note'] ?? null,
                ];
            }
        }

        $totals = $this->bookingRequestService->repriceForApproval($booking, $adjustments);

        if ($totals['included'] === 0) {
            return back()->with('error', 'Cannot approve a booking with no available items. Reject it instead.');
        }

        $approvalNote = sprintf(
            'Approved with %d item(s) available%s.',
            $totals['included'],
            $totals['excluded'] > 0 ? ", {$totals['excluded']} unavailable" : ''
        );

        if ($booking->admin_discount_amount > 0 || $booking->admin_surcharge_amount > 0) {
            $approvalNote .= ' Admin price adjustment applied';
            if ($booking->admin_discount_amount > 0) {
                $approvalNote .= ' (-₱' . number_format((float) $booking->admin_discount_amount, 2) . ')';
            }
            if ($booking->admin_surcharge_amount > 0) {
                $approvalNote .= ' (+₱' . number_format((float) $booking->admin_surcharge_amount, 2) . ')';
            }
            $approvalNote .= ': ' . $booking->price_adjustment_reason;
        }

        $updated = $booking->transitionTo(
            Booking::STATUS_APPROVED,
            [Booking::STATUS_PENDING],
            [
                'admin_discount_amount' => $booking->admin_discount_amount,
                'admin_surcharge_amount' => $booking->admin_surcharge_amount,
                'price_adjustment_reason' => $booking->price_adjustment_reason,
                'price_adjusted_at' => $booking->price_adjusted_at,
                'approved_at' => now(),
                'payment_deadline' => now()->addHours(48),
                'reviewed_by_admin_id' => auth('admin')->id(),
                'admin_notes' => $validated['admin_notes'] ?? null,
            ],
            $approvalNote
        );

        if ($updated) {
            BookingNotification::send($booking, new BookingApproved($booking));
        }

        return redirect()->route('admin.bookings.show', $booking->id)->with(
            'success',
            "Booking {$booking->booking_code} approved. Payment window of 48 hours opened" .
            ($totals['excluded'] > 0 ? " ({$totals['excluded']} item(s) excluded)." : '.')
        );
    }

    /**
     * Reject a booking request (availability could not be fulfilled).
     */
    public function reject(Request $request, $id)
    {
        $booking = Booking::findOrFail($id);

        $validated = $request->validate([
            'rejection_reason' => 'required|string|max:2000',
        ]);

        $updated = $booking->transitionTo(
            Booking::STATUS_REJECTED,
            [Booking::STATUS_PENDING],
            [
                'rejected_at' => now(),
                'rejection_reason' => $validated['rejection_reason'],
                'reviewed_by_admin_id' => auth('admin')->id(),
            ],
            'Rejected: ' . $validated['rejection_reason']
        );

        if (!$updated) {
            return back()->with('error', 'Only pending bookings can be rejected.');
        }

        BookingNotification::send($booking, new BookingRejected($booking));

        return redirect()->route('admin.bookings.show', $booking->id)
            ->with('success', "Booking {$booking->booking_code} rejected. The customer has been notified.");
    }

    /**
     * Admin-initiated cancellation.
     */
    public function cancel(Request $request, $id)
    {
        $booking = Booking::findOrFail($id);

        $cancellationReason = $request->input('cancellation_reason') ?: 'Cancelled by SunnyTrips admin.';

        $updated = $booking->transitionTo(
            Booking::STATUS_CANCELLED,
            [Booking::STATUS_PENDING, Booking::STATUS_APPROVED],
            ['cancelled_at' => now(), 'cancellation_reason' => $cancellationReason],
            'Cancelled: ' . $cancellationReason
        );

        if (!$updated) {
            return back()->with('error', 'This booking cannot be cancelled in its current state.');
        }

        BookingNotification::send($booking, new BookingCancelled($booking));

        return redirect()->route('admin.bookings.show', $booking->id)
            ->with('success', "Booking {$booking->booking_code} cancelled.");
    }

    /**
     * Manually mark a booking as paid (simulator/manual fallback).
     */
    public function markPaid(Request $request, $id)
    {
        $booking = Booking::findOrFail($id);

        $validated = $request->validate([
            'payment_reference' => 'nullable|string|max:100',
        ]);

        $data = ['paid_at' => now(), 'payment_status' => Booking::PAYMENT_PAID];
        if (!empty($validated['payment_reference'])) {
            $data['payment_reference'] = $validated['payment_reference'];
        }

        $updated = $booking->transitionTo(Booking::STATUS_PAID, [Booking::STATUS_APPROVED], $data, 'Marked paid manually by admin.');

        if (!$updated) {
            return back()->with('error', 'Only approved bookings awaiting payment can be marked paid.');
        }

        BookingNotification::send($booking, new BookingPaid($booking));

        return redirect()->route('admin.bookings.show', $booking->id)
            ->with('success', "Booking {$booking->booking_code} marked as paid.");
    }

    /**
     * Mark a paid booking as completed.
     */
    public function markCompleted($id)
    {
        $booking = Booking::findOrFail($id);

        $updated = $booking->transitionTo(Booking::STATUS_COMPLETED, [Booking::STATUS_PAID], [], 'Marked completed by admin.');

        if (!$updated) {
            return back()->with('error', 'Only paid bookings can be marked completed.');
        }

        return redirect()->route('admin.bookings.show', $booking->id)
            ->with('success', "Booking {$booking->booking_code} marked as completed.");
    }

    /**
     * Mark a paid booking as refunded (manual refund workflow).
     */
    public function markRefunded(Request $request, $id)
    {
        $booking = Booking::findOrFail($id);

        if ($booking->payment_status !== Booking::PAYMENT_PAID) {
            return back()->with('error', 'Only paid bookings can be refunded.');
        }

        $note = $request->input('refund_note') ?: 'Manual refund processed by admin.';

        $booking->payment_status = Booking::PAYMENT_REFUNDED;
        $booking->save();

        \App\Models\BookingStatusHistory::create([
            'booking_id' => $booking->id,
            'from_status' => $booking->status,
            'to_status' => $booking->status,
            'note' => 'Payment refunded: ' . $note,
            'actor_type' => auth('admin')->user() ? get_class(auth('admin')->user()) : null,
            'actor_id' => auth('admin')->id(),
        ]);

        return redirect()->route('admin.bookings.show', $booking->id)
            ->with('success', "Booking {$booking->booking_code} marked as refunded.");
    }
}
