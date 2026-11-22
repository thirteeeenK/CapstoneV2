<div class="space-y-2 text-sm font-body" x-data="{ get suggestion() { return {{ $suggestionExpression }}; } }">
    <p class="font-bold" x-text="`${suggestion.duration_days} days, ${suggestion.duration_nights ?? Math.max(0, suggestion.duration_days - 1)} nights`"></p>
    <p x-text="suggestion.start_date ? `Arrival: ${suggestion.start_date} · Departure: ${suggestion.end_date}` : 'Travel dates pending'"></p>
    <template x-if="suggestion.accommodation">
        <section class="space-y-1 rounded-lg border border-sand-200 p-3 dark:border-slate-700" x-data="{ get stay() { return suggestion.accommodation; } }">
            <h4 class="font-headline font-bold">Your stay</h4>
            <p x-text="[stay.hotel_name, stay.room_name].filter(Boolean).join(' — ')"></p>
            <p x-show="stay.capacity" x-text="`Capacity: ${stay.capacity} guests per room · ${stay.quantity} room${stay.quantity === 1 ? '' : 's'}`"></p>
            <p x-text="stay.check_in_date ? `Check-in: ${stay.check_in_date} · Check-out: ${stay.check_out_date}` : 'Stay dates pending'"></p>
            <p x-text="`${stay.nights} nights · ${(stay.pax ?? suggestion.pax) ? (stay.pax ?? suggestion.pax) + ' travelers' : 'Travelers not entered yet'}`"></p>
            <p x-text="stay.nightly_rate != null ? `₱${Number(stay.nightly_rate).toLocaleString()} per room/night · Estimated stay: ₱${Number(stay.total).toLocaleString()}` : ((stay.pax ?? suggestion.pax) ? 'Price estimate needs updating' : 'Add the number of travelers to calculate the stay estimate.')"></p>
            <p class="text-xs text-slate-600 dark:text-slate-300" x-text="stay.availability_status === 'unavailable' ? 'This room is unavailable. Choose an alternative before scheduling.' : (stay.availability_status === 'checked_available' ? 'Available when checked; confirm again before booking.' : 'Availability has not been checked for this stay.')"></p>
        </section>
    </template>
    <p x-show="suggestion.accommodation_notice" x-text="suggestion.accommodation_notice"></p>
    <template x-if="suggestion.weather">
        <section class="rounded-lg border p-3"
                 :class="{ 'border-emerald-200 bg-emerald-50 text-emerald-900 dark:border-emerald-800 dark:bg-emerald-950/50 dark:text-emerald-100': suggestion.weather.level === 'good', 'border-amber-200 bg-amber-50 text-amber-900 dark:border-amber-800 dark:bg-amber-950/50 dark:text-amber-100': suggestion.weather.level === 'okay', 'border-rose-200 bg-rose-50 text-rose-900 dark:border-rose-800 dark:bg-rose-950/50 dark:text-rose-100': suggestion.weather.level === 'poor', 'border-sand-200 dark:border-slate-700': !['good', 'okay', 'poor'].includes(suggestion.weather.level) }">
            <h4 class="font-headline font-bold" x-text="'Weather outlook: ' + suggestion.weather.label"></h4>
            <p class="text-xs capitalize" x-text="(suggestion.weather.temp != null ? Math.round(suggestion.weather.temp) + '°C · ' : '') + (suggestion.weather.description || '') + (suggestion.weather.reason ? ' — ' + suggestion.weather.reason : '')"></p>
        </section>
    </template>
    <template x-if="(suggestion.choices || []).some(choice => choice.travel && choice.travel.distance_label)">
        <section class="space-y-1 rounded-lg border border-sand-200 p-3 dark:border-slate-700">
            <h4 class="font-headline font-bold">Distance from your hotel</h4>
            <template x-for="choice in suggestion.choices.filter(item => item.travel && item.travel.distance_label)" :key="'travel:' + choice.id">
                <p class="flex justify-between gap-2 text-xs"><span x-text="choice.name"></span><span class="shrink-0 font-semibold" x-text="choice.travel.distance_label + (choice.travel.duration_minutes != null ? ' · ' + choice.travel.duration_minutes + (choice.travel.mode === 'driving' ? ' min drive' : ' min walk') : '') + (choice.travel.estimated ? ' (approx.)' : '')"></span></p>
            </template>
        </section>
    </template>
    <template x-if="suggestion.cost">
        <section class="space-y-1 rounded-lg border border-sand-200 p-3 dark:border-slate-700" x-data="{ get cost() { return suggestion.cost; }, peso(value) { return '₱' + Number(value).toLocaleString('en-PH', { minimumFractionDigits: 2, maximumFractionDigits: 2 }); } }">
            <h4 class="font-headline font-bold">Estimated cost</h4>
            <p x-show="cost.stay !== null" class="flex justify-between gap-2"><span>Stay</span><span x-text="peso(cost.stay)"></span></p>
            <template x-for="line in cost.activity_lines" :key="line.id">
                <p class="flex justify-between gap-2"><span x-text="`Day ${line.day}: ${line.name}`"></span><span x-text="line.total !== null ? peso(line.total) : line.display"></span></p>
            </template>
            <p x-show="cost.total !== null" class="flex justify-between gap-2 border-t border-sand-200 pt-1 font-bold dark:border-slate-700"><span>Total (PHP)</span><span x-text="peso(cost.total)"></span></p>
            <p class="text-xs" :class="cost.status === 'over' ? 'text-coral-700 dark:text-coral-300' : 'text-slate-600 dark:text-slate-300'" x-text="cost.summary"></p>
            <p class="text-xs text-slate-500">Catalog estimates; transport, meals and fees are not included unless listed.</p>
        </section>
    </template>
</div>
