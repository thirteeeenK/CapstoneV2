<?php

namespace Database\Seeders;

use App\Models\Faq;
use Illuminate\Database\Seeder;

class FaqSeeder extends Seeder
{
    /**
     * Seed landing-page FAQs (idempotent — safe to re-run).
     */
    public function run(): void
    {
        $faqs = [
            [
                'question' => 'Does SunnyTrips offer Airline Booking Ticket?',
                'answer' => 'Yes, but only for Packages. Customized travel itineraries are not supported by the airline ticket booking.',
                'keywords' => 'flight, airline, ticket',
                'category' => 'Booking',
                'sort_order' => 1,
            ],

            [
                'question' => 'How do I book a trip on SunnyTrips?',
                'answer' => 'Browse hotels, activities, or packages, add your choices to the Trip Basket, set your travel dates and guest count, then proceed to checkout. You will receive a booking code and a pending status until our team confirms availability with the suppliers.',
                'keywords' => 'book, booking, reserve, trip basket, checkout, how to book',
                'category' => 'Booking',
                'sort_order' => 2,
            ],

            [
                'question' => 'What payment methods do you accept and when is payment due?',
                'answer' => 'We accept major Philippine payment channels (cards, e-wallets, and bank transfer — depending on the supplier). After checkout your booking is held under a payment deadline shown on the confirmation page; if payment is not completed in time the booking expires automatically.',
                'keywords' => 'payment, pay, card, gcash, maya, bank transfer, deadline, due',
                'category' => 'Payments',
                'sort_order' => 3,
            ],

            [
                'question' => 'What is the cancellation and refund policy?',
                'answer' => 'Cancellation and refund terms depend on the hotel, activity, or package supplier. You can request cancellation from your bookings page before the supplier deadline; our team will confirm eligibility and any applicable fees. Review the supplier terms shown at checkout for specifics.',
                'keywords' => 'cancel, cancellation, refund, policy, fees',
                'category' => 'Cancellation & Refunds',
                'sort_order' => 4,
            ],

            [
                'question' => 'Which destinations does SunnyTrips cover?',
                'answer' => 'SunnyTrips covers Philippine destinations listed on our destinations page — each destination page highlights curated stays and experiences. Ask SunnyBot "what destinations are offered" for the current live list.',
                'keywords' => 'destinations, philippines, where, cover, offered',
                'category' => 'Destinations',
                'sort_order' => 5,
            ],

            [
                'question' => 'What is included in a SunnyTrips package?',
                'answer' => 'Packages bundle selected hotels and activities per destination for a fixed number of days and nights. Inclusions, exclusions, and the detailed itinerary are listed on each package page; generic inclusions such as transfers or meals are shown separately when provided by the supplier.',
                'keywords' => 'package, inclusions, itinerary, days, nights, bundle',
                'category' => 'Packages',
                'sort_order' => 6,
            ],

            [
                'question' => 'Can I book a hotel or activity without a package?',
                'answer' => 'Yes. You can book hotels (room types) and activities individually alongside packages. Add-ons such as airport transfers or extra services can be added where available. All items live together in your Trip Basket.',
                'keywords' => 'hotel, room, activity, add-on, individual, without package',
                'category' => 'Services',
                'sort_order' => 7,
            ],

            [
                'question' => 'How does SunnyBot help me plan my trip?',
                'answer' => 'SunnyBot is our AI travel assistant. It can answer questions about listings, check room availability, suggest itineraries, show you on a map, and surface weather context — all grounded in our catalog. If you need a human, you can request a support agent from the chat.',
                'keywords' => 'sunnybot, chatbot, ai, assistant, itinerary, availability, map, weather',
                'category' => 'SunnyBot & AI',
                'sort_order' => 8,
            ],

            [
                'question' => 'Do I need an account to use SunnyTrips and is my data protected?',
                'answer' => 'You can explore listings and chat as a guest. An account is required to check out, manage bookings, and receive personalized recommendations. We protect your data per our Privacy Policy and store only what is needed to deliver your trip; you can review our Terms and Privacy pages from the site footer.',
                'keywords' => 'account, register, guest, privacy, data, terms',
                'category' => 'Account & Privacy',
                'sort_order' => 9,
            ],

            [
                'question' => 'Does SunnyTrips offer discounts?',
                'answer' => 'Yes — SunnyTrips applies **Passenger Pricing Rules** at checkout:

- **Student** — ₱50 off per pax
- **Senior Citizen** — ₱50 off per pax
- **PWD (Person with Disability)** — ₱50 off per pax
- **Child (3-17)** — ₱50 off per pax
- **Infant (0-2)** — ₱50 off per pax
- **Foreign Tourist** — ₱150 surcharge per pax
- **Regular Adult** — No adjustment

Select the passenger category for each traveler in the **Trip Basket / Checkout** to see the discount or surcharge applied automatically.',
                'keywords' => 'discount discounts senior student pwd child infant foreigner surcharge offer',
                'category' => 'Pricing & Discounts',
                'sort_order' => 10,
            ],
        ];

        foreach ($faqs as $row) {
            Faq::updateOrCreate(
                ['question' => $row['question']],
                [
                    'answer' => $row['answer'],
                    'keywords' => $row['keywords'],
                    'category' => $row['category'],
                    'sort_order' => $row['sort_order'],
                    'is_active' => true,
                ]
            );
        }

        $this->command?->info('FaqSeeder: seeded '.count($faqs).' FAQs (idempotent).');
    }
}
