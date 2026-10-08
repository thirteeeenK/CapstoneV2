<x-frontend.layout>
    <x-slot name="title">{{ $document->title }} | SunnyTrips</x-slot>

    <div class="max-w-4xl mx-auto px-6 pt-32 pb-16">
        <div class="mb-10">
            <h1 class="font-headline text-3xl md:text-4xl font-bold text-slate-900 dark:text-slate-100 mb-3">{{ $document->title }}</h1>
            <p class="text-slate-500 dark:text-slate-400 text-sm">
                Effective date: {{ config('legal.documents.' . $document->key . '.effective_date') }}
                &middot; Version {{ $document->version }}
            </p>
        </div>

        <div class="prose prose-slate dark:prose-invert max-w-none text-slate-600 dark:text-slate-400 text-sm leading-relaxed space-y-6">
            @foreach ($document->content ?? [] as $section)
                <section>
                    <h2 class="text-lg font-semibold text-slate-900 dark:text-slate-100">{{ $section['heading'] ?? '' }}</h2>
                    <p>{!! $section['body'] ?? '' !!}</p>
                </section>
            @endforeach
        </div>
    </div>
</x-frontend.layout>
