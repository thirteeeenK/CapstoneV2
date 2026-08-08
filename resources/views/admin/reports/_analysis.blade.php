<div id="analysis-panel">
    @if (!$analysis)
        <div class="rounded-2xl border border-dashed border-slate-200 bg-slate-50/60 p-6 text-center">
            <span class="material-symbols-outlined text-[32px] text-slate-300">auto_awesome</span>
            <p class="text-sm font-bold text-slate-500 mt-2">No analysis yet</p>
            <p class="text-xs text-slate-400 mt-1 max-w-md mx-auto">Run "Analyze with AI" to get an executive summary, data-backed insights, anomalies, and recommendations for the current report filters.</p>
        </div>
    @else
        <div class="space-y-4">
            <div class="bg-emerald-50 border border-emerald-200/80 rounded-2xl p-4">
                <p class="text-[10px] font-extrabold uppercase tracking-wider text-emerald-700 mb-1 flex items-center gap-1">
                    <span class="material-symbols-outlined text-[14px]">summarize</span>
                    Executive Summary
                </p>
                <p class="text-xs text-emerald-900 leading-relaxed">{{ $analysis['executive_summary'] }}</p>
            </div>

            @if(!empty($analysis['insights']))
                <div>
                    <p class="text-[10px] font-extrabold uppercase tracking-wider text-slate-400 mb-2 flex items-center gap-1">
                        <span class="material-symbols-outlined text-[14px]">insights</span>
                        Insights
                    </p>
                    <ul class="space-y-1.5">
                        @foreach($analysis['insights'] as $insight)
                            <li class="flex items-start gap-2 text-xs text-slate-700 bg-sky-50/70 border border-sky-100 rounded-xl px-3 py-2">
                                <span class="material-symbols-outlined text-[15px] text-sky-600 mt-0.5">lightbulb</span>
                                <span>{{ $insight }}</span>
                            </li>
                        @endforeach
                    </ul>
                </div>
            @endif

            @if(!empty($analysis['anomalies']))
                <div>
                    <p class="text-[10px] font-extrabold uppercase tracking-wider text-slate-400 mb-2 flex items-center gap-1">
                        <span class="material-symbols-outlined text-[14px]">warning</span>
                        Anomalies & Risks
                    </p>
                    <ul class="space-y-1.5">
                        @foreach($analysis['anomalies'] as $anomaly)
                            <li class="flex items-start gap-2 text-xs text-amber-900 bg-amber-50 border border-amber-200 rounded-xl px-3 py-2">
                                <span class="material-symbols-outlined text-[15px] text-amber-600 mt-0.5">error</span>
                                <span>{{ $anomaly }}</span>
                            </li>
                        @endforeach
                    </ul>
                </div>
            @endif

            @if(!empty($analysis['recommendations']))
                <div>
                    <p class="text-[10px] font-extrabold uppercase tracking-wider text-slate-400 mb-2 flex items-center gap-1">
                        <span class="material-symbols-outlined text-[14px]">checklist</span>
                        Recommendations
                    </p>
                    <ul class="space-y-1.5">
                        @foreach($analysis['recommendations'] as $rec)
                            <li class="flex items-start gap-2 text-xs text-slate-800 bg-slate-100/80 border border-slate-200 rounded-xl px-3 py-2">
                                <span class="material-symbols-outlined text-[15px] text-slate-600 mt-0.5">task_alt</span>
                                <span>{{ $rec }}</span>
                            </li>
                        @endforeach
                    </ul>
                </div>
            @endif
        </div>
    @endif
</div>
