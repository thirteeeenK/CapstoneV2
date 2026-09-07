@extends('layouts.admin')

@section('title', 'Support Inbox | SunnyTrips Admin')

@section('content')
<div class="pb-12 font-body h-[calc(100vh-4rem)] flex"
    x-data="supportInbox()"
    x-init="startPolling()"
>
    {{-- Left: ticket list --}}
    <div class="w-80 shrink-0 border-r border-slate-200 bg-white flex flex-col overflow-hidden">
        <div class="px-4 py-3 border-b border-slate-100">
            <div class="flex items-center justify-between">
                <h2 class="font-headline text-base font-bold text-ink-800">Support Inbox</h2>
                <button @click="showNewMessage = true"
                    class="text-[11px] bg-ocean-600 text-white px-3 py-1.5 rounded-full hover:bg-ocean-700 transition-colors font-body font-semibold flex items-center gap-1 shadow-sm">
                    <span class="material-symbols-outlined text-[14px]">add</span> New Message
                </button>
            </div>
            <p class="text-xs text-slate-500 mt-0.5">
                <span x-text="pending.length"></span> pending &middot;
                <span x-text="myActive.length"></span> assigned
            </p>
        </div>

        {{-- New Message modal --}}
        <template x-if="showNewMessage">
            <div class="fixed inset-0 z-50 flex items-center justify-center p-4">
                <div class="absolute inset-0 bg-slate-900/40 backdrop-blur-sm" @click="showNewMessage = false"></div>
                <div class="relative bg-white rounded-2xl shadow-xl w-full max-w-md overflow-hidden">
                    <div class="px-5 py-4 border-b border-slate-100 flex items-center justify-between">
                        <h3 class="font-headline text-sm font-bold text-ink-800">New Message</h3>
                        <button @click="showNewMessage = false" class="w-7 h-7 rounded-full bg-slate-100 hover:bg-slate-200 flex items-center justify-center text-slate-500 transition-colors">
                            <span class="material-symbols-outlined text-[18px]">close</span>
                        </button>
                    </div>
                    <div class="px-5 py-4 space-y-4">
                        <div>
                            <label class="text-xs font-semibold text-slate-600 block mb-1.5">Search user (name or email)</label>
                            <input type="text" x-model="newMessageQuery" @input="debouncedNewSearch()" placeholder="e.g. Juan Dela Cruz or juan@example.com"
                                class="w-full rounded-xl border border-slate-200 bg-slate-50 text-sm px-3 py-2.5 focus:bg-white focus:border-ocean-400 focus:ring-2 focus:ring-ocean-100 focus:outline-none transition-all font-body">
                            <template x-if="searchingUsers">
                                <p class="text-[11px] text-slate-400 mt-1.5">Searching…</p>
                            </template>
                            <template x-if="newMessageResults.length > 0">
                                <div class="mt-2 border border-slate-200 rounded-xl overflow-hidden max-h-40 overflow-y-auto">
                                    <template x-for="u in newMessageResults" :key="u.id">
                                        <button @click="selectNewUser(u)"
                                            :class="selectedNewUser?.id === u.id ? 'bg-ocean-50 text-ocean-700' : 'bg-white hover:bg-slate-50 text-slate-700'"
                                            class="w-full text-left px-3 py-2.5 border-b border-slate-100 last:border-0 flex items-center justify-between transition-colors">
                                            <div>
                                                <p class="text-xs font-semibold" x-text="u.name"></p>
                                                <p class="text-[11px] text-slate-500" x-text="u.email"></p>
                                            </div>
                                            <span x-show="selectedNewUser?.id === u.id" class="material-symbols-outlined text-[16px] text-ocean-600">check_circle</span>
                                        </button>
                                    </template>
                                </div>
                            </template>
                            <template x-if="newMessageQuery.length >= 2 && newMessageResults.length === 0 && !searchingUsers">
                                <p class="text-[11px] text-slate-400 mt-2">No users found. Try at least 2 characters.</p>
                            </template>
                        </div>
                        <template x-if="selectedNewUser">
                            <div class="bg-ocean-50 border border-ocean-100 rounded-xl px-3 py-2 flex items-center gap-2">
                                <div class="w-7 h-7 rounded-full bg-ocean-600 text-white flex items-center justify-center text-xs font-bold" x-text="selectedNewUser.name.charAt(0).toUpperCase()"></div>
                                <div>
                                    <p class="text-xs font-semibold text-ocean-800" x-text="selectedNewUser.name"></p>
                                    <p class="text-[11px] text-ocean-600" x-text="selectedNewUser.email"></p>
                                </div>
                                <button @click="selectedNewUser = null; newMessageResults = []" class="ml-auto text-ocean-400 hover:text-ocean-700"><span class="material-symbols-outlined text-[16px]">close</span></button>
                            </div>
                        </template>
                        <div>
                            <label class="text-xs font-semibold text-slate-600 block mb-1.5">Message</label>
                            <textarea x-model="newMessageText" rows="3" placeholder="Hi, following up on your booking…"
                                class="w-full rounded-xl border border-slate-200 bg-slate-50 text-sm px-3 py-2.5 focus:bg-white focus:border-ocean-400 focus:ring-2 focus:ring-ocean-100 focus:outline-none transition-all font-body resize-none"></textarea>
                        </div>
                    </div>
                    <div class="px-5 py-3 bg-slate-50 border-t border-slate-100 flex justify-end gap-2">
                        <button @click="showNewMessage = false" class="text-xs bg-white border border-slate-200 text-slate-600 px-4 py-2 rounded-xl hover:bg-slate-100 transition-colors font-body font-semibold">Cancel</button>
                        <button @click="sendNewMessage()" :disabled="!selectedNewUser || !newMessageText.trim() || sendingNewMessage"
                            class="text-xs bg-ocean-600 text-white px-5 py-2 rounded-xl hover:bg-ocean-700 disabled:opacity-40 disabled:cursor-not-allowed transition-colors font-body font-semibold">Send Message</button>
                    </div>
                </div>
            </div>
        </template>

        <div class="flex-1 overflow-y-auto">
            {{-- Pending queue --}}
            <template x-if="pending.length > 0">
                <div>
                    <p class="px-4 py-2 text-[10px] font-bold text-slate-400 uppercase tracking-wider">Unassigned</p>
                    <template x-for="ticket in pending" :key="ticket.id">
                        <div class="px-4 py-2.5 border-b border-slate-50 hover:bg-slate-50 cursor-pointer transition-colors"
                            :class="{ 'bg-ocean-50 border-ocean-100': selectedInquiry?.id === ticket.id }"
                            @click="openInquiry(ticket)"
                        >
                            <div class="flex items-center justify-between">
                                <span class="text-xs font-semibold text-ink-700" x-text="'#' + ticket.ticket_number"></span>
                                <button @click.stop="claimTicket(ticket)"
                                    class="text-[10px] bg-ocean-600 text-white px-2 py-0.5 rounded-full hover:bg-ocean-700 transition-colors font-body font-semibold"
                                >Claim</button>
                            </div>
                            <p class="text-xs text-slate-500 mt-0.5 truncate" x-text="ticket.user_name"></p>
                            <p class="text-xs text-slate-400 mt-0.5 truncate" x-text="ticket.last_message.substring(0, 60)"></p>
                            <p class="text-[10px] text-slate-300 mt-0.5" x-text="ticket.requested_at"></p>
                        </div>
                    </template>
                </div>
            </template>

            {{-- My active --}}
            <template x-if="myActive.length > 0">
                <div>
                    <p class="px-4 py-2 text-[10px] font-bold text-slate-400 uppercase tracking-wider">My Active Chats</p>
                    <template x-for="ticket in myActive" :key="ticket.id">
                        <div class="px-4 py-2.5 border-b border-slate-50 hover:bg-slate-50 cursor-pointer transition-colors"
                            :class="{ 'bg-ocean-50 border-ocean-100': selectedInquiry?.id === ticket.id && selectedInquiry?.status === 'HUMAN_SUPPORT_ACTIVE' }"
                            @click="openInquiry(ticket)"
                        >
                            <p class="text-xs font-semibold text-ink-700" x-text="'#' + ticket.ticket_number"></p>
                            <p class="text-xs text-slate-500 mt-0.5 truncate" x-text="ticket.user_name"></p>
                            <div class="flex items-center gap-1 mt-0.5">
                                <span class="text-xs text-ocean-500 font-semibold">Active</span>
                                <span x-show="ticket.initiated_by === 'admin'" class="text-[10px] bg-ocean-100 text-ocean-700 px-1.5 py-0.5 rounded-full font-semibold">You started</span>
                            </div>
                            <p class="text-[11px] text-slate-400 truncate mt-0.5" x-text="ticket.last_message.substring(0, 55)"></p>
                        </div>
                    </template>
                </div>
            </template>

            {{-- Returned to SunnyBot --}}
            <template x-if="myReturned.length > 0">
                <div>
                    <p class="px-4 py-2 text-[10px] font-bold text-slate-400 uppercase tracking-wider">Returned to SunnyBot</p>
                    <template x-for="ticket in myReturned" :key="ticket.id">
                        <div class="px-4 py-2.5 border-b border-slate-50 hover:bg-slate-50 cursor-pointer transition-colors"
                            :class="{ 'bg-amber-50 border-amber-100': selectedInquiry?.id === ticket.id }"
                            @click="openInquiry(ticket)"
                        >
                            <div class="flex items-center justify-between">
                                <span class="text-xs font-semibold text-ink-700" x-text="'#' + ticket.ticket_number"></span>
                                <button @click.stop="resolveTicket(ticket)"
                                    class="text-[10px] bg-green-100 text-green-700 px-2 py-0.5 rounded-full hover:bg-green-200 transition-colors font-body font-semibold"
                                >Resolve</button>
                            </div>
                            <p class="text-xs text-slate-500 mt-0.5 truncate" x-text="ticket.user_name"></p>
                            <p class="text-[10px] text-amber-600 font-semibold mt-0.5">User returned to SunnyBot &middot; <span x-text="ticket.returned_to_ai_at"></span></p>
                        </div>
                    </template>
                </div>
            </template>

            <template x-if="pending.length === 0 && myActive.length === 0 && myReturned.length === 0">
                <div class="text-center py-10 text-slate-400 text-sm">
                    <span class="material-symbols-outlined text-4xl block mb-2">inbox</span>
                    <p>No support requests yet.</p>
                </div>
            </template>
        </div>
    </div>

    {{-- Right: conversation pane --}}
    <div class="flex-1 flex flex-col overflow-hidden bg-slate-50">
        <template x-if="!selectedInquiry">
            <div class="flex-1 flex items-center justify-center text-slate-400">
                <div class="text-center">
                    <span class="material-symbols-outlined text-5xl block mb-3">chat</span>
                    <p class="text-sm font-body">Select a support request to begin.</p>
                </div>
            </div>
        </template>

        <template x-if="selectedInquiry">
            <div class="flex-1 flex flex-col overflow-hidden">
                {{-- Header --}}
                <div class="px-4 py-2.5 border-b border-slate-200 bg-white flex items-center justify-between shrink-0">
                    <div>
                        <p class="text-sm font-semibold text-ink-800" x-text="'#' + selectedInquiry.ticket_number + ' — ' + selectedInquiry.user_name"></p>
                        <p class="text-xs text-slate-500">
                            <span x-text="selectedInquiry.status === 'HUMAN_SUPPORT_ACTIVE' ? 'Assigned to you' : (selectedInquiry.status === 'RETURNED_TO_AI' ? 'User returned to SunnyBot' : 'Unassigned')"></span>
                        </p>
                    </div>
                    <div class="flex gap-2" x-show="selectedInquiry.status === 'HUMAN_SUPPORT_ACTIVE' || selectedInquiry.status === 'RETURNED_TO_AI'">
                        <button @click="resumeAi()" x-show="selectedInquiry.status === 'HUMAN_SUPPORT_ACTIVE'"
                            class="text-xs bg-amber-100 text-amber-700 px-3 py-1 rounded-lg hover:bg-amber-200 transition-colors font-body"
                        >Resume AI</button>
                        <button @click="resolveTicket()"
                            class="text-xs bg-green-100 text-green-700 px-3 py-1 rounded-lg hover:bg-green-200 transition-colors font-body"
                        >Resolve</button>
                    </div>
                </div>

                {{-- Returned to SunnyBot notice --}}
                <template x-if="selectedInquiry.status === 'RETURNED_TO_AI'">
                    <div class="px-4 py-2 bg-amber-50 border-b border-amber-200 text-xs text-amber-700 font-body">
                        This user returned to SunnyBot. The conversation is back with the AI assistant.
                    </div>
                </template>

                {{-- Messages --}}
                <div class="flex-1 overflow-y-auto px-4 py-3 space-y-2" x-ref="adminMessages">
                    <template x-for="msg in conversationMessages" :key="msg.id">
                        <div>
                            <template x-if="msg.sender === 'user'">
                                <div class="flex justify-end">
                                    <div class="bg-ocean-600 text-white rounded-xl rounded-tr-sm px-3 py-1.5 max-w-[70%] shadow-sm">
                                        <p class="text-sm" x-text="msg.text"></p>
                                    </div>
                                </div>
                            </template>
                            <template x-if="msg.sender === 'admin'">
                                <div class="flex gap-2 items-start">
                                    <div class="w-6 h-6 rounded-full bg-coral-100 text-coral-600 flex items-center justify-center text-xs font-bold shrink-0">A</div>
                                    <div class="bg-white rounded-xl rounded-tl-sm px-3 py-1.5 shadow-sm border border-slate-100 max-w-[70%]">
                                        <p class="text-sm text-ink-700" x-text="msg.text"></p>
                                    </div>
                                </div>
                            </template>
                            <template x-if="msg.sender === 'bot'">
                                <div class="flex gap-2 items-start">
                                    <div class="w-6 h-6 rounded-full bg-ocean-100 text-ocean-600 flex items-center justify-center text-xs font-bold shrink-0">S</div>
                                    <div class="bg-white rounded-xl rounded-tl-sm px-3 py-1.5 shadow-sm border border-slate-100 max-w-[70%]">
                                        <p class="text-sm text-ink-600" x-text="msg.text"></p>
                                    </div>
                                </div>
                            </template>
                        </div>
                    </template>
                </div>

                {{-- Reply input (only when claimed) --}}
                <div class="border-t border-slate-200 px-4 py-3 bg-white shrink-0"
                    x-show="selectedInquiry.status === 'HUMAN_SUPPORT_ACTIVE'"
                >
                    <form @submit.prevent="sendAdminReply()" class="flex gap-2 items-end">
                        <textarea x-model="adminInput"
                            @keydown.enter.prevent="!$event.shiftKey && sendAdminReply()"
                            placeholder="Type your reply..."
                            rows="1"
                            class="flex-1 resize-none rounded-xl border-slate-300 bg-slate-50 text-sm px-3 py-2 focus:ring-ocean-400 focus:border-ocean-400 font-body"
                            :disabled="adminSending"
                            @input="autoResize($el)"
                        ></textarea>
                        <button type="submit"
                            :disabled="adminSending || !adminInput.trim()"
                            class="shrink-0 bg-ocean-600 text-white text-sm px-4 py-2 rounded-xl hover:bg-ocean-700 transition-colors disabled:opacity-40 disabled:cursor-not-allowed font-body font-semibold"
                        >Send</button>
                    </form>
                </div>
            </div>
        </template>
    </div>
</div>
@endsection

@push('scripts')
<script>
function supportInbox() {
    return {
        pending: [],
        myActive: [],
        myReturned: [],
        selectedInquiry: null,
        conversationMessages: [],
        adminInput: '',
        adminSending: false,
        pollTimer: null,
        activeConvoPollTimer: null,
        lastMessageId: 0,
        showNewMessage: false,
        newMessageQuery: '',
        newMessageResults: [],
        selectedNewUser: null,
        newMessageText: '',
        searchingUsers: false,
        sendingNewMessage: false,
        newMessageQueryTimer: null,

        autoResize(el) {
            el.style.height = 'auto';
            el.style.height = Math.min(el.scrollHeight, 100) + 'px';
        },

        startPolling() {
            this.fetchQueue();
            this.pollTimer = setInterval(() => this.fetchQueue(), 5000);
        },

        async fetchQueue() {
            try {
                const res = await fetch('/admin/support/poll', {
                    headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }
                });
                if (!res.ok) return;
                const data = await res.json();
                this.pending = data.pending || [];
                this.myActive = data.my_active || [];
                this.myReturned = data.my_returned || [];
            } catch (e) {
                console.error('Support poll error:', e);
            }
        },

        openInquiry(ticket) {
            this.selectedInquiry = ticket;
            this.lastMessageId = 0;
            this.fetchMessages();
            if (this.activeConvoPollTimer) clearInterval(this.activeConvoPollTimer);
            this.activeConvoPollTimer = setInterval(() => this.fetchMessages(), 5000);
        },

        async fetchMessages() {
            if (!this.selectedInquiry) return;
            try {
                const params = new URLSearchParams();
                if (this.lastMessageId > 0) params.set('after_id', this.lastMessageId);
                const url = `/admin/support/inquiries/${this.selectedInquiry.id}/messages?${params}`;
                const res = await fetch(url, {
                    headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }
                });
                if (!res.ok) return;
                const data = await res.json();
                this.selectedInquiry.status = data.inquiry?.status || this.selectedInquiry.status;
                this.selectedInquiry.assigned_admin_id = data.inquiry?.assigned_admin_id;
                const msgs = data.messages || [];
                if (this.lastMessageId === 0) {
                    this.conversationMessages = msgs;
                } else if (msgs.length > 0) {
                    this.conversationMessages = [...this.conversationMessages, ...msgs];
                }
                if (msgs.length > 0) {
                    this.lastMessageId = msgs[msgs.length - 1].id;
                }
                this.$nextTick(() => {
                    const el = this.$refs.adminMessages;
                    if (el) el.scrollTop = el.scrollHeight;
                });
            } catch (e) {
                console.error('Fetch messages error:', e);
            }
        },

        async claimTicket(ticket) {
            try {
                const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content || '';
                const res = await fetch(`/admin/support/inquiries/${ticket.id}/claim`, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': csrfToken,
                        'X-Requested-With': 'XMLHttpRequest',
                        'Accept': 'application/json',
                    },
                });
                const data = await res.json();
                if (data.status === 'success') {
                    await this.fetchQueue();
                    this.selectedInquiry = { ...ticket, status: 'HUMAN_SUPPORT_ACTIVE' };
                    this.lastMessageId = 0;
                    this.fetchMessages();
                    if (this.activeConvoPollTimer) clearInterval(this.activeConvoPollTimer);
                    this.activeConvoPollTimer = setInterval(() => this.fetchMessages(), 5000);
                } else {
                    alert(data.message || 'Could not claim this inquiry.');
                }
            } catch (e) {
                console.error('Claim error:', e);
            }
        },

        debouncedNewSearch() {
            if (this.newMessageQueryTimer) clearTimeout(this.newMessageQueryTimer);
            this.newMessageQueryTimer = setTimeout(() => this.searchNewUsers(), 300);
        },

        async searchNewUsers() {
            const q = this.newMessageQuery.trim();
            if (q.length < 2) { this.newMessageResults = []; return; }
            this.searchingUsers = true;
            try {
                const res = await fetch(`/admin/support/users/search?q=${encodeURIComponent(q)}`, {
                    headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }
                });
                if (!res.ok) { this.newMessageResults = []; return; }
                const data = await res.json();
                this.newMessageResults = data.users || [];
            } catch (e) {
                console.error('Search users error:', e);
            } finally {
                this.searchingUsers = false;
            }
        },

        selectNewUser(u) {
            this.selectedNewUser = u;
            this.newMessageResults = [];
        },

        async sendNewMessage() {
            if (!this.selectedNewUser || !this.newMessageText.trim() || this.sendingNewMessage) return;
            this.sendingNewMessage = true;
            try {
                const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content || '';
                const res = await fetch('/admin/support/initiate', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': csrfToken,
                        'X-Requested-With': 'XMLHttpRequest',
                        'Accept': 'application/json',
                    },
                    body: JSON.stringify({ user_id: this.selectedNewUser.id, message: this.newMessageText.trim() }),
                });
                const data = await res.json();
                if (data.status === 'success' && data.inquiry) {
                    this.showNewMessage = false;
                    this.newMessageQuery = '';
                    this.newMessageResults = [];
                    this.selectedNewUser = null;
                    this.newMessageText = '';
                    await this.fetchQueue();
                    this.selectedInquiry = data.inquiry;
                    this.lastMessageId = 0;
                    this.fetchMessages();
                    if (this.activeConvoPollTimer) clearInterval(this.activeConvoPollTimer);
                    this.activeConvoPollTimer = setInterval(() => this.fetchMessages(), 5000);
                } else {
                    alert(data.message || 'Could not send message.');
                }
            } catch (e) {
                console.error('Initiate error:', e);
                alert('Could not send message. Please try again.');
            } finally {
                this.sendingNewMessage = false;
            }
        },

        async sendAdminReply() {
            if (!this.adminInput.trim() || !this.selectedInquiry || this.adminSending) return;
            const message = this.adminInput.trim();
            this.adminInput = '';
            this.adminSending = true;

            try {
                const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content || '';
                const res = await fetch(`/admin/support/inquiries/${this.selectedInquiry.id}/reply`, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': csrfToken,
                        'X-Requested-With': 'XMLHttpRequest',
                        'Accept': 'application/json',
                    },
                    body: JSON.stringify({ message }),
                });
                const data = await res.json();
                if (data.status === 'success' && data.message) {
                    this.conversationMessages = [...this.conversationMessages, data.message];
                    this.lastMessageId = data.message.id;
                    this.$nextTick(() => {
                        const el = this.$refs.adminMessages;
                        if (el) el.scrollTop = el.scrollHeight;
                    });
                }
            } catch (e) {
                console.error('Reply error:', e);
            } finally {
                this.adminSending = false;
            }
        },

        async resumeAi() {
            if (!this.selectedInquiry) return;
            if (!confirm('Return this conversation to SunnyBot (AI assistant)? The user will be notified.')) return;

            try {
                const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content || '';
                const res = await fetch(`/admin/support/inquiries/${this.selectedInquiry.id}/resume-ai`, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrfToken, 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' },
                });
                const data = await res.json();
                if (data.status === 'success') {
                    this.selectedInquiry = null;
                    this.conversationMessages = [];
                    await this.fetchQueue();
                }
            } catch (e) {
                console.error('Resume AI error:', e);
            }
        },

        async resolveTicket(ticket = null) {
            const target = ticket || this.selectedInquiry;
            if (!target) return;
            if (!confirm('Mark this support inquiry as resolved?')) return;

            try {
                const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content || '';
                const res = await fetch(`/admin/support/inquiries/${target.id}/resolve`, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrfToken, 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' },
                });
                const data = await res.json();
                if (data.status === 'success') {
                    if (this.selectedInquiry?.id === target.id) {
                        this.selectedInquiry = null;
                        this.conversationMessages = [];
                    }
                    await this.fetchQueue();
                }
            } catch (e) {
                console.error('Resolve error:', e);
            }
        },
    };
}
</script>
@endpush
