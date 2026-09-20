{{-- Linked catalogue records (FK relations). Price stays a manual admin flat — these links are descriptive, never summed. --}}
@php
    $linkedHotelIds = isset($package) ? $package->hotels->pluck('id')->map(fn ($id) => (int) $id)->all() : [];
    $linkedRoomIds = isset($package) ? $package->rooms->pluck('id')->map(fn ($id) => (int) $id)->all() : [];
    $linkedActivityIds = isset($package) ? $package->activities->pluck('id')->map(fn ($id) => (int) $id)->all() : [];
    $linkedAddOnIds = isset($package) ? $package->addOns->pluck('id')->map(fn ($id) => (int) $id)->all() : [];
    $selHotels = old('hotel_ids', $linkedHotelIds);
    $selRooms = old('room_ids', $linkedRoomIds);
    $selActivities = old('activity_ids', $linkedActivityIds);
    $selAddOns = old('add_on_ids', $linkedAddOnIds);
@endphp

<div class="sm:col-span-2 rounded-2xl border border-slate-200 bg-slate-50/60 p-4 sm:p-5 space-y-5" id="package-relations">
    <p class="text-xs font-bold uppercase tracking-wider text-slate-700">Linked Catalogue Records <span class="normal-case font-medium text-slate-400">(optional — price stays manual)</span></p>

    {{-- Hotels + rooms --}}
    <div>
        <p class="text-xs font-bold text-slate-700 mb-1.5">Hotels & Rooms <span class="font-medium text-slate-400">(max 1 room per hotel)</span></p>
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-2 max-h-64 overflow-y-auto pr-1">
            @forelse($hotels as $hotel)
                <div class="rounded-xl border border-slate-200 bg-white p-2.5 relation-opt" data-destination="{{ $hotel->destination_id }}">
                    <label class="flex items-center gap-2 text-xs font-bold text-slate-800 cursor-pointer">
                        <input type="checkbox" name="hotel_ids[]" value="{{ $hotel->id }}" class="hotel-check rounded border-slate-300 text-sky-600" {{ in_array((int) $hotel->id, array_map('intval', (array) $selHotels)) ? 'checked' : '' }}>
                        <span class="truncate">{{ $hotel->hotel_name }}</span>
                    </label>
                    @if($hotel->rooms->isNotEmpty())
                        <div class="mt-1.5 ml-6 space-y-1">
                            @foreach($hotel->rooms as $room)
                                <label class="flex items-center gap-1.5 text-[11px] text-slate-600 cursor-pointer">
                                    <input type="checkbox" name="room_ids[]" value="{{ $room->id }}" data-hotel="{{ $hotel->id }}" class="room-check rounded border-slate-300 text-sky-600" {{ in_array((int) $room->id, array_map('intval', (array) $selRooms)) ? 'checked' : '' }}>
                                    <span class="truncate">{{ $room->room_name }} · ₱{{ number_format($room->base_price, 0) }}</span>
                                </label>
                            @endforeach
                        </div>
                    @endif
                </div>
            @empty
                <p class="text-[11px] text-slate-400">No hotels yet — create one first.</p>
            @endforelse
        </div>
        @error('room_ids')<p class="mt-1 text-[11px] font-bold text-rose-600">{{ $message }}</p>@enderror
    </div>

    {{-- Activities --}}
    <div>
        <p class="text-xs font-bold text-slate-700 mb-1.5">Activities</p>
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-2 max-h-48 overflow-y-auto pr-1">
            @forelse($activities as $activity)
                <label class="flex items-center gap-2 rounded-xl border border-slate-200 bg-white px-2.5 py-2 text-[11px] font-medium text-slate-700 cursor-pointer relation-opt" data-destination="{{ $activity->destination_id }}">
                    <input type="checkbox" name="activity_ids[]" value="{{ $activity->id }}" class="rounded border-slate-300 text-sky-600" {{ in_array((int) $activity->id, array_map('intval', (array) $selActivities)) ? 'checked' : '' }}>
                    <span class="truncate">{{ $activity->activity_name }}</span>
                </label>
            @empty
                <p class="text-[11px] text-slate-400">No activities yet.</p>
            @endforelse
        </div>
    </div>

    {{-- Add-ons --}}
    <div>
        <p class="text-xs font-bold text-slate-700 mb-1.5">Add-ons <span class="font-medium text-slate-400">(e.g. transfers — flights/fees stay as text below)</span></p>
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-2 max-h-48 overflow-y-auto pr-1">
            @forelse($addOns as $addOn)
                <label class="flex items-center gap-2 rounded-xl border border-slate-200 bg-white px-2.5 py-2 text-[11px] font-medium text-slate-700 cursor-pointer relation-opt" data-destination="{{ $addOn->destination_id }}">
                    <input type="checkbox" name="add_on_ids[]" value="{{ $addOn->id }}" class="rounded border-slate-300 text-sky-600" {{ in_array((int) $addOn->id, array_map('intval', (array) $selAddOns)) ? 'checked' : '' }}>
                    <span class="truncate">{{ $addOn->name }} <span class="text-slate-400">· {{ $addOn->type }}</span></span>
                </label>
            @empty
                <p class="text-[11px] text-slate-400">No add-ons yet.</p>
            @endforelse
        </div>
    </div>
</div>

<script>
(function () {
    var destSelect = document.querySelector('select[name="destination_id"]');
    var scope = document.getElementById('package-relations');
    if (!destSelect || !scope) return;

    function filterByDestination() {
        var dest = destSelect.value;
        scope.querySelectorAll('.relation-opt').forEach(function (el) {
            var show = !dest || !el.dataset.destination || el.dataset.destination === dest;
            el.style.display = show ? '' : 'none';
            if (!show) el.querySelectorAll('input[type="checkbox"]').forEach(function (cb) { cb.checked = false; });
        });
    }

    // One room max per hotel — checking a room unchecks its hotel-mates.
    scope.querySelectorAll('.room-check').forEach(function (cb) {
        cb.addEventListener('change', function () {
            if (!cb.checked) return;
            var hotelBox = cb.closest('.relation-opt');
            var hotelCheck = hotelBox ? hotelBox.querySelector('.hotel-check') : null;
            if (hotelCheck) hotelCheck.checked = true;
            scope.querySelectorAll('.room-check[data-hotel="' + cb.dataset.hotel + '"]').forEach(function (other) {
                if (other !== cb) other.checked = false;
            });
        });
    });

    destSelect.addEventListener('change', filterByDestination);
    filterByDestination();
})();
</script>
