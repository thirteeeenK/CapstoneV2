{{-- Individual inclusion rows (Flights / Fees / Other as text). Expects $inclusions array. --}}
<div class="sm:col-span-2">
    <label class="block text-xs font-bold uppercase tracking-wider text-slate-700 mb-1.5">Flights / Fees / Other Inclusions (one per row)</label>
    <div id="inclusions-list" class="space-y-2">
        @forelse(($inclusions ?? []) as $inc)
            <div class="inclusion-row flex items-center gap-2">
                <input type="text" name="inclusions[]" value="{{ $inc }}" placeholder="e.g. Roundtrip Airfare" class="flex-1 px-4 py-2.5 rounded-xl border border-slate-200 text-xs font-medium text-slate-900 focus:border-sky-500 focus:ring-2 focus:ring-sky-500/20">
                <button type="button" onclick="this.closest('.inclusion-row').remove()" title="Remove" class="shrink-0 w-9 h-9 rounded-xl border border-slate-200 text-slate-400 hover:text-red-600 hover:border-red-200 hover:bg-red-50 font-bold transition cursor-pointer">×</button>
            </div>
        @empty
            <div class="inclusion-row flex items-center gap-2">
                <input type="text" name="inclusions[]" value="" placeholder="e.g. Roundtrip Airfare" class="flex-1 px-4 py-2.5 rounded-xl border border-slate-200 text-xs font-medium text-slate-900 focus:border-sky-500 focus:ring-2 focus:ring-sky-500/20">
                <button type="button" onclick="this.closest('.inclusion-row').remove()" title="Remove" class="shrink-0 w-9 h-9 rounded-xl border border-slate-200 text-slate-400 hover:text-red-600 hover:border-red-200 hover:bg-red-50 font-bold transition cursor-pointer">×</button>
            </div>
        @endforelse
    </div>
    <button type="button" id="add-inclusion-btn" class="mt-2 px-4 py-2 rounded-xl border border-dashed border-sky-300 text-sky-700 font-bold text-xs hover:bg-sky-50 transition cursor-pointer">＋ Add inclusion</button>
</div>
<script>
(function () {
    var btn = document.getElementById('add-inclusion-btn');
    if (!btn || btn.dataset.bound) return;
    btn.dataset.bound = '1';
    btn.addEventListener('click', function () {
        var list = document.getElementById('inclusions-list');
        var row = document.createElement('div');
        row.className = 'inclusion-row flex items-center gap-2';
        row.innerHTML = '<input type="text" name="inclusions[]" value="" placeholder="e.g. Terminal Fee" class="flex-1 px-4 py-2.5 rounded-xl border border-slate-200 text-xs font-medium text-slate-900 focus:border-sky-500 focus:ring-2 focus:ring-sky-500/20">'
            + '<button type="button" onclick="this.closest(\'.inclusion-row\').remove()" title="Remove" class="shrink-0 w-9 h-9 rounded-xl border border-slate-200 text-slate-400 hover:text-red-600 hover:border-red-200 hover:bg-red-50 font-bold transition cursor-pointer">×</button>';
        list.appendChild(row);
        var input = row.querySelector('input');
        if (input) input.focus();
    });
})();
</script>
