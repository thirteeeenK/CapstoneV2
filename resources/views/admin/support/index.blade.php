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
            <h2 class="font-headline text-base font-bold text-ink-800">Support Inbox</h2>
            <p class="text-xs text-slate-500 mt-0.5">
                <span x-text="pending.length"></span> pending &middot;
                <span x-text="myActive.length"></span> assigned
            </p>
        </div>

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
                            <p class="text-xs text-ocean-500 font-semibold mt-0.5">Active</p>
                        </div>
                    </template>
                </div>
            </template>

            <template x-if="pending.length === 0 && myActive.length === 0">
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
                        <p class="text-xs text-slate-500" x-text="selectedInquiry.status === 'HUMAN_SUPPORT_ACTIVE' ? 'Assigned to you' : 'Unassigned'"></p>
                    </div>
                    <div class="flex gap-2" x-show="selectedInquiry.status === 'HUMAN_SUPPORT_ACTIVE'">
                        <button @click="resumeAi()"
                            class="text-xs bg-amber-100 text-amber-700 px-3 py-1 rounded-lg hover:bg-amber-200 transition-colors font-body"
                        >Resume AI</button>
                        <button @click="resolveTicket()"
                            class="text-xs bg-green-100 text-green-700 px-3 py-1 rounded-lg hover:bg-green-200 transition-colors font-body"
                        >Resolve</button>
                    </div>
                </div>

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
        selectedInquiry: null,
        conversationMessages: [],
        adminInput: '',
        adminSending: false,
        pollTimer: null,
        activeConvoPollTimer: null,
        lastMessageId: 0,

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

        async resolveTicket() {
            if (!this.selectedInquiry) return;
            if (!confirm('Mark this support inquiry as resolved?')) return;

            try {
                const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content || '';
                const res = await fetch(`/admin/support/inquiries/${this.selectedInquiry.id}/resolve`, {
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
                console.error('Resolve error:', e);
            }
        },
    };
}
</script>
@endpush
