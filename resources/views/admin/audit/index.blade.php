@extends('layouts.admin')

@section('title', 'Audit Trail | SunnyTrips Admin')

@section('content')
    <div class="pb-12 font-body">
        <div class="mb-6">
            <h1 class="text-xl sm:text-2xl font-bold text-slate-900 font-headline">Audit Trail</h1>
            <p class="text-xs sm:text-sm text-slate-500 mt-1">Read-only log of admin actions — packages, bookings and account bans. Access via <code class="px-1.5 py-0.5 bg-slate-100 rounded text-[11px] font-bold border border-slate-200">/admin/audit</code> — no navigation link.</p>
        </div>

        @if($logs->isEmpty())
            <div class="bg-white p-12 rounded-3xl border border-slate-200/80 text-center text-slate-400">
                <span class="material-symbols-outlined text-4xl mb-2 text-slate-300">history</span>
                <p class="text-sm font-bold text-slate-700">No audit entries yet.</p>
                <p class="text-xs text-slate-500 mt-1">Create a package, update a booking status, or ban/unban a user to generate an entry.</p>
            </div>
        @else
            <div class="bg-white rounded-3xl border border-slate-200/80 shadow-xs overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="w-full text-left border-collapse">
                        <thead class="bg-slate-50/80 border-b border-slate-200/80">
                            <tr class="text-[11px] font-extrabold uppercase tracking-wider text-slate-500">
                                <th class="px-4 py-3 font-extrabold">Time</th>
                                <th class="px-4 py-3">Admin</th>
                                <th class="px-4 py-3">Action</th>
                                <th class="px-4 py-3">Target</th>
                                <th class="px-4 py-3">IP</th>
                                <th class="px-4 py-3">Changes</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            @foreach($logs as $log)
                                @php
                                    $old = $log->old_values ?? [];
                                    $new = $log->new_values ?? [];
                                    $typeLower = strtolower($log->auditable_type);
                                    $typeLabel = 'Record';
                                    if (str_contains($typeLower, 'package')) $typeLabel = 'Package';
                                    elseif (str_contains($typeLower, 'booking')) $typeLabel = 'Booking';
                                    elseif (str_contains($typeLower, 'ipban') || str_contains($typeLower, 'ip_ban')) $typeLabel = 'IP Ban';
                                    elseif (str_contains($typeLower, 'abuse') || str_contains($typeLower, 'report')) $typeLabel = 'Report';
                                    elseif (str_contains($typeLower, 'user')) $typeLabel = 'User';

                                    if (empty($old)) { $action = 'Created'; $badge = 'bg-emerald-50 text-emerald-700 border-emerald-200'; }
                                    elseif (!empty($old) && empty($new)) { $action = 'Deleted'; $badge = 'bg-rose-50 text-rose-700 border-rose-200'; }
                                    elseif (isset($new['ban_level']) || isset($old['ban_level'])) {
                                        $newLevel = $new['ban_level'] ?? null;
                                        $oldLevel = $old['ban_level'] ?? null;
                                        if ($newLevel && !$oldLevel) $action = ucfirst($newLevel) . ' / Banned';
                                        elseif (!$newLevel && $oldLevel) $action = 'Unbanned';
                                        elseif ($newLevel !== $oldLevel) $action = ucfirst($newLevel ?? 'Updated');
                                        else $action = 'Ban Updated';
                                        $badge = str_contains(strtolower($action), 'unbanned') ? 'bg-emerald-50 text-emerald-700 border-emerald-200' : 'bg-amber-50 text-amber-700 border-amber-200';
                                    }
                                    elseif (isset($old['is_active']) && count($new) === 1 && array_key_exists('is_active', $new)) { $action = ($new['is_active'] ? 'Shown' : 'Hidden'); $badge = 'bg-amber-50 text-amber-700 border-amber-200'; }
                                    elseif (isset($new['status']) || isset($old['status'])) { $action = 'Status: ' . ($new['status'] ?? $old['status']); $badge = 'bg-sky-50 text-sky-700 border-sky-200'; }
                                    else { $action = 'Updated'; $badge = 'bg-sky-50 text-sky-700 border-sky-200'; }

                                    $targetLabel = $typeLabel . ' #' . $log->auditable_id;
                                    if ($typeLabel === 'Package') {
                                        $name = $new['name'] ?? $old['name'] ?? null;
                                        if ($name) $targetLabel .= ' — ' . $name;
                                    } elseif ($typeLabel === 'Booking') {
                                        $code = $new['booking_code'] ?? $old['booking_code'] ?? null;
                                        if ($code) $targetLabel .= ' — ' . $code;
                                        elseif (!empty($new['status']) || !empty($old['status'])) $targetLabel .= ' (' . ($new['status'] ?? $old['status']) . ')';
                                    } elseif ($typeLabel === 'User') {
                                        $email = $new['email'] ?? $old['email'] ?? null;
                                        $name = $new['name'] ?? $old['name'] ?? null;
                                        if ($email) $targetLabel .= ' — ' . $email;
                                        elseif ($name) $targetLabel .= ' — ' . $name;
                                    } elseif ($typeLabel === 'IP Ban') {
                                        $ip = $new['ip_address'] ?? $old['ip_address'] ?? null;
                                        if ($ip) $targetLabel .= ' — ' . $ip;
                                    } elseif ($typeLabel === 'Report') {
                                        $targetLabel .= ' — abuse report';
                                    }
                                @endphp
                                <tr class="hover:bg-slate-50/60 transition text-[12px]">
                                    <td class="px-4 py-3 whitespace-nowrap text-slate-600 font-medium">
                                        <div class="text-[11px] font-bold text-slate-900">{{ $log->created_at->format('Y-m-d H:i:s') }}</div>
                                        <div class="text-[10px] text-slate-500">{{ $log->created_at->diffForHumans() }}</div>
                                    </td>
                                    <td class="px-4 py-3 whitespace-nowrap">
                                        <div class="text-xs font-bold text-slate-900">{{ $log->admin?->email ?? '—' }}</div>
                                        <div class="text-[10px] text-slate-500">#{{ $log->admin_id ?? '—' }}</div>
                                    </td>
                                    <td class="px-4 py-3 whitespace-nowrap">
                                        <span class="inline-flex px-2 py-0.5 rounded-full text-[10px] font-extrabold uppercase tracking-wider border {{ $badge }}">{{ $action }}</span>
                                    </td>
                                    <td class="px-4 py-3 text-slate-700 font-medium max-w-[180px] truncate" title="{{ $targetLabel }}">{{ $targetLabel }}</td>
                                    <td class="px-4 py-3 whitespace-nowrap text-slate-600 font-mono text-[11px]">{{ $log->ip_address ?? '—' }}</td>
                                    <td class="px-4 py-3 max-w-[360px]">
                                        @if(empty($old) && !empty($new))
                                            <details class="text-[11px]">
                                                <summary class="cursor-pointer font-bold text-slate-700 hover:text-sky-600">Show snapshot</summary>
                                                <pre class="mt-2 p-2 bg-slate-50 border border-slate-200 rounded-xl overflow-x-auto text-[10px] leading-relaxed">{{ json_encode($new, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) }}</pre>
                                            </details>
                                        @elseif(!empty($old) && empty($new))
                                            <details class="text-[11px]">
                                                <summary class="cursor-pointer font-bold text-slate-700 hover:text-sky-600">Show deleted snapshot</summary>
                                                <pre class="mt-2 p-2 bg-rose-50 border border-rose-200 rounded-xl overflow-x-auto text-[10px] leading-relaxed">{{ json_encode($old, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) }}</pre>
                                            </details>
                                        @elseif(!empty($new))
                                            <div class="space-y-1">
                                                @foreach($new as $k => $v)
                                                    <div class="flex gap-2 text-[11px]">
                                                        <span class="font-bold text-slate-500 shrink-0">{{ $k }}:</span>
                                                        <span class="text-amber-700 line-through decoration-amber-300">{{ is_scalar($old[$k] ?? null) ? \Illuminate\Support\Str::limit((string)($old[$k] ?? ''), 40) : json_encode($old[$k] ?? null) }}</span>
                                                        <span class="text-slate-400">→</span>
                                                        <span class="font-bold text-emerald-700">{{ is_scalar($v) ? \Illuminate\Support\Str::limit((string)$v, 40) : json_encode($v) }}</span>
                                                    </div>
                                                @endforeach
                                            </div>
                                        @else
                                            <span class="text-[11px] text-slate-400">—</span>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                <div class="px-4 py-4 border-t border-slate-100 bg-slate-50/50">
                    {{ $logs->links() }}
                </div>
            </div>
        @endif
    </div>
@endsection
