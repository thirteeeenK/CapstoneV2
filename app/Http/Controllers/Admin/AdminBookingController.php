<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreAdminBookingRequest;
use App\Models\ActivityModel;
use App\Models\AddOnModel;
use App\Models\Booking;
use App\Models\BookingAttachment;
use App\Models\BookingItem;
use App\Models\BookingStatusHistory;
use App\Models\DestinationModel;
use App\Models\Package;
use App\Models\PassengerCategoryRule;
use App\Models\RoomType;
use App\Models\User;
use App\Notifications\BookingApproved;
use App\Notifications\BookingCancellationApproved;
use App\Notifications\BookingCancellationDenied;
use App\Notifications\BookingCancelled;
use App\Notifications\BookingDocumentAdded;
use App\Notifications\BookingNotification;
use App\Notifications\BookingPaid;
use App\Notifications\BookingRejected;
use App\Notifications\BookingRequestReceived;
use App\Notifications\NewGuestAccountCreated;
use App\Services\AdminAuditService;
use App\Services\BookingRequestService;
use App\Services\RoomAvailabilityService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

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

        $allStatuses = ['pending', 'approved', 'paid', 'completed', 'rejected', 'cancelled', 'expired', 'cancellation_requested', 'cancellation_denied'];

        if ($status) {
            if ($status === 'closed') {
                $query->whereIn('status', ['rejected', 'cancelled', 'expired', 'completed', 'cancellation_denied']);
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
            'cancellation_requested' => Booking::where('status', Booking::STATUS_CANCELLATION_REQUESTED)->count(),
            'total' => Booking::count(),
        ];

        if ($request->ajax()) {
            return view('admin.bookings._table', compact('bookings'));
        }

        return view('admin.bookings.index', compact('bookings', 'search', 'status', 'stats'));
    }

    /**
     * Show booking creation interface for admin proxy/concierge bookings.
     */
    public function create(Request $request)
    {
        $destinations = DestinationModel::with([
            'hotels' => fn ($q) => $q->where('is_shown', true)->with(['rooms' => fn ($rq) => $rq->where('is_shown', true)]),
            'packages' => fn ($q) => $q->where('is_active', true),
            'activities' => fn ($q) => $q->where('is_shown', true),
            'addOns' => fn ($q) => $q->where('is_shown', true),
        ])->get();

        $categoryRules = PassengerCategoryRule::where('is_active', true)->get();

        $selectedUser = null;
        if ($request->filled('user_id')) {
            $selectedUser = User::find($request->query('user_id'));
        }

        return view('admin.bookings.create', compact('destinations', 'categoryRules', 'selectedUser'));
    }

    /**
     * Search registered users for autocomplete in booking builder.
     */
    public function searchUsers(Request $request)
    {
        $term = trim((string) $request->query('query', ''));
        if (strlen($term) < 2) {
            return response()->json([]);
        }

        $users = User::where(function ($q) use ($term) {
            $q->where('name', 'ilike', "%{$term}%")
                ->orWhere('email', 'ilike', "%{$term}%")
                ->orWhere('phone_number', 'ilike', "%{$term}%");
        })
            ->orderBy('name')
            ->limit(10)
            ->get(['id', 'name', 'email', 'phone_number', 'address']);

        return response()->json($users);
    }

    /**
     * Check real-time availability for a room type.
     */
    public function checkAvailability(Request $request)
    {
        $request->validate([
            'room_id' => 'required|exists:rooms,id',
            'check_in_date' => 'required|date',
            'check_out_date' => 'required|date|after:check_in_date',
        ]);

        $room = RoomType::findOrFail($request->room_id);
        $checkIn = Carbon::parse($request->check_in_date);
        $checkOut = Carbon::parse($request->check_out_date);

        $availability = $this->availabilityService->check($room, $checkIn, $checkOut);

        return response()->json($availability);
    }

    /**
     * Store a proxy booking created by admin.
     */
    public function store(StoreAdminBookingRequest $request)
    {
        $validated = $request->validated();

        $isWalkIn = (bool) ($validated['is_walk_in'] ?? false);
        $autoCreate = (bool) ($validated['auto_create_account'] ?? false);
        $targetUser = null;
        $tempPassword = null;
        $accountCreated = false;

        return DB::transaction(function () use ($validated, $isWalkIn, $autoCreate, &$targetUser, &$tempPassword, &$accountCreated, $request) {
            // 1. Resolve User
            if ($isWalkIn) {
                if ($autoCreate) {
                    $existing = User::where('email', $validated['contact_email'])->first();
                    if ($existing) {
                        $targetUser = $existing;
                    } else {
                        $tempPassword = ! empty($validated['temporary_password'])
                            ? trim($validated['temporary_password'])
                            : 'Sunny'.rand(1000, 9999).'!';

                        $targetUser = User::create([
                            'name' => $validated['contact_name'],
                            'email' => $validated['contact_email'],
                            'phone_number' => $validated['contact_phone'],
                            'password' => Hash::make($tempPassword),
                        ]);
                        $accountCreated = true;
                    }
                    $userId = $targetUser->id;
                } else {
                    $userId = null;
                }
            } else {
                $userId = $validated['user_id'];
                $targetUser = User::find($userId);
            }

            // 2. Process Items & Availability
            $calculatedItems = [];
            $totalAmount = 0.00;

            foreach ($validated['items'] as $itemData) {
                $type = $itemData['item_type'];
                $itemId = (int) $itemData['item_id'];
                $qty = max(1, (int) $itemData['quantity']);
                $pax = max(1, (int) $itemData['selected_pax']);
                $checkIn = ! empty($itemData['check_in_date']) ? Carbon::parse($itemData['check_in_date']) : null;
                $checkOut = ! empty($itemData['check_out_date']) ? Carbon::parse($itemData['check_out_date']) : null;

                $nights = ($checkIn && $checkOut) ? max(1, (int) $checkIn->diffInDays($checkOut)) : 1;

                if ($type === 'room') {
                    $room = RoomType::with('hotel')->lockForUpdate()->findOrFail($itemId);

                    // Check room availability
                    if ($checkIn && $checkOut) {
                        $avail = $this->availabilityService->check($room, $checkIn, $checkOut);
                        if ($avail['remaining'] < $qty) {
                            $availMsg = $avail['remaining'] === 0
                                ? 'fully booked for the selected dates'
                                : "only {$avail['remaining']} of {$avail['total_rooms']} unit(s) left for {$checkIn->format('M d')}–{$checkOut->format('M d, Y')}";
                            throw ValidationException::withMessages([
                                'items' => ["Room '{$room->room_name}' — {$availMsg}. You requested {$qty} unit(s). Set Quantity to {$avail['remaining']} and increase Guests (Pax) if you need more guests in one unit, or pick another room type."],
                            ]);
                        }
                    }

                    $unitPrice = $room->calculateNightlyRate($pax);
                    $lineSubtotal = $unitPrice * $qty * $nights;
                    $title = $room->room_name;
                    $subtitle = $room->hotel ? ($room->hotel->hotel_name ?? 'Hotel Stay') : 'Hotel Stay';
                    $hotelName = $room->hotel ? ($room->hotel->hotel_name ?? null) : null;
                } elseif ($type === 'package') {
                    $pkg = Package::with('destination')->findOrFail($itemId);
                    $unitPrice = (float) $pkg->price;
                    $lineSubtotal = $unitPrice * max($pax, $qty);
                    $title = $pkg->name;
                    $subtitle = 'Tour Package • '.($pkg->destination ? $pkg->destination->name : '');
                    $hotelName = null;
                } elseif ($type === 'activity') {
                    $act = ActivityModel::with('destination')->findOrFail($itemId);
                    $effectivePax = max($pax, $qty);
                    $unitPrice = $act->calculateRateForPax($effectivePax);
                    $lineSubtotal = $act->isPerPersonRate() ? $unitPrice * $effectivePax : $unitPrice * $qty;
                    $title = $act->activity_name;
                    $subtitle = 'Activity • '.($act->destination ? $act->destination->name : '');
                    $hotelName = null;
                } elseif ($type === 'addon') {
                    $addon = AddOnModel::with('destination')->findOrFail($itemId);
                    $effectivePax = max($pax, $qty);
                    $unitPrice = $addon->getRateForPax($effectivePax);
                    $lineSubtotal = $unitPrice * $effectivePax;
                    $title = $addon->name;
                    $subtitle = 'Add-on • '.($addon->destination ? $addon->destination->name : '');
                    $hotelName = null;
                } else {
                    throw ValidationException::withMessages(['items' => ['Invalid item type specified.']]);
                }

                $totalAmount += $lineSubtotal;

                $calculatedItems[] = [
                    'item_type' => $type,
                    'item_id' => $itemId,
                    'item_title' => $title,
                    'item_subtitle' => $subtitle,
                    'hotel_name' => $hotelName,
                    'unit_price' => $lineSubtotal / max(1, $qty),
                    'quantity' => $qty,
                    'selected_pax' => $pax,
                    'check_in_date' => $checkIn?->format('Y-m-d'),
                    'check_out_date' => $checkOut?->format('Y-m-d'),
                    'nights' => $nights,
                    'subtotal' => $lineSubtotal,
                    'availability_status' => BookingItem::AVAIL_AVAILABLE,
                ];
            }

            // 3. Passenger Category Rules (guest_manifest)
            $discountAmount = 0.00;
            $surchargeAmount = 0.00;
            $guestManifest = $validated['guest_manifest'] ?? [];

            if (! empty($guestManifest)) {
                $rulesMap = PassengerCategoryRule::getActiveRulesMap();
                foreach ($guestManifest as $g) {
                    $cat = $g['category'] ?? 'Adult';
                    if (isset($rulesMap[$cat])) {
                        $rule = $rulesMap[$cat];
                        if ($rule->adjustment_type === 'discount') {
                            $discountAmount += (float) $rule->amount;
                        } elseif ($rule->adjustment_type === 'surcharge') {
                            $surchargeAmount += (float) $rule->amount;
                        }
                    }
                }
            }

            // 4. Admin Adjustments
            $adminDiscount = (float) ($validated['admin_discount_amount'] ?? 0);
            $adminSurcharge = (float) ($validated['admin_surcharge_amount'] ?? 0);
            $reason = trim((string) ($validated['price_adjustment_reason'] ?? ''));

            $netAmount = max(0.00, $totalAmount - $discountAmount + $surchargeAmount - $adminDiscount + $adminSurcharge);

            // 5. Initial Status Setup
            $initialStatus = $validated['initial_status'];
            $bookingCode = $this->bookingRequestService->generateBookingCode();

            $bookingData = [
                'booking_code' => $bookingCode,
                'user_id' => $userId,
                'status' => $initialStatus,
                'total_amount' => $totalAmount,
                'discount_amount' => $discountAmount,
                'tax_amount' => $surchargeAmount,
                'admin_discount_amount' => $adminDiscount,
                'admin_surcharge_amount' => $adminSurcharge,
                'price_adjustment_reason' => ($adminDiscount > 0 || $adminSurcharge > 0) ? $reason : null,
                'price_adjusted_at' => ($adminDiscount > 0 || $adminSurcharge > 0) ? now() : null,
                'net_amount' => $netAmount,
                'payment_status' => $initialStatus === Booking::STATUS_PAID ? Booking::PAYMENT_PAID : Booking::PAYMENT_UNPAID,
                'payment_method' => $initialStatus === Booking::STATUS_PAID ? ($validated['payment_method'] ?? 'Cash') : null,
                'payment_reference' => $initialStatus === Booking::STATUS_PAID ? ($validated['payment_reference'] ?? null) : null,
                'contact_name' => $validated['contact_name'],
                'contact_email' => $validated['contact_email'],
                'contact_phone' => $validated['contact_phone'],
                'special_requests' => $validated['special_requests'] ?? null,
                'guest_manifest' => $guestManifest,
                'admin_notes' => $validated['admin_notes'] ?? null,
                'booked_by_admin_id' => auth('admin')->id(),
                'booking_source' => $validated['booking_source'],
                'is_walk_in' => $isWalkIn,
            ];

            if ($initialStatus === Booking::STATUS_APPROVED) {
                $bookingData['approved_at'] = now();
                $bookingData['payment_deadline'] = now()->addHours(48);
                $bookingData['reviewed_by_admin_id'] = auth('admin')->id();
            } elseif ($initialStatus === Booking::STATUS_PAID) {
                $bookingData['approved_at'] = now();
                $bookingData['paid_at'] = now();
                $bookingData['reviewed_by_admin_id'] = auth('admin')->id();
            }

            $booking = Booking::create($bookingData);

            // 6. Create Booking Items
            foreach ($calculatedItems as $cItem) {
                $cItem['booking_id'] = $booking->id;
                BookingItem::create($cItem);
            }

            // 7. Audit Logging & Status History
            AdminAuditService::log($booking);

            BookingStatusHistory::create([
                'booking_id' => $booking->id,
                'from_status' => 'created',
                'to_status' => $booking->status,
                'note' => 'Booking created by admin ('.(auth('admin')->user()?->name ?? 'Admin').') via '.str_replace('_', ' ', $validated['booking_source']),
                'actor_type' => auth('admin')->user() ? get_class(auth('admin')->user()) : null,
                'actor_id' => auth('admin')->id(),
            ]);

            // 8. Notifications
            if ($accountCreated && $targetUser && $tempPassword) {
                session()->flash('new_user_account', [
                    'name' => $targetUser->name,
                    'email' => $targetUser->email,
                    'password' => $tempPassword,
                ]);

                try {
                    $resetToken = Password::broker()->createToken($targetUser);
                    $resetUrl = route('password.reset', ['token' => $resetToken, 'email' => $targetUser->email]);
                    $targetUser->notify(new NewGuestAccountCreated($booking, $tempPassword, $resetUrl));
                } catch (\Throwable $e) {
                    Log::warning('Could not deliver welcome email to '.$targetUser->email.': '.$e->getMessage());
                }
            }

            if ($booking->status === Booking::STATUS_PENDING) {
                BookingNotification::send($booking, new BookingRequestReceived($booking));
            } elseif ($booking->status === Booking::STATUS_APPROVED) {
                BookingNotification::send($booking, new BookingApproved($booking));
            } elseif ($booking->status === Booking::STATUS_PAID) {
                BookingNotification::send($booking, new BookingPaid($booking));
            }

            $successMsg = "Booking {$booking->booking_code} created successfully on behalf of {$booking->contact_name}.";

            if ($request->wantsJson()) {
                return response()->json([
                    'success' => true,
                    'message' => $successMsg,
                    'new_user_account' => $accountCreated ? [
                        'name' => $targetUser->name,
                        'email' => $targetUser->email,
                        'password' => $tempPassword,
                    ] : null,
                    'redirect_url' => route('admin.bookings.show', $booking->id),
                ]);
            }

            return redirect()->route('admin.bookings.show', $booking->id)->with('success', $successMsg);
        });
    }

    /**
     * Booking detail with per-item availability auto-check.
     */
    public function show($id)
    {
        $booking = Booking::with(['items', 'user', 'reviewer', 'history', 'attachments'])
            ->findOrFail($id);

        $flightHints = [];
        foreach ($booking->items as $item) {
            if ($item->item_type === 'package' && $item->item_id) {
                $pkg = Package::find($item->item_id);
                $text = strtolower(implode(' ', (array) ($pkg->generic_inclusions ?? [])));
                $flightHints[$item->id] = str_contains($text, 'air') || str_contains($text, 'flight') || str_contains($text, 'ticket');
            }
        }

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

        return view('admin.bookings.show', compact('booking', 'roomAvailability', 'flightHints'));
    }

    /**
     * Approve (possibly partially) the booking and open the payment window.
     */
    public function approve(Request $request, $id)
    {
        $booking = Booking::with('items')->findOrFail($id);
        $oldValues = $booking->getOriginal();

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
                    'include' => ! empty($raw['include']),
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
                $approvalNote .= ' (-₱'.number_format((float) $booking->admin_discount_amount, 2).')';
            }
            if ($booking->admin_surcharge_amount > 0) {
                $approvalNote .= ' (+₱'.number_format((float) $booking->admin_surcharge_amount, 2).')';
            }
            $approvalNote .= ': '.$booking->price_adjustment_reason;
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
            AdminAuditService::log($booking, $oldValues);
            BookingNotification::send($booking, new BookingApproved($booking));
        }

        return redirect()->route('admin.bookings.show', $booking->id)->with(
            'success',
            "Booking {$booking->booking_code} approved. Payment window of 48 hours opened".
            ($totals['excluded'] > 0 ? " ({$totals['excluded']} item(s) excluded)." : '.')
        );
    }

    /**
     * Reject a booking request (availability could not be fulfilled).
     */
    public function reject(Request $request, $id)
    {
        $booking = Booking::findOrFail($id);
        $oldValues = $booking->getOriginal();

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
            'Rejected: '.$validated['rejection_reason']
        );

        if (! $updated) {
            return back()->with('error', 'Only pending bookings can be rejected.');
        }

        AdminAuditService::log($booking->fresh() ?? $booking, $oldValues);
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
        $oldValues = $booking->getOriginal();

        $cancellationReason = $request->input('cancellation_reason') ?: 'Cancelled by SunnyTrips admin.';

        $updated = $booking->transitionTo(
            Booking::STATUS_CANCELLED,
            [Booking::STATUS_PENDING, Booking::STATUS_APPROVED],
            ['cancelled_at' => now(), 'cancellation_reason' => $cancellationReason],
            'Cancelled: '.$cancellationReason
        );

        if (! $updated) {
            return back()->with('error', 'This booking cannot be cancelled in its current state.');
        }

        AdminAuditService::log($booking->fresh() ?? $booking, $oldValues);
        BookingNotification::send($booking, new BookingCancelled($booking));

        return redirect()->route('admin.bookings.show', $booking->id)
            ->with('success', "Booking {$booking->booking_code} cancelled.");
    }

    /**
     * Approve a user cancellation request → cancelled.
     */
    public function approveCancellation(Request $request, $id)
    {
        $booking = Booking::findOrFail($id);
        $oldValues = $booking->getOriginal();

        $validated = $request->validate([
            'admin_message' => 'nullable|string|max:2000',
        ]);

        $adminMessage = trim($validated['admin_message'] ?? '') ?: 'Cancellation approved by admin.';
        $reason = $booking->cancellation_request_reason ?: $adminMessage;

        $updated = $booking->transitionTo(
            Booking::STATUS_CANCELLED,
            [Booking::STATUS_CANCELLATION_REQUESTED],
            [
                'cancelled_at' => now(),
                'cancellation_reason' => $adminMessage,
                'reviewed_by_admin_id' => auth('admin')->id(),
            ],
            'Cancellation approved: '.$adminMessage.' (user reason: '.$reason.')'
        );

        if (! $updated) {
            return back()->with('error', 'Only bookings with a pending cancellation request can be approved.');
        }

        AdminAuditService::log($booking, $oldValues);
        $booking->refresh();
        BookingNotification::send($booking, new BookingCancellationApproved($booking));

        return redirect()->route('admin.bookings.show', $booking->id)
            ->with('success', "Cancellation approved for {$booking->booking_code}. The customer has been notified.");
    }

    /**
     * Deny a user cancellation request → cancellation_denied (still reserved).
     * Admin message is required.
     */
    public function denyCancellation(Request $request, $id)
    {
        $booking = Booking::findOrFail($id);
        $oldValues = $booking->getOriginal();

        $validated = $request->validate([
            'admin_message' => 'required|string|min:10|max:2000',
        ]);

        $adminMessage = trim($validated['admin_message']);

        $updated = $booking->transitionTo(
            Booking::STATUS_CANCELLATION_DENIED,
            [Booking::STATUS_CANCELLATION_REQUESTED],
            [
                'cancellation_reason' => $adminMessage,
                'reviewed_by_admin_id' => auth('admin')->id(),
            ],
            'Cancellation denied: '.$adminMessage
        );

        if (! $updated) {
            return back()->with('error', 'Only bookings with a pending cancellation request can be denied.');
        }

        AdminAuditService::log($booking, $oldValues);
        $booking->refresh();
        BookingNotification::send($booking, new BookingCancellationDenied($booking));

        return redirect()->route('admin.bookings.show', $booking->id)
            ->with('success', "Cancellation denied for {$booking->booking_code}. The customer has been notified and the booking remains active.");
    }

    /**
     * Manually mark a booking as paid (simulator/manual fallback).
     */
    public function markPaid(Request $request, $id)
    {
        $booking = Booking::findOrFail($id);
        $oldValues = $booking->getOriginal();

        $validated = $request->validate([
            'payment_reference' => 'nullable|string|max:100',
        ]);

        $data = ['paid_at' => now(), 'payment_status' => Booking::PAYMENT_PAID];
        if (! empty($validated['payment_reference'])) {
            $data['payment_reference'] = $validated['payment_reference'];
        }

        $updated = $booking->transitionTo(Booking::STATUS_PAID, [Booking::STATUS_APPROVED], $data, 'Marked paid manually by admin.');

        if (! $updated) {
            return back()->with('error', 'Only approved bookings awaiting payment can be marked paid.');
        }

        AdminAuditService::log($booking->fresh() ?? $booking, $oldValues);
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
        $oldValues = $booking->getOriginal();

        $updated = $booking->transitionTo(Booking::STATUS_COMPLETED, [Booking::STATUS_PAID], [], 'Marked completed by admin.');

        if (! $updated) {
            return back()->with('error', 'Only paid bookings can be marked completed.');
        }

        AdminAuditService::log($booking->fresh() ?? $booking, $oldValues);

        return redirect()->route('admin.bookings.show', $booking->id)
            ->with('success', "Booking {$booking->booking_code} marked as completed.");
    }

    /**
     * Mark a paid booking as refunded (manual refund workflow).
     */
    public function markRefunded(Request $request, $id)
    {
        $booking = Booking::findOrFail($id);
        $oldValues = $booking->getOriginal();

        if ($booking->payment_status !== Booking::PAYMENT_PAID) {
            return back()->with('error', 'Only paid bookings can be refunded.');
        }

        $note = $request->input('refund_note') ?: 'Manual refund processed by admin.';

        $booking->payment_status = Booking::PAYMENT_REFUNDED;
        $booking->save();

        AdminAuditService::log($booking, $oldValues);

        BookingStatusHistory::create([
            'booking_id' => $booking->id,
            'from_status' => $booking->status,
            'to_status' => $booking->status,
            'note' => 'Payment refunded: '.$note,
            'actor_type' => auth('admin')->user() ? get_class(auth('admin')->user()) : null,
            'actor_id' => auth('admin')->id(),
        ]);

        return redirect()->route('admin.bookings.show', $booking->id)
            ->with('success', "Booking {$booking->booking_code} marked as refunded.");
    }

    /**
     * Send or resend a password reset link to the booking customer/user.
     */
    public function sendPasswordReset($id)
    {
        $booking = Booking::with('user')->findOrFail($id);

        $user = $booking->user;
        if (! $user && ! empty($booking->contact_email)) {
            $user = User::where('email', $booking->contact_email)->first();
        }

        if (! $user) {
            return back()->with('error', 'No registered account found with email "'.$booking->contact_email.'". The guest has not been registered yet.');
        }

        $status = Password::broker()->sendResetLink(['email' => $user->email]);

        if ($status === Password::RESET_LINK_SENT) {
            return back()->with('success', 'A password reset link has been emailed to '.$user->email.'.');
        }

        return back()->with('error', __($status));
    }

    /**
     * Upload a travel document for a booking. Works in any non-terminal
     * status (even after payment/confirmation) — attachments never touch
     * the status machine or money fields.
     */
    public function uploadAttachment(Request $request, $id)
    {
        $booking = Booking::with('items')->findOrFail($id);

        if (in_array($booking->status, BookingAttachment::TERMINAL_STATUSES, true)) {
            return back()->with('error', 'Documents cannot be added to a closed booking.');
        }

        $validated = $request->validate([
            'kind' => 'required|string|in:'.implode(',', BookingAttachment::KINDS),
            'label' => 'required|string|max:120',
            'booking_item_id' => 'nullable|integer|exists:booking_items,id',
            'file' => 'required|file|mimes:jpg,jpeg,png,webp,pdf|max:5120',
        ]);

        if (! empty($validated['booking_item_id'])
            && ! $booking->items->contains('id', (int) $validated['booking_item_id'])) {
            return back()->with('error', 'The selected booking item does not belong to this booking.');
        }

        $file = $request->file('file');

        $attachment = BookingAttachment::create([
            'booking_id' => $booking->id,
            'booking_item_id' => $validated['booking_item_id'] ?? null,
            'kind' => $validated['kind'],
            'label' => trim($validated['label']),
            'path' => $file->store('booking-documents', 'public'),
            'mime' => $file->getMimeType(),
            'size' => $file->getSize(),
            'uploaded_by_admin_id' => auth('admin')->id(),
        ]);

        BookingNotification::send($booking, new BookingDocumentAdded($attachment->load('booking')));

        return back()->with('success', "Document \"{$attachment->label}\" added and sent to the customer.");
    }

    /**
     * Delete a travel document (admin-only, any non-terminal status).
     */
    public function destroyAttachment($id, $attachmentId)
    {
        $booking = Booking::findOrFail($id);

        if (in_array($booking->status, BookingAttachment::TERMINAL_STATUSES, true)) {
            return back()->with('error', 'Documents cannot be removed from a closed booking.');
        }

        $attachment = BookingAttachment::where('booking_id', $booking->id)->findOrFail($attachmentId);
        $label = $attachment->label;

        if ($attachment->path) {
            Storage::disk('public')->delete($attachment->path);
        }
        $attachment->delete();

        return back()->with('success', "Document \"{$label}\" removed.");
    }
}
