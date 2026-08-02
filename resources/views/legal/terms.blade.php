<x-frontend.layout>
    <x-slot name="title">Terms and Conditions | SunnyTrips</x-slot>

    <div class="max-w-4xl mx-auto px-6 pt-32 pb-16">
        <div class="mb-10">
            <h1 class="font-headline text-3xl md:text-4xl font-bold text-slate-900 mb-3">Terms and Conditions</h1>
            <p class="text-slate-500 text-sm">Effective date: {{ config('legal.documents.terms.effective_date') }}</p>
        </div>

        <div class="prose prose-slate max-w-none text-slate-600 text-sm leading-relaxed space-y-6">
            <section>
                <h2 class="text-lg font-semibold text-slate-900">1. About SunnyTrips</h2>
                <p>SunnyTrips is an AI-assisted travel decision-support and booking platform focused on Philippine destinations. SunnyTrips is not a travel provider; flights, ferries, hotels, tours, and other services are supplied by independent third parties.</p>
            </section>

            <section>
                <h2 class="text-lg font-semibold text-slate-900">2. Eligibility</h2>
                <p>You must be at least <strong>18 years old</strong> and legally capable of entering into contracts under Philippine law. By registering, you confirm that you are of legal age and that all information you provide is true and accurate.</p>
            </section>

            <section>
                <h2 class="text-lg font-semibold text-slate-900">3. Account Security</h2>
                <p>You are responsible for keeping your login credentials secure and for all activity under your account. You must notify us immediately of any unauthorized use.</p>
            </section>

            <section>
                <h2 class="text-lg font-semibold text-slate-900">4. AI-Assisted Recommendations</h2>
                <p>SunnyTrips uses artificial intelligence to generate personalized travel recommendations, itineraries, and responses. AI outputs may contain errors, outdated information, or incomplete details. You agree to independently verify prices, availability, schedules, travel advisories, safety conditions, and supplier terms before booking or traveling.</p>
            </section>

            <section>
                <h2 class="text-lg font-semibold text-slate-900">5. Bookings and Third-Party Suppliers</h2>
                <p>When you book through SunnyTrips, your contract may be directly with the travel supplier. Supplier terms, including cancellation, refund, and rebooking policies, will apply. SunnyTrips is not liable for supplier overbooking, cancellations, changes, or failure to perform.</p>
            </section>

            <section>
                <h2 class="text-lg font-semibold text-slate-900">6. Payments and Refunds</h2>
                <p>Payment terms are shown at checkout. Refunds, cancellations, and rebooking are governed by the supplier's policy and these Terms.</p>
            </section>

            <section>
                <h2 class="text-lg font-semibold text-slate-900">7. User Conduct</h2>
                <p>You agree not to use SunnyTrips for fraudulent, unlawful, abusive, harassing, or harmful purposes, including misuse of the AI chatbot or booking system. We may suspend or terminate accounts that violate these rules.</p>
            </section>

            <section>
                <h2 class="text-lg font-semibold text-slate-900">8. Limitation of Liability</h2>
                <p>To the fullest extent permitted by law, SunnyTrips and its operators are not liable for any indirect, incidental, consequential, special, or punitive damages arising from your use of the platform, AI recommendations, or third-party travel services.</p>
            </section>

            <section>
                <h2 class="text-lg font-semibold text-slate-900">9. Binding Arbitration Clause</h2>
                <p>Any dispute, claim, or controversy arising from or relating to these Terms, your use of SunnyTrips, or any booking arranged through the platform shall be resolved exclusively through <strong>binding arbitration</strong> in the Philippines, administered by the <strong>Philippine Dispute Resolution Center, Inc. (PDRCI)</strong> or any successor arbitration body mutually agreed upon by the parties.</p>
                <ul class="list-disc pl-5 space-y-2">
                    <li>Both you and SunnyTrips waive the right to a trial by jury, class action, or collective proceeding.</li>
                    <li>Arbitration shall be conducted in <strong>English or Filipino</strong>, in <strong>Metro Manila, Philippines</strong>, unless both parties agree otherwise.</li>
                    <li>The arbitrator's decision shall be final and binding, and judgment may be entered in any court of competent jurisdiction.</li>
                </ul>
                <p>If any portion of this clause is found unenforceable, the remainder shall continue in effect, and the unenforceable portion shall be reformed to the minimum extent necessary.</p>
            </section>

            <section>
                <h2 class="text-lg font-semibold text-slate-900">10. Governing Law</h2>
                <p>These Terms are governed by the laws of the <strong>Republic of the Philippines</strong>, without regard to conflict-of-law principles.</p>
            </section>

            <section>
                <h2 class="text-lg font-semibold text-slate-900">11. Changes to Terms</h2>
                <p>We may update these Terms from time to time. If you continue to use SunnyTrips after a material change, you will be deemed to have accepted the revised Terms. For major changes, we may require you to re-confirm your consent.</p>
            </section>
        </div>
    </div>
</x-frontend.layout>
