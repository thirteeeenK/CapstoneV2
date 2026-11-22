{{-- Dashboard starter itinerary: top-ranked stay + experiences with price, weather and mapped distances. --}}
@props(['suggestion' => null])

@php
    $peso = fn ($amount) => '₱'.number_format((float) $amount, 2);
@endphp

<section aria-labelledby="suggested-itinerary-heading" class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-3xl p-5 sm:p-7 shadow-sm">
    <div class="flex flex-col md:flex-row md:items-start md:justify-between gap-4">
        <div class="space-y-1">
            <p class="font-label text-[11px] uppercase font-bold tracking-[0.15em] text-ocean-700 dark:text-ocean-300 flex items-center gap-1.5">
                <span class="material-symbols-outlined text-[16px]" aria-hidden="true">route</span>
                {{ ($suggestion['personalized'] ?? false) ? 'Suggested itinerary for you' : 'Starter itinerary' }}
            </p>
            <h2 id="suggested-itinerary-heading" class="font-headline text-xl sm:text-2xl font-black text-slate-900 dark:text-slate-100">
                @if ($suggestion)
                    3 days in {{ $suggestion['destination']->name }} for {{ $suggestion['pax'] }} {{ $suggestion['pax'] === 1 ? 'traveler' : 'travelers' }}
                @else
                    Not sure where to start?
                @endif
            </h2>
            <p class="text-xs sm:text-sm text-slate-500 dark:text-slate-400 max-w-2xl">
                @if ($suggestion)
                    Built from your top-ranked stay and experiences{{ ($suggestion['personalized'] ?? false) ? '' : ' (popular picks until you personalize)' }}, with estimated prices, the 5-day forecast and mapped travel from your hotel.
                @else
                    Let us surprise you with a ready-made trip, or build your own day by day.
                @endif
            </p>
        </div>
        <div class="flex flex-wrap gap-2 shrink-0">
            <a href="{{ route('lucky.index') }}"
               class="min-h-11 inline-flex items-center gap-1.5 rounded-xl bg-coral-500 hover:bg-coral-600 px-4 py-2.5 text-xs font-bold text-white shadow-sm transition">
                <span class="material-symbols-outlined text-[16px]" aria-hidden="true">casino</span>
                I'm Feeling Lucky — win a discount
            </a>
            @if ($suggestion && $suggestion['room'])
                <form method="POST" action="{{ route('trip-planner.suggested') }}">
                    @csrf
                    <input type="hidden" name="destination_id" value="{{ $suggestion['destination']->id }}">
                    <input type="hidden" name="room_id" value="{{ $suggestion['room']->id }}">
                    <input type="hidden" name="pax" value="{{ $suggestion['pax'] }}">
                    <input type="hidden" name="nights" value="{{ $suggestion['nights'] }}">
                    @foreach ($suggestion['days'] as $day)
                        @foreach ($day['activities'] as $stop)
                            <input type="hidden" name="activity_ids[]" value="{{ $stop['activity']->id }}">
                        @endforeach
                    @endforeach
                    <button type="submit"
                            class="min-h-11 inline-flex items-center gap-1.5 rounded-xl border border-ocean-600 bg-ocean-50 dark:bg-ocean-950/60 px-4 py-2.5 text-xs font-bold text-ocean-800 dark:text-ocean-100 transition hover:bg-ocean-100 cursor-pointer">
                        <span class="material-symbols-outlined text-[16px]" aria-hidden="true">map</span>
                        Open this itinerary in Trip Planner
                    </button>
                </form>
            @else
                <a href="{{ route('trip-planner.index', $suggestion ? ['destination' => $suggestion['destination']->id] : []) }}"
                   class="min-h-11 inline-flex items-center gap-1.5 rounded-xl border border-ocean-600 bg-ocean-50 dark:bg-ocean-950/60 px-4 py-2.5 text-xs font-bold text-ocean-800 dark:text-ocean-100 transition hover:bg-ocean-100">
                    <span class="material-symbols-outlined text-[16px]" aria-hidden="true">map</span>
                    Build your own in Trip Planner
                </a>
            @endif
        </div>
    </div>

    @if ($errors->any())
        <p role="alert" class="mt-4 rounded-xl border border-amber-200 dark:border-amber-800 bg-amber-50 dark:bg-amber-950/50 px-4 py-3 text-xs text-amber-900 dark:text-amber-100">
            We couldn't open this itinerary in the Trip Planner: {{ $errors->first() }}
        </p>
    @endif

    @if ($suggestion)
        <div class="mt-5 grid grid-cols-1 lg:grid-cols-3 gap-5">
            <div class="lg:col-span-2 space-y-4">
                <div class="rounded-2xl bg-sand-50 dark:bg-slate-800 border border-sand-200 dark:border-slate-700 p-4">
                    <p class="text-[10px] uppercase tracking-wider font-bold text-slate-400 dark:text-slate-500">Your stay · {{ $suggestion['nights'] }} nights</p>
                    <a href="{{ route('hotels.show', $suggestion['hotel']->id) }}" class="mt-1 block font-headline font-bold text-slate-900 dark:text-slate-100 hover:text-ocean-700">{{ $suggestion['hotel']->hotel_name }}</a>
                    @if ($suggestion['room'])
                        <p class="text-xs text-slate-500 dark:text-slate-400">{{ $suggestion['room']->room_name }} · {{ $peso($suggestion['room']->calculateNightlyRate($suggestion['pax'])) }}/night</p>
                    @endif
                </div>

                <ol class="space-y-3">
                    @foreach ($suggestion['days'] as $day)
                        <li class="flex gap-3">
                            <span class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-ocean-700 text-white font-bold text-sm">{{ $day['day'] }}</span>
                            <div class="min-w-0 flex-1 border-b border-sand-200 dark:border-slate-700 pb-3">
                                <p class="font-headline font-bold text-sm text-slate-900 dark:text-slate-100">Day {{ $day['day'] }} · {{ $day['title'] }}</p>
                                @forelse ($day['activities'] as $stop)
                                    <div class="mt-1.5 flex items-start justify-between gap-3 text-xs">
                                        <div class="min-w-0">
                                            <p class="font-semibold text-slate-800 dark:text-slate-200">{{ $stop['activity']->activity_name }}</p>
                                            <p class="text-slate-500 dark:text-slate-400 flex items-center gap-1">
                                                <span class="material-symbols-outlined text-[14px]" aria-hidden="true">directions_walk</span>
                                                @if ($stop['leg'] && $stop['leg']['distance_label'])
                                                    {{ $stop['leg']['distance_label'] }}{{ $stop['leg']['duration_minutes'] !== null ? ' · '.$stop['leg']['duration_minutes'].' min '.($stop['leg']['mode'] === 'driving' ? 'drive' : 'walk') : '' }} from your hotel{{ $stop['leg']['estimated'] ? ' (approx. — meeting point not pinned)' : '' }}
                                                @else
                                                    Distance from hotel unavailable
                                                @endif
                                            </p>
                                        </div>
                                        <p class="shrink-0 font-semibold tabular-nums text-slate-800 dark:text-slate-200">{{ $peso($stop['price']) }}</p>
                                    </div>
                                @empty
                                    <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">Free time — relax at the beach or add an activity in the Trip Planner.</p>
                                @endforelse
                            </div>
                        </li>
                    @endforeach
                </ol>
            </div>

            <div class="space-y-4">
                <x-frontend.itinerary-weather :weather="$suggestion['weather']" />
                <div class="rounded-2xl bg-sand-100 dark:bg-slate-800 p-4 text-sm">
                    <p class="font-headline font-bold">Estimated cost</p>
                    <p class="mt-2 flex justify-between gap-3"><span>Stay</span><span class="tabular-nums">{{ $peso($suggestion['prices']['stay']) }}</span></p>
                    <p class="mt-1 flex justify-between gap-3"><span>Activities</span><span class="tabular-nums">{{ $peso($suggestion['prices']['activities']) }}</span></p>
                    <p class="mt-2 pt-2 border-t border-sand-200 dark:border-slate-700 flex justify-between gap-3 font-bold"><span>Total</span><span class="tabular-nums">{{ $peso($suggestion['prices']['total']) }}</span></p>
                    <p class="mt-2 text-[11px] text-slate-500 dark:text-slate-400">From listed rates for {{ $suggestion['pax'] }} {{ $suggestion['pax'] === 1 ? 'traveler' : 'travelers' }}. Transport, meals and fees are not included; prices are checked again at checkout.</p>
                </div>
            </div>
        </div>
    @endif
</section>
