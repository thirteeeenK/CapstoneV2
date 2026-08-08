<div x-data="chatWidget()" x-init="init()"
    class="fixed bottom-6 right-6 z-50 flex flex-col items-end gap-3"
    x-trap="open"
>
    {{-- Floating bubble button --}}
    <button @click="toggle()"
        class="w-14 h-14 rounded-full bg-ocean-600 hover:bg-ocean-700 text-white shadow-lg flex items-center justify-center transition-all duration-200 focus:outline-none focus:ring-2 focus:ring-ocean-400 focus:ring-offset-2"
        :class="open ? 'rotate-90 scale-90' : ''"
        aria-label="Chat with SunnyTrips AI"
    >
        <svg x-show="!open" class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 10h.01M12 10h.01M16 10h.01M21 12c0 4.418-4.03 8-9 8a9.86 9.86 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z" />
        </svg>
        <svg x-show="open" class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
        </svg>
    </button>

    {{-- Chat panel --}}
    <div x-show="open" x-transition:enter="transition ease-out duration-200"
        x-transition:enter-start="opacity-0 translate-y-4 scale-95"
        x-transition:enter-end="opacity-100 translate-y-0 scale-100"
         class="w-[calc(100vw-3rem)] max-w-sm sm:w-96 h-[500px] bg-white rounded-2xl shadow-2xl border border-sand-200 flex flex-col overflow-hidden"
        @click.outside="close()"
    >
        {{-- Header --}}
        <div class="bg-ocean-600 text-white px-4 py-3 flex items-center justify-between shrink-0">
            <div class="flex items-center gap-2">
                <div class="w-8 h-8 rounded-full bg-white/20 flex items-center justify-center text-sm font-bold">
                    S
                </div>
                <div>
                    <p class="font-headline text-sm font-semibold">SunnyTrips AI</p>
                    <p class="text-xs text-ocean-200">SunnyBot — your travel assistant</p>
                </div>
            </div>
            <div class="flex items-center gap-1">
                <button @click="requestHandoff()" x-show="!handoffStatus"
                    class="text-ocean-200 hover:text-white transition-colors text-[10px] px-2 py-1 border border-ocean-400 rounded-lg font-body"
                    title="Talk to a human agent"
                >
                    <svg class="w-4 h-4 inline-block mr-0.5 -mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18.364 5.636a9 9 0 010 12.728m-4.95-7.778a4.5 4.5 0 010 6.364M8.485 3.515a12 12 0 0116.97 16.97M6 14l-3 3 1 4 4-1 3-3m-5 0a1 1 0 100 2 1 1 0 000-2z"/>
                    </svg>
                    Talk to agent
                </button>
                <button @click="close()" class="text-ocean-200 hover:text-white transition-colors" aria-label="Close chat">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </button>
            </div>
        </div>

        {{-- Messages area --}}
        <div x-ref="messages" class="flex-1 overflow-y-auto px-3 py-3 space-y-3 bg-sand-50">
            {{-- Handoff pending banner --}}
            <template x-if="handoffStatus === 'pending'">
                <div class="bg-amber-50 border border-amber-200 rounded-lg p-3 mx-3 mt-3">
                    <p class="text-sm text-amber-700 font-body">
                        <span class="font-semibold">Waiting for an agent...</span> An administrator will be with you shortly. Ticket: <span class="font-mono text-xs" x-text="handoffTicket"></span>
                    </p>
                    <button @click="cancelHandoff()" class="mt-1 text-xs text-amber-600 underline hover:text-amber-800 font-body">Cancel request</button>
                </div>
            </template>

            {{-- Handoff active banner --}}
            <template x-if="handoffStatus === 'active'">
                <div class="bg-green-50 border border-green-200 rounded-lg p-3 mx-3 mt-3">
                    <p class="text-sm text-green-700 font-body"><span class="font-semibold">You're chatting with an agent.</span> They'll respond shortly.</p>
                </div>
            </template>

            {{-- Handoff resolved banner --}}
            <template x-if="handoffStatus === 'resolved'">
                <div class="bg-ocean-50 border border-ocean-200 rounded-lg p-3 mx-3 mt-3">
                    <p class="text-sm text-ocean-700 font-body">The support chat has ended. SunnyBot is back! You can ask me anything about your trip.</p>
                </div>
            </template>
                <div class="text-center py-6 px-4">
                    <div class="w-12 h-12 rounded-full bg-coral-100 text-coral-500 mx-auto flex items-center justify-center mb-3">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01M21 12c0 4.418-4.03 8-9 8a9.86 9.86 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z" />
                        </svg>
                    </div>
                    <p class="text-ink-700 font-body text-sm font-semibold mb-1" x-text="guestLimitMessage"></p>
                    <div class="flex flex-col gap-2 mt-3">
                        <a :href="loginUrl" class="inline-block bg-ocean-600 text-white text-sm px-4 py-2 rounded-lg hover:bg-ocean-700 transition-colors font-body font-semibold">
                            Log in
                        </a>
                        <a :href="registerUrl" class="inline-block text-ocean-600 text-sm hover:underline font-body">
                            Sign up for free
                        </a>
                    </div>
                </div>
            </template>

            {{-- Welcome --}}
            <template x-if="messages.length === 0 && !guestLimited">
                <div class="text-center py-4">
                    <p class="text-ink-500 text-sm font-body">Hi! I'm SunnyBot, your SunnyTrips AI assistant. Ask me about destinations, hotels, rooms, activities, or trip itineraries in the Philippines!</p>
                    <p class="text-ink-400 text-xs mt-2 font-body">Tip: I'm an AI and can make mistakes. The more specific your question (destination, dates, guests), the more accurate my answers!</p>
                </div>
            </template>

            {{-- Messages --}}
            <template x-for="msg in messages" :key="msg.id">
                <div>
                    {{-- Bot --}}
                    <template x-if="msg.sender === 'bot'">
                        <div class="flex gap-2">
<div class="w-7 h-7 rounded-full bg-ocean-100 text-ocean-600 flex items-center justify-center text-xs font-bold shrink-0">S</div>
                            <div class="bg-white rounded-xl rounded-tl-sm px-3 py-2 shadow-sm border border-sand-100 max-w-[85%]">
                                <div class="text-sm text-ink-700 font-body leading-relaxed space-y-2" x-html="renderMarkdown(msg.text)"></div>

                                {{-- Room cards --}}
                                <template x-if="msg.rooms">
                                    <div class="mt-2 space-y-2">
                                        <template x-for="room in msg.rooms">
                                            <div class="bg-sand-50 rounded-lg p-2 border border-sand-200">
                                                <img x-show="room.image" :src="'/storage/' + room.image" class="w-full h-20 object-cover rounded mb-1" alt="">
                                                <p class="text-xs font-semibold text-ink-800" x-text="room.room_name"></p>
                                                <p class="text-xs text-ink-500" x-text="room.hotel_name"></p>
                                                <div class="flex items-center justify-between mt-1">
                                                    <span class="text-xs font-bold text-ocean-600" x-text="'₱' + new Intl.NumberFormat().format(room.base_price)"></span>
                                                    <span class="text-xs text-ink-400" x-text="room.occupancy + ' pax max'"></span>
                                                </div>
                                                <template x-if="room.check_in_date && room.check_out_date">
                                                    <div class="text-xs text-ink-400 mt-1">
                                                        <span x-text="room.check_in_date + ' → ' + room.check_out_date"></span>
                                                    </div>
                                                </template>
                                                <template x-if="!room.check_in_date || !room.check_out_date">
                                                    <div class="mt-1" @date-range-changed.stop="room._checkIn = $event.detail.checkIn; room._checkOut = $event.detail.checkOut; room._available = $event.detail.available">
                                                        <x-frontend.date-range-picker :dynamic-room-id="'room.id'" :dynamic-base-price="'room.base_price'" />
                                                    </div>
                                                </template>
                                                <div class="flex gap-1 mt-1">
                                                    <button @click="$store.preview.openRoomById(room.id)"
                                                        class="flex-1 text-xs bg-ink-100 text-ink-700 px-2 py-1 rounded hover:bg-ink-200 transition-colors font-body"
                                                    >Preview</button>
                                                    <button
                                                        @click="addToBasket('room', room.id, room.check_in_date ? { check_in_date: room.check_in_date, check_out_date: room.check_out_date, selected_pax: room.pax || 1 } : { check_in_date: room._checkIn, check_out_date: room._checkOut, selected_pax: 1 })"
                                                        :disabled="(!room.check_in_date || !room.check_out_date) && (!room._checkIn || !room._checkOut)"
                                                        class="flex-1 text-xs bg-ocean-600 text-white px-2 py-1 rounded hover:bg-ocean-700 transition-colors font-body disabled:opacity-40 disabled:cursor-not-allowed"
                                                    >+ Add to Trip Basket</button>
                                                </div>
                                            </div>
                                        </template>
                                    </div>
                                </template>

                                {{-- Activity cards --}}
                                <template x-if="msg.activities">
                                    <div class="mt-2 space-y-2">
                                        <template x-for="act in msg.activities">
                                            <div class="bg-sand-50 rounded-lg p-2 border border-sand-200">
                                                <img x-show="act.image" :src="'/storage/' + act.image" class="w-full h-20 object-cover rounded mb-1" alt="">
                                                <p class="text-xs font-semibold text-ink-800" x-text="act.activity_name"></p>
                                                <p class="text-xs text-ink-500" x-text="act.destination"></p>
                                                <div class="flex items-center justify-between mt-1">
                                                    <span class="text-xs font-bold text-ocean-600" x-text="act.rate || '₱' + new Intl.NumberFormat().format(act.base_price)"></span>
                                                    <span class="text-xs text-ink-400" x-text="act.category"></span>
                                                </div>
                                                <div class="flex gap-1 mt-1">
                                                    <button @click="$store.preview.openActivityById(act.id)"
                                                        class="flex-1 text-xs bg-ink-100 text-ink-700 px-2 py-1 rounded hover:bg-ink-200 transition-colors font-body"
                                                    >Preview</button>
                                                    <button @click="addToBasket('activity', act.id, { selected_pax: 1 })"
                                                        class="flex-1 text-xs bg-coral-500 text-white px-2 py-1 rounded hover:bg-coral-600 transition-colors font-body"
                                                    >+ Add to Basket</button>
                                                </div>
                                            </div>
                                        </template>
                                    </div>
                                </template>

                                {{-- Hotel cards --}}
                                <template x-if="msg.hotels">
                                    <div class="mt-2 space-y-2">
                                        <template x-for="hotel in msg.hotels">
                                            <div class="bg-sand-50 rounded-lg p-2 border border-sand-200">
                                                <img x-show="hotel.image" :src="'/storage/' + hotel.image" class="w-full h-20 object-cover rounded mb-1" alt="">
                                                <p class="text-xs font-semibold text-ink-800" x-text="hotel.hotel_name"></p>
                                                <p class="text-xs text-ink-500" x-text="hotel.destination"></p>
                                                <div class="flex items-center justify-between mt-1">
                                                    <span class="text-xs text-ink-400" x-text="hotel.type"></span>
                                                </div>
                                                <a :href="'/hotels/' + hotel.id"
                                                    class="mt-1 block text-center text-xs bg-ocean-600 text-white px-2 py-1 rounded hover:bg-ocean-700 transition-colors font-body"
                                                >View Hotel</a>
                                            </div>
                                        </template>
                                    </div>
                                </template>

                                {{-- Package cards --}}
                                <template x-if="msg.packages">
                                    <div class="mt-2 space-y-2">
                                        <template x-for="pkg in msg.packages">
                                            <div class="bg-sand-50 rounded-lg p-2 border border-sand-200">
                                                <img x-show="pkg.image" :src="'/storage/' + pkg.image" class="w-full h-20 object-cover rounded mb-1" alt="">
                                                <p class="text-xs font-semibold text-ink-800" x-text="pkg.name"></p>
                                                <p class="text-xs text-ink-500" x-text="pkg.destination"></p>
                                                <div class="flex items-center justify-between mt-1">
                                                    <span class="text-xs font-bold text-ocean-600" x-text="'₱' + new Intl.NumberFormat().format(pkg.price)"></span>
                                                    <span class="text-xs text-ink-400" x-text="pkg.days + 'D/' + pkg.nights + 'N'"></span>
                                                </div>
                                                <button @click="addToBasket('package', pkg.id, { selected_pax: pkg.min_pax || 2 })"
                                                    class="mt-1 w-full text-xs bg-ocean-600 text-white px-2 py-1 rounded hover:bg-ocean-700 transition-colors font-body"
                                                >+ Add to Trip Basket</button>
                                            </div>
                                        </template>
                                    </div>
                                </template>

                                {{-- Itinerary card --}}
                                <template x-if="msg.itinerary">
                                    <div class="mt-2 bg-sand-50 rounded-lg p-2 border border-sand-200 text-xs space-y-1">
                                        <p class="font-semibold text-ink-800" x-text="msg.itinerary.destination.name"></p>
                                        <p class="text-ink-600"><span x-text="msg.itinerary.room.room_name"></span> &mdash; <span x-text="msg.itinerary.room.formatted_total"></span></p>
                                        <template x-for="act in msg.itinerary.activities">
                                            <p class="text-ink-600"><span x-text="act.activity_name"></span> &mdash; <span x-text="act.formatted_cost"></span></p>
                                        </template>
                                        <p class="font-bold text-ocean-600" x-text="'Total: ' + msg.itinerary.formatted_grand_total"></p>
                                        <div class="flex gap-1 mt-1">
                                            <button @click="addToBasket('room', msg.itinerary.room.id, { check_in_date: msg.itinerary.check_in_date || null, check_out_date: msg.itinerary.check_out_date || null, selected_pax: 1 })"
                                                class="flex-1 bg-ocean-600 text-white px-2 py-1 rounded hover:bg-ocean-700 transition-colors">+ Room</button>
                                            <template x-for="act in msg.itinerary.activities">
                                                <button @click="addToBasket('activity', act.id, {})"
                                                    class="flex-1 bg-coral-500 text-white px-2 py-1 rounded hover:bg-coral-600 transition-colors">+ <span x-text="act.activity_name.substring(0,12)"></span></button>
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
                            <div class="bg-ocean-600 text-white rounded-xl rounded-tr-sm px-3 py-2 max-w-[80%] shadow-sm">
                                <p class="text-sm font-body" x-text="msg.text"></p>
                            </div>
                        </div>
                    </template>
                    {{-- Admin (support agent) --}}
                    <template x-if="msg.sender === 'admin'">
                        <div class="flex gap-2">
                            <div class="w-7 h-7 rounded-full bg-coral-100 text-coral-600 flex items-center justify-center text-xs font-bold shrink-0">A</div>
                            <div class="bg-white rounded-xl rounded-tl-sm px-3 py-2 shadow-sm border border-coral-100 max-w-[85%]">
                                <div class="text-sm text-ink-700 font-body leading-relaxed space-y-2" x-html="renderMarkdown(msg.text)"></div>
                            </div>
                        </div>
                    </template>
                </div>
            </template>

            {{-- Thinking orb --}}
            <div x-show="sending" class="flex gap-2 pl-2 py-1">
                <div class="w-7 h-7 rounded-full bg-ocean-100 text-ocean-600 flex items-center justify-center text-xs font-bold shrink-0">S</div>
                <div class="bg-white rounded-xl rounded-tl-sm px-4 py-3 shadow-sm border border-sand-100">
                    <x-thinking-orb state="composing" :size="20" />
                </div>
            </div>
        </div>

        {{-- Input area --}}
        <div class="border-t border-sand-200 px-3 py-2 bg-white shrink-0"
            x-show="!guestLimited"
        >
            <p class="text-center text-ink-400 text-xs font-body mb-1">SunnyBot is an AI and can make mistakes. For the most accurate answers, include your destination, travel dates, and number of guests.</p>
            <form @submit.prevent="send()" class="flex gap-2 items-end">
                <textarea x-model="input" @keydown.enter.prevent="!$event.shiftKey && send()"
                    placeholder="Ask me anything about your trip..."
                    rows="1"
                    class="flex-1 resize-none rounded-xl border-sand-300 bg-sand-50 text-sm px-3 py-2 focus:ring-ocean-400 focus:border-ocean-400 font-body placeholder:text-sand-400"
                    :disabled="sending"
                    x-ref="input"
                    @input="autoResize($el)"
                ></textarea>
                <button type="submit"
                    :disabled="sending || !input.trim()"
                    class="shrink-0 w-9 h-9 rounded-full bg-ocean-600 text-white flex items-center justify-center hover:bg-ocean-700 transition-colors disabled:opacity-40 disabled:cursor-not-allowed"
                    aria-label="Send message"
                >
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 19l9 2-9-18-9 18 9-2zm0 0v-8" />
                    </svg>
                </button>
            </form>
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

        toggle() { this.open = !this.open; if (this.open) this.$nextTick(() => this.$refs.input?.focus()); },
        close() { this.open = false; },

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

        async requestHandoff() {
            if (!this.sessionToken || this.handoffStatus) return;
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
            this.handoffLastMessageId = 0;
            this.handoffPollTimer = setInterval(() => this.pollHandoffMessages(), 5000);
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
            try {
                const params = new URLSearchParams({ session_token: this.sessionToken });
                if (this.handoffLastMessageId > 0) params.set('after_id', this.handoffLastMessageId);
                const res = await fetch(`/chat/poll?${params}`, {
                    headers: { 'Accept': 'application/json' }
                });
                if (!res.ok) return;
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
