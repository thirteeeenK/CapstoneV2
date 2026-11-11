<?php

namespace App\Http\Controllers;

use App\Models\Booking;
use App\Services\BookingExpiryService;
use App\Services\BookingVoucherService;
use App\Services\Payment\GoTymePaymentService;
use App\Services\Payment\PaymentService;
use App\Services\TravelDocumentPdf;
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
    public function show($bookingCode, Request $request, BookingVoucherService $vouchers)
    {
        $booking = $this->loadOwnedBooking($bookingCode);

        if ($this->expiryService->expireIfDue($booking)) {
            return redirect()->route('booking.show', $booking->booking_code)
                ->with('error', 'This booking expired because payment was not completed by the agreed payment deadline.');
        }

        return view('booking.show', [
            'booking' => $booking,
            'gateways' => $this->paymentService->availableGateways(),
            'paymentSummary' => $this->goTymePaymentService->summary($booking),
            'paymentTransactions' => $booking->payment_plan_version === 2 ? $booking->paymentTransactions()->get() : collect(),
            'canRequestCancellation' => BookingPaymentController::canRequestCancellation($booking),
            'voucherAvailable' => $vouchers->isAvailable($booking),
            'voucherName' => $vouchers->isConfirmed($booking) ? 'Voucher' : 'Booking Summary',
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
        $data = ['itinerary' => $snapshot, 'reference' => 'Booking '.$booking->booking_code.' · Saved itinerary', 'referenceLabel' => 'Booking reference', 'referenceCode' => $booking->booking_code, 'booking' => $booking];
        if ($request->boolean('print')) {
            return view('trip-planner.document', $data);
        }

        return TravelDocumentPdf::render('trip-planner.document', $data)->download('sunnytrips-'.$booking->booking_code.'-itinerary.pdf');
    }

    /**
     * The laid-out travel voucher (or booking summary before payment) as a PDF download,
     * or as a printable page with ?print=1.
     */
    public function voucher(string $code, Request $request, BookingVoucherService $vouchers): mixed
    {
        $booking = $this->loadOwnedBooking($code);
        abort_unless($vouchers->isAvailable($booking), 404);
        if ($request->boolean('print')) {
            return view('booking.voucher', $vouchers->viewData($booking) + ['webView' => true]);
        }

        return $vouchers->pdf($booking)->download($vouchers->filename($booking));
    }
}
