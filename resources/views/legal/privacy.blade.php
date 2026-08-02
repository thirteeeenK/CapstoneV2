<x-frontend.layout>
    <x-slot name="title">Privacy Policy | SunnyTrips</x-slot>

    <div class="max-w-4xl mx-auto px-6 pt-32 pb-16">
        <div class="mb-10">
            <h1 class="font-headline text-3xl md:text-4xl font-bold text-slate-900 mb-3">Privacy Policy</h1>
            <p class="text-slate-500 text-sm">Effective date: {{ config('legal.documents.privacy.effective_date') }}</p>
        </div>

        <div class="prose prose-slate max-w-none text-slate-600 text-sm leading-relaxed space-y-6">
            <section>
                <h2 class="text-lg font-semibold text-slate-900">1. Information We Collect</h2>
                <p>We collect personal information that you provide when creating an account, booking travel, or interacting with our AI features. This includes your name, email address, phone number, address, account credentials, travel preferences, chatbot conversations, and a mathematical representation (embedding) of your preferences used to personalize recommendations.</p>
            </section>

            <section>
                <h2 class="text-lg font-semibold text-slate-900">2. How We Use Your Information</h2>
                <p>We use your information to:</p>
                <ul class="list-disc pl-5 space-y-2">
                    <li>Provide, operate, and improve SunnyTrips services;</li>
                    <li>Generate AI-assisted travel recommendations and itineraries;</li>
                    <li>Process bookings and communicate with travel suppliers;</li>
                    <li>Record your consent to our Terms, Privacy Policy, and AI Disclosure, including your IP address and the time of consent;</li>
                    <li>Respond to your inquiries and provide customer support.</li>
                </ul>
            </section>

            <section>
                <h2 class="text-lg font-semibold text-slate-900">3. Sharing of Information</h2>
                <p>We share your information with travel suppliers and payment processors only when necessary to complete your bookings. We do not sell your personal information to third parties.</p>
            </section>

            <section>
                <h2 class="text-lg font-semibold text-slate-900">4. Data Security</h2>
                <p>We protect your personal data using reasonable administrative, technical, and physical security measures. However, no online service can guarantee absolute security.</p>
            </section>

            <section>
                <h2 class="text-lg font-semibold text-slate-900">5. Your Rights</h2>
                <p>Depending on applicable law, you may have the right to access, correct, or delete your personal information. To exercise these rights, please contact us through the support channels provided in the platform.</p>
            </section>

            <section>
                <h2 class="text-lg font-semibold text-slate-900">6. Data Disclosure Summary</h2>
                <p>By creating an account, you agree that SunnyTrips may:</p>
                <ul class="list-disc pl-5 space-y-2">
                    <li>Collect and store your name, email, phone number, address, and account credentials.</li>
                    <li>Record your consent to our Terms, Privacy Policy, and AI Disclosure, including your IP address and the time of consent.</li>
                    <li>Process your travel preferences and chatbot interactions to generate AI-assisted recommendations.</li>
                    <li>Share your booking details with travel suppliers and payment processors only as necessary to complete your bookings.</li>
                </ul>
                <p>We do not sell your personal information.</p>
            </section>

            <section>
                <h2 class="text-lg font-semibold text-slate-900">7. Changes to This Policy</h2>
                <p>We may update this Privacy Policy from time to time. If we make material changes, we will notify you and may ask you to renew your consent.</p>
            </section>
        </div>
    </div>
</x-frontend.layout>
