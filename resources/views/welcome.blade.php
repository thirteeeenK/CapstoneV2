<x-frontend.layout title="SunnyTrips Travel Services">
    <x-frontend.hero />
    <x-frontend.destinations />
    <x-frontend.experiences />
    <x-frontend.brand-story />
    <x-frontend.reviews-highlight :summary="$platformSummary" :reviews="$featuredReviews" />
</x-frontend.layout>