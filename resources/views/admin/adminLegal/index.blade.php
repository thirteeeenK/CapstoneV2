@extends('layouts.admin')

@section('title', 'Legal Documents Management | SunnyTrips Admin')

@section('content')
    <div class="pb-12">
        <div class="mb-6 flex flex-col sm:flex-row sm:items-center justify-between gap-4">
            <div>
                <h1 class="text-xl font-bold text-slate-900">Legal Documents</h1>
                <p class="text-xs text-slate-500 mt-1">Edit the Terms and Conditions, Privacy Policy, and AI Usage Disclosure that are displayed to users.</p>
            </div>
        </div>

        {{-- Success Banner --}}
        @if (session('success'))
            <div class="bg-emerald-50 border border-emerald-200/80 text-emerald-800 text-sm px-4 py-3 rounded-md mb-6 flex items-center gap-2">
                <span class="material-symbols-outlined text-[18px]">check_circle</span>
                {{ session('success') }}
            </div>
        @endif

        <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
            @foreach ($documents as $document)
                @php
                    $descriptions = [
                        'terms'         => 'User agreement including the binding arbitration clause.',
                        'privacy'       => 'How we collect, use, and protect user personal data.',
                        'ai_disclosure' => 'Disclosure about AI-assisted recommendations and data usage.',
                    ];
                    $icons = [
                        'terms' => 'description',
                        'privacy' => 'lock',
                        'ai_disclosure' => 'psychology',
                    ];
                @endphp
                <div class="bg-white border border-slate-200 rounded-lg shadow-sm p-6 flex flex-col gap-4">
                    <div class="flex items-start gap-4">
                        <div class="w-11 h-11 rounded-lg bg-ocean-50 text-ocean-600 flex items-center justify-center shrink-0">
                            <span class="material-symbols-outlined text-[22px]">{{ $icons[$document->key] ?? 'article' }}</span>
                        </div>
                        <div>
                            <h2 class="text-sm font-bold text-slate-900">{{ $document->title }}</h2>
                            <p class="text-xs text-slate-500 mt-0.5">{{ $descriptions[$document->key] ?? '' }}</p>
                        </div>
                    </div>

                    <div class="flex flex-wrap items-center gap-x-4 gap-y-2 text-[11px] text-slate-500 border-t border-slate-100 pt-4">
                        <span class="inline-flex items-center gap-1">
                            <span class="material-symbols-outlined text-[14px] text-slate-400">label</span>
                            Version: <strong class="text-slate-700">{{ $document->version }}</strong>
                        </span>
                        <span class="inline-flex items-center gap-1">
                            <span class="material-symbols-outlined text-[14px] text-slate-400">subject</span>
                            {{ count($document->content ?? []) }} section(s)
                        </span>
                        <span class="inline-flex items-center gap-1">
                            <span class="material-symbols-outlined text-[14px] text-slate-400">schedule</span>
                            {{ $document->updated_at ? $document->updated_at->format('M d, Y') : '—' }}
                        </span>
                    </div>

                    <a href="{{ route('admin.legal.edit', $document->key) }}"
                       class="inline-flex items-center justify-center gap-2 w-full px-4 py-2.5 rounded-md bg-ocean-600 text-white text-xs font-semibold hover:bg-ocean-700 transition-colors">
                        <span class="material-symbols-outlined text-[16px]">edit</span>
                        Edit {{ $document->title }}
                    </a>
                </div>
            @endforeach
        </div>
    </div>
@endsection