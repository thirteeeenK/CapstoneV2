<x-frontend.layout :title="'Notifications — SunnyTrips'">
    <div class="max-w-3xl mx-auto px-4 sm:px-6 lg:px-8 py-10 font-body">
        <div class="mb-6 flex items-center justify-between">
            <div>
                <h1 class="text-2xl sm:text-3xl font-extrabold text-slate-900 dark:text-slate-100 tracking-tight font-headline">Notifications</h1>
                <p class="text-sm text-slate-500 dark:text-slate-400 mt-1">Booking updates and travel alerts.</p>
            </div>
            @if($notifications->isNotEmpty())
                <form action="{{ route('notifications.read-all') }}" method="POST">
                    @csrf
                    <button type="submit" class="px-4 py-2 rounded-xl bg-sky-50 dark:bg-sky-950/60 text-sky-700 dark:text-sky-100 border border-sky-200 dark:border-sky-800 font-bold text-xs hover:bg-sky-100 dark:hover:bg-sky-900/60 transition cursor-pointer flex items-center gap-1.5">
                        <span class="material-symbols-outlined text-[16px]">done_all</span>
                        Mark all as read
                    </button>
                </form>
            @endif
        </div>

        @if($notifications->isEmpty())
            <div class="bg-white dark:bg-slate-900 rounded-3xl border border-slate-200 dark:border-slate-700/80 shadow-xs p-12 text-center">
                <div class="w-16 h-16 rounded-3xl bg-slate-50 dark:bg-slate-800 border border-slate-200 dark:border-slate-700 flex items-center justify-center mx-auto mb-4">
                    <span class="material-symbols-outlined text-3xl text-slate-400 dark:text-slate-500">notifications_none</span>
                </div>
                <h3 class="text-sm font-bold text-slate-900 dark:text-slate-100">No notifications yet</h3>
                <p class="text-xs text-slate-500 dark:text-slate-400 mt-1">Booking updates will appear here.</p>
            </div>
        @else
            <div class="space-y-3">
                @foreach($notifications as $notification)
                    @php
                        $data = $notification->data;
                        $isUnread = $notification->read_at === null;
                        $statusColor = [
                            'approved' => 'bg-sky-50 dark:bg-sky-950/60 border-sky-200 dark:border-sky-800 text-sky-700 dark:text-sky-100',
                            'paid' => 'bg-emerald-50 dark:bg-emerald-950/60 border-emerald-200 dark:border-emerald-800 text-emerald-700 dark:text-emerald-100',
                            'rejected' => 'bg-rose-50 dark:bg-rose-950/60 border-rose-200 dark:border-rose-800 text-rose-700 dark:text-rose-100',
                            'cancelled' => 'bg-slate-100 dark:bg-slate-800 border-slate-200 dark:border-slate-700 text-slate-600 dark:text-slate-400',
                            'expired' => 'bg-amber-50 dark:bg-amber-950/60 border-amber-200 dark:border-amber-800 text-amber-700 dark:text-amber-100',
                            'pending' => 'bg-amber-50 dark:bg-amber-950/60 border-amber-200 dark:border-amber-800 text-amber-700 dark:text-amber-100',
                        ][$data['status'] ?? 'pending'] ?? 'bg-slate-100 dark:bg-slate-800 border-slate-200 dark:border-slate-700 text-slate-600 dark:text-slate-400';
                    @endphp
                    <div class="bg-white dark:bg-slate-900 rounded-2xl border {{ $isUnread ? 'border-sky-300 shadow-md' : 'border-slate-200/80 shadow-xs' }} p-5 flex items-start gap-4">
                        <div class="flex-1 min-w-0">
                            <div class="flex items-center gap-2 flex-wrap">
                                <span class="px-2 py-0.5 rounded-md text-[10px] font-extrabold uppercase tracking-wider border {{ $statusColor }}">
                                    {{ strtoupper($data['status'] ?? '') }}
                                </span>
                                <span class="text-[11px] text-slate-400 dark:text-slate-500 font-semibold">{{ $notification->created_at->format('M j, Y g:i A') }}</span>
                                @if($isUnread)
                                    <span class="w-2 h-2 rounded-full bg-sky-500"></span>
                                @endif
                            </div>
                            <p class="text-sm font-bold text-slate-900 dark:text-slate-100 mt-2">{{ $data['message'] ?? 'Booking update' }}</p>
                            @if(!empty($data['booking_url']))
                                <a href="{{ $data['booking_url'] }}" class="inline-flex items-center gap-1 mt-2 text-xs font-bold text-sky-600 hover:text-sky-800">
                                    <span class="material-symbols-outlined text-[14px]">receipt_long</span>
                                    View booking {{ $data['booking_code'] ?? '' }}
                                </a>
                            @endif
                        </div>
                        @if($isUnread)
                            <form action="{{ route('notifications.read', $notification->id) }}" method="POST">
                                @csrf
                                <button type="submit" class="text-[10px] font-bold text-slate-400 dark:text-slate-500 hover:text-slate-600 dark:hover:text-slate-400 cursor-pointer" title="Mark as read">
                                    Mark read
                                </button>
                            </form>
                        @endif
                    </div>
                @endforeach
            </div>

            <div class="mt-6">
                {{ $notifications->links() }}
            </div>
        @endif
    </div>
</x-frontend.layout>
