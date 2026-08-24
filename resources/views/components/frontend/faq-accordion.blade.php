@props([
    'groups' => null, // Collection<string, Collection<Faq>> keyed by category
])

@php
    $groups = $groups instanceof \Illuminate\Support\Collection ? $groups : collect();
@endphp

@if($groups->isNotEmpty())
<section id="faq" class="py-24 bg-sand-50/70 border-y border-slate-200/50">
    <div class="max-w-4xl mx-auto px-8">
        {{-- Header --}}
        <div class="text-center max-w-2xl mx-auto mb-10 reveal-on-scroll">
            <span class="font-label text-primary font-bold tracking-[0.2em] text-xs uppercase mb-3 block">HELP CENTER</span>
            <h2 class="font-headline text-3xl md:text-5xl font-extrabold tracking-tight text-slate-900 leading-tight">Frequently Asked Questions</h2>
            <p class="mt-4 text-slate-500 text-xs md:text-sm leading-relaxed">
                Quick answers to common questions. Managed by our team — if you don't find what you need, chat with SunnyBot or chat with our Admin.
            </p>
        </div>

        <div class="space-y-8 reveal-on-scroll">
            @foreach($groups as $category => $faqs)
                <div>
                    <h3 class="font-headline text-sm font-extrabold tracking-widest uppercase text-slate-700 mb-3 flex items-center gap-2">
                        <span class="w-1.5 h-1.5 rounded-full bg-ocean-500"></span>
                        {{ $category }}
                        <span class="text-[11px] font-label font-semibold normal-case tracking-normal text-slate-400">· {{ $faqs->count() }} {{ Str::plural('question', $faqs->count()) }}</span>
                    </h3>

                    <div class="bg-white border border-slate-200/60 rounded-2xl shadow-sm overflow-hidden divide-y divide-slate-200/60">
                        @foreach($faqs as $faq)
                            <div x-data="{ open: false }" class="group">
                                <button
                                    type="button"
                                    @click="open = !open"
                                    :aria-expanded="open.toString()"
                                    aria-controls="faq-answer-{{ $faq->id }}"
                                    class="w-full flex items-center justify-between gap-4 px-5 sm:px-6 py-4 text-left hover:bg-slate-50/70 transition focus:outline-none focus-visible:ring-2 focus-visible:ring-ocean-500/30 focus-visible:ring-inset"
                                >
                                    <span class="font-body font-semibold text-sm text-slate-900 leading-snug pr-2">{{ $faq->question }}</span>
                                    <span
                                        class="shrink-0 w-8 h-8 rounded-xl bg-ocean-50 border border-ocean-100 text-ocean-700 flex items-center justify-center transition-transform duration-200"
                                        :class="open ? 'rotate-180' : ''"
                                        aria-hidden="true"
                                    >
                                        <span class="material-symbols-outlined text-[18px]">expand_more</span>
                                    </span>
                                </button>
                                <div
                                    id="faq-answer-{{ $faq->id }}"
                                    x-show="open"
                                    x-transition:enter="transition ease-out duration-200"
                                    x-transition:enter-start="opacity-0 -translate-y-1"
                                    x-transition:enter-end="opacity-100 translate-y-0"
                                    x-transition:leave="transition ease-in duration-150"
                                    x-transition:leave-start="opacity-100 translate-y-0"
                                    x-transition:leave-end="opacity-0 -translate-y-1"
                                    x-cloak
                                    class="px-5 sm:px-6 pb-5"
                                >
                                    <p class="text-xs md:text-sm leading-relaxed text-slate-600 bg-slate-50 border border-slate-100 rounded-xl px-4 py-3">
                                        {{ $faq->answer }}
                                    </p>
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>
            @endforeach
        </div>

        <p class="text-center mt-10 text-[11px] font-label text-slate-400">
            Still need help? Open the chat bubble in the bottom-right corner to ask <span class="font-bold text-ocean-600">SunnyBot</span> — our AI travel assistant.
        </p>
    </div>
</section>
@endif
