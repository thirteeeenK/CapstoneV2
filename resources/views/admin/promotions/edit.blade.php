@extends('layouts.admin')

@section('title', 'Edit Lucky Campaign | SunnyTrips Admin')

@section('content')
    <div class="max-w-4xl mx-auto pb-12 font-body">
        <div class="mb-6">
            <a href="{{ route('admin.promotions.index') }}" class="text-xs font-bold text-sky-600 hover:underline flex items-center gap-1 mb-1">
                <span class="material-symbols-outlined text-sm">arrow_back</span>
                <span>Back to Lucky Promotions</span>
            </a>
            <h1 class="text-xl sm:text-2xl font-bold text-slate-900 font-headline">Edit {{ $promotion->name }}</h1>
            <p class="text-xs text-slate-500 mt-1">Changes apply to codes issued from now on. Codes already issued, and bookings that reserved them, keep the terms recorded at issuance.</p>
        </div>

        <form action="{{ route('admin.promotions.update', $promotion) }}" method="POST" class="bg-white rounded-3xl p-6 sm:p-8 border border-slate-200/80 shadow-xs space-y-6">
            @csrf
            @method('PUT')
            @include('admin.promotions._form')
            <div class="flex justify-end">
                <button type="submit" class="min-h-11 px-6 py-2.5 rounded-xl bg-sky-600 hover:bg-sky-700 text-white font-bold text-sm cursor-pointer">Save changes</button>
            </div>
        </form>
    </div>
@endsection
