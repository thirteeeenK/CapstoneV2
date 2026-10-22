@extends('layouts.admin')

@section('title', 'Edit Travel Guidance | SunnyTrips Admin')

@section('content')
    <div class="max-w-4xl mx-auto pb-12 font-body">
        <div class="mb-6">
            <a href="{{ route('admin.knowledge.index') }}" class="text-xs font-bold text-sky-600 hover:underline flex items-center gap-1 mb-1">
                <span class="material-symbols-outlined text-sm" aria-hidden="true">arrow_back</span>
                <span>Back to Travel Guidance</span>
            </a>
            <h1 class="text-xl sm:text-2xl font-bold text-slate-900 font-headline">Edit Travel Guidance</h1>
        </div>

        <form action="{{ route('admin.knowledge.update', $article) }}" method="POST" class="bg-white rounded-3xl p-6 sm:p-8 border border-slate-200/80 shadow-xs space-y-6">
            @csrf
            @method('PUT')
            @include('admin.knowledge._form')

            <div class="flex items-center justify-end gap-3 pt-2 border-t border-slate-100">
                <a href="{{ route('admin.knowledge.index') }}" class="px-5 py-2.5 rounded-xl border border-slate-200 text-xs font-bold text-slate-600 hover:bg-slate-50 transition">Cancel</a>
                <button type="submit" class="px-5 py-2.5 rounded-xl bg-gradient-to-r from-sky-600 to-sky-700 hover:from-sky-500 hover:to-sky-600 text-white font-bold text-xs shadow-md shadow-sky-600/20 transition">Save Changes</button>
            </div>
        </form>
    </div>
@endsection
