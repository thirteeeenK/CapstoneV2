<?php

namespace App\Http\Controllers;

use App\Models\Booking;
use App\Services\BookingExpiryService;
use App\Services\Payment\GoTymePaymentService;
use App\Services\Payment\PaymentService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;

class BookingConfirmationController extends Controller
{
    protected BookingExpiryService $expiryService;

    protected PaymentService $paymentService;

    public function __construct(
        BookingExpiryService $expiryService,
        PaymentService $paymentService,
        protected GoTymePaymentService $goTymePaymentService,
    ) {
        $this->expiryService = $expiryService;
        $this->paymentService = $paymentService;
    }

    /**
     * Display the booking status page (request received / awaiting payment / voucher).
     */
    public function show($bookingCode, Request $request)
    {
        $booking = $this->loadOwnedBooking($bookingCode);

        if ($this->expiryService->expireIfDue($booking)) {
            return redirect()->route('booking.show', $booking->booking_code)
                ->with('error', 'This booking expired because payment was not completed within the 48-hour window.');
        }

        return view('booking.show', [
            'booking' => $booking,
            'gateways' => $this->paymentService->availableGateways(),
            'paymentSummary' => $this->goTymePaymentService->summary($booking),
        ]);
    }

    /**
     * Resolve a booking by code, restricted to its owner or an administrator.
     * 404 is returned (not 403) so booking codes cannot be probed.
     */
    protected function loadOwnedBooking(string $bookingCode): Booking
    {
        $booking = Booking::with(['items', 'user', 'history', 'reviews', 'attachments', 'payments'])
            ->where('booking_code', $bookingCode)
            ->first();

        $isOwner = auth()->check() && $booking && $booking->user_id === auth()->id();
        $isAdmin = auth('admin')->check();

        if (! $booking || (! $isOwner && ! $isAdmin)) {
            abort(404);
        }

        return $booking;
    }

    public function itinerary(string $code, int $version, Request $request): mixed
    {
        $booking = $this->loadOwnedBooking($code);
        $snapshot = $booking->trip_itineraries[$version] ?? null;
        abort_unless(is_array($snapshot), 404);
        $data = ['itinerary' => $snapshot, 'reference' => 'Booking '.$booking->booking_code.' · Saved itinerary'];
        if ($request->boolean('print')) {
            return view('trip-planner.document', $data);
        }

        return Pdf::loadView('trip-planner.document', $data)->setPaper('a4')->download('sunnytrips-'.$booking->booking_code.'-itinerary.pdf');
    }
}
