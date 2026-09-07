<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>SunnyTrips Booking Report</title>
    <style>
        body { font-family: 'DejaVu Sans', sans-serif; font-size: 10px; color: #1e293b; margin: 0; padding: 0; }
        h1 { font-size: 18px; margin: 0 0 2px 0; color: #0f172a; }
        .sub { color: #64748b; font-size: 9px; margin-bottom: 16px; }
        h2 { font-size: 12px; color: #0f172a; border-bottom: 1px solid #e2e8f0; padding-bottom: 4px; margin: 18px 0 8px 0; }
        table { width: 100%; border-collapse: collapse; }
        th { background: #f1f5f9; text-align: left; font-size: 8px; text-transform: uppercase; letter-spacing: 0.5px; color: #64748b; padding: 5px 6px; border-bottom: 1px solid #cbd5e1; }
        td { padding: 5px 6px; border-bottom: 1px solid #e2e8f0; }
        .kpis { width: 100%; border-collapse: collapse; }
        .kpis td { border: 1px solid #e2e8f0; padding: 8px 10px; }
        .kpis .label { font-size: 8px; text-transform: uppercase; letter-spacing: 0.5px; color: #64748b; }
        .kpis .value { font-size: 14px; font-weight: bold; color: #0f172a; margin-top: 2px; }
        .right { text-align: right; }
        .footer { margin-top: 20px; font-size: 8px; color: #94a3b8; border-top: 1px solid #e2e8f0; padding-top: 6px; }
        .pos { color: #059669; font-weight: bold; }
    </style>
</head>
<body>
    <h1>SunnyTrips — Booking Report</h1>
    <div class="sub">Period: {{ $fromLabel }} to {{ $toLabel }} · Status: {{ $status ?: 'All statuses' }} · Generated {{ now()->format('M j, Y g:i A') }}</div>

    <table class="kpis">
        <tr>
            <td><div class="label">Total Bookings</div><div class="value">{{ $totalBookings }}</div></td>
            <td><div class="label">Revenue (Collected)</div><div class="value">₱{{ number_format($collected, 2) }}</div></td>
            <td><div class="label">Awaiting Payment</div><div class="value">₱{{ number_format($estimated, 2) }}</div></td>
            <td><div class="label">Avg Booking Value</div><div class="value">₱{{ number_format($avgValue, 2) }}</div></td>
        </tr>
    </table>

    <h2>Status Breakdown</h2>
    <table>
        <tr><th>Status</th><th class="right">Count</th></tr>
        @foreach ($statusCounts as $key => $count)
            <tr><td>{{ ucfirst($key) }}</td><td class="right">{{ $count }}</td></tr>
        @endforeach
    </table>

    <h2>Daily Breakdown</h2>
    <table>
        <tr><th>Date</th><th class="right">Bookings</th><th class="right">Collected</th><th class="right">Awaiting Payment</th></tr>
        @forelse ($daily as $row)
            <tr>
                <td>{{ \Carbon\Carbon::parse($row->day)->format('M j, Y') }}</td>
                <td class="right">{{ $row->bookings }}</td>
                <td class="right">₱{{ number_format((float)$row->collected, 2) }}</td>
                <td class="right">₱{{ number_format((float)$row->estimated, 2) }}</td>
            </tr>
        @empty
            <tr><td colspan="4">No bookings in this period.</td></tr>
        @endforelse
    </table>

    <h2>Booking Details (latest {{ $bookings->count() }} of {{ $totalBookings }})</h2>
    <table>
        <tr>
            <th>Code</th><th>Guest</th><th>Items</th><th class="right">Net Amount</th><th>Status</th><th>Payment</th><th>Requested</th>
        </tr>
        @forelse ($bookings->take(50) as $booking)
            <tr>
                <td>{{ $booking->booking_code }}</td>
                <td>{{ $booking->contact_name }}</td>
                <td class="right">{{ $booking->items_count }}</td>
                <td class="right">₱{{ number_format((float)$booking->net_amount, 2) }}</td>
                <td>{{ $booking->status }}</td>
                <td>{{ $booking->payment_status }}</td>
                <td>{{ $booking->created_at->format('M j, Y g:i A') }}</td>
            </tr>
        @empty
            <tr><td colspan="7">No bookings match the current filters.</td></tr>
        @endforelse
    </table>

    <div class="footer">SunnyTrips Admin Portal — {{ config('app.url') }}</div>
</body>
</html>
