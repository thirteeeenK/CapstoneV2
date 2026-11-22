@extends('layouts.admin')

@section('title', 'New Email Campaign | SunnyTrips Admin')

@section('content')
    <div class="max-w-7xl mx-auto pb-12 font-body">
        <div class="mb-6">
            <a href="{{ route('admin.email-campaigns.index') }}" class="text-xs font-bold text-sky-600 hover:underline flex items-center gap-1 mb-1">
                <span class="material-symbols-outlined text-sm" aria-hidden="true">arrow_back</span>
                <span>Back to Email Campaigns</span>
            </a>
            <h1 class="text-xl sm:text-2xl font-bold text-slate-900 font-headline">New email campaign</h1>
            <p class="text-xs text-slate-500 mt-1">Nothing is sent when you save. Manual campaigns send from their page after you preview and test them.</p>
        </div>

        <form action="{{ route('admin.email-campaigns.store') }}" method="POST" enctype="multipart/form-data">
            @csrf
            @include('admin.email-campaigns._form')
        </form>
    </div>
@endsection
