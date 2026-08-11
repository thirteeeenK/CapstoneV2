{{-- Live-search results partial: inventory nav tabs (with live counts) and active tab table.
    The filter controls (location/visibility tabs + search) live in the wrapper so the input never loses focus on AJAX swaps.
    Rendered standalone for AJAX requests (X-Requested-With) and inline for full page loads. --}}

<div id="listings-results">
    <!-- Inventory Navigation Tabs -->
    <div class="mb-6 border-b border-slate-200 bg-white px-4 pt-3 rounded-t-xl">
        <nav class="flex space-x-6 overflow-x-auto">
            <a href="{{ route('admin.inventory.index', array_filter(['tab' => 'hotels', 'destination_id' => $selectedDestinationId, 'visibility' => $visibility, 'search' => $search])) }}"
                @click.prevent="setTab('hotels')"
                class="pb-3 border-b-2 font-medium text-sm transition-colors flex items-center gap-2 shrink-0 {{ $activeTab === 'hotels' ? 'border-ocean-600 text-ocean-600 font-semibold' : 'border-transparent text-slate-500 hover:text-slate-700' }}">
                <span class="material-symbols-outlined text-[18px]">house</span>
                Hotels Inventory ({{ $hotels->count() }})
            </a>
            <a href="{{ route('admin.inventory.index', array_filter(['tab' => 'rooms', 'destination_id' => $selectedDestinationId, 'visibility' => $visibility, 'search' => $search])) }}"
                @click.prevent="setTab('rooms')"
                class="pb-3 border-b-2 font-medium text-sm transition-colors flex items-center gap-2 shrink-0 {{ $activeTab === 'rooms' ? 'border-ocean-600 text-ocean-600 font-semibold' : 'border-transparent text-slate-500 hover:text-slate-700' }}">
                <span class="material-symbols-outlined text-[18px]">bed</span>
                Room Types Inventory ({{ $rooms->count() }})
            </a>
            <a href="{{ route('admin.inventory.index', array_filter(['tab' => 'activities', 'destination_id' => $selectedDestinationId, 'visibility' => $visibility, 'search' => $search])) }}"
                @click.prevent="setTab('activities')"
                class="pb-3 border-b-2 font-medium text-sm transition-colors flex items-center gap-2 shrink-0 {{ $activeTab === 'activities' ? 'border-ocean-600 text-ocean-600 font-semibold' : 'border-transparent text-slate-500 hover:text-slate-700' }}">
                <span class="material-symbols-outlined text-[18px]">explore</span>
                Activities Inventory ({{ $activities->count() }})
            </a>
            <a href="{{ route('admin.inventory.index', array_filter(['tab' => 'addons', 'destination_id' => $selectedDestinationId, 'visibility' => $visibility, 'search' => $search])) }}"
                @click.prevent="setTab('addons')"
                class="pb-3 border-b-2 font-medium text-sm transition-colors flex items-center gap-2 shrink-0 {{ $activeTab === 'addons' ? 'border-ocean-600 text-ocean-600 font-semibold' : 'border-transparent text-slate-500 hover:text-slate-700' }}">
                <span class="material-symbols-outlined text-[18px]">extension</span>
                Transfers & Add-ons ({{ $addons->count() }})
            </a>
        </nav>
    </div>

    <!-- TAB CONTENT -->
    <div class="bg-white rounded-b-xl border border-slate-200 shadow-sm overflow-hidden">
        @if ($activeTab === 'hotels')
            {{-- HOTELS TABLE --}}
            <div class="overflow-x-auto">
                <table class="w-full text-left border-collapse">
                    <thead>
                        <tr
                            class="bg-slate-50 border-b border-slate-200 text-[11px] font-bold text-slate-500 uppercase tracking-wider">
                            <th class="py-3 px-4">Hotel Name</th>
                            <th class="py-3 px-4">Destination</th>
                            <th class="py-3 px-4">Category / Type</th>
                            <th class="py-3 px-4">Address</th>
                            <th class="py-3 px-4 text-center">Public Visibility</th>
                            <th class="py-3 px-4 text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 text-xs text-slate-700">
                        @forelse ($hotels as $hotel)
                            <tr class="hover:bg-slate-50/80 transition-colors">
                                <td class="py-3 px-4 font-semibold text-slate-900">
                                    <a href="{{ route('hotels.show', $hotel->id) }}" target="_blank" class="hover:text-ocean-600 hover:underline inline-flex items-center gap-1 group/link">
                                        <span>{{ $hotel->hotel_name }}</span>
                                        <span class="material-symbols-outlined text-[14px] opacity-0 group-hover/link:opacity-100 text-ocean-500 transition-opacity">open_in_new</span>
                                    </a>
                                </td>
                                <td class="py-3 px-4">
                                    <span
                                        class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[11px] font-medium bg-slate-100 text-slate-700">
                                        <span class="material-symbols-outlined text-[14px] text-slate-400">place</span>
                                        {{ $hotel->destination->name ?? 'N/A' }}
                                    </span>
                                </td>
                                <td class="py-3 px-4 capitalize text-slate-600">
                                    {{ ucwords(str_replace('-', ' ', $hotel->type ?? '—')) }}
                                </td>
                                <td class="py-3 px-4 text-slate-500 truncate max-w-[200px]">
                                    {{ $hotel->specific_address }}
                                </td>
                                <td class="py-3 px-4 text-center">
                                    <form action="{{ route('admin.inventory.toggle-visibility') }}" method="POST"
                                        class="inline-block">
                                        @csrf
                                        <input type="hidden" name="type" value="hotel">
                                        <input type="hidden" name="id" value="{{ $hotel->id }}">
                                        <input type="hidden" name="is_shown" value="{{ $hotel->is_shown ? '0' : '1' }}">
                                        <button type="submit"
                                            class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-semibold transition-all cursor-pointer shadow-2xs {{ $hotel->is_shown ? 'bg-emerald-100 text-emerald-800 border border-emerald-300 hover:bg-emerald-200' : 'bg-amber-100 text-amber-800 border border-amber-300 hover:bg-amber-200' }}">
                                            <span
                                                class="w-2 h-2 rounded-full {{ $hotel->is_shown ? 'bg-emerald-500' : 'bg-amber-500' }}"></span>
                                            {{ $hotel->is_shown ? 'Visible to Users' : 'Hidden from Users' }}
                                        </button>
                                    </form>
                                </td>
                                <td class="py-3 px-4 text-right space-x-2">
                                    <a href="{{ route('hotels.show', $hotel->id) }}" target="_blank"
                                        class="text-emerald-700 hover:text-emerald-900 font-semibold inline-flex items-center gap-1" title="Preview user-facing hotel page">
                                        <span class="material-symbols-outlined text-[15px]">visibility</span>
                                        Preview
                                    </a>
                                    <a href="{{ route('edit-view', $hotel->id) }}"
                                        class="text-ocean-600 hover:text-ocean-800 font-semibold inline-flex items-center gap-1">
                                        <span class="material-symbols-outlined text-[15px]">edit</span>
                                        Edit
                                    </a>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="py-8 text-center text-slate-400 text-xs">No hotels matching the current
                                    filters.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

        @elseif ($activeTab === 'rooms')
            {{-- ROOMS TABLE --}}
            <div class="overflow-x-auto">
                <table class="w-full text-left border-collapse">
                    <thead>
                        <tr
                            class="bg-slate-50 border-b border-slate-200 text-[11px] font-bold text-slate-500 uppercase tracking-wider">
                            <th class="py-3 px-4">Room Name</th>
                            <th class="py-3 px-4">Hotel</th>
                            <th class="py-3 px-4">Base Rate</th>
                            <th class="py-3 px-4 text-center">Total Inventory</th>
                            <th class="py-3 px-4 text-center">Public Visibility</th>
                            <th class="py-3 px-4 text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 text-xs text-slate-700">
                        @forelse ($rooms as $room)
                            <tr class="hover:bg-slate-50/80 transition-colors">
                                <td class="py-3 px-4 font-semibold text-slate-900">
                                    {{ $room->room_name }}
                                </td>
                                <td class="py-3 px-4 font-medium text-slate-700">
                                    {{ $room->hotel->hotel_name ?? 'N/A' }}
                                </td>
                                <td class="py-3 px-4 font-bold text-emerald-700">
                                    ₱{{ number_format($room->base_price, 2) }}
                                </td>
                                <td class="py-3 px-4 text-center font-medium">
                                    {{ $room->total_rooms }} rooms
                                </td>
                                <td class="py-3 px-4 text-center">
                                    <form action="{{ route('admin.inventory.toggle-visibility') }}" method="POST"
                                        class="inline-block">
                                        @csrf
                                        <input type="hidden" name="type" value="room">
                                        <input type="hidden" name="id" value="{{ $room->id }}">
                                        <input type="hidden" name="is_shown" value="{{ $room->is_shown ? '0' : '1' }}">
                                        <button type="submit"
                                            class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-semibold transition-all cursor-pointer shadow-2xs {{ $room->is_shown ? 'bg-emerald-100 text-emerald-800 border border-emerald-300 hover:bg-emerald-200' : 'bg-amber-100 text-amber-800 border border-amber-300 hover:bg-amber-200' }}">
                                            <span
                                                class="w-2 h-2 rounded-full {{ $room->is_shown ? 'bg-emerald-500' : 'bg-amber-500' }}"></span>
                                            {{ $room->is_shown ? 'Visible to Users' : 'Hidden from Users' }}
                                        </button>
                                    </form>
                                </td>
                                <td class="py-3 px-4 text-right space-x-2">
                                    <a href="{{ route('edit-room', $room->id) }}"
                                        class="text-ocean-600 hover:text-ocean-800 font-semibold inline-flex items-center gap-1">
                                        <span class="material-symbols-outlined text-[15px]">edit</span>
                                        Edit Room
                                    </a>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="py-8 text-center text-slate-400 text-xs">No rooms matching the current
                                    filters.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

        @elseif ($activeTab === 'activities')
            {{-- ACTIVITIES TABLE --}}
            <div class="overflow-x-auto">
                <table class="w-full text-left border-collapse">
                    <thead>
                        <tr
                            class="bg-slate-50 border-b border-slate-200 text-[11px] font-bold text-slate-500 uppercase tracking-wider">
                            <th class="py-3 px-4">Activity Name</th>
                            <th class="py-3 px-4">Category</th>
                            <th class="py-3 px-4">Destination</th>
                            <th class="py-3 px-4">Rate</th>
                            <th class="py-3 px-4 text-center">Public Visibility</th>
                            <th class="py-3 px-4 text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 text-xs text-slate-700">
                        @forelse ($activities as $activity)
                            <tr class="hover:bg-slate-50/80 transition-colors">
                                <td class="py-3 px-4 font-semibold text-slate-900">
                                    {{ $activity->activity_name }}
                                </td>
                                <td class="py-3 px-4">
                                    <span
                                        class="inline-flex items-center px-2 py-0.5 rounded text-[11px] font-medium bg-ocean-50 text-ocean-700">
                                        {{ $activity->category }}
                                    </span>
                                </td>
                                <td class="py-3 px-4 font-medium text-slate-700">
                                    {{ $activity->destination->name ?? 'N/A' }}
                                </td>
                                <td class="py-3 px-4 font-bold text-emerald-700">
                                    {{ $activity->rate }}
                                </td>
                                <td class="py-3 px-4 text-center">
                                    <form action="{{ route('admin.inventory.toggle-visibility') }}" method="POST"
                                        class="inline-block">
                                        @csrf
                                        <input type="hidden" name="type" value="activity">
                                        <input type="hidden" name="id" value="{{ $activity->id }}">
                                        <input type="hidden" name="is_shown" value="{{ $activity->is_shown ? '0' : '1' }}">
                                        <button type="submit"
                                            class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-semibold transition-all cursor-pointer shadow-2xs {{ $activity->is_shown ? 'bg-emerald-100 text-emerald-800 border border-emerald-300 hover:bg-emerald-200' : 'bg-amber-100 text-amber-800 border border-amber-300 hover:bg-amber-200' }}">
                                            <span
                                                class="w-2 h-2 rounded-full {{ $activity->is_shown ? 'bg-emerald-500' : 'bg-amber-500' }}"></span>
                                            {{ $activity->is_shown ? 'Visible to Users' : 'Hidden from Users' }}
                                        </button>
                                    </form>
                                </td>
                                <td class="py-3 px-4 text-right space-x-2">
                                    <a href="{{ route('admin.activities.edit', $activity->id) }}"
                                        class="text-ocean-600 hover:text-ocean-800 font-semibold inline-flex items-center gap-1">
                                        <span class="material-symbols-outlined text-[15px]">edit</span>
                                        Edit Activity
                                    </a>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="py-8 text-center text-slate-400 text-xs">No activities matching the
                                    current filters.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

        @else
            {{-- ADD-ONS & TRANSFERS TABLE --}}
            <div class="overflow-x-auto">
                <table class="w-full text-left border-collapse">
                    <thead>
                        <tr
                            class="bg-slate-50 border-b border-slate-200 text-[11px] font-bold text-slate-500 uppercase tracking-wider">
                            <th class="py-3 px-4">Service Name</th>
                            <th class="py-3 px-4">Type</th>
                            <th class="py-3 px-4">Destination</th>
                            <th class="py-3 px-4">Details / Inclusions</th>
                            <th class="py-3 px-4 text-center">Public Visibility</th>
                            <th class="py-3 px-4 text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 text-xs text-slate-700">
                        @forelse ($addons as $addon)
                            <tr class="hover:bg-slate-50/80 transition-colors">
                                <td class="py-3 px-4 font-semibold text-slate-900">
                                    {{ $addon->name }}
                                </td>
                                <td class="py-3 px-4">
                                    <span
                                        class="inline-flex items-center px-2 py-0.5 rounded text-[11px] font-medium bg-indigo-50 text-indigo-700">
                                        {{ $addon->type }}
                                    </span>
                                </td>
                                <td class="py-3 px-4 font-medium text-slate-700">
                                    {{ $addon->destination->name ?? 'N/A' }}
                                </td>
                                <td class="py-3 px-4 text-slate-600 truncate max-w-[220px]">
                                    @if (!empty($addon->inclusions) && is_array($addon->inclusions))
                                        {{ implode(', ', $addon->inclusions) }}
                                    @elseif (!empty($addon->description))
                                        {{ Str::limit($addon->description, 40) }}
                                    @else
                                        —
                                    @endif
                                </td>
                                <td class="py-3 px-4 text-center">
                                    <form action="{{ route('admin.inventory.toggle-visibility') }}" method="POST"
                                        class="inline-block">
                                        @csrf
                                        <input type="hidden" name="type" value="addon">
                                        <input type="hidden" name="id" value="{{ $addon->id }}">
                                        <input type="hidden" name="is_shown" value="{{ $addon->is_shown ? '0' : '1' }}">
                                        <button type="submit"
                                            class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-semibold transition-all cursor-pointer shadow-2xs {{ $addon->is_shown ? 'bg-emerald-100 text-emerald-800 border border-emerald-300 hover:bg-emerald-200' : 'bg-amber-100 text-amber-800 border border-amber-300 hover:bg-amber-200' }}">
                                            <span
                                                class="w-2 h-2 rounded-full {{ $addon->is_shown ? 'bg-emerald-500' : 'bg-amber-500' }}"></span>
                                            {{ $addon->is_shown ? 'Visible to Users' : 'Hidden from Users' }}
                                        </button>
                                    </form>
                                </td>
                                <td class="py-3 px-4 text-right space-x-2">
                                    <a href="{{ route('admin.addons.edit', $addon->id) }}"
                                        class="text-ocean-600 hover:text-ocean-800 font-semibold inline-flex items-center gap-1">
                                        <span class="material-symbols-outlined text-[15px]">edit</span>
                                        Edit Add-on
                                    </a>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="py-8 text-center text-slate-400 text-xs">No add-ons or transfers matching
                                    the current filters.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        @endif
    </div>
</div>