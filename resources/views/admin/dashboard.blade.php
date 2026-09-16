<x-admin-layout>
    <div class="pb-12 font-body">
        <script id="dashboard-chart-data" type="application/json">{!! json_encode($chartData, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) !!}</script>

        {{-- Header --}}
        <div class="mb-6 flex flex-col sm:flex-row sm:items-center justify-between gap-4">
            <div>
                <h1 class="text-xl sm:text-2xl font-bold text-slate-900 font-headline">Dashboard</h1>
                <p class="text-xs sm:text-sm text-slate-500 mt-1">System and business status at a glance · last 30 days unless noted.</p>
            </div>
            <a href="{{ route('admin.reports.index') }}"
               class="px-4 py-2.5 rounded-xl bg-slate-900 hover:bg-slate-800 text-white font-bold text-xs transition flex items-center gap-1.5 w-fit">
                <span class="material-symbols-outlined text-[16px]">bar_chart</span>
                Full reports
            </a>
        </div>

        {{-- 1. Action queue --}}
        <h2 class="text-xs font-extrabold uppercase tracking-wider text-slate-400 mb-3">Needs attention</h2>
        <div class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-5 gap-4 mb-8">
            <a href="{{ route('admin.bookings.index', ['status' => 'pending']) }}"
               class="bg-white rounded-3xl border {{ $staleCount > 0 ? 'border-rose-200' : 'border-slate-200/80' }} shadow-xs p-5 hover:border-sky-300 transition block">
                <p class="text-[10px] font-extrabold uppercase tracking-wider text-slate-400">Pending review</p>
                <p class="text-2xl font-black text-slate-900 mt-1">{{ $pendingCount }}</p>
                <p class="text-[11px] font-bold mt-1 {{ $staleCount > 0 ? 'text-rose-600' : 'text-slate-400' }}">
                    {{ $staleCount > 0 ? $staleCount.' stale over 48h' : 'Queue clear' }}
                </p>
            </a>
            <a href="{{ route('admin.bookings.index', ['status' => 'approved']) }}"
               class="bg-white rounded-3xl border {{ $deadlineRisk > 0 ? 'border-amber-200' : 'border-slate-200/80' }} shadow-xs p-5 hover:border-sky-300 transition block">
                <p class="text-[10px] font-extrabold uppercase tracking-wider text-slate-400">Awaiting payment</p>
                <p class="text-2xl font-black text-slate-900 mt-1">₱{{ number_format($pipelineValue, 2) }}</p>
                <p class="text-[11px] font-bold mt-1 {{ $deadlineRisk > 0 ? 'text-amber-600' : 'text-slate-400' }}">
                    {{ $approvedCount }} approved{{ $deadlineRisk > 0 ? ' · '.$deadlineRisk.' due within 24h' : '' }}
                </p>
            </a>
            <a href="{{ route('admin.bookings.index', ['status' => 'cancellation_requested']) }}"
               class="bg-white rounded-3xl border border-slate-200/80 shadow-xs p-5 hover:border-sky-300 transition block">
                <p class="text-[10px] font-extrabold uppercase tracking-wider text-slate-400">Cancellation requests</p>
                <p class="text-2xl font-black text-slate-900 mt-1">{{ $cancelRequests }}</p>
                <p class="text-[11px] font-bold mt-1 text-slate-400">{{ $cancelRequests === 0 ? 'None waiting' : 'Needs decision' }}</p>
            </a>
            <a href="{{ route('admin.support.index') }}"
               class="bg-white rounded-3xl border {{ $supportUnassigned > 0 ? 'border-amber-200' : 'border-slate-200/80' }} shadow-xs p-5 hover:border-sky-300 transition block">
                <p class="text-[10px] font-extrabold uppercase tracking-wider text-slate-400">Open support tickets</p>
                <p class="text-2xl font-black text-slate-900 mt-1">{{ $supportOpen }}</p>
                <p class="text-[11px] font-bold mt-1 {{ $supportUnassigned > 0 ? 'text-amber-600' : 'text-slate-400' }}">
                    {{ $supportUnassigned }} unassigned{{ $abusePending > 0 ? ' · '.$abusePending.' abuse report(s)' : '' }}
                </p>
            </a>
            <a href="{{ route('admin.bookings.index', ['status' => 'expired']) }}"
               class="bg-white rounded-3xl border border-slate-200/80 shadow-xs p-5 hover:border-sky-300 transition block">
                <p class="text-[10px] font-extrabold uppercase tracking-wider text-slate-400">Expired unpaid (7d)</p>
                <p class="text-2xl font-black text-slate-900 mt-1">{{ $expiredWeek }}</p>
                <p class="text-[11px] font-bold mt-1 text-slate-400">{{ $expiredWeek === 0 ? 'No leakage' : 'Review deadlines' }}</p>
            </a>
        </div>

        {{-- 2. Business KPIs --}}
        <h2 class="text-xs font-extrabold uppercase tracking-wider text-slate-400 mb-3">Business performance</h2>
        <div class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-4 gap-4 mb-8">
            <div class="bg-white rounded-3xl border border-slate-200/80 shadow-xs p-5">
                <p class="text-[10px] font-extrabold uppercase tracking-wider text-slate-400">Bookings today</p>
                <p class="text-2xl font-black text-slate-900 mt-1">{{ $kpi['today']['bookings'] }}</p>
                <p class="text-[11px] text-slate-500 mt-1">₱{{ number_format($kpi['today']['collected'], 2) }} collected</p>
            </div>
            <div class="bg-white rounded-3xl border border-slate-200/80 shadow-xs p-5">
                <p class="text-[10px] font-extrabold uppercase tracking-wider text-slate-400">Bookings · last 7 days</p>
                <p class="text-2xl font-black text-slate-900 mt-1">{{ $kpi['week']['bookings'] }}</p>
                <p class="text-[11px] text-slate-500 mt-1">₱{{ number_format($kpi['week']['collected'], 2) }} collected</p>
            </div>
            <div class="bg-white rounded-3xl border border-slate-200/80 shadow-xs p-5">
                <p class="text-[10px] font-extrabold uppercase tracking-wider text-slate-400">Bookings · last 30 days</p>
                <p class="text-2xl font-black text-slate-900 mt-1">{{ $kpi['month']['bookings'] }}</p>
                <p class="text-[11px] text-slate-500 mt-1">₱{{ number_format($kpi['month']['collected'], 2) }} collected</p>
            </div>
            <div class="bg-white rounded-3xl border border-slate-200/80 shadow-xs p-5">
                <p class="text-[10px] font-extrabold uppercase tracking-wider text-slate-400">Pipeline &amp; value</p>
                <p class="text-2xl font-black text-emerald-700 mt-1">₱{{ number_format($kpi['month']['pipeline'], 2) }}</p>
                <p class="text-[11px] text-slate-500 mt-1">awaiting payment · avg ₱{{ number_format($kpi['month']['avg'], 2) }}/booking</p>
            </div>
        </div>

        {{-- 3. Charts (ApexCharts, same pattern as reviews) --}}
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-4 mb-4">
            <div class="lg:col-span-2 bg-white rounded-3xl border border-slate-200/80 shadow-xs p-6">
                <h2 class="text-base font-bold text-slate-900 flex items-center gap-2 border-b border-slate-100 pb-3 mb-4">
                    <span class="material-symbols-outlined text-sky-600">show_chart</span>
                    Bookings &amp; revenue · 30 days
                </h2>
                <div id="chart-trend" class="min-h-[280px]"></div>
            </div>
            <div class="bg-white rounded-3xl border border-slate-200/80 shadow-xs p-6">
                <h2 class="text-base font-bold text-slate-900 flex items-center gap-2 border-b border-slate-100 pb-3 mb-4">
                    <span class="material-symbols-outlined text-sky-600">donut_large</span>
                    Status funnel
                </h2>
                <div id="chart-funnel" class="min-h-[280px]"></div>
            </div>
        </div>
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-4 mb-8">
            <div class="bg-white rounded-3xl border border-slate-200/80 shadow-xs p-6">
                <h2 class="text-base font-bold text-slate-900 flex items-center gap-2 border-b border-slate-100 pb-3 mb-4">
                    <span class="material-symbols-outlined text-sky-600">leaderboard</span>
                    Top sellers · 30 days
                </h2>
                <div id="chart-topsellers" class="min-h-[220px]"></div>
            </div>
            <div class="bg-white rounded-3xl border border-slate-200/80 shadow-xs p-6">
                <h2 class="text-base font-bold text-slate-900 flex items-center gap-2 border-b border-slate-100 pb-3 mb-4">
                    <span class="material-symbols-outlined text-sky-600">reviews</span>
                    Satisfaction &amp; catalog
                </h2>
                @if($platformSummary)
                    <div class="flex items-end gap-2">
                        <p class="text-3xl font-black text-slate-900">{{ number_format($platformSummary['average_rating'], 1) }}<span class="text-base text-slate-400"> ★</span></p>
                        <p class="text-[11px] font-bold text-slate-500 pb-1.5">{{ $platformSummary['total_reviews'] }} reviews · {{ round($platformSummary['negative_percentage']) }}% negative</p>
                    </div>
                @else
                    <p class="text-xs text-slate-400 font-medium">No review summary yet.</p>
                @endif
                <div class="mt-4 space-y-2 text-[11px] font-bold">
                    <div class="flex items-center justify-between gap-2">
                        <span class="text-slate-500">Low ratings (1–2★) last 7d</span>
                        <a href="{{ route('admin.reviews.index') }}" class="px-2 py-0.5 rounded-md {{ $lowReviewsWeek > 0 ? 'bg-rose-100 text-rose-700' : 'bg-slate-100 text-slate-500' }}">{{ $lowReviewsWeek }}</a>
                    </div>
                    <div class="flex items-center justify-between gap-2">
                        <span class="text-slate-500">New users last 7d</span>
                        <a href="{{ route('admin.users.index') }}" class="px-2 py-0.5 rounded-md bg-sky-100 text-sky-700">{{ $newUsersWeek }}</a>
                    </div>
                    <div class="flex items-center justify-between gap-2">
                        <span class="text-slate-500">Hidden listings (hotels / rooms / activities)</span>
                        <a href="{{ route('admin.inventory.index') }}" class="px-2 py-0.5 rounded-md bg-slate-100 text-slate-600">{{ $hiddenCounts['hotels'] }} / {{ $hiddenCounts['rooms'] }} / {{ $hiddenCounts['activities'] }}</a>
                    </div>
                    <div class="flex items-center justify-between gap-2">
                        <span class="text-slate-500">Packages expiring within 14d</span>
                        <a href="{{ route('admin.packages.index') }}" class="px-2 py-0.5 rounded-md {{ $packagesExpiring > 0 ? 'bg-amber-100 text-amber-700' : 'bg-slate-100 text-slate-500' }}">{{ $packagesExpiring }}</a>
                    </div>
                </div>
            </div>
        </div>

        {{-- 4. Recent activity --}}
        <h2 class="text-xs font-extrabold uppercase tracking-wider text-slate-400 mb-3">Recent activity</h2>
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-4 mb-8">
            <div class="lg:col-span-1 bg-white rounded-3xl border border-slate-200/80 shadow-xs overflow-hidden">
                <div class="px-5 pt-5 pb-3 border-b border-slate-100 flex items-center justify-between">
                    <h3 class="text-sm font-bold text-slate-900">Latest bookings</h3>
                    <a href="{{ route('admin.bookings.index') }}" class="text-[11px] font-extrabold text-sky-600 hover:text-sky-700">View all</a>
                </div>
                <div class="divide-y divide-slate-100">
                    @forelse($recentBookings as $booking)
                        <a href="{{ route('admin.bookings.show', $booking['id']) }}" class="flex items-center justify-between gap-3 px-5 py-3 hover:bg-slate-50/80 transition">
                            <div class="min-w-0">
                                <p class="font-mono text-xs font-bold text-slate-900 truncate">{{ $booking['booking_code'] }}</p>
                                <p class="text-[11px] text-slate-500 truncate">{{ $booking['contact_name'] }} · {{ $booking['items_count'] }} item(s)</p>
                            </div>
                            <div class="text-right shrink-0">
                                <p class="text-xs font-black text-slate-900">₱{{ number_format($booking['net_amount'], 2) }}</p>
                                <p class="text-[10px] font-extrabold uppercase tracking-wider text-slate-400">{{ $booking['status'] }}</p>
                            </div>
                        </a>
                    @empty
                        <p class="px-5 py-8 text-center text-xs text-slate-400 font-medium">No bookings yet.</p>
                    @endforelse
                </div>
            </div>
            <div class="bg-white rounded-3xl border border-slate-200/80 shadow-xs overflow-hidden">
                <div class="px-5 pt-5 pb-3 border-b border-slate-100 flex items-center justify-between">
                    <h3 class="text-sm font-bold text-slate-900">Latest support tickets</h3>
                    <a href="{{ route('admin.support.index') }}" class="text-[11px] font-extrabold text-sky-600 hover:text-sky-700">Inbox</a>
                </div>
                <div class="divide-y divide-slate-100">
                    @forelse($recentTickets as $ticket)
                        <a href="{{ route('admin.support.index') }}" class="flex items-center justify-between gap-3 px-5 py-3 hover:bg-slate-50/80 transition">
                            <div class="min-w-0">
                                <p class="font-mono text-xs font-bold text-slate-900 truncate">{{ $ticket['ticket_number'] }}</p>
                                <p class="text-[11px] text-slate-500 truncate">{{ $ticket['user_name'] }}</p>
                            </div>
                            <span class="shrink-0 px-2 py-0.5 rounded-md text-[10px] font-extrabold uppercase tracking-wider {{ $ticket['status'] === 'PENDING_ASSIGNMENT' ? 'bg-amber-100 text-amber-700' : ($ticket['status'] === 'RESOLVED' ? 'bg-emerald-100 text-emerald-700' : 'bg-sky-100 text-sky-700') }}">
                                {{ $ticket['status'] === 'PENDING_ASSIGNMENT' ? 'Awaiting agent' : ($ticket['status'] === 'HUMAN_SUPPORT_ACTIVE' ? 'With agent' : ucfirst(strtolower(str_replace('_', ' ', $ticket['status'])))) }}
                            </span>
                        </a>
                    @empty
                        <p class="px-5 py-8 text-center text-xs text-slate-400 font-medium">No tickets yet.</p>
                    @endforelse
                </div>
            </div>
            <div class="bg-white rounded-3xl border border-slate-200/80 shadow-xs overflow-hidden">
                <div class="px-5 pt-5 pb-3 border-b border-slate-100 flex items-center justify-between">
                    <h3 class="text-sm font-bold text-slate-900">Low ratings to review</h3>
                    <a href="{{ route('admin.reviews.index') }}" class="text-[11px] font-extrabold text-sky-600 hover:text-sky-700">Reviews</a>
                </div>
                <div class="divide-y divide-slate-100">
                    @forelse($recentLowReviews as $review)
                        <a href="{{ route('admin.reviews.index') }}" class="flex items-center justify-between gap-3 px-5 py-3 hover:bg-slate-50/80 transition">
                            <div class="min-w-0">
                                <p class="text-xs font-bold text-slate-900 truncate">{{ $review['reviewer_name'] }} · {{ $review['rating'] }}★</p>
                                <p class="text-[11px] text-slate-500 truncate">{{ \Illuminate\Support\Str::limit($review['comment'], 60) }}</p>
                            </div>
                            <span class="shrink-0 text-[10px] font-bold text-slate-400">{{ $review['time_ago'] }}</span>
                        </a>
                    @empty
                        <p class="px-5 py-8 text-center text-xs text-slate-400 font-medium">No low ratings. Nice.</p>
                    @endforelse
                </div>
            </div>
        </div>

        {{-- 5. AI Insights (rule-based; Gemini deep-dive reuses Reports) --}}
        <div class="bg-white rounded-3xl border border-slate-200/80 shadow-xs p-6">
            <div class="flex items-center justify-between flex-wrap gap-3 mb-4">
                <h2 class="text-base font-bold text-slate-900 flex items-center gap-2">
                    <span class="material-symbols-outlined text-sky-600">psychology</span>
                    AI Insights
                </h2>
                <form method="POST" action="{{ route('admin.reports.analyze', ['from' => now()->subDays(30)->format('Y-m-d'), 'to' => now()->format('Y-m-d')]) }}">
                    @csrf
                    <button type="submit"
                            class="px-4 py-2.5 rounded-xl bg-sky-600 text-white font-extrabold text-xs transition-all hover:bg-sky-500 shadow-lg shadow-sky-600/25 flex items-center gap-1.5 cursor-pointer">
                        <span class="material-symbols-outlined text-[16px]">auto_awesome</span>
                        Analyze with AI
                    </button>
                </form>
            </div>
            @if(count($insights) === 0)
                <div class="flex items-center gap-2 text-sm text-slate-500">
                    <span class="material-symbols-outlined text-emerald-500">check_circle</span>
                    <span class="font-medium">All clear — no anomalies in the last 30 days.</span>
                </div>
            @else
                <ul class="space-y-2.5">
                    @foreach($insights as $insight)
                        <li class="flex items-start gap-3 rounded-2xl border px-4 py-3 text-[13px] leading-snug
                            {{ $insight['level'] === 'danger' ? 'border-rose-200 bg-rose-50/60' : ($insight['level'] === 'warn' ? 'border-amber-200 bg-amber-50/60' : 'border-sky-200/70 bg-sky-50/50') }}">
                            <span class="material-symbols-outlined text-[20px] mt-0.5 shrink-0
                                {{ $insight['level'] === 'danger' ? 'text-rose-500' : ($insight['level'] === 'warn' ? 'text-amber-500' : 'text-sky-600') }}">{{ $insight['icon'] }}</span>
                            <span class="flex-1 font-medium text-slate-700">{{ $insight['text'] }}</span>
                            <a href="{{ route($insight['route'], $insight['params']) }}"
                               class="shrink-0 text-[11px] font-extrabold text-sky-600 hover:text-sky-700 mt-0.5">Open</a>
                        </li>
                    @endforeach
                </ul>
            @endif
        </div>

        @push('scripts')
            @vite('resources/js/dashboard-charts.js')
        @endpush
    </div>
</x-admin-layout>
