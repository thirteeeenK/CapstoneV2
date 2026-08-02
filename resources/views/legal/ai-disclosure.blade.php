<x-frontend.layout>
    <x-slot name="title">AI Usage Disclosure | SunnyTrips</x-slot>

    <div class="max-w-4xl mx-auto px-6 pt-32 pb-16">
        <div class="mb-10">
            <h1 class="font-headline text-3xl md:text-4xl font-bold text-slate-900 mb-3">AI Usage Disclosure</h1>
            <p class="text-slate-500 text-sm">Effective date: {{ config('legal.documents.ai_disclosure.effective_date') }}</p>
        </div>

        <div class="prose prose-slate max-w-none text-slate-600 text-sm leading-relaxed space-y-6">
            <section>
                <h2 class="text-lg font-semibold text-slate-900">1. AI-Powered Features</h2>
                <p>SunnyTrips uses artificial intelligence to help you plan trips. The AI may recommend destinations, accommodations, activities, itineraries, and respond to your travel questions.</p>
            </section>

            <section>
                <h2 class="text-lg font-semibold text-slate-900">2. Data Used by AI</h2>
                <p>To personalize your experience, the AI may process:</p>
                <ul class="list-disc pl-5 space-y-2">
                    <li>Your stated travel preferences, budget, and interests;</li>
                    <li>Your chatbot conversation history;</li>
                    <li>Your search and interaction history on SunnyTrips;</li>
                    <li>A mathematical summary (embedding) of your preferences stored in our database.</li>
                </ul>
            </section>

            <section>
                <h2 class="text-lg font-semibold text-slate-900">3. AI Limitations</h2>
                <p>AI-generated content may be inaccurate, incomplete, outdated, or not suitable for your situation. SunnyTrips does not guarantee that AI recommendations are correct, safe, or currently available.</p>
            </section>

            <section>
                <h2 class="text-lg font-semibold text-slate-900">4. Your Responsibility</h2>
                <p>Always verify important details such as prices, availability, travel advisories, weather, health requirements, and supplier terms before booking or making travel decisions.</p>
            </section>

            <section>
                <h2 class="text-lg font-semibold text-slate-900">5. Human Support</h2>
                <p>For urgent issues, booking disputes, or cancellations, please contact our human support team through the channels provided in the platform.</p>
            </section>

            <section>
                <h2 class="text-lg font-semibold text-slate-900">6. Consent</h2>
                <p>By using SunnyTrips, you acknowledge that our recommendations are AI-assisted and that your preference data may be processed to improve your experience.</p>
            </section>
        </div>
    </div>
</x-frontend.layout>
