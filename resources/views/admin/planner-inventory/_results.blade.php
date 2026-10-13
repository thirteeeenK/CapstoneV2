<div id="listings-results" class="space-y-4">
    @forelse($listings as $listing)
        @php($scheduling = $listing->scheduling ?? [])
        <details class="rounded-lg border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-900 p-4">
            <summary class="cursor-pointer min-h-11 py-2 font-bold">{{ $listing->{$nameColumn} }} <span class="font-normal text-sm text-slate-500">· {{ $listing->destination?->name }} · {{ $listing->is_shown ? 'Visible' : 'Hidden' }}</span></summary>
            <form method="POST" action="{{ route('admin.planner-inventory.update', [$type, $listing->id]) }}" class="mt-4 grid sm:grid-cols-2 lg:grid-cols-3 gap-4">@csrf @method('PATCH')
                @foreach(['latitude' => 'Entrance / meeting latitude', 'longitude' => 'Entrance / meeting longitude'] as $field => $label)<div><label for="{{ $type }}-{{ $listing->id }}-{{ $field }}" class="block text-sm mb-1">{{ $label }}</label><input id="{{ $type }}-{{ $listing->id }}-{{ $field }}" name="{{ $field }}" type="number" step="any" value="{{ $listing->{$field} }}" class="w-full rounded-lg border-slate-300 dark:bg-slate-800"></div>@endforeach
                <label class="flex items-center gap-2 text-sm"><input name="is_shown" type="checkbox" value="1" @checked($listing->is_shown)> Show listing</label>
                @if($type === 'hotel')
                    @foreach(['check_in_time' => 'Check-in time', 'check_out_time' => 'Check-out time'] as $field => $label)<div><label for="{{ $type }}-{{ $listing->id }}-{{ $field }}" class="block text-sm mb-1">{{ $label }}</label><input id="{{ $type }}-{{ $listing->id }}-{{ $field }}" name="{{ $field }}" type="time" value="{{ substr($listing->{$field} ?? '', 0, 5) }}" class="w-full rounded-lg border-slate-300 dark:bg-slate-800"></div>@endforeach
                @else
                    <div><label for="duration-{{ $listing->id }}" class="block text-sm mb-1">Visit duration (minutes)</label><input id="duration-{{ $listing->id }}" name="duration_minutes" type="number" min="1" max="1440" value="{{ $scheduling['duration_minutes'] ?? '' }}" class="w-full rounded-lg border-slate-300 dark:bg-slate-800"></div>
                    <div><label for="environment-{{ $listing->id }}" class="block text-sm mb-1">Environment</label><select id="environment-{{ $listing->id }}" name="environment" class="w-full rounded-lg border-slate-300 dark:bg-slate-800"><option value="">Infer from listing</option><option value="indoor" @selected(($scheduling['environment'] ?? '') === 'indoor')>Indoor</option><option value="outdoor" @selected(($scheduling['environment'] ?? '') === 'outdoor')>Outdoor / water</option></select></div>
                    @foreach(['opens_at' => 'Opens at', 'closes_at' => 'Closes at'] as $field => $label)<div><label for="{{ $field }}-{{ $listing->id }}" class="block text-sm mb-1">{{ $label }}</label><input id="{{ $field }}-{{ $listing->id }}" name="{{ $field }}" type="time" value="{{ $scheduling[$field] ?? '' }}" class="w-full rounded-lg border-slate-300 dark:bg-slate-800"></div>@endforeach
                    <div><label for="departures-{{ $listing->id }}" class="block text-sm mb-1">Fixed starts (e.g. 09:00, 14:30)</label><input id="departures-{{ $listing->id }}" name="fixed_start_times" value="{{ implode(', ', $scheduling['fixed_start_times'] ?? []) }}" maxlength="255" class="w-full rounded-lg border-slate-300 dark:bg-slate-800"></div>
                    <label class="flex items-center gap-2 text-sm"><input type="checkbox" name="includes_lunch" value="1" @checked($scheduling['includes_lunch'] ?? false)> Operator includes lunch</label>
                @endif
                <div class="sm:col-span-2 lg:col-span-3"><button type="submit" class="min-h-11 rounded-lg bg-ocean-700 text-white px-4 py-3 font-bold">Save verified details</button></div>
            </form>
        </details>
    @empty<p class="text-sm text-slate-500">No matching inventory.</p>@endforelse
    {{ $listings->links() }}
</div>
