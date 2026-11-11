<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $document['title'] }} {{ $booking->booking_code }} — SunnyTrips</title>
    @include('trip-planner.pdf._styles')
</head>
@php
    $webView = $webView ?? request()->boolean('print');
    $peso = fn ($amount) => '₱'.number_format((float) $amount, 2);
    $cents = fn (int $amount) => '₱'.number_format($amount / 100, 2);
    $date = fn ($value) => $value ? \Illuminate\Support\Carbon::parse($value)->format('M j, Y') : null;
    $typeLabel = fn (string $type) => match ($type) { 'room' => 'Room', 'activity' => 'Activity', 'package' => 'Package', 'addon', 'add_on' => 'Add-on', default => ucfirst(str_replace('_', ' ', $type)) };
    $manifest = is_array($booking->guest_manifest) ? array_values($booking->guest_manifest) : [];
    $settled = $summary['remaining_cents'] <= 0;
@endphp
<body class="{{ $webView ? 'web' : '' }}">
    @if ($webView)<div class="print-controls"><button type="button" onclick="window.print()">Print voucher</button></div>@endif
    @unless ($webView)
        <div class="page-footer"><table><tr><td>SunnyTrips · {{ $document['title'] }} · Booking reference {{ $booking->booking_code }}</td></tr></table></div>
    @endunless
    <div class="sheet">
        @include('trip-planner.pdf._masthead', ['documentTitle' => $document['title'], 'documentMeta' => 'Issued '.$issuedAt->format('M j, Y g:i A').' (Philippine time)'])

        {{-- Reference panel --}}
        <table class="ref-panel"><tr>
            <td>
                <div class="ref-label">Booking reference</div>
                <div class="ref-code">{{ $booking->booking_code }}</div>
                <span class="chip chip-{{ $document['tone'] }}">{{ $document['badge'] }}</span>
            </td>
            <td class="ref-side">
                <div class="ref-label">Booking total</div>
                <div class="ref-amount">{{ $peso($booking->net_amount) }}</div>
                <div class="small">Paid {{ $cents($summary['verified_cents']) }} · {{ $settled ? 'Fully settled' : 'Balance '.$cents($summary['remaining_cents']) }}</div>
            </td>
        </tr></table>

        <div class="notice {{ $document['confirmed'] ? '' : 'notice-amber' }}">
            {{ $document['subtitle'] }}
            @if ($deadline)<br><strong>{{ $deadline['label'] }}: {{ $cents($summary['remaining_cents']) }} by {{ $deadline['at']->format('M j, Y g:i A') }} (Philippine time).</strong>@endif
        </div>

        {{-- Traveler and trip facts --}}
        <div class="section">
            <table class="facts"><tr>
                <td class="col">
                    <div class="card">
                        <div class="card-title">Lead traveler</div>
                        <table class="kv">
                            <tr><td class="k">Name</td><td class="v">{{ $booking->contact_name }}</td></tr>
                            <tr><td class="k">Email</td><td class="v">{{ $booking->contact_email }}</td></tr>
                            <tr><td class="k">Phone</td><td class="v">{{ $booking->contact_phone ?: '—' }}</td></tr>
                            <tr><td class="k">Travelers</td><td class="v">{{ $trip['travelers'] ?: '—' }}</td></tr>
                        </table>
                    </div>
                </td>
                <td class="gap"></td>
                <td class="col">
                    <div class="card">
                        <div class="card-title">Trip</div>
                        <table class="kv">
                            <tr><td class="k">Destination</td><td class="v">{{ $trip['destination'] ?? '—' }}</td></tr>
                            <tr><td class="k">Travel dates</td><td class="v">@if ($trip['start']){{ $date($trip['start']) }}{{ $trip['end'] && ! $trip['end']->isSameDay($trip['start']) ? ' – '.$date($trip['end']) : '' }}@else Dates to be arranged @endif</td></tr>
                            <tr><td class="k">Length</td><td class="v">{{ $trip['nights'] !== null ? ($trip['nights'] + 1).' '.\Illuminate\Support\Str::plural('day', $trip['nights'] + 1).', '.$trip['nights'].' '.\Illuminate\Support\Str::plural('night', $trip['nights']) : '—' }}</td></tr>
                            <tr><td class="k">Booked on</td><td class="v">{{ $booking->created_at?->timezone('Asia/Manila')->format('M j, Y') }}{{ $booking->approved_at ? ' · approved '.$booking->approved_at->timezone('Asia/Manila')->format('M j, Y') : '' }}</td></tr>
                        </table>
                    </div>
                </td>
            </tr></table>
        </div>

        @if ($manifest !== [])
            <div class="section">
                <div class="section-title">Guest list</div>
                <table class="data">
                    <thead><tr><th style="width: 6%;">#</th><th style="width: 40%;">Guest</th><th>Notes</th></tr></thead>
                    <tbody>@foreach ($manifest as $guest)<tr class="{{ $loop->even ? 'alt' : '' }}"><td>{{ str_pad($loop->iteration, 2, '0', STR_PAD_LEFT) }}</td><td class="strong">{{ $guest['full_name'] ?? 'Guest' }}</td><td class="muted">{{ $guest['special_notes'] ?? '—' }}</td></tr>@endforeach</tbody>
                </table>
            </div>
        @endif

        {{-- Services --}}
        <div class="section">
            <div class="section-title">Booked services</div>
            <table class="data">
                <thead><tr><th style="width: 4%;">#</th><th>Service</th><th>Dates</th><th>Qty · guests</th><th>Status</th><th class="amount">Amount</th></tr></thead>
                <tbody>
                    @foreach ($booking->items as $item)
                        @php($excluded = $item->availability_status === 'unavailable')
                        <tr class="{{ $loop->even ? 'alt' : '' }}">
                            <td>{{ $loop->iteration }}</td>
                            <td>
                                <span class="small muted">{{ strtoupper($typeLabel((string) $item->item_type)) }}</span><br>
                                <span class="strong">{{ $item->item_title }}</span>
                                @if ($item->hotel_name)<br><span class="muted">{{ $item->hotel_name }}</span>@endif
                                @if ($item->item_subtitle && $item->item_subtitle !== $item->hotel_name)<br><span class="small muted">{{ $item->item_subtitle }}</span>@endif
                            </td>
                            <td class="nowrap">
                                {{ $date($item->check_in_date) ?? 'To be arranged' }}@if ($item->check_out_date)<br>to {{ $date($item->check_out_date) }}@endif
                                @if ($item->item_type === 'room' && $item->nights)<br><span class="small muted">{{ $item->nights }} {{ \Illuminate\Support\Str::plural('night', $item->nights) }}</span>@endif
                                @if ($item->item_snapshot['check_in_time'] ?? null)<br><span class="small muted">Check-in {{ $item->item_snapshot['check_in_time'] }}</span>@endif
                            </td>
                            <td>{{ $item->quantity }} × · {{ $item->selected_pax }} {{ \Illuminate\Support\Str::plural('guest', (int) $item->selected_pax) }}</td>
                            <td>
                                @if ($excluded)<span class="chip chip-slate">Excluded — not charged</span>
                                @elseif ($item->fulfillment_status === 'fulfilled')<span class="chip chip-green">Completed</span>
                                @else<span class="chip chip-blue">Included</span>@endif
                            </td>
                            <td class="amount">{{ $excluded ? '—' : $peso($item->subtotal) }}@if ((float) $item->promotion_discount_amount > 0)<br><span class="small muted">Lucky −{{ $peso($item->promotion_discount_amount) }}</span>@endif</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        {{-- Payment breakdown --}}
        <div class="section">
            <div class="section-title">Payment breakdown</div>
            <table class="totals-wrap"><tr>
                <td style="padding-right: 14px;">
                    <p class="section-note">All amounts are in Philippine pesos (PHP).@if ($booking->payment_policy['deposit_eligible'] ?? false) This booking allows a 50% deposit to reserve, with the balance due before travel.@endif</p>
                    @if ($booking->price_adjustment_reason)<p class="small muted"><strong>Price adjustment:</strong> {{ $booking->price_adjustment_reason }}</p>@endif
                </td>
                <td class="totals-cell">
                    <table class="totals">
                        <tr><td>Services subtotal</td><td class="amount">{{ $peso($booking->total_amount) }}</td></tr>
                        @if ((float) $booking->discount_amount > 0)<tr><td>Passenger discounts</td><td class="amount">−{{ $peso($booking->discount_amount) }}</td></tr>@endif
                        @if ((float) $booking->tax_amount > 0)<tr><td>Passenger surcharges</td><td class="amount">+{{ $peso($booking->tax_amount) }}</td></tr>@endif
                        @if ((float) $booking->promotion_discount_amount > 0)<tr><td>Lucky discount</td><td class="amount">−{{ $peso($booking->promotion_discount_amount) }}</td></tr>@endif
                        @if ((float) $booking->admin_discount_amount > 0)<tr><td>Admin discount</td><td class="amount">−{{ $peso($booking->admin_discount_amount) }}</td></tr>@endif
                        @if ((float) $booking->admin_surcharge_amount > 0)<tr><td>Additional amount</td><td class="amount">+{{ $peso($booking->admin_surcharge_amount) }}</td></tr>@endif
                        <tr class="grand"><td>Booking total</td><td class="amount">{{ $peso($booking->net_amount) }}</td></tr>
                        <tr class="paid"><td>Paid so far</td><td class="amount">{{ $cents($summary['verified_cents']) }}</td></tr>
                        @if ($settled)
                            <tr class="settled"><td>Remaining balance</td><td class="amount">Fully settled</td></tr>
                        @else
                            <tr class="balance"><td>Remaining balance{{ $deadline ? ' · due '.$deadline['at']->format('M j, Y') : '' }}</td><td class="amount">{{ $cents($summary['remaining_cents']) }}</td></tr>
                        @endif
                    </table>
                </td>
            </tr></table>

            @if ($paymentHistory !== [])
                <div class="keep">
                <div class="subhead">Payment history</div>
                <table class="data">
                    <thead><tr><th>Date</th><th>Method</th><th>Reference</th><th>Type</th><th class="amount">Amount</th></tr></thead>
                    <tbody>
                        @foreach ($paymentHistory as $row)
                            <tr class="{{ $loop->even ? 'alt' : '' }}">
                                <td class="nowrap">{{ $row['date']?->format('M j, Y g:i A') ?? '—' }}</td>
                                <td>{{ $row['method'] }}</td>
                                <td class="mono small">{{ $row['reference'] }}@if ($row['note'])<br><span class="warn">{{ $row['note'] }}</span>@endif</td>
                                <td>{{ $row['kind'] }}</td>
                                <td class="amount">{{ $row['kind'] === 'Refund' ? '−' : '' }}{{ $cents($row['amount_cents']) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
                </div>
            @endif
        </div>

        @if ($booking->special_requests)
            <div class="section">
                <div class="section-title">Special requests</div>
                <p>{{ $booking->special_requests }}</p>
            </div>
        @endif

        <div class="section">
            <div class="section-title">Good to know</div>
            <div class="notice">
                <ul>
                    <li>Show this {{ $document['confirmed'] ? 'voucher' : 'summary' }} and a valid ID when you check in and join your activities. Quote booking reference <strong>{{ $booking->booking_code }}</strong> whenever you contact us.</li>
                    <li>We email payment-deadline reminders to {{ $booking->contact_email }}, plus a pre-trip reminder when trip reminders are on in your account settings.</li>
                    <li>Need to cancel? Request it from your booking page. Refundable amounts follow the booking terms and are confirmed with a reference; you can also rebook at current prices and availability.</li>
                    @if ($itineraries === [])<li>No day-by-day itinerary is attached to this booking. Plan one in the SunnyTrips Trip Planner to get routes, distances and weather for each day.</li>@endif
                </ul>
            </div>
        </div>

        {{-- Saved itineraries, each starting on its own page --}}
        @foreach ($itineraries as $itinerary)
            <div class="page-break"></div>
            @include('trip-planner.pdf._masthead', ['documentTitle' => 'Trip Itinerary', 'documentMeta' => 'Booking reference '.$booking->booking_code])
            <table class="ref-panel"><tr>
                <td>
                    <div class="ref-label">Itinerary{{ count($itineraries) > 1 ? ' '.$loop->iteration.' of '.count($itineraries) : '' }}</div>
                    <div class="strong" style="font-size: 15px; color: #063f58;">{{ $itinerary['title'] }}</div>
                    <div class="muted">{{ $itinerary['destination'] }} · {{ \Illuminate\Support\Carbon::parse($itinerary['start_date'])->format('M j') }} – {{ \Illuminate\Support\Carbon::parse($itinerary['end_date'])->format('M j, Y') }} · {{ $itinerary['pax'] }} travelers · {{ ucfirst($itinerary['mode']) }} routes</div>
                </td>
                <td class="ref-side"><div class="ref-label">Booking reference</div><div class="mono strong" style="font-size: 14px;">{{ $booking->booking_code }}</div></td>
            </tr></table>
            @include('trip-planner.pdf._itinerary', ['itinerary' => $itinerary])
        @endforeach

        <div class="doc-footer">SunnyTrips · Booking reference {{ $booking->booking_code }} · Issued {{ $issuedAt->format('M j, Y g:i A') }} (Philippine time)<br>Road/path estimates do not include live traffic, boat fares or operator safety clearance. Weather is a forecast and can change; recheck it closer to departure.</div>
    </div>
</body>
</html>
