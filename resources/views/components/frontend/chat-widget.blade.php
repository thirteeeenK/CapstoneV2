<div x-data="chatWidget()" x-init="init()" class="fixed bottom-6 right-6 z-50 flex flex-col items-end gap-3"
    x-trap="open">
    {{-- Floating bubble button --}}
    <button @click="toggle()"
        class="w-14 h-14 rounded-full bg-gradient-to-r from-ocean-600 to-ocean-700 text-white shadow-xl shadow-ocean-600/30 flex items-center justify-center transition-all duration-200 focus:outline-none focus:ring-2 focus:ring-ocean-400 focus:ring-offset-2 hover:scale-105 active:scale-95 cursor-pointer"
        :class="open ? 'rotate-90 scale-95 bg-slate-900 shadow-slate-900/30' : ''" aria-label="Chat with SunnyTrips AI">
        <svg x-show="!open" class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                d="M8 10h.01M12 10h.01M16 10h.01M21 12c0 4.418-4.03 8-9 8a9.86 9.86 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z" />
        </svg>
        <svg x-show="open" class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
        </svg>
    </button>

    {{-- Chat panel --}}
    <div x-show="open" x-transition:enter="transition ease-out duration-200"
        x-transition:enter-start="opacity-0 translate-y-4 scale-95"
        x-transition:enter-end="opacity-100 translate-y-0 scale-100"
        class="w-[calc(100vw-2.5rem)] max-w-sm sm:w-[410px] h-[520px] sm:h-[570px] bg-white rounded-3xl shadow-2xl border border-slate-200/90 flex flex-col overflow-hidden"
        @click.outside="close()">
        {{-- Header --}}
        <div
            class="bg-slate-900 text-white px-4 py-3.5 flex items-center justify-between shrink-0 border-b border-slate-800 relative overflow-hidden">
            <div class="absolute top-0 right-0 w-32 h-32 bg-ocean-500/10 rounded-full blur-2xl pointer-events-none">
            </div>

            <div class="flex items-center gap-3 relative z-10">
                <div class="relative">
                    <div
                        class="w-9 h-9 rounded-2xl bg-ocean-600 text-white flex items-center justify-center text-sm font-black font-headline shadow-xs">
                        S
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

        {{-- Messages area --}}
        <div x-ref="messages" class="flex-1 overflow-y-auto px-4 py-4 space-y-4 bg-slate-50/80">
            {{-- Handoff pending banner --}}
            <template x-if="handoffStatus === 'pending'">
                <div class="bg-amber-50 border border-amber-200/90 rounded-2xl p-3.5 mx-1 shadow-xs">
                    <p class="text-xs text-amber-800 font-body leading-relaxed">
                        <span class="font-bold">Waiting for an agent...</span> An administrator will be with you
                        shortly. Ticket: <span class="font-mono font-bold text-amber-900" x-text="handoffTicket"></span>
                    </p>
                    <button @click="cancelHandoff()"
                        class="mt-1.5 text-[11px] text-amber-700 underline hover:text-amber-900 font-bold cursor-pointer">Cancel
                        request</button>
                </div>
            </template>

            {{-- Handoff active banner --}}
            <template x-if="handoffStatus === 'active'">
                <div class="bg-emerald-50 border border-emerald-200/90 rounded-2xl p-3.5 mx-1 shadow-xs">
                    <p class="text-xs text-emerald-800 font-body leading-relaxed"><span class="font-bold">You're
                            chatting with a human agent.</span> They will respond shortly.</p>
                </div>
            </template>

            {{-- Handoff resolved banner --}}
            <template x-if="handoffStatus === 'resolved'">
                <div class="bg-ocean-50 border border-ocean-200/90 rounded-2xl p-3.5 mx-1 shadow-xs">
                    <p class="text-xs text-ocean-800 font-body leading-relaxed">The support chat has ended. SunnyBot is
                        back! You can ask me anything about your island trip.</p>
                </div>
            </template>

            {{-- Guest rate limit reached --}}
            <template x-if="guestLimited">
                <div class="text-center py-6 px-4 bg-white rounded-2xl border border-slate-200/80 shadow-xs space-y-3">
                    <div
                        class="w-12 h-12 rounded-2xl bg-coral-50 text-coral-600 mx-auto flex items-center justify-center border border-coral-100">
                        <span class="material-symbols-outlined text-[24px]">lock</span>
                    </div>
                    <p class="text-slate-800 font-body text-xs font-bold" x-text="guestLimitMessage"></p>
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

            {{-- Welcome --}}
            <template x-if="messages.length === 0 && !guestLimited">
                <div class="text-center py-6 px-4 bg-white rounded-2xl border border-slate-200/80 shadow-xs space-y-2">
                    <div
                        class="w-10 h-10 rounded-2xl bg-ocean-50 text-ocean-600 mx-auto flex items-center justify-center font-headline font-black text-sm border border-ocean-100">
                        S
                    </div>
                    <p class="text-slate-800 text-xs font-bold font-headline">Welcome to SunnyBot AI!</p>
                    <p class="text-slate-500 text-[11px] font-body leading-relaxed">Ask me about sanctuary hotels,
                        island activities, room rates, or custom itineraries in the Philippines.</p>
                </div>
            </template>

            {{-- Messages --}}
            <template x-for="msg in messages" :key="msg.id">
                <div class="space-y-2">
                    {{-- Bot --}}
                    <template x-if="msg.sender === 'bot'">
                        <div class="flex gap-2.5 items-start">
                            <div
                                class="w-7 h-7 rounded-xl bg-ocean-600 text-white flex items-center justify-center text-xs font-bold shrink-0 font-headline shadow-xs mt-0.5">
                                S</div>
                            <div
                                class="bg-white rounded-2xl rounded-tl-xs px-3.5 py-3 shadow-xs border border-slate-200/80 max-w-[85%]">
                                <div class="text-xs sm:text-sm text-slate-800 font-body leading-relaxed space-y-2"
                                    x-html="renderMarkdown(msg.text)"></div>

                                {{-- Room cards --}}
                                <template x-if="msg.rooms">
                                    <div class="mt-3 space-y-2">
                                        <template x-for="room in msg.rooms">
                                            <div
                                                class="bg-slate-50 rounded-xl p-2.5 border border-slate-200/80 space-y-1.5">
                                                <img x-show="room.image" :src="imgSrc(room.image)"
                                                    class="w-full h-24 object-cover rounded-lg mb-1 border border-slate-200"
                                                    alt="" onerror="this.style.display='none'">
                                                <p class="text-xs font-bold text-slate-900 font-headline"
                                                    x-text="room.room_name"></p>
                                                <p class="text-[11px] text-slate-500" x-text="room.hotel_name"></p>
                                                <div class="flex items-center justify-between pt-0.5">
                                                    <span class="text-xs font-black text-ocean-600 font-headline"
                                                        x-text="'₱' + new Intl.NumberFormat().format(room.base_price)"></span>
                                                    <span
                                                        class="text-[10px] font-bold text-slate-500 bg-white px-2 py-0.5 rounded-md border border-slate-200"
                                                        x-text="room.occupancy + ' pax max'"></span>
                                                </div>
                                                <template x-if="room.check_in_date && room.check_out_date">
                                                    <div class="text-[11px] text-slate-500 font-medium">
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
                                                        class="flex-1 text-[11px] bg-white border border-slate-200 text-slate-700 font-bold px-2 py-1.5 rounded-lg hover:bg-slate-100 transition-colors font-headline cursor-pointer">Preview</button>
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
                                                class="bg-slate-50 rounded-xl p-2.5 border border-slate-200/80 space-y-1.5">
                                                <img x-show="act.image" :src="imgSrc(act.image)"
                                                    class="w-full h-24 object-cover rounded-lg mb-1 border border-slate-200"
                                                    alt="" onerror="this.style.display='none'">
                                                <p class="text-xs font-bold text-slate-900 font-headline"
                                                    x-text="act.activity_name"></p>
                                                <p class="text-[11px] text-slate-500" x-text="act.destination"></p>
                                                <div class="flex items-center justify-between pt-0.5">
                                                    <span class="text-xs font-black text-emerald-600 font-headline"
                                                        x-text="act.rate || '₱' + new Intl.NumberFormat().format(act.base_price)"></span>
                                                    <span
                                                        class="text-[10px] font-bold text-slate-500 bg-white px-2 py-0.5 rounded-md border border-slate-200 capitalize"
                                                        x-text="act.category"></span>
                                                </div>
                                                <div class="flex gap-1.5 pt-1">
                                                    <button @click="$store.preview.openActivityById(act.id)"
                                                        class="flex-1 text-[11px] bg-white border border-slate-200 text-slate-700 font-bold px-2 py-1.5 rounded-lg hover:bg-slate-100 transition-colors font-headline cursor-pointer">Preview</button>
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
                                                class="bg-slate-50 rounded-xl p-2.5 border border-slate-200/80 space-y-1.5">
                                                <img x-show="hotel.image" :src="imgSrc(hotel.image)"
                                                    class="w-full h-24 object-cover rounded-lg mb-1 border border-slate-200"
                                                    alt="" onerror="this.style.display='none'">
                                                <p class="text-xs font-bold text-slate-900 font-headline"
                                                    x-text="hotel.hotel_name"></p>
                                                <p class="text-[11px] text-slate-500" x-text="hotel.destination"></p>
                                                <div class="flex items-center justify-between pt-0.5">
                                                    <span
                                                        class="text-[10px] font-bold text-slate-500 bg-white px-2 py-0.5 rounded-md border border-slate-200 capitalize"
                                                        x-text="hotel.type"></span>
                                                </div>
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
                                                class="bg-slate-50 rounded-xl p-2.5 border border-slate-200/80 space-y-1.5">
                                                <img x-show="pkg.image" :src="imgSrc(pkg.image)"
                                                    class="w-full h-24 object-cover rounded-lg mb-1 border border-slate-200"
                                                    alt="" onerror="this.style.display='none'">
                                                <p class="text-xs font-bold text-slate-900 font-headline"
                                                    x-text="pkg.name"></p>
                                                <p class="text-[11px] text-slate-500" x-text="pkg.destination"></p>
                                                <div class="flex items-center justify-between pt-0.5">
                                                    <span class="text-xs font-black text-amber-600 font-headline"
                                                        x-text="'₱' + new Intl.NumberFormat().format(pkg.price)"></span>
                                                    <span
                                                        class="text-[10px] font-bold text-slate-500 bg-white px-2 py-0.5 rounded-md border border-slate-200"
                                                        x-text="pkg.days + 'D/' + pkg.nights + 'N'"></span>
                                                </div>
                                                <button
                                                    @click="addToBasket('package', pkg.id, { selected_pax: pkg.min_pax || 2 })"
                                                    class="mt-1.5 w-full text-[11px] bg-amber-500 text-slate-950 font-bold px-2 py-1.5 rounded-lg hover:bg-amber-600 transition-colors font-headline cursor-pointer">+
                                                    Add to Trip Basket</button>
                                            </div>
                                        </template>
                                    </div>
                                </template>

                                {{-- Itinerary card --}}
                                <template x-if="msg.itinerary">
                                    <div
                                        class="mt-3 bg-slate-50 rounded-xl p-2.5 border border-slate-200/80 text-xs space-y-1.5">
                                        <p class="font-bold text-slate-900 font-headline"
                                            x-text="msg.itinerary.destination.name"></p>
                                        <p class="text-slate-600"><span x-text="msg.itinerary.room.room_name"></span>
                                            &mdash; <span x-text="msg.itinerary.room.formatted_total"></span></p>
                                        <template x-for="act in msg.itinerary.activities">
                                            <p class="text-slate-600"><span x-text="act.activity_name"></span> &mdash;
                                                <span x-text="act.formatted_cost"></span>
                                            </p>
                                        </template>
                                        <p class="font-black text-ocean-600 font-headline text-xs pt-1 border-t border-slate-200"
                                            x-text="'Total: ' + msg.itinerary.formatted_grand_total"></p>
                                        <div class="flex gap-1.5 pt-1">
                                            <button
                                                @click="addToBasket('room', msg.itinerary.room.id, { check_in_date: msg.itinerary.check_in_date || null, check_out_date: msg.itinerary.check_out_date || null, selected_pax: 1 })"
                                                class="flex-1 bg-ocean-600 text-white font-bold px-2 py-1.5 rounded-lg hover:bg-ocean-700 transition-colors text-[11px] font-headline cursor-pointer">+
                                                Room</button>
                                            <template x-for="act in msg.itinerary.activities">
                                                <button @click="addToBasket('activity', act.id, {})"
                                                    class="flex-1 bg-emerald-600 text-white font-bold px-2 py-1.5 rounded-lg hover:bg-emerald-700 transition-colors text-[11px] font-headline cursor-pointer">+
                                                    <span x-text="act.activity_name.substring(0,12)"></span></button>
                                            </template>
                                        </div>
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
                                class="bg-white rounded-2xl rounded-tl-xs px-3.5 py-3 shadow-xs border border-coral-200/80 max-w-[85%]">
                                <div class="text-xs sm:text-sm text-slate-800 font-body leading-relaxed space-y-2"
                                    x-html="renderMarkdown(msg.text)"></div>
                            </div>
                        </div>
                    </template>
                </div>
            </template>

            {{-- Thinking orb --}}
            <div x-show="sending" class="flex gap-2.5 items-start pl-1 py-1">
                <div
                    class="w-7 h-7 rounded-xl bg-ocean-600 text-white flex items-center justify-center text-xs font-bold shrink-0 font-headline shadow-xs">
                    S</div>
                <div class="bg-white rounded-2xl rounded-tl-xs px-4 py-3 shadow-xs border border-slate-200/80">
                    <x-thinking-orb state="composing" :size="18" />
                </div>
            </div>
        </div>

        {{-- Input area --}}
        <div class="border-t border-slate-100 px-3.5 py-3 bg-white shrink-0 space-y-2" x-show="!guestLimited">
            <form @submit.prevent="send()" class="flex gap-2 items-end">
                <textarea x-model="input" @keydown.enter.prevent="!$event.shiftKey && send()"
                    placeholder="Ask about islands, hotels, rates..." rows="1"
                    class="flex-1 resize-none rounded-2xl border border-slate-200 bg-slate-50 text-xs sm:text-sm px-3.5 py-2.5 focus:bg-white focus:border-ocean-500 focus:outline-none transition-all font-body text-slate-800 placeholder:text-slate-400"
                    :disabled="sending" x-ref="input" @input="autoResize($el)"></textarea>
                <button type="submit" :disabled="sending || !input.trim()"
                    class="shrink-0 w-9 h-9 sm:w-10 sm:h-10 rounded-2xl bg-ocean-600 text-white flex items-center justify-center hover:bg-ocean-700 transition-colors shadow-xs disabled:opacity-40 disabled:cursor-not-allowed cursor-pointer"
                    aria-label="Send message">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M12 19l9 2-9-18-9 18 9-2zm0 0v-8" />
                    </svg>
                </button>
            </form>
            <p class="text-center text-slate-400 text-[10px] font-medium leading-tight">SunnyBot AI can make mistakes.
                Be specific in inquiries for higher accuracy.</p>
        </div>

        {{-- Shared Preview Modals --}}
        <x-frontend.room-preview-modal />
        <x-frontend.activity-preview-modal />
    </div>
</div>

<script>
    function chatWidget() {
        return {
            open: false,
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

            init() {
                this.sessionToken = localStorage.getItem('sunnytrips_chat_session');
                this.loadConversation();
            },

            async loadConversation() {
                if (!this.sessionToken) return;
                try {
                    const res = await fetch(`/chat/history?session_token=${encodeURIComponent(this.sessionToken)}`, {
                        headers: { 'Accept': 'application/json' }
                    });
                    if (!res.ok) return;
                    const data = await res.json();
                    if (data.session_token) {
                        this.sessionToken = data.session_token;
                        localStorage.setItem('sunnytrips_chat_session', data.session_token);
                    }
                    if (!data.messages || !data.messages.length) return;

                    const handoffStatus = data.handoff_status;
                    if (handoffStatus === 'PENDING_ASSIGNMENT') {
                        this.handoffStatus = 'pending';
                    } else if (handoffStatus === 'HUMAN_SUPPORT_ACTIVE') {
                        this.handoffStatus = 'active';
                    } else if (handoffStatus === 'RETURNED_TO_AI' || handoffStatus === 'RESOLVED') {
                        this.handoffStatus = 'resolved';
                    }
                    if (this.handoffStatus === 'pending' || this.handoffStatus === 'active') {
                        this.startHandoffPolling();
                    }

                    this.messages = data.messages.map(msg => {
                        const extras = {};
                        const ctx = msg.context_data || {};
                        if (ctx.retrieved_rooms?.length) extras.rooms = ctx.retrieved_rooms;
                        if (ctx.retrieved_hotels?.length) extras.hotels = ctx.retrieved_hotels;
                        if (ctx.retrieved_activities?.length) extras.activities = ctx.retrieved_activities;
                        if (ctx.retrieved_packages?.length) extras.packages = ctx.retrieved_packages;
                        if (ctx.itinerary) extras.itinerary = ctx.itinerary;
                        return { id: msg.id, sender: msg.sender, text: msg.text, ...extras };
                    });
                    this.$nextTick(() => this.scrollDown());
                } catch (err) {
                    console.error('Failed to load chat history:', err);
                }
            },
            toggle() {
                this.open = !this.open;
                if (this.open) {
                    this.loadConversation();
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

                const formatInline = value => value
                    .replace(/\*\*(.+?)\*\*/g, '<strong class="font-semibold text-ink-800">$1</strong>')
                    .replace(/(^|[^*])\*([^*\n]+)\*(?!\*)/g, '$1<em>$2</em>');

                const flushParagraph = () => {
                    if (!paragraph.length) return;
                    blocks.push(`<p class="leading-relaxed">${paragraph.map(formatInline).join('<br>')}</p>`);
                    paragraph = [];
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
                        blocks.push(`<h4 class="font-headline text-sm font-semibold text-ink-900 leading-snug">${formatInline(heading[1])}</h4>`);
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

                return blocks.join('');
            },

            imgSrc(path) {
                if (!path) return '';
                return path.startsWith('http') ? path : '/storage/' + path;
            },

            scrollDown() {
                this.$nextTick(() => {
                    const el = this.$refs.messages;
                    if (el) el.scrollTop = el.scrollHeight;
                });
            },

            addMessage(sender, text, extra = {}) {
                this.messages.push({ id: Date.now(), sender, text, ...extra });
                this.scrollDown();
            },

            async send() {
                const msg = this.input.trim();
                if (!msg || this.sending || this.guestLimited) return;

                this.addMessage('user', msg);
                this.input = '';
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
                        }),
                    });

                    const data = await res.json();

                    if (res.status === 429 && data.status === 'guest_limit_reached') {
                        this.guestLimited = true;
                        this.guestLimitMessage = data.reply;
                        this.loginUrl = data.login_url || this.loginUrl;
                        this.registerUrl = data.register_url || this.registerUrl;
                        return;
                    }

                    if (res.status === 429) {
                        this.addMessage('bot', 'You are sending messages too quickly. Please wait a few seconds and try again.', {});
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
                    }
                    if (data.itinerary) {
                        extras.itinerary = data.itinerary;
                    }
                    if (data.retrieved_hotels) extras.hotels = data.retrieved_hotels;
                    if (data.retrieved_activities) extras.activities = data.retrieved_activities;
                    if (data.retrieved_packages) extras.packages = data.retrieved_packages;

                    if (data.control === 'admin' || data.status === 'human_support_active') {
                        this.handoffStatus = 'active';
                        this.startHandoffPolling();
                        return;
                    }
                    if (data.control === 'pending' || data.status === 'pending_assignment') {
                        this.handoffStatus = 'pending';
                        this.startHandoffPolling();
                    }

                    this.addMessage('bot', data.reply || 'I could not process that.', extras);
                } catch (err) {
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

            onControlButton() {
                if (this.handoffStatus === 'active') {
                    this.returnToBot();
                } else {
                    this.requestHandoff();
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
                if (!this.open) return;
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

                    const msgs = data.messages || [];
                    for (const msg of msgs) {
                        if (msg.sender === 'admin') {
                            this.addMessage('admin', msg.text);
                            this.handoffLastMessageId = msg.id;
                        }
                    }
                } catch (err) {
                    console.error('Handoff poll error:', err);
                }
            },
        };
    }
</script>