{{-- Prices section of the itinerary document: stored booking prices, saved estimates, or an honest "unavailable". --}}
@php
    $peso = fn ($amount) => '₱'.number_format((float) $amount, 2);
    $booked = isset($booking) && $booking;
    $bookedItems = $booked ? $booking->items->filter(fn ($item) => (isset($itinerary['itinerary_id']) && ($item->item_snapshot['trip_itinerary_id'] ?? null) === $itinerary['itinerary_id'])
        || in_array($item->item_snapshot['trip_plan_item_id'] ?? null, $itinerary['booked_plan_item_ids'] ?? [], true))->values() : collect();
@endphp
<div class="section">
    @if ($booked)
        <div class="section-title">Booked services and prices</div>
        <p class="section-note">Stored prices from booking {{ $booking->booking_code }}. Only the services listed here belong to the booking; other plan choices are not purchased.</p>
        <table class="data">
            <thead><tr><th>Service</th><th>Dates · quantity</th><th>Status</th><th class="amount">Amount</th></tr></thead>
            <tbody>
                @foreach ($bookedItems as $item)
                    <tr class="{{ $loop->even ? 'alt' : '' }}">
                        <td><span class="strong">{{ $item->item_title }}</span>@if ($item->hotel_name)<br><span class="muted">{{ $item->hotel_name }}</span>@endif</td>
                        <td>{{ $item->check_in_date?->format('M j, Y') ?? 'Date to be arranged' }}{{ $item->check_out_date ? ' – '.$item->check_out_date->format('M j, Y') : '' }}<br><span class="muted">{{ $item->quantity }} × · {{ $item->selected_pax }} guests</span></td>
                        <td>@if ($item->availability_status === 'unavailable')<span class="chip chip-slate">Excluded at review — not charged</span>@else<span class="chip chip-green">Included in booking</span>@endif</td>
                        <td class="amount">{{ $item->availability_status === 'unavailable' ? '—' : $peso($item->subtotal) }}@if ((float) $item->promotion_discount_amount > 0)<br><span class="muted">Lucky −{{ $peso($item->promotion_discount_amount) }}</span>@endif</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
        <table class="totals-wrap" style="margin-top: 6px;"><tr><td></td><td class="totals-cell">
            <table class="totals">
                <tr><td>Booking subtotal (all services in {{ $booking->booking_code }})</td><td class="amount">{{ $peso($booking->total_amount) }}</td></tr>
                @if ((float) $booking->discount_amount > 0)<tr><td>Passenger discounts</td><td class="amount">−{{ $peso($booking->discount_amount) }}</td></tr>@endif
                @if ((float) $booking->tax_amount > 0)<tr><td>Passenger surcharges</td><td class="amount">+{{ $peso($booking->tax_amount) }}</td></tr>@endif
                @if ((float) $booking->promotion_discount_amount > 0)<tr><td>Lucky discount</td><td class="amount">−{{ $peso($booking->promotion_discount_amount) }}</td></tr>@endif
                @if ((float) $booking->admin_discount_amount > 0)<tr><td>Admin discount</td><td class="amount">−{{ $peso($booking->admin_discount_amount) }}</td></tr>@endif
                @if ((float) $booking->admin_surcharge_amount > 0)<tr><td>Additional amount</td><td class="amount">+{{ $peso($booking->admin_surcharge_amount) }}</td></tr>@endif
                <tr class="grand"><td>Booking total (PHP) · {{ $booking->payment_status === 'paid' ? 'paid in full' : ($booking->payment_status === 'partial' ? 'partially paid; a balance remains' : ($booking->payment_status === 'refunded' ? 'refunded' : 'not yet paid')) }}</td><td class="amount">{{ $peso($booking->net_amount) }}</td></tr>
            </table>
        </td></tr></table>
    @elseif (! empty($itinerary['pricing']['lines']))
        <div class="section-title">Estimated prices</div>
        <p class="section-note">Estimates in PHP as of {{ \Illuminate\Support\Carbon::parse($itinerary['pricing']['estimated_at'])->timezone('Asia/Manila')->format('M j, Y g:i A') }}. Current prices and availability are checked again at checkout and approval.</p>
        <table class="data">
            <thead><tr><th>Choice</th><th>Details</th><th class="amount">Estimate</th></tr></thead>
            <tbody>
                @foreach ($itinerary['pricing']['lines'] as $line)
                    <tr class="{{ $loop->even ? 'alt' : '' }}"><td class="strong">{{ $line['name'] }}</td><td>{{ $line['detail'] }}</td><td class="amount">{{ $peso($line['subtotal']) }}</td></tr>
                @endforeach
            </tbody>
        </table>
        <table class="totals-wrap" style="margin-top: 6px;"><tr><td></td><td class="totals-cell">
            <table class="totals"><tr class="grand"><td>Estimated total (PHP)</td><td class="amount">{{ $peso($itinerary['pricing']['total']) }}</td></tr></table>
        </td></tr></table>
        <p class="small muted" style="margin-top: 4px;">{{ $itinerary['pricing']['exclusions'] }}</p>
    @else
        <div class="section-title">Prices</div>
        <p class="section-note">Estimate unavailable: this saved version was created before prices were recorded with itineraries. Today’s prices are not shown as historical prices.</p>
    @endif
</div>
