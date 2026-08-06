@props([
    'triggerLabel' => 'Leave a Review',
    'bookingId' => null,
])

@php
    $csrfToken = csrf_token();
@endphp

<div x-data="reviewModal({ bookingId: @js($bookingId), csrfToken: @js($csrfToken) })">
    <button type="button" @click="open()"
        class="px-5 py-3 rounded-2xl bg-teal-600 hover:bg-teal-700 text-white font-bold text-xs transition flex items-center gap-1.5 shadow-lg shadow-teal-600/20 cursor-pointer">
        <span class="material-symbols-outlined text-[16px]">rate_review</span>
        <span>{{ $triggerLabel }}</span>
    </button>

    {{-- Modal shell --}}
    <div x-show="show" x-cloak @keydown.escape.window="close()" x-transition.opacity
        class="fixed inset-0 z-[70] flex items-start sm:items-center justify-center px-4 py-8 overflow-y-auto">
        <div class="fixed inset-0 bg-slate-950/60 backdrop-blur-sm" @click="close()"></div>

        <div class="relative w-full max-w-lg bg-white rounded-[1.75rem] shadow-2xl border border-sand-200 overflow-hidden">
            {{-- Header --}}
            <div class="px-6 sm:px-8 pt-6 pb-5 border-b border-sand-100 flex items-start justify-between gap-4">
                <div>
                    <p class="font-label text-[10px] uppercase font-bold tracking-[0.25em] text-ocean-600">
                        SunnyTrips · Verified Guest Feedback
                    </p>
                    <h3 class="font-headline text-lg font-bold text-slate-900 mt-1">Share your experience</h3>
                </div>
                <button type="button" @click="close()"
                    class="w-9 h-9 rounded-xl border border-sand-200 text-slate-400 hover:text-slate-700 hover:bg-sand-50 flex items-center justify-center transition cursor-pointer">
                    <span class="material-symbols-outlined text-[18px]">close</span>
                </button>
            </div>

            <div class="px-6 sm:px-8 py-6">

                {{-- Loading --}}
                <div x-show="loading" class="py-10 text-center">
                    <div class="w-10 h-10 mx-auto rounded-full border-4 border-sand-200 border-t-ocean-500 animate-spin mb-4"></div>
                    <p class="text-xs text-slate-500 font-medium">Checking your completed trips...</p>
                </div>

                {{-- Error / no eligible bookings --}}
                <div x-show="!loading && !success && !selectedBooking && bookings.length === 0" class="py-8 text-center">
                    <div class="w-14 h-14 rounded-2xl bg-sand-100 border border-sand-200 flex items-center justify-center mx-auto mb-4">
                        <span class="material-symbols-outlined text-2xl text-slate-400">verified</span>
                    </div>
                    <h4 class="font-headline text-sm font-bold text-slate-900">No reviewable trips yet</h4>
                    <p class="text-xs text-slate-500 mt-2 max-w-xs mx-auto leading-relaxed">
                        Reviews unlock after a trip is marked completed. Check back once your journey finishes.
                    </p>
                </div>

                {{-- Step 1: choose booking --}}
                <div x-show="!loading && !success && bookings.length > 0 && !selectedBooking">
                    <p class="text-xs text-slate-500 mb-3">Choose the completed booking you'd like to review:</p>
                    <div class="space-y-2.5">
                        <template x-for="b in bookings" :key="b.booking_id">
                            <button type="button" @click="selectBooking(b)"
                                class="w-full text-left p-4 rounded-2xl border border-sand-200 bg-white hover:border-ocean-300 hover:bg-ocean-50/40 transition group cursor-pointer">
                                <div class="flex items-center justify-between gap-3">
                                    <div class="min-w-0">
                                        <p class="font-headline text-sm font-bold text-slate-900"
                                            x-text="'Journal ' + b.booking_code"></p>
                                        <p class="text-[11px] text-slate-500 mt-0.5 truncate"
                                            x-text="b.reviewable_items.map(i => i.item_title).join(' · ')"></p>
                                    </div>
                                    <span class="material-symbols-outlined text-slate-300 group-hover:text-ocean-500 transition shrink-0">chevron_right</span>
                                </div>
                            </button>
                        </template>
                    </div>
                </div>

                {{-- Step 2: pick item (multi-item bookings) --}}
                <div x-show="!loading && !success && selectedBooking && selectedBooking.reviewable_items.length > 1 && !selectedItem">
                    <button type="button" @click="selectedBooking = null"
                        class="text-[11px] font-bold text-slate-400 hover:text-slate-700 flex items-center gap-1 mb-3 transition cursor-pointer">
                        <span class="material-symbols-outlined text-[14px]">arrow_back</span> Back
                    </button>
                    <p class="text-xs text-slate-500 mb-3">Which item did you book in <strong x-text="selectedBooking.booking_code" class="text-slate-700"></strong>?</p>
                    <div class="space-y-2.5">
                        <template x-for="item in selectedBooking.reviewable_items" :key="item.id">
                            <button type="button" @click="selectItem(item)"
                                class="w-full text-left p-4 rounded-2xl border border-sand-200 bg-white hover:border-ocean-300 hover:bg-ocean-50/40 transition group cursor-pointer">
                                <p class="font-headline text-sm font-bold text-slate-900" x-text="item.item_title"></p>
                                <p class="text-[11px] text-slate-500 mt-0.5" x-text="item.hotel_name || item.item_subtitle"></p>
                            </button>
                        </template>
                    </div>
                </div>

                {{-- Step 3: rating + comment form --}}
                <div x-show="!loading && !success && selectedBooking && (selectedBooking.reviewable_items.length === 1 || selectedItem)" x-cloak>
                    <template x-if="selectedBooking && selectedBooking.reviewable_items.length === 1">
                        <div class="rounded-2xl bg-sand-50 border border-sand-200 px-4 py-3 mb-5">
                            <p class="font-label text-[9px] uppercase font-bold tracking-[0.15em] text-slate-400 mb-0.5">
                                <span x-text="selectedBooking.booking_code"></span> · Verified booking
                            </p>
                            <p class="text-sm font-bold text-slate-900 font-headline"
                                x-text="selectedBooking.reviewable_items[0].item_title"></p>
                            <p class="text-[11px] text-slate-500"
                                x-text="selectedBooking.reviewable_items[0].hotel_name || selectedBooking.reviewable_items[0].item_subtitle"></p>
                        </div>
                    </template>

                    <p class="font-label text-[10px] uppercase font-bold tracking-[0.2em] text-slate-400 mb-2">Your rating</p>
                    <div class="flex items-center gap-1.5 mb-5">
                        <template x-for="n in 5" :key="n">
                            <button type="button"
                                @mouseenter="hoverStar = n"
                                @mouseleave="hoverStar = 0"
                                @click="rating = n"
                                class="text-3xl transition-transform hover:scale-110 cursor-pointer focus:outline-none"
                                :class="(hoverStar || rating) >= n ? 'text-amber-400' : 'text-sand-300'">
                                <span class="material-symbols-outlined text-[32px]" style="font-variation-settings: 'FILL' 1">star</span>
                            </button>
                        </template>
                        <span class="ml-2 font-headline text-sm font-bold text-slate-700" x-text="rating + ' / 5'"></span>
                    </div>

                    <p class="font-label text-[10px] uppercase font-bold tracking-[0.2em] text-slate-400 mb-2">Your feedback</p>
                    <textarea x-model="comment" rows="4" maxlength="1000" placeholder="What made this stay or experience memorable?"
                        class="w-full rounded-2xl border border-sand-200 bg-white px-4 py-3 text-sm text-slate-900 placeholder:text-slate-400 focus:border-ocean-400 focus:ring-ocean-100 resize-none"></textarea>
                    <div class="flex items-center justify-between mt-1.5 mb-5">
                        <p class="text-[10px] text-slate-400" x-text="comment.length < 10 ? 'Minimum 10 characters.' : (1000 - comment.length) + ' characters remaining'"></p>
                        <p class="text-[10px] font-bold" :class="comment.length >= 10 ? 'text-emerald-600' : 'text-slate-400'">
                            <span x-show="comment.length >= 10">Ready to share</span>
                        </p>
                    </div>

                    <button type="button" @click="submit()" :disabled="submitting || rating === 0 || comment.length < 10"
                        :class="rating === 0 || comment.length < 10 ? 'bg-sand-300 cursor-not-allowed' : 'bg-slate-900 hover:bg-slate-800 cursor-pointer'"
                        class="w-full px-6 py-3.5 rounded-2xl text-white font-bold text-xs transition flex items-center justify-center gap-2 shadow-lg shadow-slate-900/15">
                        <span class="material-symbols-outlined text-[16px]" x-text="submitting ? 'progress_activity' : 'send'"></span>
                        <span x-text="submitting ? 'Publishing...' : 'Publish Review'"></span>
                    </button>

                    <p x-show="errorMessage" x-cloak class="mt-3 text-[11px] font-semibold text-rose-600 bg-rose-50 border border-rose-100 rounded-xl px-3 py-2" x-text="errorMessage"></p>
                </div>

                {{-- Success state --}}
                <div x-show="!loading && success" class="py-8 text-center">
                    <div class="w-16 h-16 rounded-full bg-emerald-100 border border-emerald-200 flex items-center justify-center mx-auto mb-5">
                        <span class="material-symbols-outlined text-3xl text-emerald-600">check_circle</span>
                    </div>
                    <h4 class="font-headline text-lg font-bold text-slate-900">Review published!</h4>
                    <p class="text-xs text-slate-500 mt-2 max-w-xs mx-auto leading-relaxed">
                        Thank you for helping fellow travelers. Our AI is analyzing your feedback to update the community summary.
                    </p>
                    <button type="button" @click="close()"
                        class="mt-6 px-6 py-3 rounded-2xl bg-slate-900 text-white font-bold text-xs hover:bg-slate-800 transition cursor-pointer">
                        Done
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>

@once
    <script>
        function reviewModal({ bookingId = null, csrfToken = '' } = {}) {
            return {
                show: false,
                loading: false,
                submitting: false,
                success: false,
                errorMessage: null,
                bookings: [],
                selectedBooking: null,
                selectedItem: null,
                rating: 0,
                hoverStar: 0,
                comment: '',

                async open() {
                    this.show = true;
                    this.loading = true;
                    this.success = false;
                    this.errorMessage = null;
                    this.rating = 0;
                    this.comment = '';
                    this.selectedBooking = null;
                    this.selectedItem = null;
                    try {
                        const res = await fetch('/reviews/eligible', {
                            headers: { 'Accept': 'application/json' }
                        });
                        const data = await res.json();
                        this.bookings = data.bookings || [];
                        if (bookingId) {
                            const preset = this.bookings.find(b => String(b.booking_id) === String(bookingId));
                            if (preset) this.selectBooking(preset);
                        }
                    } catch (e) {
                        this.bookings = [];
                    } finally {
                        this.loading = false;
                    }
                },

                close() {
                    this.show = false;
                    document.body.classList.remove('overflow-y-hidden');
                },

                selectBooking(booking) {
                    this.selectedBooking = booking;
                    if (booking.reviewable_items.length === 1) {
                        this.selectedItem = booking.reviewable_items[0];
                    } else {
                        this.selectedItem = null;
                    }
                },

                selectItem(item) {
                    this.selectedItem = item;
                },

                async submit() {
                    if (this.submitting) return;
                    this.submitting = true;
                    this.errorMessage = null;
                    try {
                        const res = await fetch('/reviews', {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                                'Accept': 'application/json',
                                'X-CSRF-TOKEN': csrfToken
                            },
                            body: JSON.stringify({
                                booking_id: this.selectedBooking.booking_id,
                                booking_item_id: this.selectedItem ? this.selectedItem.id : null,
                                rating: this.rating,
                                comment: this.comment
                            })
                        });
                        const data = await res.json();
                        if (!res.ok) {
                            this.errorMessage = data.message || 'Something went wrong. Please try again.';
                            return;
                        }
                        this.success = true;
                        this.bookings = this.bookings.filter(b => b.booking_id !== this.selectedBooking.booking_id);
                    } catch (e) {
                        this.errorMessage = 'Network error. Please try again.';
                    } finally {
                        this.submitting = false;
                    }
                }
            };
        }
    </script>
@endonce