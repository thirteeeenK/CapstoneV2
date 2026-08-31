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
    <template x-teleport="body">
        <div x-show="show" x-cloak @keydown.escape.window="close()" x-transition.opacity
            class="fixed inset-0 z-[70] flex items-start sm:items-center justify-center px-4 py-6 overflow-y-auto">
            <div class="fixed inset-0 bg-slate-950/60 backdrop-blur-sm" @click="close()"></div>

            <div class="relative w-full max-w-xl bg-white rounded-[1.75rem] shadow-2xl border border-sand-200 overflow-hidden">
            {{-- Header --}}
            <div class="px-7 sm:px-9 pt-5 pb-4 border-b border-sand-100 flex items-start justify-between gap-4">
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

            <div class="px-7 sm:px-9 py-5">

                {{-- Loading --}}
                <div x-show="loading" class="py-12 text-center">
                    <div class="w-10 h-10 mx-auto rounded-full border-4 border-sand-200 border-t-ocean-500 animate-spin mb-4"></div>
                    <p class="text-xs text-slate-500 font-medium">Checking your completed trips...</p>
                </div>

                {{-- Error / no eligible bookings --}}
                <div x-show="!loading && !success && bookings.length === 0 && !selectedBooking" class="py-10 text-center">
                    <template x-if="errorMessage">
                        <div>
                            <div class="w-14 h-14 rounded-2xl bg-rose-50 border border-rose-200 flex items-center justify-center mx-auto mb-4">
                                <span class="material-symbols-outlined text-2xl text-rose-500">error</span>
                            </div>
                            <h4 class="font-headline text-sm font-bold text-slate-900">Something went wrong</h4>
                            <p class="text-xs text-slate-500 mt-2 max-w-xs mx-auto leading-relaxed" x-text="errorMessage"></p>
                        </div>
                    </template>
                    <template x-if="!errorMessage">
                        <div>
                            <div class="w-14 h-14 rounded-2xl bg-sand-100 border border-sand-200 flex items-center justify-center mx-auto mb-4">
                                <span class="material-symbols-outlined text-2xl text-slate-400">verified</span>
                            </div>
                            <h4 class="font-headline text-sm font-bold text-slate-900">No reviewable trips yet</h4>
                            <p class="text-xs text-slate-500 mt-2 max-w-xs mx-auto leading-relaxed">
                                Reviews unlock after a trip is marked completed. Check back once your journey finishes.
                            </p>
                        </div>
                    </template>
                </div>

                {{-- Step 1: choose booking (only when no bookingId preset and multiple bookings) --}}
                <div x-show="!loading && !success && bookings.length > 0 && !selectedBooking && !presetBookingId">
                    <p class="text-xs text-slate-500 mb-4">Choose the completed booking you'd like to review:</p>
                    <div class="space-y-3">
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
                <div x-show="!loading && !success && selectedBooking && !selectedItem">
                    <button type="button" @click="selectedBooking = null; selectedItem = null"
                        x-show="!presetBookingId"
                        class="text-[11px] font-bold text-slate-400 hover:text-slate-700 flex items-center gap-1 mb-4 transition cursor-pointer">
                        <span class="material-symbols-outlined text-[14px]">arrow_back</span> Back
                    </button>
                    <p class="text-xs text-slate-500 mb-4">
                        Items in <strong x-text="'Journal ' + (selectedBooking?.booking_code || '')" class="text-slate-700"></strong> — review each separately:
                    </p>
                    <div class="space-y-3">
                        <template x-for="item in (selectedBooking?.reviewable_items || [])" :key="item.id">
                            <div>
                                {{-- Unreviewed item --}}
                                <button type="button" x-show="!item.is_reviewed" @click="selectItem(item)"
                                    class="w-full text-left p-4 rounded-2xl border border-sand-200 bg-white hover:border-ocean-300 hover:bg-ocean-50/40 transition group cursor-pointer">
                                    <div class="flex items-center justify-between gap-3">
                                        <div class="min-w-0">
                                            <p class="font-headline text-sm font-bold text-slate-900" x-text="item.item_title"></p>
                                            <p class="text-[11px] text-slate-500 mt-0.5" x-text="item.hotel_name || item.item_subtitle"></p>
                                        </div>
                                        <span class="material-symbols-outlined text-slate-300 group-hover:text-ocean-500 transition shrink-0">chevron_right</span>
                                    </div>
                                </button>
                                {{-- Reviewed item --}}
                                <div x-show="item.is_reviewed"
                                    class="w-full text-left p-4 rounded-2xl border border-emerald-200 bg-emerald-50/50">
                                    <div class="flex items-center justify-between gap-3">
                                        <div class="min-w-0">
                                            <div class="flex items-center gap-2">
                                                <p class="font-headline text-sm font-bold text-slate-900" x-text="item.item_title"></p>
                                                <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-lg text-[10px] font-bold bg-emerald-100 text-emerald-700 border border-emerald-200">
                                                    <span class="material-symbols-outlined text-[12px]">check_circle</span>
                                                    Reviewed
                                                </span>
                                            </div>
                                            <p class="text-[11px] text-slate-500 mt-0.5" x-text="item.hotel_name || item.item_subtitle"></p>
                                            <div x-show="item.existing_review" class="flex items-center gap-1.5 mt-1.5">
                                                <template x-for="n in 5" :key="n">
                                                    <span class="material-symbols-outlined text-[14px]"
                                                        :class="n <= item.existing_review?.rating ? 'text-amber-400' : 'text-sand-300'"
                                                        style="font-variation-settings: 'FILL' 1">star</span>
                                                </template>
                                                <span class="text-[10px] text-slate-400 font-medium ml-1"
                                                    x-text="item.existing_review?.rating + '/5'"></span>
                                            </div>
                                        </div>
                                        <button type="button" @click="viewReviewedItem(item)"
                                            class="text-[11px] font-bold text-ocean-600 hover:text-ocean-800 transition cursor-pointer shrink-0">
                                            View
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </template>
                    </div>
                </div>

                {{-- Step 3: rating + comment form --}}
                <div x-show="!loading && !success && selectedBooking && selectedItem && !showingReviewedItem" x-cloak>
                    <button type="button" @click="selectedItem = null"
                        class="text-[11px] font-bold text-slate-400 hover:text-slate-700 flex items-center gap-1 mb-3 transition cursor-pointer">
                        <span class="material-symbols-outlined text-[14px]">arrow_back</span> Back to items
                    </button>

                    <div class="rounded-2xl bg-sand-50 border border-sand-200 px-4 py-3 mb-4">
                        <p class="font-label text-[9px] uppercase font-bold tracking-[0.15em] text-slate-400 mb-0.5">
                            <span x-text="selectedBooking?.booking_code || ''"></span> · Verified booking
                        </p>
                        <p class="text-sm font-bold text-slate-900 font-headline"
                            x-text="selectedItem?.item_title || ''"></p>
                        <p class="text-[11px] text-slate-500"
                            x-text="(selectedItem?.hotel_name || selectedItem?.item_subtitle) || ''"></p>
                    </div>

                    <div class="mb-5">
                        <p class="font-label text-[10px] uppercase font-bold tracking-[0.2em] text-slate-400 mb-2">Your rating</p>
                        <div class="flex items-center gap-1.5">
                            <template x-for="n in 5" :key="n">
                                <button type="button"
                                    @mouseenter="hoverStar = n"
                                    @mouseleave="hoverStar = 0"
                                    @click="rating = n"
                                    class="text-2xl transition-transform hover:scale-110 cursor-pointer focus:outline-none"
                                    :class="(hoverStar || rating) >= n ? 'text-amber-400' : 'text-sand-300'">
                                    <span class="material-symbols-outlined text-[26px]" style="font-variation-settings: 'FILL' 1">star</span>
                                </button>
                            </template>
                            <span class="ml-3 font-headline text-sm font-bold text-slate-700" x-text="rating ? rating + ' / 5' : ''"></span>
                        </div>
                    </div>

                    <div class="mb-5">
                        <p class="font-label text-[10px] uppercase font-bold tracking-[0.2em] text-slate-400 mb-2">Your feedback</p>
                        <textarea x-model="comment" rows="3" maxlength="1000" placeholder="What made this stay or experience memorable?"
                            class="w-full rounded-2xl border border-sand-200 bg-white px-5 py-3.5 text-sm text-slate-900 placeholder:text-slate-400 focus:border-ocean-400 focus:ring-ocean-100 resize-none"></textarea>
                        <div class="flex items-center justify-between mt-2">
                            <p class="text-[10px] text-slate-400" x-text="comment.length < 10 ? 'Minimum 10 characters.' : (1000 - comment.length) + ' characters remaining'"></p>
                            <p class="text-[10px] font-bold" :class="comment.length >= 10 ? 'text-emerald-600' : 'text-slate-400'">
                                <span x-show="comment.length >= 10">Ready to share</span>
                            </p>
                        </div>
                    </div>

                    {{-- Optional photos (3x3MB) --}}
                    <div class="mb-5">
                        <p class="font-label text-[10px] uppercase font-bold tracking-[0.2em] text-slate-400 mb-2">Photos <span class="font-normal normal-case tracking-normal text-slate-400">(optional, up to 3 · 3MB each · JPG/PNG/WebP)</span></p>
                        <input x-ref="fileInput" type="file" accept="image/jpeg,image/png,image/webp" multiple class="hidden" @change="onFiles($event)">
                        <div @click="$refs.fileInput.click()"
                             @dragover.prevent="dragOver = true" @dragleave="dragOver = false" @drop.prevent="onDrop($event)"
                             :class="dragOver ? 'border-ocean-400 bg-ocean-50' : 'border-sand-200 bg-white hover:border-ocean-200'"
                             class="w-full rounded-2xl border-2 border-dashed px-4 py-4 flex flex-col items-center justify-center gap-1.5 cursor-pointer transition text-center">
                            <span class="material-symbols-outlined text-[20px] text-slate-400">add_a_photo</span>
                            <p class="text-[11px] font-bold text-slate-600">Click or drag photos here</p>
                            <p class="text-[10px] text-slate-400" x-text="selectedFiles.length ? selectedFiles.length + '/3 selected' : 'No photos selected — reviews work without them'"></p>
                        </div>
                        <p x-show="imageError" x-cloak class="mt-2 text-[11px] font-semibold text-rose-600 bg-rose-50 border border-rose-100 rounded-xl px-3 py-2" x-text="imageError"></p>
                        <div x-show="previews.length" class="mt-3 grid grid-cols-3 gap-2">
                            <template x-for="(src, idx) in previews" :key="idx">
                                <div class="relative group">
                                    <img :src="src" class="w-full h-24 object-cover rounded-xl border border-sand-200">
                                    <button type="button" @click="removeAt(idx)" class="absolute -top-2 -right-2 w-6 h-6 rounded-full bg-slate-900 text-white flex items-center justify-center shadow cursor-pointer hover:bg-rose-600 transition">
                                        <span class="material-symbols-outlined text-[14px]">close</span>
                                    </button>
                                </div>
                            </template>
                        </div>
                    </div>

                    <button type="button" @click="submit()" :disabled="submitting || rating === 0 || comment.length < 10"
                        :class="rating === 0 || comment.length < 10 ? 'bg-sand-300 cursor-not-allowed' : 'bg-slate-900 hover:bg-slate-800 cursor-pointer'"
                        class="w-full px-6 py-3 rounded-2xl text-white font-bold text-xs transition flex items-center justify-center gap-2 shadow-lg shadow-slate-900/15">
                        <span class="material-symbols-outlined text-[16px]" x-text="submitting ? 'progress_activity' : 'send'"></span>
                        <span x-text="submitting ? 'Publishing...' : 'Publish Review'"></span>
                    </button>

                    <p x-show="errorMessage" x-cloak class="mt-4 text-[11px] font-semibold text-rose-600 bg-rose-50 border border-rose-100 rounded-xl px-4 py-2.5" x-text="errorMessage"></p>
                </div>

                {{-- Viewing an already-reviewed item --}}
                <div x-show="!loading && !success && showingReviewedItem" x-cloak>
                    <button type="button" @click="showingReviewedItem = null"
                        class="text-[11px] font-bold text-slate-400 hover:text-slate-700 flex items-center gap-1 mb-5 transition cursor-pointer">
                        <span class="material-symbols-outlined text-[14px]">arrow_back</span> Back to items
                    </button>

                    <div class="rounded-2xl bg-emerald-50 border border-emerald-200 px-5 py-4 mb-5">
                        <div class="flex items-center gap-2 mb-1">
                            <span class="material-symbols-outlined text-[16px] text-emerald-600">check_circle</span>
                            <p class="font-label text-[10px] uppercase font-bold tracking-[0.15em] text-emerald-700">Review Submitted</p>
                        </div>
                        <p class="text-sm font-bold text-slate-900 font-headline"
                            x-text="showingReviewedItem?.item_title"></p>
                    </div>

                    <div class="flex items-center gap-1.5 mb-4">
                        <template x-for="n in 5" :key="n">
                            <span class="material-symbols-outlined text-[24px]"
                                :class="n <= (showingReviewedItem?.existing_review?.rating || 0) ? 'text-amber-400' : 'text-sand-300'"
                                style="font-variation-settings: 'FILL' 1">star</span>
                        </template>
                        <span class="ml-2 font-headline text-sm font-bold text-slate-700"
                            x-text="(showingReviewedItem?.existing_review?.rating || 0) + ' / 5'"></span>
                    </div>

                    <div class="rounded-2xl bg-sand-50 border border-sand-200 px-5 py-4">
                        <p class="font-label text-[9px] uppercase font-bold tracking-[0.15em] text-slate-400 mb-1.5">Your comment</p>
                        <p class="text-sm text-slate-700 leading-relaxed" x-text="showingReviewedItem?.existing_review?.comment"></p>
                    </div>
                </div>

                {{-- Success state --}}
                <div x-show="!loading && success" class="py-10 text-center">
                    <div class="w-16 h-16 rounded-full bg-emerald-100 border border-emerald-200 flex items-center justify-center mx-auto mb-5">
                        <span class="material-symbols-outlined text-3xl text-emerald-600">check_circle</span>
                    </div>
                    <h4 class="font-headline text-lg font-bold text-slate-900">Review published!</h4>
                    <p class="text-xs text-slate-500 mt-2 max-w-xs mx-auto leading-relaxed">
                        Thank you for helping fellow travelers. Our AI is analyzing your feedback to update the community summary.
                    </p>
                    <div class="mt-6 flex items-center justify-center gap-3">
                        <button type="button" @click="afterSuccess()"
                            x-show="selectedBooking && hasMoreUnreviewed()"
                            class="px-6 py-3 rounded-2xl bg-white border border-sand-200 text-slate-900 font-bold text-xs hover:bg-sand-50 transition cursor-pointer">
                            Review Another Item
                        </button>
                        <button type="button" @click="close()"
                            class="px-6 py-3 rounded-2xl bg-slate-900 text-white font-bold text-xs hover:bg-slate-800 transition cursor-pointer">
                            Done
                        </button>
                    </div>
                </div>
            </div>
        </div>
        </div>
    </template>
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
                imageError: null,
                bookings: [],
                selectedBooking: null,
                selectedItem: null,
                showingReviewedItem: null,
                rating: 0,
                hoverStar: 0,
                comment: '',
                selectedFiles: [],
                previews: [],
                dragOver: false,
                presetBookingId: bookingId,

                clearImages() {
                    this.previews.forEach(u => URL.revokeObjectURL(u));
                    this.selectedFiles = [];
                    this.previews = [];
                    this.imageError = null;
                    this.dragOver = false;
                    if (this.$refs.fileInput) this.$refs.fileInput.value = '';
                },
                onFiles(event) {
                    const files = Array.from(event.target.files || []);
                    this.addFiles(files);
                    if (this.$refs.fileInput) this.$refs.fileInput.value = '';
                },
                onDrop(event) {
                    this.dragOver = false;
                    const files = Array.from(event.dataTransfer?.files || []);
                    this.addFiles(files);
                },
                addFiles(files) {
                    this.imageError = null;
                    const allowed = ['image/jpeg','image/png','image/webp','image/jpg'];
                    for (const f of files) {
                        if (this.selectedFiles.length >= 3) { this.imageError = 'Up to 3 photos only.'; break; }
                        if (!allowed.includes(f.type)) { this.imageError = f.name + ': only JPG, PNG, WebP allowed.'; continue; }
                        if (f.size > 3*1024*1024) { this.imageError = f.name + ' exceeds 3MB.'; continue; }
                        this.selectedFiles.push(f);
                        this.previews.push(URL.createObjectURL(f));
                    }
                },
                removeAt(idx) {
                    if (this.previews[idx]) URL.revokeObjectURL(this.previews[idx]);
                    this.selectedFiles.splice(idx,1);
                    this.previews.splice(idx,1);
                    this.imageError = null;
                },
                async open() {
                    this.show = true;
                    this.loading = true;
                    this.success = false;
                    this.errorMessage = null;
                    this.imageError = null;
                    this.rating = 0;
                    this.comment = '';
                    this.selectedBooking = null;
                    this.selectedItem = null;
                    this.showingReviewedItem = null;
                    this.clearImages();
                    try {
                        const res = await fetch('/reviews/eligible', {
                            headers: { 'Accept': 'application/json' }
                        });
                        if (res.redirected && res.url.includes('/onboarding')) {
                            throw new Error('Please finish your onboarding before leaving a review.');
                        }
                        if (!res.ok) {
                            throw new Error('Something went wrong. Please try again.');
                        }
                        const data = await res.json();
                        this.bookings = data.bookings || [];
                        if (bookingId) {
                            const preset = this.bookings.find(b => String(b.booking_id) === String(bookingId));
                            if (preset) {
                                this.selectedBooking = preset;
                                const unreviewed = preset.reviewable_items.filter(i => !i.is_reviewed);
                                if (unreviewed.length === 1) {
                                    this.selectedItem = unreviewed[0];
                                }
                            }
                        }
                    } catch (e) {
                        this.bookings = [];
                        this.errorMessage = e.message || 'Unable to load your reviews.';
                    } finally {
                        this.loading = false;
                    }
                },

                close() {
                    if (this.selectedBooking && !this.hasMoreUnreviewed()) {
                        window.dispatchEvent(new CustomEvent('booking-reviews-synced', {
                            detail: { bookingId: this.selectedBooking.booking_id }
                        }));
                    }
                    this.show = false;
                    document.body.classList.remove('overflow-y-hidden');
                    this.clearImages();
                },

                selectBooking(booking) {
                    this.selectedBooking = booking;
                    const unreviewed = booking.reviewable_items.filter(i => !i.is_reviewed);
                    if (unreviewed.length === 1) {
                        this.selectedItem = unreviewed[0];
                    } else {
                        this.selectedItem = null;
                    }
                },

                selectItem(item) {
                    this.selectedItem = item;
                    this.rating = 0;
                    this.comment = '';
                    this.errorMessage = null;
                    this.clearImages();
                },

                viewReviewedItem(item) {
                    this.showingReviewedItem = item;
                },

                hasMoreUnreviewed() {
                    if (!this.selectedBooking) return false;
                    return this.selectedBooking.reviewable_items.some(i => !i.is_reviewed);
                },

                afterSuccess() {
                    if (!this.selectedBooking) {
                        this.close();
                        return;
                    }
                    if (this.selectedItem) {
                        const item = this.selectedBooking.reviewable_items.find(i => i.id === this.selectedItem.id);
                        if (item) {
                            item.is_reviewed = true;
                            item.existing_review = { rating: this.rating, comment: this.comment };
                        }
                    }
                    this.success = false;
                    this.selectedItem = null;
                    this.rating = 0;
                    this.comment = '';
                    this.errorMessage = null;
                    this.clearImages();

                    const hasMore = this.hasMoreUnreviewed();
                    if (!hasMore) {
                        this.bookings = this.bookings.filter(b => b.booking_id !== this.selectedBooking.booking_id);
                        if (this.bookings.length === 0) {
                            this.close();
                        } else {
                            this.selectedBooking = null;
                        }
                    }
                },

                async submit() {
                    if (this.submitting) return;
                    this.submitting = true;
                    this.errorMessage = null;
                    try {
                        let res;
                        if (this.selectedFiles.length) {
                            const fd = new FormData();
                            fd.append('booking_id', this.selectedBooking.booking_id);
                            fd.append('booking_item_id', this.selectedItem.id);
                            fd.append('rating', this.rating);
                            fd.append('comment', this.comment);
                            this.selectedFiles.forEach(f => fd.append('images[]', f));
                            res = await fetch('/reviews', {
                                method: 'POST',
                                headers: { 'Accept': 'application/json', 'X-CSRF-TOKEN': csrfToken },
                                body: fd
                            });
                        } else {
                            res = await fetch('/reviews', {
                                method: 'POST',
                                headers: {
                                    'Content-Type': 'application/json',
                                    'Accept': 'application/json',
                                    'X-CSRF-TOKEN': csrfToken
                                },
                                body: JSON.stringify({
                                    booking_id: this.selectedBooking.booking_id,
                                    booking_item_id: this.selectedItem.id,
                                    rating: this.rating,
                                    comment: this.comment
                                })
                            });
                        }
                        const data = await res.json();
                        if (!res.ok) {
                            if (data.errors) {
                                const first = Object.values(data.errors).flat()[0];
                                this.errorMessage = first || data.message || 'Please check your photos (max 3, 3MB each, JPG/PNG/WebP).';
                            } else {
                                this.errorMessage = data.message || 'Something went wrong. Please try again.';
                            }
                            return;
                        }
                        if (this.selectedBooking && this.selectedItem) {
                            const item = this.selectedBooking.reviewable_items.find(i => i.id === this.selectedItem.id);
                            if (item) {
                                item.is_reviewed = true;
                                item.existing_review = { rating: this.rating, comment: this.comment };
                            }
                        }
                        this.success = true;
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
