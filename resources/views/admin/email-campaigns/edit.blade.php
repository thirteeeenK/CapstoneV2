@extends('layouts.admin')

@section('title', 'Edit Email Campaign | SunnyTrips Admin')

@section('content')
    <div class="max-w-7xl mx-auto pb-12 font-body">
        <div class="mb-6">
            <a href="{{ route('admin.email-campaigns.show', $campaign) }}" class="text-xs font-bold text-sky-600 hover:underline flex items-center gap-1 mb-1">
                <span class="material-symbols-outlined text-sm" aria-hidden="true">arrow_back</span>
                <span>Back to {{ $campaign->name }}</span>
            </a>
            <h1 class="text-xl sm:text-2xl font-bold text-slate-900 font-headline">Edit {{ $campaign->name }}</h1>
            <p class="text-xs text-slate-500 mt-1">{{ $campaign->isAutomatic() ? 'Changes apply to the next time this campaign sends.' : 'You can edit a campaign until it starts sending.' }}</p>
        </div>

        <form action="{{ route('admin.email-campaigns.update', $campaign) }}" method="POST" enctype="multipart/form-data">
            @csrf
            @method('PUT')
            @include('admin.email-campaigns._form')
        </form>
    </div>
@endsection
