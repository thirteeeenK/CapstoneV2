<div x-data="chatWidget()" x-init="init()"
    @cart-drawer-toggle.window="cartOpen = $event.detail.open; if (cartOpen && open) open = false" x-show="!cartOpen"
    x-cloak x-transition:enter="transition ease-out duration-200"
    x-transition:enter-start="opacity-0 scale-90 translate-y-2"
    x-transition:enter-end="opacity-100 scale-100 translate-y-0" x-transition:leave="transition ease-in duration-150"
    x-transition:leave-start="opacity-100 scale-100 translate-y-0"
    x-transition:leave-end="opacity-0 scale-90 translate-y-2"
    class="fixed bottom-4 right-4 sm:bottom-6 sm:right-6 z-40 flex flex-col items-end gap-3" x-trap="open">
    {{-- Floating bubble button --}}
    <button @click="toggle()"
        class="relative w-14 h-14 rounded-full flex items-center justify-center transition-all duration-200 focus:outline-none focus:ring-2 focus:ring-ocean-400 focus:ring-offset-2 hover:scale-105 active:scale-95 cursor-pointer"
        :class="open ? 'rotate-90 scale-95 bg-slate-900 text-white shadow-xl shadow-slate-900/30' : 'bg-transparent'"
        aria-label="Chat with SunnyTrips AI">
        <img x-show="!open" src="{{ asset('images/sun-chat-dots.svg') }}" alt="Chat with SunnyBot"
            class="w-14 h-14 rounded-full bg-white p-1 shadow-xl shadow-ocean-600/20">
        <svg x-show="open" class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
        </svg>
        <span x-show="!open && hasUnreadAdmin"
            class="absolute -top-1 -right-1 w-4 h-4 bg-coral-500 border-2 border-white rounded-full flex items-center justify-center">
            <span class="w-1.5 h-1.5 bg-white rounded-full animate-pulse"></span>
        </span>
    </button>

    {{-- Chat panel --}}
    <div x-show="open" x-transition:enter="transition ease-out duration-200"
        x-transition:enter-start="opacity-0 translate-y-4 scale-95"
        x-transition:enter-end="opacity-100 translate-y-0 scale-100"
        class="w-[calc(100vw-2rem)] max-w-sm sm:w-[410px] h-[min(520px,calc(100dvh-6rem))] sm:h-[min(570px,calc(100dvh-6rem))] bg-white dark:bg-slate-900 rounded-3xl shadow-2xl border border-slate-200/90 dark:border-slate-700/90 flex flex-col overflow-hidden"
        @click.outside="close()">
        {{-- Header --}}
        <div
            class="bg-slate-900 text-white px-4 py-3.5 flex items-center justify-between shrink-0 border-b border-slate-800 relative overflow-hidden">
            <div class="absolute top-0 right-0 w-32 h-32 bg-ocean-500/10 rounded-full blur-2xl pointer-events-none">
            </div>

            <div class="flex items-center gap-3 relative z-10">
                <div class="relative">
                    <div
                        class="w-9 h-9 rounded-2xl bg-white flex items-center justify-center shadow-xs overflow-hidden">
                        <img src="{{ asset('images/sun-chat-bot.svg') }}" alt="SunnyBot" class="w-9 h-9 object-contain">
                    </div>
                    <span
                        class="absolute -bottom-0.5 -right-0.5 w-3 h-3 rounded-full bg-emerald-400 border-2 border-slate-900"></span>
                </div>
                <div>
                    <h3 class="font-headline text-sm font-black text-white leading-tight">SunnyBot AI</h3>
                    <!-- <p class="text-[11px] text-slate-300 font-medium">Your Smart Island Guide</p> -->
                </div>
            </div>

            {{-- Action button (Talk to Admin / Talk to SunnyBot) — INNER X REMOVED! --}}
            <div class="flex items-center gap-1.5 relative z-10">
                <button @click="onControlButton()"
                    x-show="!handoffStatus || handoffStatus === 'active' || handoffStatus === 'resolved'"
                    class="bg-slate-800/90 hover:bg-slate-700 text-slate-200 hover:text-white transition-all text-[11px] px-3 py-1.5 border border-slate-700/80 rounded-xl font-headline font-bold flex items-center gap-1.5 shadow-xs cursor-pointer"
                    :title="handoffStatus === 'active' ? 'Return to SunnyBot' : 'Talk to a human agent'">
                    <span class="material-symbols-outlined text-[15px] text-ocean-400">headset_mic</span>
                    <span x-text="handoffStatus === 'active' ? 'Talk to SunnyBot' : 'Talk to Admin'"></span>
                </button>
            </div>
        </div>

        {{-- Handoff status bar (always visible below the header) --}}
        <div class="px-3.5 py-2.5 border-b border-slate-100 dark:border-slate-800 bg-slate-50/80 dark:bg-slate-800/80 shrink-0 space-y-2"
            x-show="!guestLimited && (handoffStatus === 'pending' || handoffStatus === 'active')">
            <template x-if="handoffStatus === 'pending'">
                <div class="bg-amber-50 dark:bg-amber-950/60 border border-amber-200/90 dark:border-amber-800 rounded-2xl p-3 shadow-xs">
                    <p class="text-xs text-amber-800 dark:text-amber-100 font-body leading-relaxed">
                        <span class="font-bold">Waiting for an agent...</span> An administrator will be with you
                        shortly. SunnyBot stays available while you wait. Ticket: <span
                            class="font-mono font-bold text-amber-900 dark:text-amber-100" x-text="handoffTicket"></span>
                    </p>
                    <button @click="cancelHandoff()"
                        class="mt-1.5 text-[11px] text-amber-700 dark:text-amber-200 underline hover:text-amber-900 dark:hover:text-amber-100 font-bold cursor-pointer">Cancel
                        request</button>
                </div>
            </template>

            <template x-if="handoffStatus === 'active'">
                <div class="bg-emerald-50 dark:bg-emerald-950/60 border border-emerald-200/90 dark:border-emerald-800 rounded-2xl p-3 shadow-xs">
                    <p class="text-xs text-emerald-800 dark:text-emerald-100 font-body leading-relaxed"><span class="font-bold">You're
                            chatting with a human agent.</span> They will respond shortly.</p>
                </div>
            </template>
        </div>

        {{-- Messages area --}}
        <div x-ref="messages" class="flex-1 overflow-y-auto px-4 py-4 space-y-4 bg-slate-50/80 dark:bg-slate-800/80">
            {{-- First-open guide --}}
            <template x-if="showGuide && messages.length === 0 && !guestLimited">
                <div data-chat-guide class="bg-white dark:bg-slate-900 rounded-2xl border border-slate-200/80 dark:border-slate-700/80 shadow-xs overflow-hidden">
                    <div class="px-4 pt-4 pb-3 text-center space-y-2">
                        <div
                            class="w-10 h-10 rounded-2xl bg-white mx-auto flex items-center justify-center border border-ocean-100 overflow-hidden">
                            <img src="{{ asset('images/sun-chat-dots.svg') }}" alt="SunnyBot"
                                class="w-10 h-10 object-contain">
                        </div>
                        <p class="text-slate-800 dark:text-slate-200 text-xs font-bold font-headline">Mabuhay! I'm SunnyBot, your travel
                            assistant.</p>
                        <p class="text-slate-500 dark:text-slate-400 text-[11px] font-body leading-relaxed">How can I help you today?
                            Here's what I can do:</p>
                    </div>
                    <ul class="px-4 pb-3 space-y-1.5 text-left">
                        <li class="flex items-center gap-2 text-[11px] text-slate-600 dark:text-slate-400 font-body">
                            <span class="material-symbols-outlined text-[15px] text-ocean-500">hotel</span>
                            Find sanctuary hotel and rooms to stay
                        </li>
                        <li class="flex items-center gap-2 text-[11px] text-slate-600 dark:text-slate-400 font-body">
                            <span class="material-symbols-outlined text-[15px] text-ocean-500">king_bed</span>
                            Check rooms and rates
                        </li>
                        <li class="flex items-center gap-2 text-[11px] text-slate-600 dark:text-slate-400 font-body">
                            <span class="material-symbols-outlined text-[15px] text-ocean-500">explore</span>
                            Recommend activities and packages
                        </li>
                        <li class="flex items-center gap-2 text-[11px] text-slate-600 dark:text-slate-400 font-body">
                            <span class="material-symbols-outlined text-[15px] text-ocean-500">headset_mic</span>
                            Hand you to a human agent any time — just click the Talk to Agent button above.
                        </li>
                    </ul>
                    <div class="px-4 pb-3 flex flex-wrap gap-2 justify-center">
                        <button @click='input = "Plan a 3-day Boracay trip for 2"; send()'
                            class="px-3 py-1.5 rounded-full bg-ocean-50 dark:bg-ocean-900/50 hover:bg-ocean-100 dark:hover:bg-ocean-900 text-ocean-700 dark:text-ocean-100 text-[11px] font-bold border border-ocean-200 dark:border-ocean-800 transition-colors cursor-pointer">Plan
                            a 3-day Boracay trip</button>
                        <button @click='input = "Where to stay in El Nido?"; send()'
                            class="px-3 py-1.5 rounded-full bg-emerald-50 dark:bg-emerald-950/60 hover:bg-emerald-100 dark:hover:bg-emerald-950 text-emerald-700 dark:text-emerald-100 text-[11px] font-bold border border-emerald-200 dark:border-emerald-800 transition-colors cursor-pointer">Where
                            to stay in El Nido?</button>
                        <button @click='input = "Best activities in El Nido?"; send()'
                            class="px-3 py-1.5 rounded-full bg-amber-50 dark:bg-amber-950/60 hover:bg-amber-100 dark:hover:bg-amber-950 text-amber-700 dark:text-amber-100 text-[11px] font-bold border border-amber-200 dark:border-amber-800 transition-colors cursor-pointer">Best
                            activities in El Nido?</button>
                        <button @click='input = "What is the weather like in Boracay?"; send()'
                            class="px-3 py-1.5 rounded-full bg-sky-50 dark:bg-sky-950/60 hover:bg-sky-100 dark:hover:bg-sky-950 text-sky-700 dark:text-sky-100 text-[11px] font-bold border border-sky-200 dark:border-sky-800 transition-colors cursor-pointer">Boracay
                            weather today?</button>
                    </div>
                    <button @click="dismissGuide()"
                        class="w-full py-2 bg-slate-50 dark:bg-slate-800 hover:bg-slate-100 dark:hover:bg-slate-700 border-t border-slate-100 dark:border-slate-800 text-slate-500 dark:text-slate-400 hover:text-slate-700 dark:hover:text-slate-200 text-[11px] font-bold font-headline transition-colors cursor-pointer">Got
                        it, thanks!</button>
                </div>
            </template>

            {{-- Welcome --}}
            <template x-if="!showGuide && messages.length === 0 && !guestLimited">
                <div class="text-center py-6 px-4 bg-white dark:bg-slate-900 rounded-2xl border border-slate-200/80 dark:border-slate-700/80 shadow-xs space-y-2">
                    <div
                        class="w-10 h-10 rounded-2xl bg-white mx-auto flex items-center justify-center border border-ocean-100 overflow-hidden">
                        <img src="{{ asset('images/sun-chat-dots.svg') }}" alt="SunnyBot"
                            class="w-10 h-10 object-contain">
                    </div>
                    <p class="text-slate-800 dark:text-slate-200 text-xs font-bold font-headline">Mabuhay! I'm SunnyBot, your travel
                        assistant.</p>
                    <p class="text-slate-500 dark:text-slate-400 text-[11px] font-body leading-relaxed">How can I help you today? Ask me
                        about
                        hotels, island activities, room rates, or custom itineraries.</p>
                    <div class="flex flex-wrap gap-2 justify-center pt-2">
                        <!-- <button @click='input = "Where to stay in Boracay?"; send()'
                            class="px-3 py-1.5 rounded-full bg-ocean-50 hover:bg-ocean-100 text-ocean-700 text-[11px] font-bold border border-ocean-200 transition-colors cursor-pointer">Where
                            to stay in Boracay?</button>
                        <button @click='input = "Budget-friendly hotels in Boracay?"; send()'
                            class="px-3 py-1.5 rounded-full bg-emerald-50 hover:bg-emerald-100 text-emerald-700 text-[11px] font-bold border border-emerald-200 transition-colors cursor-pointer">Budget-friendly
                            hotels in Boracay?</button>
                        <button @click='input = "Where to stay in El Nido?"; send()'
                            class="px-3 py-1.5 rounded-full bg-ocean-50 hover:bg-ocean-100 text-ocean-700 text-[11px] font-bold border border-ocean-200 transition-colors cursor-pointer">Where
                            to stay in El Nido?</button>
                        <button @click='input = "Best activities in El Nido?"; send()'
                            class="px-3 py-1.5 rounded-full bg-amber-50 hover:bg-amber-100 text-amber-700 text-[11px] font-bold border border-amber-200 transition-colors cursor-pointer">Best
                            activities in El Nido?</button> -->
                    </div>
                </div>
            </template>

            {{-- Messages --}}
            <template x-for="msg in displayMessages()" :key="msg.id">
                <div class="space-y-2">
                    {{-- Bot --}}
                    <template x-if="msg.sender === 'bot'">
                        <div class="flex gap-2.5 items-start" data-bot-bubble>
                            <div
                                class="w-7 h-7 rounded-xl bg-white flex items-center justify-center shrink-0 shadow-xs mt-0.5 overflow-hidden border border-amber-100">
                                <img src="{{ asset('images/sun-chat-dots.svg') }}" alt="SunnyBot"
                                    class="w-7 h-7 object-contain">
                            </div>
                            <div
                                class="bg-white dark:bg-slate-800 rounded-2xl rounded-tl-xs px-3.5 py-3 shadow-xs border border-slate-200/80 dark:border-slate-700 max-w-[85%]">
                                <div class="text-xs sm:text-sm text-slate-800 dark:text-slate-200 font-body leading-relaxed space-y-2"
                                    x-html="renderMarkdown(msg.text)"></div>

                                {{-- AI fallback notice (Gemini → Groq failover) --}}
                                <template x-if="msg.ai_notice">
                                    <p class="mt-2 text-[10px] leading-relaxed text-amber-700 dark:text-amber-100 bg-amber-50 dark:bg-amber-950/60 border border-amber-200 dark:border-amber-800 rounded-lg px-2 py-1.5"
                                        x-text="msg.ai_notice"></p>
                                </template>

                                {{-- Evidence: FAQ review state, and cited guidance that changed or was withdrawn after this answer --}}
                                <template x-if="msg.evidence_note">
                                    <p class="mt-2 text-[10px] leading-relaxed text-slate-500 dark:text-slate-400" x-text="msg.evidence_note"></p>
                                </template>
                                <template x-if="msg.knowledge && msg.knowledge.some(source => source.current_status && source.current_status !== 'current')">
                                    <p role="note" class="mt-2 text-[10px] leading-relaxed text-amber-700 dark:text-amber-100 bg-amber-50 dark:bg-amber-950/60 border border-amber-200 dark:border-amber-800 rounded-lg px-2 py-1.5">
                                        A source cited in this answer has since been updated or withdrawn. Ask again for current guidance.
                                    </p>
                                </template>

                                {{-- Response-linked feedback (stored with the trace ID only) --}}
                                <template x-if="msg.trace_id">
                                    <div class="mt-2" x-data="{ feedbackOpen: false, feedbackSent: false }">
                                        <button type="button" x-show="!feedbackSent" @click="feedbackOpen = !feedbackOpen" :aria-expanded="feedbackOpen.toString()"
                                            class="text-[10px] text-slate-400 dark:text-slate-500 hover:text-slate-600 dark:hover:text-slate-300 hover:underline underline-offset-2 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ocean-400 rounded">
                                            Report a problem with this answer
                                        </button>
                                        <div x-show="feedbackOpen && !feedbackSent" class="mt-1.5 flex flex-wrap gap-1.5" role="group" aria-label="What was wrong with this answer?">
                                            <template x-for="reason in [['wrong_information', 'Wrong information'], ['missing_part', 'Missing part'], ['not_helpful', 'Not helpful']]" :key="reason[0]">
                                                <button type="button" x-text="reason[1]"
                                                    @click="sendFeedback(msg, reason[0]).then(ok => { feedbackSent = ok; feedbackOpen = !ok; })"
                                                    class="px-2 py-1 rounded-lg border border-slate-200 dark:border-slate-600 text-[10px] font-label text-slate-600 dark:text-slate-300 hover:bg-slate-50 dark:hover:bg-slate-700 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ocean-400"></button>
                                            </template>
                                        </div>
                                        <p x-show="feedbackSent" role="status" class="text-[10px] text-emerald-700 dark:text-emerald-300">Thanks, this was sent to the SunnyTrips team.</p>
                                    </div>
                                </template>

                                {{-- Room cards --}}
                                <template x-if="msg.rooms">
                                    <div class="mt-3 space-y-2">
                                        <template x-for="room in msg.rooms">
                                            <div
                                                class="bg-slate-50 dark:bg-slate-800 rounded-xl p-2.5 border border-slate-200/80 dark:border-slate-700 space-y-1.5 relative">
                                                <span x-show="msg.rooms[0] && msg.rooms[0].id === room.id"
                                                    class="absolute -top-1.5 -right-1.5 text-[10px] font-black bg-coral-500 text-white px-2 py-0.5 rounded-full shadow-xs border border-white"
                                                    x-text="rankPill(msg)"></span>
                                                <img x-show="room.image" :src="imgSrc(room.image)"
                                                    class="w-full h-24 object-cover rounded-lg mb-1 border border-slate-200 dark:border-slate-700"
                                                    alt="" onerror="this.style.display='none'">
                                                <p class="text-xs font-bold text-slate-900 dark:text-slate-100 font-headline"
                                                    x-text="room.room_name"></p>
                                                <p class="text-[11px] text-slate-500 dark:text-slate-400" x-text="room.hotel_name"></p>
                                                <div class="flex items-center justify-between pt-0.5">
                                                    <span class="text-xs font-black text-ocean-600 font-headline"
                                                        x-text="'₱' + new Intl.NumberFormat().format(room.base_price)"></span>
                                                    <span
                                                        class="text-[10px] font-bold text-slate-500 dark:text-slate-400 bg-white dark:bg-slate-900 px-2 py-0.5 rounded-md border border-slate-200 dark:border-slate-700"
                                                        x-text="(room.max_occupancy || room.occupancy) + ' pax max'"></span>
                                                </div>
                                                <p x-show="room.price_updated_at" class="text-[10px] text-slate-500 dark:text-slate-400"
                                                    x-text="'Price updated ' + room.price_updated_at"></p>
                                                <template x-if="room.check_in_date && room.check_out_date">
                                                     <div class="text-[11px] text-slate-500 dark:text-slate-400 font-medium">
                                                        <span
                                                            x-text="room.check_in_date + ' → ' + room.check_out_date"></span>
                                                    </div>
                                                </template>
                                                <template x-if="!room.check_in_date || !room.check_out_date">
                                                    <div class="mt-1"
                                                        @date-range-changed.stop="room._checkIn = $event.detail.checkIn; room._checkOut = $event.detail.checkOut; room._available = $event.detail.available">
                                                        <x-frontend.date-range-picker :dynamic-room-id="'room.id'"
                                                            :dynamic-base-price="'room.base_price'" />
                                                    </div>
                                                </template>
                                                <div class="flex gap-1.5 pt-1">
                                                    <button @click="$store.preview.openRoomById(room.id)"
                                                        class="flex-1 text-[11px] bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-700 text-slate-700 dark:text-slate-300 font-bold px-2 py-1.5 rounded-lg hover:bg-slate-100 dark:hover:bg-slate-700 transition-colors font-headline cursor-pointer">Preview</button>
                                                    <button
                                                        @click="addToBasket('room', room.id, room.check_in_date ? { check_in_date: room.check_in_date, check_out_date: room.check_out_date, selected_pax: room.pax || 1 } : { check_in_date: room._checkIn, check_out_date: room._checkOut, selected_pax: 1 })"
                                                        :disabled="(!room.check_in_date || !room.check_out_date) && (!room._checkIn || !room._checkOut)"
                                                        class="flex-1 text-[11px] bg-ocean-600 text-white font-bold px-2 py-1.5 rounded-lg hover:bg-ocean-700 transition-colors font-headline disabled:opacity-40 disabled:cursor-not-allowed cursor-pointer">+
                                                        Trip Basket</button>
                                                </div>
                                            </div>
                                        </template>
                                    </div>
                                </template>

                                {{-- Activity cards --}}
                                <template x-if="msg.activities">
                                    <div class="mt-3 space-y-2">
                                        <template x-for="act in msg.activities">
                                            <div
                                                class="bg-slate-50 dark:bg-slate-800 rounded-xl p-2.5 border border-slate-200/80 dark:border-slate-700 space-y-1.5 relative">
                                                <span x-show="msg.activities[0] && msg.activities[0].id === act.id"
                                                    class="absolute -top-1.5 -right-1.5 text-[10px] font-black bg-coral-500 text-white px-2 py-0.5 rounded-full shadow-xs border border-white"
                                                    x-text="rankPill(msg)"></span>
                                                <img x-show="act.image" :src="imgSrc(act.image)"
                                                    class="w-full h-24 object-cover rounded-lg mb-1 border border-slate-200 dark:border-slate-700"
                                                    alt="" onerror="this.style.display='none'">
                                                <p class="text-xs font-bold text-slate-900 dark:text-slate-100 font-headline"
                                                    x-text="act.activity_name"></p>
                                                <p x-show="act.weather_dependent === true" class="mt-1 text-xs text-slate-500 dark:text-slate-400">Weather-dependent</p>
                                                <p class="text-[11px] text-slate-500 dark:text-slate-400" x-text="act.destination"></p>
                                                <div class="flex items-center justify-between pt-0.5">
                                                    <span class="text-xs font-black text-emerald-600 font-headline"
                                                        x-text="act.rate || '₱' + new Intl.NumberFormat().format(act.base_price)"></span>
                                                    <span
                                                        class="text-[10px] font-bold text-slate-500 dark:text-slate-400 bg-white dark:bg-slate-900 px-2 py-0.5 rounded-md border border-slate-200 dark:border-slate-700 capitalize"
                                                        x-text="act.category"></span>
                                                </div>
                                                <p x-show="act.price_updated_at" class="text-[10px] text-slate-500 dark:text-slate-400"
                                                    x-text="'Price updated ' + act.price_updated_at"></p>
                                                <div class="flex gap-1.5 pt-1">
                                                    <button @click="$store.preview.openActivityById(act.id)"
                                                        class="flex-1 text-[11px] bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-700 text-slate-700 dark:text-slate-300 font-bold px-2 py-1.5 rounded-lg hover:bg-slate-100 dark:hover:bg-slate-700 transition-colors font-headline cursor-pointer">Preview</button>
                                                    <button
                                                        @click="addToBasket('activity', act.id, { selected_pax: 1 })"
                                                        class="flex-1 text-[11px] bg-emerald-600 text-white font-bold px-2 py-1.5 rounded-lg hover:bg-emerald-700 transition-colors font-headline cursor-pointer">+
                                                        Trip Basket</button>
                                                </div>
                                            </div>
                                        </template>
                                    </div>
                                </template>

                                {{-- Hotel cards --}}
                                <template x-if="msg.hotels">
                                    <div class="mt-3 space-y-2">
                                        <template x-for="hotel in msg.hotels">
                                            <div
                                                class="bg-slate-50 dark:bg-slate-800 rounded-xl p-2.5 border border-slate-200/80 dark:border-slate-700 space-y-1.5 relative">
                                                <span x-show="msg.hotels[0] && msg.hotels[0].id === hotel.id"
                                                    class="absolute -top-1.5 -right-1.5 text-[10px] font-black bg-coral-500 text-white px-2 py-0.5 rounded-full shadow-xs border border-white"
                                                    x-text="rankPill(msg)"></span>
                                                <img x-show="hotel.image" :src="imgSrc(hotel.image)"
                                                    class="w-full h-24 object-cover rounded-lg mb-1 border border-slate-200 dark:border-slate-700"
                                                    alt="" onerror="this.style.display='none'">
                                                <p class="text-xs font-bold text-slate-900 dark:text-slate-100 font-headline"
                                                    x-text="hotel.hotel_name"></p>
                                                <p class="text-[11px] text-slate-500 dark:text-slate-400" x-text="hotel.destination"></p>
                                                <div class="flex items-center justify-between pt-0.5">
                                                    <span
                                                        class="text-[10px] font-bold text-slate-500 dark:text-slate-400 bg-white dark:bg-slate-900 px-2 py-0.5 rounded-md border border-slate-200 dark:border-slate-700 capitalize"
                                                        x-text="hotel.type"></span>
                                                    <span
                                                        x-show="hotel.price_from !== null && hotel.price_from !== undefined"
                                                        class="text-[11px] font-black text-ocean-700"
                                                        x-text="'From ₱' + new Intl.NumberFormat().format(hotel.price_from) + '/night'"></span>
                                                </div>
                                                <p x-show="hotel.price_updated_at" class="text-[10px] text-slate-500 dark:text-slate-400"
                                                    x-text="'Price updated ' + hotel.price_updated_at"></p>
                                                <a :href="'/hotels/' + hotel.id"
                                                    class="mt-1.5 block text-center text-[11px] bg-ocean-600 text-white font-bold px-2 py-1.5 rounded-lg hover:bg-ocean-700 transition-colors font-headline cursor-pointer">View
                                                    Hotel</a>
                                            </div>
                                        </template>
                                    </div>
                                </template>

                                {{-- Package cards --}}
                                <template x-if="msg.packages">
                                    <div class="mt-3 space-y-2">
                                        <template x-for="pkg in msg.packages">
                                            <div
                                                class="bg-slate-50 dark:bg-slate-800 rounded-xl p-2.5 border border-slate-200/80 dark:border-slate-700 space-y-1.5 relative">
                                                <span x-show="msg.packages[0] && msg.packages[0].id === pkg.id"
                                                    class="absolute -top-1.5 -right-1.5 text-[10px] font-black bg-coral-500 text-white px-2 py-0.5 rounded-full shadow-xs border border-white"
                                                    x-text="rankPill(msg)"></span>
                                                <img x-show="pkg.image" :src="imgSrc(pkg.image)"
                                                    class="w-full h-24 object-cover rounded-lg mb-1 border border-slate-200 dark:border-slate-700"
                                                    alt="" onerror="this.style.display='none'">
                                                <p class="text-xs font-bold text-slate-900 dark:text-slate-100 font-headline"
                                                    x-text="pkg.name"></p>
                                                <p class="text-[11px] text-slate-500 dark:text-slate-400" x-text="pkg.destination"></p>
                                                <div class="flex items-center justify-between pt-0.5">
                                                    <span class="text-xs font-black text-amber-600 font-headline"
                                                        x-text="'₱' + new Intl.NumberFormat().format(pkg.price)"></span>
                                                    <span
                                                        class="text-[10px] font-bold text-slate-500 dark:text-slate-400 bg-white dark:bg-slate-900 px-2 py-0.5 rounded-md border border-slate-200 dark:border-slate-700"
                                                        x-text="pkg.days + 'D/' + pkg.nights + 'N'"></span>
                                                </div>
                                                <p x-show="pkg.price_updated_at" class="text-[10px] text-slate-500 dark:text-slate-400"
                                                    x-text="'Price updated ' + pkg.price_updated_at"></p>
                                                <div class="flex gap-1.5 pt-1">
                                                    <button @click="$store.preview.openPackageById(pkg.id)"
                                                        class="flex-1 text-[11px] bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-700 text-slate-700 dark:text-slate-300 font-bold px-2 py-1.5 rounded-lg hover:bg-slate-100 dark:hover:bg-slate-700 transition-colors font-headline cursor-pointer">Preview</button>
                                                    <button
                                                        @click="addToBasket('package', pkg.id, { quantity: pkg.min_pax || 2, selected_pax: pkg.min_pax || 2 })"
                                                        class="flex-1 text-[11px] bg-amber-500 text-slate-950 font-bold px-2 py-1.5 rounded-lg hover:bg-amber-600 transition-colors font-headline cursor-pointer">+
                                                        Add to Trip Basket</button>
                                                </div>
                                            </div>
                                        </template>
                                    </div>
                                </template>

                                {{-- Itinerary card --}}
                                <template x-if="msg.addons">
                                    <div class="mt-3 space-y-2">
                                        <template x-for="addon in msg.addons" :key="addon.id">
                                            <div class="border border-slate-200 dark:border-slate-700 rounded-xl p-3 font-body text-xs">
                                                <p class="font-bold" x-text="addon.name"></p>
                                                <p x-text="addon.price_quote?.display || 'Price unavailable'"></p>
                                            </div>
                                        </template>
                                    </div>
                                </template>
                                <template x-if="msg.itinerary">
                                    <div
                                        class="mt-3 bg-slate-50 dark:bg-slate-800 rounded-xl p-2.5 border border-slate-200/80 dark:border-slate-700 text-xs space-y-1.5">
                                        <p class="font-bold text-slate-900 dark:text-slate-100 font-headline flex items-center gap-1.5">
                                            <span x-text="msg.itinerary.destination.name"></span>
                                            <span x-show="msg.itinerary.days"
                                                class="text-[10px] font-body font-bold px-1.5 py-0.5 rounded bg-ocean-100 dark:bg-ocean-900/50 text-ocean-700 dark:text-ocean-100"
                                                x-text="msg.itinerary.days + 'D/' + msg.itinerary.nights + 'N'"></span>
                                        </p>
                                        <p class="text-slate-600 dark:text-slate-400"><span x-text="msg.itinerary.room.room_name"></span>
                                            &mdash; <span x-text="msg.itinerary.room.formatted_total"></span></p>
                                        <template x-for="act in msg.itinerary.activities">
                                            <p class="text-slate-600 dark:text-slate-400"><span x-text="act.activity_name"></span> &mdash;
                                                <span x-text="act.formatted_cost"></span>
                                            </p>
                                        </template>
                                        <p class="font-black text-ocean-600 font-headline text-xs pt-1 border-t border-slate-200 dark:border-slate-700"
                                            x-text="'Total: ' + msg.itinerary.formatted_grand_total"></p>
                                        <p x-show="itineraryBudgetLabel(msg.itinerary)"
                                            class="text-[11px] font-bold"
                                            :class="{
                                                'text-emerald-700 dark:text-emerald-400': msg.itinerary.budget_check?.status === 'within',
                                                'text-amber-700 dark:text-amber-400': msg.itinerary.budget_check?.status === 'slightly_over',
                                                'text-slate-500 dark:text-slate-400': msg.itinerary.budget_check?.status === 'under_target',
                                                'text-red-600 dark:text-red-400': msg.itinerary.budget_check?.status === 'over',
                                            }"
                                            x-text="itineraryBudgetLabel(msg.itinerary)"></p>
                                        <div class="grid grid-cols-2 gap-1.5 pt-1">
                                            <button type="button" @click="addItineraryToBasket(msg.itinerary)"
                                                class="col-span-2 bg-slate-900 text-white font-bold px-2 py-1.5 rounded-lg hover:bg-slate-800 transition-colors text-[11px] font-headline cursor-pointer flex items-center justify-center gap-1">
                                                <span
                                                    class="material-symbols-outlined text-[14px]">add_shopping_cart</span>
                                                Add Whole Itinerary to Trip Basket</button>
                                            <button
                                                @click="addToBasket('room', msg.itinerary.room.id, { check_in_date: msg.itinerary.check_in_date || null, check_out_date: msg.itinerary.check_out_date || null, selected_pax: 1 })"
                                                class="bg-ocean-600 text-white font-bold px-2 py-1.5 rounded-lg hover:bg-ocean-700 transition-colors text-[11px] font-headline cursor-pointer">+
                                                Room</button>
                                            <template x-for="act in msg.itinerary.activities">
                                                <button @click="addToBasket('activity', act.id, {})"
                                                    class="bg-emerald-600 text-white font-bold px-2 py-1.5 rounded-lg hover:bg-emerald-700 transition-colors text-[11px] font-headline cursor-pointer">+
                                                    <span x-text="act.activity_name.substring(0,12)"></span></button>
                                            </template>
                                        </div>
                                    </div>
                                </template>

                                {{-- Suggested actions (pills) --}}
                                <template x-if="msg.suggested_actions && msg.suggested_actions.length">
                                    <div class="mt-3 flex flex-wrap gap-1.5">
                                        <template x-for="action in msg.suggested_actions" :key="action.id">
                                            <button type="button" @click="useSuggestedAction(msg, action)"
                                                :disabled="isActionDisabled(msg, action)"
                                                :aria-disabled="isActionDisabled(msg, action)"
                                                 :class="isActionUsed(msg.id, action.id) ? 'bg-slate-100 dark:bg-slate-800 text-slate-400 dark:text-slate-500 border-slate-200 dark:border-slate-700 cursor-default' : 'bg-ocean-50 dark:bg-ocean-900/50 text-ocean-700 dark:text-ocean-100 border-ocean-200 dark:border-ocean-800 hover:bg-ocean-100 dark:hover:bg-ocean-900 cursor-pointer'"
                                                class="text-[11px] font-bold px-3 py-1.5 rounded-full border transition-colors font-headline disabled:opacity-60">
                                                <span x-text="action.label"></span>
                                            </button>
                                        </template>
                                    </div>
                                </template>

                                @include('components.frontend.chat-trip-details')

                                <template x-if="msg.itinerary_suggestion">
                                    <div data-trip-plan-actions class="mt-3 space-y-2 text-xs font-body">
                                        @include('components.frontend.chat-itinerary-summary', ['suggestionExpression' => 'msg.itinerary_suggestion'])
                                        <template x-for="day in msg.itinerary_suggestion.days" :key="day.day">
                                            <section class="border-t border-sand-200 pt-2 dark:border-slate-700">
                                                <h4 class="font-bold" x-text="`Day ${day.day}${day.date ? ' · ' + day.date : ''}${day.day === 1 ? ' · Arrival' : (day.day === msg.itinerary_suggestion.duration_days ? ' · Departure' : '')}`"></h4>
                                                <template x-for="choice in day.choices" :key="choice.type + ':' + choice.id"><p x-text="choice.name"></p></template>
                                                <p x-show="!day.choices.length">Open time — arrange activities at your own pace.</p>
                                            </section>
                                        </template>
                                        <p class="text-slate-600 dark:text-slate-300" x-text="msg.itinerary_suggestion.weather_notice"></p>
                                        <p class="text-slate-500 dark:text-slate-400">Save this as a draft. Rooms and activities are reserved only through checkout.</p>
                                        <button type="button" x-show="!savedTripPlans[msg.itinerary_suggestion.id]" @click="saveItineraryPlan(msg.itinerary_suggestion, $el)" :disabled="savingTripPlan === msg.itinerary_suggestion.id"
                                            class="min-h-11 rounded-lg bg-ocean-700 px-4 py-2 font-bold text-white disabled:opacity-50">Add to Trip Plan</button>
                                        <a data-trip-plan-return-focus x-show="typeof savedTripPlans[msg.itinerary_suggestion.id] === 'string'" :href="savedTripPlans[msg.itinerary_suggestion.id] || '#'" class="inline-flex min-h-11 items-center font-bold text-ocean-700 dark:text-ocean-300">Saved — View Trip Plan</a>
                                        <p x-show="savedTripPlans[msg.itinerary_suggestion.id] === true" class="font-bold text-ocean-700 dark:text-ocean-300">Added to your Trip Plan — <a data-trip-plan-return-focus :href="loginUrl" class="underline">sign in to view it</a>.</p>
                                        <p role="status" x-show="tripPlanNotices[msg.itinerary_suggestion.id]" x-text="tripPlanNotices[msg.itinerary_suggestion.id]"></p>
                                    </div>
                                </template>

                                {{-- Compact trip summary with per-field edits --}}
                                <template x-if="activeTripSummary(msg)">
                                    <div class="mt-3 rounded-xl border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-800/60 p-3 space-y-1.5">
                                        <p class="text-[11px] font-bold text-ink-700 dark:text-slate-200 font-headline"
                                            x-text="activeTripSummary(msg).heading"></p>
                                        <dl class="space-y-1">
                                            <template x-for="item in activeTripSummary(msg).items" :key="item.field">
                                                <div class="flex items-center justify-between gap-2">
                                                    <div class="min-w-0">
                                                        <dt class="inline text-[11px] text-slate-500 dark:text-slate-400 font-label"
                                                            x-text="item.label + ': '"></dt>
                                                        <dd class="inline text-[11px] font-bold text-ink-800 dark:text-slate-100 font-body"
                                                            x-text="item.value"></dd>
                                                    </div>
                                                    <button type="button" @click="editTripField(item)"
                                                        :aria-label="'Change ' + item.label"
                                                        class="shrink-0 text-[10px] font-bold px-2.5 py-1.5 rounded-full border border-slate-200 dark:border-slate-700 text-ocean-700 dark:text-ocean-100 hover:bg-ocean-50 dark:hover:bg-ocean-900 transition-colors font-headline cursor-pointer">
                                                        Change
                                                    </button>
                                                </div>
                                            </template>
                                        </dl>
                                    </div>
                                </template>

                                {{-- Question controls: shown only for the question the
                                     server is still waiting on. The composer stays
                                     available for a typed answer at every step. --}}
                                <template x-if="activeControl(msg)">
                                    <form @submit.prevent="submitControl(msg)"
                                        class="mt-3 rounded-xl border border-ocean-200/80 dark:border-ocean-800 bg-ocean-50/70 dark:bg-ocean-900/40 p-3 space-y-2.5">
                                        <fieldset class="space-y-2.5" :disabled="controlBusy || sending">
                                            <legend class="sr-only" x-text="msg.clarification.text"></legend>
                                            <template x-for="field in activeControl(msg).fields" :key="field.name">
                                                <div class="space-y-1">
                                                    <label class="block text-[11px] font-bold text-ink-700 dark:text-slate-200 font-label"
                                                        :for="msg.clarification.id + '-' + field.name" x-text="field.label"></label>

                                                    <template x-if="field.input === 'select'">
                                                        <select :id="msg.clarification.id + '-' + field.name"
                                                            :value="controlValue(msg.clarification.id, field.name)"
                                                            @change="setControlValue(msg.clarification.id, field.name, $event.target.value)"
                                                            class="w-full rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-900 text-xs px-3 py-2 font-body text-slate-800 dark:text-slate-200 focus:border-ocean-500 focus:outline-none min-h-[44px]">
                                                            <option value="">Choose one…</option>
                                                            <template x-for="option in field.options" :key="option.value">
                                                                <option :value="option.value" x-text="option.label"
                                                                    :selected="controlValue(msg.clarification.id, field.name) === option.value"></option>
                                                            </template>
                                                        </select>
                                                    </template>

                                                    <template x-if="field.input === 'date' || field.input === 'number'">
                                                        <input :type="field.input"
                                                            :id="msg.clarification.id + '-' + field.name"
                                                            :min="field.min" :max="field.max" :step="field.step || null"
                                                            :value="controlValue(msg.clarification.id, field.name)"
                                                            @input="setControlValue(msg.clarification.id, field.name, $event.target.value)"
                                                            :aria-invalid="controlError(field.name) ? 'true' : 'false'"
                                                            :aria-describedby="controlError(field.name) ? msg.clarification.id + '-' + field.name + '-error' : null"
                                                            class="w-full rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-900 text-xs px-3 py-2 font-body text-slate-800 dark:text-slate-200 focus:border-ocean-500 focus:outline-none min-h-[44px]">
                                                    </template>

                                                    <template x-if="field.input === 'radio'">
                                                        <div class="flex flex-wrap gap-1.5" role="radiogroup" :aria-label="field.label">
                                                            <template x-for="option in field.options" :key="option.value">
                                                                <button type="button"
                                                                    @click="setControlValue(msg.clarification.id, field.name, option.value)"
                                                                    :aria-pressed="controlValue(msg.clarification.id, field.name) === option.value"
                                                                    :class="controlValue(msg.clarification.id, field.name) === option.value
                                                                        ? 'bg-ocean-600 text-white border-ocean-600'
                                                                        : 'bg-white dark:bg-slate-900 text-ocean-700 dark:text-ocean-100 border-ocean-200 dark:border-ocean-800 hover:bg-ocean-100 dark:hover:bg-ocean-900'"
                                                                    class="text-[11px] font-bold px-3 py-2 rounded-full border transition-colors font-headline cursor-pointer min-h-[44px]">
                                                                    <span x-text="option.label"></span>
                                                                </button>
                                                            </template>
                                                        </div>
                                                    </template>

                                                    <template x-if="controlError(field.name)">
                                                        <p class="text-[11px] font-bold text-coral-600 dark:text-coral-300 font-label"
                                                            :id="msg.clarification.id + '-' + field.name + '-error'"
                                                            x-text="controlError(field.name)"></p>
                                                    </template>
                                                </div>
                                            </template>

                                            <div class="flex flex-wrap items-center gap-1.5 pt-0.5">
                                                <button type="submit"
                                                    class="text-[11px] font-bold px-3 py-2 rounded-full bg-ocean-600 text-white hover:bg-ocean-700 transition-colors font-headline cursor-pointer disabled:opacity-60 min-h-[44px]">
                                                    <span x-text="activeControl(msg).submit_label"></span>
                                                </button>
                                                <template x-if="activeControl(msg).skip">
                                                    <button type="button" @click="submitControl(msg, { skip: true })"
                                                        class="text-[11px] font-bold px-3 py-2 rounded-full border border-slate-200 dark:border-slate-700 text-slate-600 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-800 transition-colors font-headline cursor-pointer min-h-[44px]">
                                                        <span x-text="activeControl(msg).skip.label"></span>
                                                    </button>
                                                </template>
                                                <p class="text-[10px] text-slate-500 dark:text-slate-400 font-body"
                                                    x-text="activeControl(msg).manual_hint"></p>
                                            </div>
                                        </fieldset>
                                    </form>
                                </template>

                                {{-- Location request --}}
                                <template x-if="msg.location_request">
                                    <div class="mt-3 bg-ocean-50 dark:bg-ocean-900/50 rounded-xl p-3 border border-ocean-200/80 dark:border-ocean-800 space-y-2">
                                        <p
                                            class="text-xs font-bold text-ocean-800 dark:text-ocean-100 font-headline flex items-center gap-1.5">
                                            <span class="material-symbols-outlined text-[16px]">my_location</span>
                                            Share your location?
                                        </p>
                                        <p class="text-[11px] text-ocean-700 dark:text-ocean-100 leading-relaxed">Allow location access to
                                            see your distance to <span class="font-bold"
                                                x-text="msg.location_target"></span>.</p>
                                        <button @click="shareLocation(msg.location_target)" :disabled="locatingLocation"
                                            class="w-full text-xs bg-ocean-600 text-white font-bold px-3 py-2 rounded-xl hover:bg-ocean-700 transition-colors font-headline flex items-center justify-center gap-1.5 disabled:opacity-50 disabled:cursor-not-allowed cursor-pointer">
                                            <span class="material-symbols-outlined text-[16px]"
                                                x-text="locatingLocation ? 'progress_activity' : 'near_me'"></span>
                                            <span
                                                x-text="locatingLocation ? 'Locating…' : 'Share my current location'"></span>
                                        </button>
                                        <p x-show="geoError" x-text="geoError"
                                            class="text-[11px] text-amber-700 dark:text-amber-100 bg-amber-50 dark:bg-amber-950/60 border border-amber-200 dark:border-amber-800 rounded-lg px-2 py-1.5">
                                        </p>
                                        <p class="text-[10px] text-slate-500 dark:text-slate-400">Or tell me where you are, e.g. “How far is
                                            El Nido from <span x-text="msg.location_target"></span>?”</p>
                                    </div>
                                </template>

                                {{-- Distance result --}}
                                <template x-if="msg.map && msg.map.distance_label">
                                    <div
                                        class="mt-3 bg-emerald-50 dark:bg-emerald-950/60 rounded-xl p-2.5 border border-emerald-200/80 dark:border-emerald-800 flex items-center justify-between">
                                        <span
                                            class="text-xs font-bold text-emerald-800 dark:text-emerald-100 font-headline flex items-center gap-1">
                                            <span class="material-symbols-outlined text-[16px]">route</span>
                                            <span x-text="msg.map.distance_label + ' away'"></span>
                                        </span>
                                        <span
                                            class="text-[10px] text-emerald-700 dark:text-emerald-100 bg-white dark:bg-slate-900 px-2 py-0.5 rounded-md border border-emerald-200 dark:border-emerald-800"
                                            x-text="msg.map.from?.label === 'You' ? 'from you' : ''"></span>
                                    </div>
                                </template>
                            </div>
                        </div>
                    </template>

                    {{-- User --}}
                    <template x-if="msg.sender === 'user'">
                        <div class="flex justify-end">
                            <div
                                class="bg-ocean-600 text-white rounded-2xl rounded-tr-xs px-4 py-2.5 max-w-[80%] shadow-xs">
                                <p class="text-xs sm:text-sm font-body font-medium" x-text="msg.text"></p>
                            </div>
                        </div>
                    </template>

                    {{-- Admin (support agent) --}}
                    <template x-if="msg.sender === 'admin'">
                        <div class="flex gap-2.5 items-start">
                            <div
                                class="w-7 h-7 rounded-xl bg-coral-600 text-white flex items-center justify-center text-xs font-bold shrink-0 font-headline shadow-xs mt-0.5">
                                A</div>
                            <div
                                class="bg-white dark:bg-slate-800 rounded-2xl rounded-tl-xs px-3.5 py-3 shadow-xs border border-coral-200/80 max-w-[85%]">
                                <div class="text-xs sm:text-sm text-slate-800 dark:text-slate-200 font-body leading-relaxed space-y-2"
                                    x-html="renderMarkdown(msg.text)"></div>
                            </div>
                        </div>
                    </template>
                </div>
            </template>

            {{-- Thinking orb --}}
            <div x-show="sending" class="flex gap-2.5 items-start pl-1 py-1">
                <div
                    class="w-7 h-7 rounded-xl bg-white flex items-center justify-center shrink-0 shadow-xs overflow-hidden border border-amber-100">
                    <img src="{{ asset('images/sun-chat-dots.svg') }}" alt="SunnyBot" class="w-7 h-7 object-contain">
                </div>
                <div class="bg-white dark:bg-slate-800 rounded-2xl rounded-tl-xs px-4 py-3 shadow-xs border border-slate-200/80 dark:border-slate-700">
                    <x-thinking-orb state="composing" :size="18" />
                </div>
            </div>

            {{-- Guest rate limit / spam block: last child so it renders at the
            bottom of the list where the eye already rests (list auto-scrolls
            to bottom on every message; input hides while limited). --}}
            <template x-if="guestLimited">
                <div class="text-center py-6 px-4 bg-white dark:bg-slate-900 rounded-2xl border border-slate-200/80 dark:border-slate-700/80 shadow-xs space-y-3">
                    <div
                        class="w-12 h-12 rounded-2xl bg-coral-50 text-coral-600 mx-auto flex items-center justify-center border border-coral-100">
                        <span class="material-symbols-outlined text-[24px]">lock</span>
                    </div>
                    <p class="text-slate-800 dark:text-slate-200 font-body text-xs font-bold" x-text="guestLimitMessage"></p>
                    <div class="flex flex-col gap-2 pt-1">
                        <a :href="loginUrl"
                            class="inline-block bg-ocean-600 text-white text-xs px-4 py-2.5 rounded-xl hover:bg-ocean-700 transition-colors font-headline font-bold shadow-xs">
                            Log in
                        </a>
                        <a :href="registerUrl" class="inline-block text-ocean-600 text-xs hover:underline font-bold">
                            Sign up for free
                        </a>
                    </div>
                </div>
            </template>
        </div>

        {{-- Input area --}}
        <div class="border-t border-slate-100 dark:border-slate-800 px-3.5 py-3 bg-white dark:bg-slate-900 shrink-0 space-y-2" x-show="!guestLimited">
            <form @submit.prevent="send()" class="flex gap-2 items-end">
                <textarea x-model="input" @keydown.enter.prevent="!$event.shiftKey && send()"
                    placeholder="Ask about islands, hotels, rates..." rows="1"
                    class="flex-1 resize-none rounded-2xl border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-800 text-xs sm:text-sm px-3.5 py-2.5 focus:bg-white dark:focus:bg-slate-900 focus:border-ocean-500 focus:outline-none transition-all font-body text-slate-800 dark:text-slate-200 placeholder:text-slate-400"
                    :disabled="sending" x-ref="input" @input="autoResize($el)"
                    :aria-describedby="input.length > inputLimit * 0.8 ? 'chat-input-counter' : null"></textarea>
                <button type="submit" :disabled="sending || !input.trim() || input.length > inputLimit"
                    class="shrink-0 w-9 h-9 sm:w-10 sm:h-10 rounded-2xl bg-ocean-600 text-white flex items-center justify-center hover:bg-ocean-700 transition-colors shadow-xs disabled:opacity-40 disabled:cursor-not-allowed cursor-pointer"
                    aria-label="Send message">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M12 19l9 2-9-18-9 18 9-2zm0 0v-8" />
                    </svg>
                </button>
            </form>
            <template x-if="input.length > inputLimit * 0.8">
                <p id="chat-input-counter" aria-live="polite"
                    :class="input.length > inputLimit ? 'text-coral-600 dark:text-coral-300' : 'text-slate-500 dark:text-slate-400'"
                    class="text-right text-[10px] font-bold font-label">
                    <span x-text="input.length"></span>/<span x-text="inputLimit"></span>
                    <span x-show="input.length > inputLimit"> — please shorten this message before sending.</span>
                </p>
            </template>
            <p class="text-center text-black dark:text-slate-400 text-[10px] font-medium leading-tight">SunnyBot AI can make mistakes.
                Be specific in inquiries for higher accuracy. Do not treat SunnyBot as a Human.</p>
        </div>
    </div>
</div>

<script>
    function chatWidget() {
        return {
            open: false,
            cartOpen: false,
            input: '',
            messages: [],
            sending: false,
            guestLimited: false,
            guestLimitMessage: '',
            loginUrl: '{{ route('login') }}',
            registerUrl: '{{ route('register') }}',
            sessionToken: null,
            handoffStatus: null,
            handoffTicket: '',
            handoffPollTimer: null,
            handoffLastMessageId: 0,
            locatingLocation: false,
            geoError: null,
            hasUnreadAdmin: false,
            showGuide: false,
            // Plain array, not a Set: an immutable reassignment is reactive in
            // every Alpine version, so a used pill disables itself immediately.
            usedActionIds: [],
            // The question the server is currently waiting on. Held as a
            // primitive on the component root so staleness is read from live
            // state rather than inferred from a derived message object.
            activeQuestionId: null,
            nextMessageId: 0,
            controlValues: {},
            controlErrors: {},
            controlBusy: false,
            activeTripFormId: null,
            savingTripPlan: null,
            savedTripPlans: {},
            tripPlanNotices: {},
            inputLimit: @json(\App\Http\Controllers\ChatbotController::MESSAGE_LIMIT),
            isAuthed: @json(auth()->check()),

            async init() {
                this.sessionToken = localStorage.getItem('sunnytrips_chat_session');
                // Authed users with lost token (logout rotation) recover via /chat/active instead of empty.
                if (!this.sessionToken && this.isAuthed) {
                    await this.syncActiveSession();
                }
                await this.loadConversation();
                // After initial load, sync again to catch admin-initiated while offline (token mismatch).
                if (this.isAuthed) {
                    await this.syncActiveSession();
                    // Background check for admin-initiated messages while widget closed (badge).
                    setInterval(() => {
                        if (!this.open) this.syncActiveSession();
                    }, 30000);
                }
            },

            async syncActiveSession() {
                try {
                    const res = await fetch('/chat/active', {
                        headers: { 'Accept': 'application/json' }
                    });
                    if (!res.ok) return false;
                    const data = await res.json();
                    if (data.session_token && data.session_token !== this.sessionToken) {
                        this.sessionToken = data.session_token;
                        localStorage.setItem('sunnytrips_chat_session', data.session_token);
                        if (data.handoff_status === 'PENDING_ASSIGNMENT') {
                            this.handoffStatus = 'pending';
                            this.handoffTicket = data.ticket_number || '';
                        } else if (data.handoff_status === 'HUMAN_SUPPORT_ACTIVE') {
                            this.handoffStatus = 'active';
                        }
                        // Reload conversation with the recovered token so history reappears.
                        await this.loadConversation();
                        return true;
                    }
                    if (data.has_active) {
                        if (data.handoff_status === 'PENDING_ASSIGNMENT') {
                            this.handoffStatus = 'pending';
                            this.handoffTicket = data.ticket_number || '';
                        } else if (data.handoff_status === 'HUMAN_SUPPORT_ACTIVE') {
                            this.handoffStatus = 'active';
                        }
                        if (!this.open) this.hasUnreadAdmin = true;
                        this.startHandoffPolling();
                    }
                    return false;
                } catch (e) {
                    return false;
                }
            },

            async loadConversation() {
                if (!this.sessionToken) {
                    // Try one more recovery for authed users before giving up (covers first load with empty storage).
                    if (this.isAuthed) {
                        const recovered = await this.syncActiveSession();
                        if (!recovered) return;
                    } else {
                        return;
                    }
                }
                try {
                    const res = await fetch(`/chat/history?session_token=${encodeURIComponent(this.sessionToken)}`, {
                        headers: { 'Accept': 'application/json' }
                    });
                    if (!res.ok) return;
                    const data = await res.json();
                    // Guest probing an authed token — server returns a fresh guest token with isolated=true.
                    // Keep the owner's original token instead of overwriting it, otherwise logout→login loses history.
                    if (data.isolated) {
                        return;
                    }
                    if (data.session_token && data.session_token !== this.sessionToken) {
                        this.sessionToken = data.session_token;
                        localStorage.setItem('sunnytrips_chat_session', data.session_token);
                    }
                    if (!data.messages || !data.messages.length) {
                        // Even with no messages, honour handoff status (e.g., fresh admin-initiated).
                        if (data.handoff_status === 'PENDING_ASSIGNMENT') {
                            this.handoffStatus = 'pending';
                        } else if (data.handoff_status === 'HUMAN_SUPPORT_ACTIVE') {
                            this.handoffStatus = 'active';
                            if (!this.open) this.hasUnreadAdmin = true;
                        }
                        if (this.handoffStatus === 'pending' || this.handoffStatus === 'active') {
                            this.startHandoffPolling();
                        }
                        return;
                    }

                    const handoffStatus = data.handoff_status;
                    if (handoffStatus === 'PENDING_ASSIGNMENT') {
                        this.handoffStatus = 'pending';
                    } else if (handoffStatus === 'HUMAN_SUPPORT_ACTIVE') {
                        this.handoffStatus = 'active';
                    } else if (handoffStatus === 'RETURNED_TO_AI' || handoffStatus === 'RESOLVED') {
                        this.handoffStatus = 'resolved';
                    }
                    this.handoffLastMessageId = Math.max(0, ...data.messages.map(m => m.id));

                    if (this.handoffStatus === 'pending' || this.handoffStatus === 'active') {
                        this.startHandoffPolling();
                    }

                    this.messages = data.messages.map(msg => {
                        const extras = {};
                        const ctx = msg.context_data || {};
                        extras.sections = ctx.sections;
                        extras.clarification = ctx.clarification;
                        extras.trip_summary = ctx.trip_summary;
                        extras.trip_details_form = ctx.trip_details_form;
                        extras.itinerary_suggestion = ctx.itinerary_suggestion;
                        extras.dialogue_notice = ctx.dialogue_notice;
                        extras.section_footer = ctx.section_footer;
                        extras.price_effective_note = ctx.price_effective_note;
                        if (ctx.retrieved_rooms?.length) extras.rooms = ctx.retrieved_rooms;
                        if (ctx.retrieved_hotels?.length) extras.hotels = ctx.retrieved_hotels;
                        if (ctx.retrieved_activities?.length) extras.activities = ctx.retrieved_activities;
                        if (ctx.retrieved_packages?.length) extras.packages = ctx.retrieved_packages;
                        if (ctx.result_ordering) extras.ordering = ctx.result_ordering;
                        if (ctx.itinerary) extras.itinerary = ctx.itinerary;
                        if (ctx.map) extras.map = ctx.map;
                        if (ctx.ai_notice) extras.ai_notice = ctx.ai_notice;
                        if (ctx.evidence_note) extras.evidence_note = ctx.evidence_note;
                        if (ctx.knowledge?.length) extras.knowledge = ctx.knowledge;
                        if (msg.sender === 'bot' && ctx.trace?.trace_id) extras.trace_id = ctx.trace.trace_id;
                        if (ctx.suggested_actions?.length) extras.suggested_actions = ctx.suggested_actions;
                        if (ctx.location_request) {
                            extras.location_request = true;
                            extras.location_target = ctx.location_target || ctx.map?.target?.name || '';
                        }
                        return { id: msg.id, sender: msg.sender, text: msg.text, ...extras };
                    });
                    // The question the server is still waiting on survives a
                    // refresh, so its control stays live and older ones do not.
                    const lastBot = [...this.messages].reverse().find(m => m.sender === 'bot');
                    this.activeQuestionId = lastBot?.clarification?.id ?? null;
                    this.activeTripFormId = lastBot?.trip_details_form?.id ?? null;
                    await this.restoreSavedTripPlans();
                    this.$nextTick(() => this.scrollDown());
                } catch (err) {
                    console.error('Failed to load chat history:', err);
                }
            },
            toggle() {
                this.open = !this.open;
                if (this.open) {
                    this.hasUnreadAdmin = false;
                    @if (session('show_chat_guide'))
                        // Fresh login: reset the seen-flag so the guide shows again.
                        localStorage.removeItem('sunnytrips_chat_guide_seen');
                    @endif
                    // First-open guide: show once per browser, only when there is
                    // no history to restore. Flag is set on display, so even a
                    // dismiss-free close still counts as "seen".
                    if (this.messages.length === 0
                        && !localStorage.getItem('sunnytrips_chat_guide_seen')) {
                        this.showGuide = true;
                        localStorage.setItem('sunnytrips_chat_guide_seen', '1');
                    }
                    this.syncActiveSession().then(() => this.loadConversation());
                    this.$nextTick(() => {
                        this.$refs.input?.focus();
                        this.scrollDown();
                    });
                }
            },

            close() {
                this.open = false;
                this.stopHandoffPolling();
            },

            dismissGuide() {
                this.showGuide = false;
            },

            autoResize(el) {
                el.style.height = 'auto';
                el.style.height = Math.min(el.scrollHeight, 100) + 'px';
            },

            escapeHtml(value) {
                return String(value ?? '').replace(/[&<>"']/g, character => ({
                    '&': '&amp;',
                    '<': '&lt;',
                    '>': '&gt;',
                    '"': '&quot;',
                    "'": '&#039;',
                }[character]));
            },

            renderMarkdown(text) {
                const escaped = this.escapeHtml(text);
                const lines = escaped.split(/\r?\n/);
                const blocks = [];
                let paragraph = [];
                let listType = null;
                let listItems = [];

                const linkify = value => {
                    const stored = [];
                    const withPlaceholders = value.replace(
                        /\[([^\]]+)\]\((https?:\/\/[^)\s]+|\/[^)\s]*)\)/g,
                        (match, label, url) => {
                            stored.push(`<a href="${url}" target="_blank" rel="noopener noreferrer" class="text-ocean-600 dark:text-ocean-300 underline underline-offset-2 hover:text-ocean-700 dark:hover:text-ocean-200 break-all">${label}</a>`);
                            return `\u0000CHATLINK${stored.length - 1}\u0000`;
                        }
                    );
                    const autolinked = withPlaceholders
                        .replace(/(https?:\/\/[^\s<]+?)([.,;:!?)]?(?=\s|$|<))/g, '<a href="$1" target="_blank" rel="noopener noreferrer" class="text-ocean-600 dark:text-ocean-300 underline underline-offset-2 hover:text-ocean-700 dark:hover:text-ocean-200 break-all">$1</a>$2')
                        .replace(/(?<![\w/"'])(\/bookings\/[A-Za-z0-9\-_]+)([.,;:!?)]?(?=\s|$|<))/g, '<a href="$1" target="_blank" rel="noopener noreferrer" class="text-ocean-600 dark:text-ocean-300 underline underline-offset-2 hover:text-ocean-700 dark:hover:text-ocean-200 break-all">$1</a>$2');
                    return autolinked.replace(/\u0000CHATLINK(\d+)\u0000/g, (match, index) => stored[Number(index)] ?? match);
                };

                const formatInline = value => linkify(value
                    .replace(/\*\*(.+?)\*\*/g, '<strong class="font-semibold text-ink-800 dark:text-slate-200">$1</strong>')
                    .replace(/(^|[^*])\*([^*\n]+)\*(?!\*)/g, '$1<em>$2</em>'));

                const flushParagraph = () => {
                    if (!paragraph.length) return;
                    blocks.push(`<p class="leading-relaxed">${paragraph.map(formatInline).join('<br>')}</p>`);
                    paragraph = [];
                };

                // Comparison tables ("| | A | B |"): the first row is the header,
                // separator rows are dropped, and the first cell of each body
                // row labels it. Scrolls horizontally inside the bubble.
                let tableRows = [];
                const flushTable = () => {
                    if (!tableRows.length) return;
                    const rows = tableRows.filter(cells => !cells.every(cell => /^:?-{3,}:?$/.test(cell)));
                    tableRows = [];
                    if (!rows.length) return;
                    const [header, ...body] = rows;
                    const head = header.map(cell => `<th scope="col" class="px-2 py-1.5 font-semibold text-ink-800 dark:text-slate-100 border-b border-slate-200 dark:border-slate-600 align-bottom">${formatInline(cell)}</th>`).join('');
                    const bodyHtml = body.map(cells => `<tr class="align-top">${cells.map((cell, index) => index === 0
                        ? `<th scope="row" class="px-2 py-1.5 font-semibold text-slate-600 dark:text-slate-300 border-b border-slate-100 dark:border-slate-700 whitespace-nowrap">${formatInline(cell)}</th>`
                        : `<td class="px-2 py-1.5 border-b border-slate-100 dark:border-slate-700">${formatInline(cell)}</td>`).join('')}</tr>`).join('');
                    blocks.push(`<div class="overflow-x-auto rounded-lg border border-slate-200 dark:border-slate-700" role="region" aria-label="Comparison table" tabindex="0"><table class="min-w-full text-left text-xs"><thead class="bg-slate-50 dark:bg-slate-900/40"><tr>${head}</tr></thead><tbody>${bodyHtml}</tbody></table></div>`);
                };

                const flushList = () => {
                    if (!listType) return;
                    const listTag = listType === 'ul' ? 'ul' : 'ol';
                    const listClass = listType === 'ul' ? 'list-disc' : 'list-decimal';
                    blocks.push(`<${listTag} class="${listClass} pl-4 space-y-1">${listItems.map(item => `<li class="leading-relaxed">${formatInline(item)}</li>`).join('')}</${listTag}>`);
                    listType = null;
                    listItems = [];
                };

                for (const rawLine of lines) {
                    const line = rawLine.trim();
                    const tableRow = line.match(/^\|(.+)\|$/);
                    if (tableRow) {
                        flushParagraph();
                        flushList();
                        tableRows.push(tableRow[1].split('|').map(cell => cell.trim()));
                        continue;
                    }
                    flushTable();
                    if (!line) {
                        flushParagraph();
                        flushList();
                        continue;
                    }

                    const heading = line.match(/^#{2,3}\s+(.+)$/);
                    const unorderedItem = line.match(/^[-*]\s+(.+)$/);
                    const orderedItem = line.match(/^\d+[.)]\s+(.+)$/);

                    if (heading) {
                        flushParagraph();
                        flushList();
                        blocks.push(`<h4 class="font-headline text-sm font-semibold text-ink-900 dark:text-slate-100 leading-snug">${formatInline(heading[1])}</h4>`);
                        continue;
                    }

                    if (unorderedItem || orderedItem) {
                        flushParagraph();
                        const nextListType = unorderedItem ? 'ul' : 'ol';
                        if (listType && listType !== nextListType) flushList();
                        listType = nextListType;
                        listItems.push((unorderedItem || orderedItem)[1]);
                        continue;
                    }

                    flushList();
                    paragraph.push(line);
                }

                flushParagraph();
                flushList();
                flushTable();

                return blocks.join('');
            },

            imgSrc(path) {
                if (!path) return '';
                if (path.startsWith('http')) return path;
                if (path.startsWith('/storage/')) return path;
                if (path.startsWith('storage/')) return '/' + path;
                return '/storage/' + path.replace(/^\/+/, '');
            },

            rankPill(msg) {
                return ({ 'price-asc': 'Lowest Price', 'price-desc': 'Highest Price', 'exact': 'Exact Match' })[msg.ordering] || 'Best Match';
            },

            scrollDown() {
                this.$nextTick(() => {
                    const el = this.$refs.messages;
                    if (!el) return;
                    const bubbles = el.querySelectorAll('[data-bot-bubble]');
                    const last = bubbles[bubbles.length - 1];
                    if (last && last.offsetHeight > el.clientHeight * 0.7) {
                        el.scrollTop = Math.max(0, last.offsetTop - 12);
                    } else {
                        el.scrollTop = el.scrollHeight;
                    }
                });
            },

            showLockPanel(message) {
                this.guestLimited = true;
                this.guestLimitMessage = message;
            },

            displayMessages() {
                return window.displayChatMessages(this.messages);
            },

            addMessage(sender, text, extra = {}) {
                this.messages = [...this.messages, { id: `local-${Date.now()}-${++this.nextMessageId}`, sender, text, ...extra }];
                this.scrollDown();
            },

            async send(question = null, textOverride = null, answer = null, details = null) {
                const structured = answer || details;
                const typed = this.input;
                const msg = (textOverride ?? this.input).trim();
                if (this.sending || this.guestLimited) return;
                if (!structured && !msg) return;
                if (!structured && msg.length > this.inputLimit) {
                    this.addMessage('bot', `That message is ${msg.length} characters. Please shorten it to ${this.inputLimit} or fewer and send again — your text is still in the box.`);
                    return;
                }

                // Optimistic echo. A structured answer has no typed text, so
                // the readable sentence comes back from the server instead.
                if (!structured) {
                    this.addMessage('user', msg);
                }
                if (textOverride === null && !structured) {
                    this.input = '';
                }
                this.sending = true;

                try {
                    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content || '';
                    const res = await fetch('/chat', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': csrfToken,
                            'Accept': 'application/json',
                        },
                        body: JSON.stringify({
                            ...(details ? details : (answer ? { answer } : { message: msg })),
                            session_token: this.sessionToken,
                            ...(question ? { question_id: question.question_id, question_revision: question.question_revision } : {}),
                        }),
                    });

                    const data = await res.json();

                    if (res.status === 409) {
                        // Busy or stale: the draft is handed back for retry and
                        // the live question id is resynced from the server.
                        if (textOverride === null && !answer) this.input = typed;
                        this.activeQuestionId = data.clarification?.id ?? this.activeQuestionId;
                        this.activeTripFormId = data.trip_details_form?.id ?? null;
                        this.addMessage('bot', data.reply || 'Your previous request is still being checked.', { clarification: data.clarification, trip_details_form: data.trip_details_form });
                        return;
                    }

                    if (res.status === 422) {
                        if (textOverride === null && !answer) this.input = typed;
                        this.controlErrors = Object.fromEntries(
                            Object.entries(data.errors || {}).map(([key, value]) => [key, Array.isArray(value) ? value[0] : value])
                        );
                        if (!structured) {
                            this.addMessage('bot', data.message || 'Please check your message and try again.');
                        }
                        return;
                    }

                    if (res.status === 429 && data.status === 'guest_limit_reached') {
                        if (data.limit_type === 'minute') {
                            this.addMessage('bot', data.reply, {});
                            return;
                        }
                        this.showLockPanel(data.reply);
                        this.loginUrl = data.login_url || this.loginUrl;
                        this.registerUrl = data.register_url || this.registerUrl;
                        return;
                    }

                    if (res.status === 429) {
                        this.addMessage('bot', 'You are sending messages too quickly. Please wait a few seconds and try again.', {});
                        return;
                    }

                    if (res.status === 403 && data.status === 'blocked') {
                        this.addMessage('bot', data.reply || 'Your message was blocked by our safety system.',
                            data.suggested_actions?.length ? { suggested_actions: data.suggested_actions } : {});
                        return;
                    }

                    if (res.status === 403) {
                        this.addMessage('bot', data.reply || 'Your message was blocked by our safety system.');
                        return;
                    }

                    if (data.session_token) {
                        this.sessionToken = data.session_token;
                        localStorage.setItem('sunnytrips_chat_session', data.session_token);
                    }

                    const extras = {};
                    if (data.retrieved_rooms && data.retrieved_rooms.length > 0) {
                        extras.rooms = data.retrieved_rooms;
                    } else if (data.availability && data.availability.length > 0) {
                        // Map raw availability entries (item + availability) to room cards when retrieved_rooms missing
                        extras.rooms = data.availability.map(entry => {
                            const r = entry.item || entry;
                            return {
                                id: r.id,
                                room_name: r.room_name,
                                hotel_name: r.hotel?.hotel_name || r.hotel_name || 'Unknown Hotel',
                                hotel_id: r.hotel_id || r.hotel?.id || null,
                                destination: r.hotel?.destination?.name || r.destination || null,
                                base_price: r.base_price,
                                occupancy: r.occupancy,
                                max_occupancy: r.max_occupancy || r.occupancy,
                                base_occupancy: r.base_occupancy || 2,
                                image: r.images ? (Array.isArray(r.images) ? r.images[0] : r.images) : r.image,
                                check_in_date: entry.check_in_date || null,
                                check_out_date: entry.check_out_date || null,
                                pax: entry.pax || null,
                            };
                        });
                    }
                    if (data.itinerary) {
                        extras.itinerary = data.itinerary;
                    }
                    if (data.retrieved_hotels) extras.hotels = data.retrieved_hotels;
                    if (data.retrieved_activities) extras.activities = data.retrieved_activities;
                    if (data.retrieved_packages) extras.packages = data.retrieved_packages;
                    if (data.result_ordering) extras.ordering = data.result_ordering;
                    if (data.map) extras.map = data.map;
                    if (data.ai_notice) extras.ai_notice = data.ai_notice;
                    if (data.evidence_note) extras.evidence_note = data.evidence_note;
                    if (data.knowledge?.length) extras.knowledge = data.knowledge;
                    if (data.trace_id) extras.trace_id = data.trace_id;
                    if (data.suggested_actions?.length) extras.suggested_actions = data.suggested_actions;
                    if (data.location_request) {
                        extras.location_request = true;
                        extras.location_target = data.location_target || data.map?.target?.name || '';
                    }

                    if (data.control === 'admin' || data.status === 'human_support_active') {
                        this.handoffStatus = 'active';
                        this.startHandoffPolling();
                        return;
                    }
                    if (data.control === 'pending' || data.status === 'pending_assignment'
                        || (data.control === 'ai' && data.handoff_status === 'PENDING_ASSIGNMENT')) {
                        this.handoffStatus = 'pending';
                        this.startHandoffPolling();
                    }

                    extras.sections = data.sections;
                    extras.clarification = data.clarification;
                    extras.trip_summary = data.trip_summary;
                    extras.trip_details_form = data.trip_details_form;
                    extras.itinerary_suggestion = data.itinerary_suggestion;
                    if (structured && data.user_message) {
                        this.addMessage('user', data.user_message);
                    }
                    this.activeQuestionId = data.clarification?.id ?? null;
                    this.activeTripFormId = data.trip_details_form?.id ?? null;
                    extras.dialogue_notice = data.dialogue_notice;
                    extras.section_footer = data.section_footer;
                    extras.price_effective_note = data.price_effective_note;
                    this.addMessage('bot', data.reply || 'I could not process that.', extras);
                } catch (err) {
                    // The draft is preserved so the user can retry the same text.
                    if (textOverride === null && !answer) this.input = typed;
                    this.addMessage('bot', 'Something went wrong. Please try again.');
                } finally {
                    this.sending = false;
                    this.$nextTick(() => this.$refs.input?.focus());
                }
            },

            addToBasket(type, id, opts) {
                if (typeof window.addToCart === 'function') {
                    window.addToCart(type, id, opts);
                }
            },

            async submitTripDetails(form, values) {
                this.controlErrors = {};
                const changes = {};
                for (const [key, raw] of Object.entries(values)) {
                    const value = raw === '' ? null : raw;
                    const previous = form.values[key] === '' ? null : (form.values[key] ?? null);
                    if (JSON.stringify(value) !== JSON.stringify(previous)) changes[key] = value;
                }
                if (changes.dates_undecided === true) {
                    delete changes.check_in_date; delete changes.check_out_date;
                }
                if (values.dates_undecided === false && values.check_in_date && values.check_out_date) {
                    delete changes.days;
                }
                if (!Object.keys(changes).length) changes.destination_id = values.destination_id;
                await this.send(null, '', null, { trip_details: changes, form_id: form.id, form_revision: form.revision, scope_id: form.scope_id });
            },

            async saveItineraryPlan(suggestion, returnFocus = null) {
                if (this.savingTripPlan) return;
                this.savingTripPlan = suggestion.id;
                try {
                    const response = await fetch(@js(route('trip-planner.chatbot-store')), { method: 'POST',
                        headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || '' },
                        body: JSON.stringify({ suggestion_id: suggestion.id, session_token: this.sessionToken }) });
                    const data = await response.json();
                    if (!response.ok) throw new Error(Object.values(data.errors || {}).flat()[0] || data.message || 'Unable to save this itinerary. Please try again.');
                    // A guest has no planner page yet, so the saved state is held
                    // as `true` until the plan is claimed at sign-in.
                    this.savedTripPlans = { ...this.savedTripPlans, [suggestion.id]: data.url || true };
                    window.dispatchEvent(new CustomEvent('show-trip-plan-modal', { detail: {
                        suggestion, url: data.url, claimed: data.claimed, alreadySaved: response.status === 200, returnFocus,
                    } }));
                } catch (error) {
                    this.tripPlanNotices = { ...this.tripPlanNotices, [suggestion.id]: error.message };
                } finally { this.savingTripPlan = null; }
            },

            async restoreSavedTripPlans() {
                const ids = this.displayMessages().map(message => message.itinerary_suggestion?.id).filter(Boolean).slice(-50);
                if (!ids.length) return;
                const params = new URLSearchParams();
                ids.forEach(id => params.append('suggestion_ids[]', id));
                try {
                    const response = await fetch(@js(route('trip-planner.chatbot-status')) + '?' + params, { headers: { Accept: 'application/json' } });
                    if (response.ok) this.savedTripPlans = (await response.json()).data;
                } catch { /* The save action can still be retried safely. */ }
            },

            itineraryBudgetLabel(itin) {
                const check = itin?.budget_check;
                if (!check || !check.budget) return '';
                const peso = (amount) => '₱' + Number(Math.abs(amount)).toLocaleString('en-PH', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
                if (check.scope === 'lodging') {
                    return (itin.room.total <= check.budget ? 'Hotel within budget' : 'Hotel over budget') + ' · activities extra';
                }
                if (check.scope === 'activities') {
                    return (itin.grand_total - itin.room.total <= check.budget ? 'Activities within budget' : 'Activities over budget') + ' · hotel extra';
                }
                switch (check.status) {
                    case 'within': return 'Within budget · ' + check.percent + '% of ' + peso(check.budget);
                    case 'slightly_over': return 'Slightly over budget · ' + peso(check.delta) + ' over ' + peso(check.budget);
                    case 'under_target': return peso(check.delta) + ' under your ' + peso(check.budget) + ' budget';
                    case 'over': return 'Over budget · ' + peso(check.delta) + ' over ' + peso(check.budget);
                    default: return '';
                }
            },

            async addItineraryToBasket(itin) {
                if (!itin || !itin.room || !itin.check_in_date || !itin.check_out_date) {
                    alert('This itinerary is missing stay dates. Please ask for a new itinerary.');
                    return;
                }
                try {
                    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content;
                    const res = await fetch('/cart/add-itinerary', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': csrfToken,
                            'Accept': 'application/json',
                            'X-Requested-With': 'XMLHttpRequest'
                        },
                        body: JSON.stringify({
                            room_id: itin.room.id,
                            activity_ids: (itin.activities || []).map(a => a.id),
                            pax: itin.pax || 2,
                            check_in_date: itin.check_in_date,
                            check_out_date: itin.check_out_date,
                        })
                    });
                    const data = await res.json();
                    if (data.success) {
                        window.dispatchEvent(new CustomEvent('cart-updated'));
                        window.dispatchEvent(new CustomEvent('show-cart-modal', {
                            detail: {
                                message: data.message || 'Your itinerary is in your Trip Basket!',
                                cartCount: data.cart_count,
                            }
                        }));
                    } else {
                        alert(data.message || 'Could not add the itinerary to your basket.');
                    }
                } catch (err) {
                    console.error('Error adding itinerary:', err);
                    alert('Could not add the itinerary to your basket. Please try again.');
                }
            },

            /**
             * The trip summary is live only on the newest bot message, so an
             * older turn never offers edits against stale values.
             */
            activeTripSummary(msg) {
                if (!msg.trip_summary?.items?.length) return null;
                const shown = this.displayMessages();
                const latest = [...shown].reverse().find(m => m.sender === 'bot' && m.trip_summary);
                return latest && latest.id === msg.id ? msg.trip_summary : null;
            },

            /**
             * Puts an edit prompt in the composer for the user to complete. A
             * typed draft is never replaced without asking.
             */
            editTripField(item) {
                if (this.input.trim() !== '' && !this.input.startsWith('Change the ')) {
                    return;
                }
                this.input = item.edit_prompt;
                this.$nextTick(() => {
                    const field = this.$refs.input;
                    if (!field) return;
                    field.focus();
                    field.setSelectionRange(field.value.length, field.value.length);
                    this.autoResize(field);
                });
            },

            isActionUsed(msgId, actionId) {
                return this.usedActionIds.includes(`${msgId}:${actionId}`);
            },

            /**
             * A question control is dead once the server moved on: either the
             * renderer marked it stale, or it no longer matches the question
             * the server is waiting on.
             */
            isActionStale(action) {
                if (!action.question_id) return false;
                return action.stale === true || action.question_id !== this.activeQuestionId;
            },

            isActionDisabled(msg, action) {
                return this.sending || this.controlBusy || this.isActionStale(action)
                    || (action.question_id && this.handoffStatus === 'active')
                    || this.isActionUsed(msg.id, action.id);
            },

            markActionUsed(key) {
                if (!this.usedActionIds.includes(key)) {
                    this.usedActionIds = [...this.usedActionIds, key];
                }
            },

            async useSuggestedAction(msg, action) {
                if (!action || (!action.prompt && !action.handoff)) {
                    return;
                }
                const key = `${msg.id}:${action.id}`;
                if (this.isActionDisabled(msg, action)) {
                    return;
                }
                this.markActionUsed(key);
                if (action.handoff) {
                    await this.requestHandoff();
                    return;
                }
                // Sent as an override so a typed draft is never overwritten.
                await this.send(action.question_id ? action : null, action.prompt);
            },

            /**
             * The control for the question the server is still waiting on, or
             * null. Answered questions render as read-only history instead.
             */
            activeControl(msg) {
                if (this.activeTripFormId) return null;
                const clarification = msg.clarification;
                if (!clarification?.control || !clarification.id) return null;
                if (clarification.id !== this.activeQuestionId) return null;
                if (this.handoffStatus === 'active') return null;
                return clarification.control;
            },

            controlValue(questionId, name) {
                return this.controlValues[`${questionId}:${name}`] ?? '';
            },

            setControlValue(questionId, name, value) {
                this.controlValues = { ...this.controlValues, [`${questionId}:${name}`]: value };
                if (this.controlErrors[name]) {
                    const next = { ...this.controlErrors };
                    delete next[name];
                    this.controlErrors = next;
                }
            },

            controlError(name) {
                return this.controlErrors[name] ?? null;
            },

            async submitControl(msg, options = {}) {
                const clarification = msg.clarification;
                const control = this.activeControl(msg);
                if (!control || this.sending || this.controlBusy) return;

                const answer = {};
                if (options.skip) {
                    answer.skip = true;
                } else {
                    for (const field of control.fields) {
                        answer[field.name] = this.controlValue(clarification.id, field.name);
                    }
                }

                this.controlBusy = true;
                this.controlErrors = {};
                try {
                    await this.send({
                        question_id: clarification.id,
                        question_revision: clarification.revision,
                    }, null, answer);
                } finally {
                    this.controlBusy = false;
                }
            },

            getCachedCoords() {
                try {
                    const raw = sessionStorage.getItem('sunnytrip_chat_coords');
                    if (!raw) return null;
                    const data = JSON.parse(raw);
                    if (!data || typeof data.lat !== 'number' || typeof data.lng !== 'number') return null;
                    return { lat: data.lat, lng: data.lng };
                } catch (_) {
                    return null;
                }
            },

            saveCoords(coords) {
                try {
                    sessionStorage.setItem('sunnytrip_chat_coords', JSON.stringify({ lat: coords.lat, lng: coords.lng, at: Date.now() }));
                } catch (_) { }
            },

            async shareLocation(destName) {
                const cached = this.getCachedCoords();
                if (cached) {
                    await this.sendWithLocation(cached.lat, cached.lng, destName);
                    return;
                }
                if (!navigator.geolocation) {
                    this.geoError = 'Your browser does not support location sharing.';
                    return;
                }
                this.locatingLocation = true;
                this.geoError = null;
                try {
                    const pos = await new Promise((resolve, reject) => {
                        navigator.geolocation.getCurrentPosition(resolve, reject, { enableHighAccuracy: true, timeout: 10000, maximumAge: 60000 });
                    });
                    const coords = { lat: pos.coords.latitude, lng: pos.coords.longitude };
                    this.saveCoords(coords);
                    await this.sendWithLocation(coords.lat, coords.lng, destName);
                } catch (err) {
                    this.geoError = err && err.code === 1
                        ? 'Location permission was blocked. Allow access in your browser settings or tell me where you are instead.'
                        : 'Could not get your location. Please try again or tell me where you are (e.g., “Manila”).';
                } finally {
                    this.locatingLocation = false;
                }
            },

            async sendWithLocation(lat, lng, destName) {
                const msg = destName ? `How far am I from ${destName}?` : 'How far am I from there?';
                this.addMessage('user', '📍 Shared my location');
                this.sending = true;
                try {
                    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content || '';
                    const res = await fetch('/chat', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': csrfToken,
                            'Accept': 'application/json',
                        },
                        body: JSON.stringify({
                            message: msg,
                            session_token: this.sessionToken,
                            user_lat: lat,
                            user_lng: lng,
                        }),
                    });
                    const data = await res.json();
                    if (data.session_token) {
                        this.sessionToken = data.session_token;
                        localStorage.setItem('sunnytrips_chat_session', data.session_token);
                    }
                    const extras = {};
                    if (data.map) extras.map = data.map;
                    if (data.location_request) {
                        extras.location_request = true;
                        extras.location_target = data.location_target || '';
                    }
                    if (data.retrieved_rooms?.length) extras.rooms = data.retrieved_rooms;
                    if (data.retrieved_hotels?.length) extras.hotels = data.retrieved_hotels;
                    if (data.retrieved_activities?.length) extras.activities = data.retrieved_activities;
                    if (data.retrieved_packages?.length) extras.packages = data.retrieved_packages;
                    if (data.result_ordering) extras.ordering = data.result_ordering;
                    if (data.itinerary) extras.itinerary = data.itinerary;
                    extras.sections = data.sections;
                    extras.clarification = data.clarification;
                    extras.trip_summary = data.trip_summary;
                    this.activeQuestionId = data.clarification?.id ?? null;
                    extras.dialogue_notice = data.dialogue_notice;
                    extras.section_footer = data.section_footer;
                    extras.price_effective_note = data.price_effective_note;
                    this.addMessage('bot', data.reply || 'I could not process that.', extras);
                } catch (_) {
                    this.addMessage('bot', 'Something went wrong. Please try again.');
                } finally {
                    this.sending = false;
                    this.$nextTick(() => this.scrollDown());
                }
            },

            onControlButton() {
                if (this.handoffStatus === 'active') {
                    this.returnToBot();
                } else {
                    this.requestHandoff();
                }
            },

            /**
             * Report a problem with one answer. Only the trace ID, task ID and
             * reason are sent; the server checks the trace belongs to this
             * conversation.
             */
            async sendFeedback(msg, reason) {
                try {
                    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content || '';
                    const res = await fetch('/chat/feedback', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': csrfToken },
                        body: JSON.stringify({ trace_id: msg.trace_id, task_id: msg.task_id || null, reason, helpful: false, session_token: this.sessionToken }),
                    });

                    return res.ok;
                } catch (err) {
                    return false;
                }
            },

            async requestHandoff() {
                if (!this.sessionToken) return;
                if (this.handoffStatus && this.handoffStatus !== 'resolved') return;
                try {
                    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content || '';
                    const res = await fetch('/chat/handoff', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': csrfToken,
                            'Accept': 'application/json',
                        },
                        body: JSON.stringify({ session_token: this.sessionToken }),
                    });
                    const data = await res.json();
                    if (data.status === 'success') {
                        this.handoffStatus = data.handoff_status === 'PENDING_ASSIGNMENT' ? 'pending' : 'active';
                        this.handoffTicket = data.ticket_number || '';
                        if (data.session_token) {
                            this.sessionToken = data.session_token;
                            localStorage.setItem('sunnytrips_chat_session', data.session_token);
                        }
                        this.startHandoffPolling();
                    } else if (data.status === 'support_unavailable') {
                        this.addMessage('bot', data.message + '\n' + data.office_hours, {});
                    }
                } catch (err) {
                    console.error('Handoff request error:', err);
                }
            },

            async returnToBot() {
                if (!this.sessionToken || this.handoffStatus !== 'active') return;
                try {
                    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content || '';
                    const res = await fetch('/chat/handoff/return', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': csrfToken,
                            'Accept': 'application/json',
                        },
                        body: JSON.stringify({ session_token: this.sessionToken }),
                    });
                    const data = await res.json();
                    if (data.status === 'success') {
                        this.handoffStatus = 'resolved';
                        this.handoffTicket = '';
                        this.stopHandoffPolling();
                        if (data.message) {
                            this.addMessage(data.message.sender, data.message.text, { id: data.message.id });
                        }
                    }
                } catch (err) {
                    console.error('Return to SunnyBot error:', err);
                }
            },

            async cancelHandoff() {
                if (!this.sessionToken) return;
                try {
                    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content || '';
                    const res = await fetch('/chat/handoff/cancel', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': csrfToken,
                            'Accept': 'application/json',
                        },
                        body: JSON.stringify({ session_token: this.sessionToken }),
                    });
                    const data = await res.json();
                    if (data.status === 'success') {
                        this.handoffStatus = null;
                        this.handoffTicket = '';
                        this.stopHandoffPolling();
                    }
                } catch (err) {
                    console.error('Cancel handoff error:', err);
                }
            },

            startHandoffPolling() {
                this.stopHandoffPolling();
                this.handoffPollTimer = setInterval(() => this.pollHandoffMessages(), this.handoffStatus === 'pending' ? 15000 : 5000);
                this.pollHandoffMessages();
            },

            stopHandoffPolling() {
                if (this.handoffPollTimer) {
                    clearInterval(this.handoffPollTimer);
                    this.handoffPollTimer = null;
                }
            },

            async pollHandoffMessages() {
                if (!this.sessionToken || !this.handoffStatus) return;
                // Keep polling even when closed to catch admin-initiated while off-screen (badge), but throttle via hasUnread.
                const wasClosed = !this.open;
                try {
                    const params = new URLSearchParams({ session_token: this.sessionToken });
                    if (this.handoffLastMessageId > 0) params.set('after_id', this.handoffLastMessageId);
                    const res = await fetch(`/chat/poll?${params}`, {
                        headers: { 'Accept': 'application/json' }
                    });
                    if (!res.ok) {
                        if (res.status === 429) {
                            this.stopHandoffPolling();
                            setTimeout(() => {
                                if (this.open && (this.handoffStatus === 'pending' || this.handoffStatus === 'active')) {
                                    this.startHandoffPolling();
                                }
                            }, 60000);
                        }
                        return;
                    }
                    const data = await res.json();

                    if (data.session_token && data.session_token !== this.sessionToken) {
                        this.sessionToken = data.session_token;
                        localStorage.setItem('sunnytrips_chat_session', data.session_token);
                    }

                    const newStatus = data.handoff_status;

                    const msgs = data.messages || [];
                    for (const msg of msgs) {
                        if (msg.id > this.handoffLastMessageId) this.handoffLastMessageId = msg.id;
                        if ((msg.sender === 'admin' || msg.sender === 'bot') && !this.messages.some(m => m.id === msg.id)) {
                            if (wasClosed) this.hasUnreadAdmin = true;
                            this.addMessage(msg.sender, msg.text, { id: msg.id });
                        }
                    }

                    if (newStatus && newStatus !== 'PENDING_ASSIGNMENT' && newStatus !== 'HUMAN_SUPPORT_ACTIVE') {
                        if (newStatus === 'RETURNED_TO_AI' || newStatus === 'RESOLVED') {
                            this.handoffStatus = 'resolved';
                        } else {
                            this.handoffStatus = null;
                        }
                        this.stopHandoffPolling();
                        return;
                    }

                    if (newStatus === 'HUMAN_SUPPORT_ACTIVE' && this.handoffStatus === 'pending') {
                        this.handoffStatus = 'active';
                        this.startHandoffPolling();
                    }
                } catch (err) {
                    console.error('Handoff poll error:', err);
                }
            },
        };
    }
</script>
