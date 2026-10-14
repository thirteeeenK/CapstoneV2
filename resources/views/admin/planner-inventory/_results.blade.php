<div id="listings-results">
    <div class="flex flex-wrap items-center justify-between gap-2 py-4 text-xs text-slate-600 dark:text-slate-300">
        <p role="status">@if($listings->total())Showing {{ $listings->firstItem() }}–{{ $listings->lastItem() }} of {{ $listings->total() }} {{ $type === 'hotel' ? 'hotels' : 'activities' }}@else 0 {{ $type === 'hotel' ? 'hotels' : 'activities' }}@endif</p>
        <p>Open a listing to edit its schedule and location.</p>
    </div>
    <div class="divide-y divide-slate-200 border-y border-slate-200 bg-white dark:divide-slate-700 dark:border-slate-700 dark:bg-slate-900">
        @forelse($listings as $listing)
            @php
                $scheduling = $listing->scheduling ?? [];
                $prefix = $type.'-'.$listing->id;
                $restore = $errors->any() && old('listing_type') === $type && (int) old('listing_id') === $listing->id;
                $locationFields = [
                    'latitude' => ['label' => 'Latitude', 'input' => 'number', 'min' => -90, 'max' => 90, 'value' => $listing->latitude],
                    'longitude' => ['label' => 'Longitude', 'input' => 'number', 'min' => -180, 'max' => 180, 'value' => $listing->longitude],
                ];
                $scheduleFields = $type === 'hotel' ? [
                    'check_in_time' => ['label' => 'Check-in time', 'input' => 'time', 'value' => substr($listing->check_in_time ?? '', 0, 5)],
                    'check_out_time' => ['label' => 'Check-out time', 'input' => 'time', 'value' => substr($listing->check_out_time ?? '', 0, 5)],
                ] : [
                    'duration_minutes' => ['label' => 'Visit duration (minutes)', 'input' => 'number', 'min' => 1, 'max' => 1440, 'value' => $scheduling['duration_minutes'] ?? ''],
                    'environment' => ['label' => 'Environment', 'input' => 'select', 'value' => $scheduling['environment'] ?? ''],
                    'opens_at' => ['label' => 'Opens at', 'input' => 'time', 'value' => $scheduling['opens_at'] ?? ''],
                    'closes_at' => ['label' => 'Closes at', 'input' => 'time', 'value' => $scheduling['closes_at'] ?? ''],
                    'fixed_start_times' => ['label' => 'Fixed departure times', 'input' => 'text', 'value' => implode(', ', $scheduling['fixed_start_times'] ?? []), 'hint' => 'Use 24-hour times separated by commas, such as 09:00, 14:30.'],
                ];
                $groups = ['Entrance / meeting location' => $locationFields, $type === 'hotel' ? 'Hotel stay times' : 'Activity schedule' => $scheduleFields];
            @endphp
            <details x-data="{dirty: @js($restore), saving: false}" @if($restore) open @endif class="group">
                <summary class="grid min-h-16 cursor-pointer list-none gap-3 px-4 py-4 hover:bg-slate-50 focus-visible:outline focus-visible:outline-2 focus-visible:outline-inset focus-visible:outline-ocean-600 dark:hover:bg-slate-800/50 sm:grid-cols-[minmax(0,1fr)_minmax(10rem,16rem)_auto] sm:items-center [&::-webkit-details-marker]:hidden">
                    <span class="min-w-0"><span class="block break-words font-headline text-sm font-semibold text-slate-900 dark:text-slate-100">{{ $listing->{$nameColumn} }}</span><span class="mt-1 block text-xs text-slate-600 dark:text-slate-300">{{ $listing->destination?->name ?? 'Destination not set' }} · Catalog visibility: {{ $listing->is_shown ? 'Visible' : 'Hidden' }}</span></span>
                    <span class="block text-xs leading-relaxed text-slate-600 dark:text-slate-300">
                        @if($type === 'hotel')
                            <span class="block">Check-in {{ $listing->check_in_time ? substr($listing->check_in_time, 0, 5) : 'Not set' }} · Check-out {{ $listing->check_out_time ? substr($listing->check_out_time, 0, 5) : 'Not set' }}</span>
                        @else
                            <span class="block">{{ isset($scheduling['duration_minutes']) ? $scheduling['duration_minutes'].' min visit' : 'Duration not set' }}</span>
                            <span class="block">{{ !empty($scheduling['opens_at']) && !empty($scheduling['closes_at']) ? $scheduling['opens_at'].'–'.$scheduling['closes_at'] : 'Operating hours not set' }}</span>
                        @endif
                    </span>
                    <span class="inline-flex items-center gap-2 text-xs font-semibold text-ocean-700 dark:text-ocean-300"><span class="group-open:hidden">Edit details</span><span class="hidden group-open:inline">Close editor</span><span aria-hidden="true" class="material-symbols-outlined text-lg transition-transform group-open:rotate-180">expand_more</span></span>
                </summary>
                <form x-ref="editorForm" data-scheduling-form :data-dirty="dirty" @input="dirty = true" @change="dirty = true" @submit="saving = true; dirty = false" method="POST" action="{{ route('admin.planner-inventory.update', [$type, $listing->id]) }}" class="border-t border-slate-200 bg-slate-50/60 px-4 py-5 dark:border-slate-700 dark:bg-slate-950/40 sm:px-6">
                    @csrf @method('PATCH')
                    <input type="hidden" name="listing_type" value="{{ $type }}"><input type="hidden" name="listing_id" value="{{ $listing->id }}">
                    <div class="grid gap-7 lg:grid-cols-[minmax(0,1fr)_minmax(0,2fr)]">
                        @foreach($groups as $legend => $fields)
                            <fieldset class="min-w-0">
                                <legend class="mb-2 font-headline text-sm font-semibold text-slate-900 dark:text-slate-100">{{ $legend }}</legend>
                                <p class="mb-4 text-xs leading-relaxed text-slate-600 dark:text-slate-300">{{ $loop->first ? 'Use the road entrance or land meeting point. Leave both coordinates blank if unknown.' : 'Use local Philippine time. Leave unknown times blank; the planner will use its documented defaults.' }}</p>
                                <div class="grid gap-4 sm:grid-cols-2">
                                    @foreach($fields as $field => $definition)
                                        @php
                                            $value = $restore ? old($field, $definition['value']) : $definition['value'];
                                            $invalid = $restore && $errors->has($field);
                                            $inputId = $prefix.'-'.$field;
                                        @endphp
                                        <div class="min-w-0 {{ isset($definition['hint']) ? 'sm:col-span-2' : '' }}">
                                            <label for="{{ $inputId }}" class="mb-1.5 block text-xs font-semibold text-slate-700 dark:text-slate-200">{{ $definition['label'] }}</label>
                                            @if($definition['input'] === 'select')
                                                <select id="{{ $inputId }}" name="{{ $field }}" data-saved-value="{{ $definition['value'] }}" @if($invalid) aria-invalid="true" aria-describedby="{{ $inputId }}-error" @endif class="min-h-11 w-full rounded-lg border-slate-300 text-sm focus:border-ocean-600 focus:ring-ocean-600 dark:border-slate-600 dark:bg-slate-900 dark:text-slate-100"><option value="">Infer from listing</option><option value="indoor" @selected($value === 'indoor')>Indoor</option><option value="outdoor" @selected($value === 'outdoor')>Outdoor / water</option></select>
                                            @else
                                                <input id="{{ $inputId }}" name="{{ $field }}" type="{{ $definition['input'] }}" value="{{ $value }}" data-saved-value="{{ $definition['value'] }}" @if(isset($definition['min'])) min="{{ $definition['min'] }}" max="{{ $definition['max'] }}" step="{{ $field === 'latitude' || $field === 'longitude' ? 'any' : '1' }}" @endif @if($definition['input'] === 'text') maxlength="255" @endif @if($invalid) aria-invalid="true" @endif @if($invalid || isset($definition['hint'])) aria-describedby="{{ $invalid ? $inputId.'-error ' : '' }}{{ isset($definition['hint']) ? $inputId.'-hint' : '' }}" @endif class="min-h-11 w-full min-w-0 rounded-lg border-slate-300 text-sm focus:border-ocean-600 focus:ring-ocean-600 dark:border-slate-600 dark:bg-slate-900 dark:text-slate-100">
                                            @endif
                                            @if(isset($definition['hint']))<p id="{{ $inputId }}-hint" class="mt-1.5 text-xs leading-relaxed text-slate-600 dark:text-slate-300">{{ $definition['hint'] }}</p>@endif
                                            @if($invalid)<p id="{{ $inputId }}-error" class="mt-1.5 text-xs text-rose-700 dark:text-rose-300">{{ $errors->first($field) }}</p>@endif
                                        </div>
                                    @endforeach
                                    @if($type === 'activity' && !$loop->first)
                                        <label for="{{ $prefix }}-includes_lunch" class="flex min-h-11 items-center gap-2 text-sm text-slate-700 dark:text-slate-200 sm:col-span-2"><input id="{{ $prefix }}-includes_lunch" type="checkbox" name="includes_lunch" value="1" data-saved-value="{{ !empty($scheduling['includes_lunch']) ? '1' : '0' }}" @checked($restore ? old('includes_lunch', false) : ($scheduling['includes_lunch'] ?? false)) class="rounded border-slate-300 text-ocean-700 focus:ring-ocean-600">Operator includes lunch</label>
                                    @endif
                                </div>
                            </fieldset>
                        @endforeach
                    </div>
                    <div class="mt-6 flex flex-wrap items-center justify-end gap-3 border-t border-slate-200 pt-4 dark:border-slate-700"><span x-show="dirty" x-cloak class="mr-auto text-xs text-amber-800 dark:text-amber-300">Unsaved changes</span><button type="button" @click="resetEditor($refs.editorForm); dirty = false; $el.closest('details').open = false" :disabled="saving" class="min-h-11 rounded-lg px-3 py-2 text-sm font-semibold text-slate-600 hover:bg-slate-100 dark:text-slate-300 dark:hover:bg-slate-800">Cancel changes</button><button type="submit" :disabled="saving" class="min-h-11 rounded-lg bg-ocean-700 px-4 py-2 text-sm font-semibold text-white hover:bg-ocean-800 disabled:opacity-50" x-text="saving ? 'Saving…' : 'Save details'">Save details</button></div>
                </form>
            </details>
        @empty
            <div class="px-4 py-10 text-center"><h2 class="font-headline text-base font-semibold text-slate-900 dark:text-slate-100">No matching hotels or activities</h2><p class="mt-2 text-sm text-slate-600 dark:text-slate-300">{{ $search || $selectedDestinationId ? 'Try another destination or clear your search.' : 'Add listings in the catalog before configuring their schedules.' }}</p>@if($search || $selectedDestinationId)<a href="{{ route('admin.planner-inventory.index', ['type' => $type]) }}" @click.prevent="resetFilters()" class="mt-3 inline-flex min-h-11 items-center text-sm font-semibold text-ocean-700 dark:text-ocean-300">Clear filters</a>@endif</div>
        @endforelse
    </div>
    <div class="pt-5">{{ $listings->links() }}</div>
</div>
