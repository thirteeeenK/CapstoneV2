@extends('layouts.admin')

@section('title', 'Create Review | SunnyTrips Admin')

@section('content')
    <div class="pb-12"
        x-data="reviewCreateForm({{ Js::from($bookings) }}, {{ Js::from($hotels) }}, {{ Js::from($rooms) }}, {{ Js::from($activities) }}, {{ Js::from($packages) }})">

        <div class="mb-6 flex flex-col sm:flex-row sm:items-center justify-between gap-4">
            <div>
                <h1 class="text-xl font-bold text-slate-900">Create Review</h1>
                <p class="text-xs text-slate-500 mt-1">
                    Backfill a review from a past booking, or manually add feedback from pre-system customers. Sentiment analysis and summaries are generated automatically.
                </p>
            </div>
            <a href="{{ route('admin.reviews.index') }}" class="inline-flex items-center gap-1.5 h-9 px-3 rounded-md bg-slate-100 hover:bg-slate-200 text-slate-700 text-xs font-semibold transition-colors self-start sm:self-auto">
                <span class="material-symbols-outlined text-[16px]">arrow_back</span>
                Back to Reviews
            </a>
        </div>

        @if ($errors->any())
            <div class="bg-rose-50 border border-rose-200/80 text-rose-800 text-xs px-4 py-3 rounded-md mb-6 space-y-1">
                <p class="font-bold">Please correct the errors below:</p>
                <ul class="list-disc list-inside">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        {{-- Mode toggle --}}
        <div class="flex items-center gap-1 p-1 bg-slate-100 rounded-xl mb-6 w-fit">
            <button type="button" @click="mode = 'booking'"
                class="px-4 py-2 rounded-lg text-xs font-bold transition-all cursor-pointer"
                :class="mode === 'booking' ? 'bg-white text-slate-900 shadow-sm' : 'text-slate-500 hover:text-slate-700'">
                <span class="material-symbols-outlined text-[14px] align-middle mr-1">receipt_long</span>
                From Booking
            </button>
            <button type="button" @click="mode = 'manual'"
                class="px-4 py-2 rounded-lg text-xs font-bold transition-all cursor-pointer"
                :class="mode === 'manual' ? 'bg-white text-slate-900 shadow-sm' : 'text-slate-500 hover:text-slate-700'">
                <span class="material-symbols-outlined text-[14px] align-middle mr-1">edit_note</span>
                Manual Entry
            </button>
        </div>

        <form action="{{ route('admin.reviews.store') }}" method="POST"
            class="grid grid-cols-12 gap-8 items-start">
            @csrf

            <input type="hidden" name="review_mode" :value="mode">

            {{-- ======================== --}}
            {{-- LEFT COLUMN --}}
            {{-- ======================== --}}
            <div class="col-span-12 lg:col-span-7 space-y-6">

                {{-- BOOKING MODE --}}
                <template x-if="mode === 'booking'">
                    <section class="bg-white rounded-lg p-6 border border-slate-200">
                        <h2 class="text-base font-semibold text-slate-900 border-b border-slate-100 pb-3 mb-5 flex items-center gap-2">
                            <span class="inline-block w-1.5 h-4 bg-primary rounded-full"></span>
                            Booking
                        </h2>

                        <div class="space-y-1.5 mb-5">
                            <label for="booking_id" class="block text-xs font-semibold text-slate-700">
                                Completed booking without a review <span class="text-rose-500">*</span>
                            </label>
                            <select id="booking_id" name="booking_id" x-model="bookingId"
                                class="w-full bg-slate-50 border border-slate-300 rounded-md px-3 py-2 text-xs text-slate-900 focus:ring-2 focus:ring-primary/20 focus:border-primary transition-all">
                                <option value="">Select booking...</option>
                                @foreach ($bookings as $b)
                                    <option value="{{ $b['id'] }}">{{ '#'.$b['booking_code'].' — '.$b['contact_name'].' ('.$b['guest_name'].')' }}</option>
                                @endforeach
                            </select>
                            <p class="text-[11px] text-slate-400">
                                {{ count($bookings) }} completed booking(s) awaiting a review.
                            </p>
                        </div>

                        <div class="space-y-1.5">
                            <label for="booking_item_id" class="block text-xs font-semibold text-slate-700">
                                Reviewable item <span class="text-rose-500">*</span>
                            </label>
                            <select id="booking_item_id" name="booking_item_id" x-model="itemId"
                                class="w-full bg-slate-50 border border-slate-300 rounded-md px-3 py-2 text-xs text-slate-900 focus:ring-2 focus:ring-primary/20 focus:border-primary transition-all">
                                <option value="">Select booking first...</option>
                                <template x-for="item in items" :key="item.id">
                                    <option :value="item.id" x-text="item.item_title + (item.item_subtitle ? ' — ' + item.item_subtitle : '')"></option>
                                </template>
                            </select>
                        </div>
                    </section>
                </template>

                {{-- MANUAL MODE --}}
                <template x-if="mode === 'manual'">
                    <section class="bg-white rounded-lg p-6 border border-slate-200">
                        <h2 class="text-base font-semibold text-slate-900 border-b border-slate-100 pb-3 mb-5 flex items-center gap-2">
                            <span class="inline-block w-1.5 h-4 bg-primary rounded-full"></span>
                            Reviewer & Entity
                        </h2>

                        <div class="space-y-1.5 mb-5">
                            <label for="reviewer_name" class="block text-xs font-semibold text-slate-700">
                                Reviewer name <span class="text-rose-500">*</span>
                            </label>
                            <input type="text" id="reviewer_name" name="reviewer_name" value="{{ old('reviewer_name') }}" required
                                placeholder="e.g. Maria Santos"
                                class="w-full bg-slate-50 border border-slate-300 rounded-md px-3 py-2 text-xs text-slate-900 focus:ring-2 focus:ring-primary/20 focus:border-primary transition-all">
                            <p class="text-[11px] text-slate-400">The name shown publicly on the review card.</p>
                        </div>

                        <div class="space-y-1.5 mb-5">
                            <label class="block text-xs font-semibold text-slate-700">
                                Entity type <span class="text-rose-500">*</span>
                            </label>
                            <div class="flex flex-wrap gap-2">
                                @foreach (['hotel' => 'Hotel', 'room' => 'Room', 'activity' => 'Activity', 'package' => 'Package'] as $key => $label)
                                    <label class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg border text-xs font-bold cursor-pointer transition-all"
                                        :class="manualEntityType === '{{ $key }}' ? 'bg-ocean-50 text-ocean-700 border-ocean-200' : 'bg-slate-50 text-slate-500 border-slate-200 hover:bg-slate-100'">
                                        <input type="radio" name="manual_entity_type" value="{{ $key }}" x-model="manualEntityType"
                                            class="sr-only" required>
                                        {{ $label }}
                                    </label>
                                @endforeach
                            </div>
                            <input type="hidden" name="manual_entity_type" x-effect="$el.value = resolvedManualType">
                        </div>

                        <div class="space-y-4">
                            <input type="hidden" name="manual_entity_id" x-effect="$el.value = manualEntityId">

                            {{-- Hotel / Room: two-step cascade (rooms belong to a hotel) --}}
                            <template x-if="manualEntityType === 'hotel' || manualEntityType === 'room'">
                                <div class="space-y-4">
                                    <div class="space-y-1.5">
                                        <label for="manual_hotel_id" class="block text-xs font-semibold text-slate-700">
                                            Hotel <span class="text-rose-500">*</span>
                                        </label>
                                        <select id="manual_hotel_id" x-model="manualHotelId" required
                                            class="w-full bg-slate-50 border border-slate-300 rounded-md px-3 py-2 text-xs text-slate-900 focus:ring-2 focus:ring-primary/20 focus:border-primary transition-all">
                                            <option value="">Select hotel...</option>
                                            <template x-for="h in hotels" :key="h.id">
                                                <option :value="h.id" x-text="h.hotel_name"></option>
                                            </template>
                                        </select>
                                    </div>

                                    <div class="space-y-1.5" x-show="manualHotelId" x-transition.opacity>
                                        <label for="manual_room_id" class="block text-xs font-semibold text-slate-700">
                                            Room <span class="text-rose-500">*</span>
                                        </label>
                                        <select id="manual_room_id" x-model="manualRoomId" required
                                            :disabled="!manualHotelId"
                                            class="w-full bg-slate-50 border border-slate-300 rounded-md px-3 py-2 text-xs text-slate-900 focus:ring-2 focus:ring-primary/20 focus:border-primary transition-all"
                                            :class="!manualHotelId && 'opacity-50'">
                                            <option value="" x-text="manualHotelId ? 'Select room...' : 'Select a hotel first...'"></option>
                                            <template x-for="r in filteredRooms" :key="r.id">
                                                <option :value="r.id" x-text="r.room_name"></option>
                                            </template>
                                        </select>
                                        <p class="text-[11px] text-slate-400"
                                            x-text="manualHotelId ? filteredRooms.length + ' room(s) available' : 'Rooms appear once you pick a hotel.'"></p>
                                    </div>
                                </div>
                            </template>

                            {{-- Activity: independent --}}
                            <template x-if="manualEntityType === 'activity'">
                                <div class="space-y-1.5">
                                    <label for="manual_entity_id" class="block text-xs font-semibold text-slate-700">
                                        Select activity <span class="text-rose-500">*</span>
                                    </label>
                                    <select id="manual_entity_id" x-model="manualActivityId" required
                                        class="w-full bg-slate-50 border border-slate-300 rounded-md px-3 py-2 text-xs text-slate-900 focus:ring-2 focus:ring-primary/20 focus:border-primary transition-all">
                                        <option value="">Select activity...</option>
                                        <template x-for="e in activities" :key="e.id">
                                            <option :value="e.id" x-text="e.activity_name"></option>
                                        </template>
                                    </select>
                                </div>
                            </template>

                            {{-- Package: independent --}}
                            <template x-if="manualEntityType === 'package'">
                                <div class="space-y-1.5">
                                    <label for="manual_entity_id" class="block text-xs font-semibold text-slate-700">
                                        Select package <span class="text-rose-500">*</span>
                                    </label>
                                    <select id="manual_entity_id" x-model="manualPackageId" required
                                        class="w-full bg-slate-50 border border-slate-300 rounded-md px-3 py-2 text-xs text-slate-900 focus:ring-2 focus:ring-primary/20 focus:border-primary transition-all">
                                        <option value="">Select package...</option>
                                        <template x-for="e in packages" :key="e.id">
                                            <option :value="e.id" x-text="e.name"></option>
                                        </template>
                                    </select>
                                </div>
                            </template>
                        </div>
                    </section>
                </template>

                {{-- Review content (shared) --}}
                <section class="bg-white rounded-lg p-6 border border-slate-200">
                    <h2 class="text-base font-semibold text-slate-900 border-b border-slate-100 pb-3 mb-5 flex items-center gap-2">
                        <span class="inline-block w-1.5 h-4 bg-primary rounded-full"></span>
                        Review
                    </h2>

                    <div class="space-y-1.5 mb-5">
                        <label class="block text-xs font-semibold text-slate-700">
                            Rating <span class="text-rose-500">*</span>
                        </label>
                        <div class="flex items-center gap-1">
                            <template x-for="star in 5" :key="star">
                                <button type="button" @click="rating = star"
                                    class="text-3xl transition-transform hover:scale-110 cursor-pointer">
                                    <span class="material-symbols-outlined text-[30px] text-amber-400"
                                        :style="'font-variation-settings: \'FILL\' ' + (star <= rating ? 1 : 0)">star</span>
                                </button>
                            </template>
                            <span class="ml-2 text-xs font-bold text-slate-700" x-text="rating + ' / 5'"></span>
                        </div>
                        <input type="hidden" name="rating" :value="rating">
                    </div>

                    <div class="space-y-1.5">
                        <label for="comment" class="block text-xs font-semibold text-slate-700">
                            Comment <span class="text-rose-500">*</span>
                        </label>
                        <textarea id="comment" name="comment" rows="6" required minlength="10" maxlength="2000"
                            placeholder="Write the guest's feedback..."
                            class="w-full bg-slate-50 border border-slate-300 rounded-md px-3 py-2 text-xs text-slate-900 focus:ring-2 focus:ring-primary/20 focus:border-primary transition-all">{{ old('comment') }}</textarea>
                        <p class="text-[11px] text-slate-400">10–2000 characters. This text is sent to the Gemini sentiment analyzer.</p>
                    </div>
                </section>
            </div>

            {{-- ======================== --}}
            {{-- SIDEBAR --}}
            {{-- ======================== --}}
            <aside class="col-span-12 lg:col-span-5 space-y-6 lg:sticky lg:top-6">
                <section class="bg-white rounded-lg p-6 border border-slate-200">
                    <h2 class="text-base font-semibold text-slate-900 border-b border-slate-100 pb-3 mb-4 flex items-center gap-2">
                        <span class="inline-block w-1.5 h-4 bg-teal-500 rounded-full"></span>
                        What happens next
                    </h2>
                    <ul class="space-y-3 text-xs text-slate-600">
                        <li class="flex items-start gap-2.5">
                            <span class="material-symbols-outlined text-[16px] text-ocean-600 mt-0.5">sentiment_satisfied</span>
                            Gemini analyzes the comment and stores sentiment + confidence + keywords on the review.
                        </li>
                        <li class="flex items-start gap-2.5">
                            <span class="material-symbols-outlined text-[16px] text-ocean-600 mt-0.5">summarize</span>
                            The entity and platform summaries regenerate automatically.
                        </li>
                        <li class="flex items-start gap-2.5">
                            <span class="material-symbols-outlined text-[16px] text-ocean-600 mt-0.5">star</span>
                            You can feature this review on the landing page from the Reviews dashboard.
                        </li>
                    </ul>
                </section>

                <section class="bg-white rounded-lg p-6 border border-slate-200">
                    <h2 class="text-base font-semibold text-slate-900 border-b border-slate-100 pb-3 mb-4 flex items-center gap-2">
                        <span class="inline-block w-1.5 h-4 bg-amber-500 rounded-full"></span>
                        Selected review
                    </h2>
                    <div class="text-xs text-slate-600 space-y-2">
                        <template x-if="mode === 'booking'">
                            <div>
                                <p><span class="font-bold text-slate-900">Booking:</span> <span x-text="selectedBooking ? '#' + selectedBooking.booking_code : '—'"></span></p>
                                <p><span class="font-bold text-slate-900">Guest:</span> <span x-text="selectedBooking ? selectedBooking.guest_name : '—'"></span></p>
                                <p><span class="font-bold text-slate-900">Item:</span> <span x-text="selectedItemTitle"></span></p>
                            </div>
                        </template>
                        <template x-if="mode === 'manual'">
                            <div>
                                <p><span class="font-bold text-slate-900">Reviewer:</span> <span x-text="manualReviewerName || '—'"></span></p>
                                <p><span class="font-bold text-slate-900">Type:</span> <span x-text="manualEntityType ? manualEntityType.charAt(0).toUpperCase() + manualEntityType.slice(1) : '—'"></span></p>
                                <p><span class="font-bold text-slate-900">Entity:</span> <span x-text="selectedManualEntityTitle"></span></p>
                                <p class="mt-2 pt-2 border-t border-slate-100">
                                    <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-lg bg-amber-50 text-amber-700 border border-amber-100 text-[10px] font-bold">
                                        <span class="material-symbols-outlined text-[11px]">info</span>
                                        Not a verified booking
                                    </span>
                                </p>
                            </div>
                        </template>
                    </div>
                </section>

                <button type="submit"
                    class="w-full inline-flex items-center justify-center gap-2 h-11 px-4 rounded-md bg-primary hover:bg-primary/90 text-white text-sm font-semibold transition-colors cursor-pointer">
                    <span class="material-symbols-outlined text-[18px]">check_circle</span>
                    Create review
                </button>
            </aside>
        </form>
    </div>

    <script>
        function reviewCreateForm(bookings, hotels, rooms, activities, packages) {
            return {
                mode: 'booking',
                bookings: bookings,
                hotels: hotels,
                rooms: rooms,
                activities: activities,
                packages: packages,
                bookingId: '',
                itemId: '',
                rating: 5,
                manualEntityType: 'hotel',
                manualReviewerName: '',
                manualHotelId: '',
                manualRoomId: '',
                manualActivityId: '',
                manualPackageId: '',
                get items() {
                    const booking = this.bookings.find((b) => String(b.id) === String(this.bookingId));
                    return booking ? booking.items : [];
                },
                get selectedBooking() {
                    return this.bookings.find((b) => String(b.id) === String(this.bookingId));
                },
                get selectedItemTitle() {
                    const item = this.items.find((i) => String(i.id) === String(this.itemId));
                    return item ? item.item_title : '—';
                },
                get filteredRooms() {
                    if (!this.manualHotelId) return this.rooms;
                    return this.rooms.filter((r) => String(r.hotel_id) === String(this.manualHotelId));
                },
                get manualEntityId() {
                    if (this.manualEntityType === 'hotel' || this.manualEntityType === 'room') return this.manualRoomId;
                    if (this.manualEntityType === 'activity') return this.manualActivityId;
                    return this.manualPackageId;
                },
                get resolvedManualType() {
                    if (this.manualEntityType === 'hotel' || this.manualEntityType === 'room') return 'room';
                    return this.manualEntityType;
                },
                get selectedManualEntityTitle() {
                    if (this.manualEntityType === 'hotel' || this.manualEntityType === 'room') {
                        if (!this.manualRoomId) return '—';
                        const room = this.rooms.find((r) => String(r.id) === String(this.manualRoomId));
                        const hotel = this.hotels.find((h) => String(h.id) === String(this.manualHotelId));
                        if (!room) return '—';
                        return room.room_name + (hotel ? ' — ' + hotel.hotel_name : '');
                    }
                    if (this.manualEntityType === 'activity') {
                        const activity = this.activities.find((a) => String(a.id) === String(this.manualActivityId));
                        return activity ? activity.activity_name : '—';
                    }
                    const pkg = this.packages.find((p) => String(p.id) === String(this.manualPackageId));
                    return pkg ? pkg.name : '—';
                },
                init() {
                    this.$watch('manualEntityType', (type) => {
                        const connected = type === 'hotel' || type === 'room';
                        if (!connected) {
                            this.manualHotelId = '';
                            this.manualRoomId = '';
                        }
                        if (type !== 'activity') this.manualActivityId = '';
                        if (type !== 'package') this.manualPackageId = '';
                    });
                },
            };
        }
    </script>
@endsection