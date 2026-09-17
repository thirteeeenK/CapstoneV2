<?php

namespace Database\Seeders;

use App\Models\LegalDocument;
use Illuminate\Database\Seeder;

class LegalDocumentsSeeder extends Seeder
{
    /**
     * Seed the legal documents with the current default content.
     */
    public function run(): void
    {
        $documents = [
            'terms' => [
                'title' => 'Terms and Conditions',
                'version' => '1.0',
                'content' => [
                    [
                        'heading' => 'About SunnyTrips',
                        'body' => 'SunnyTrips is an AI-assisted travel decision-support and booking platform focused on Philippine destinations. SunnyTrips is not a travel provider; flights, ferries, hotels, tours, and other services are supplied by independent third parties.',
                    ],
                    [
                        'heading' => 'Eligibility',
                        'body' => 'You must be at least <strong>18 years old</strong> and legally capable of entering into contracts under Philippine law. By registering, you confirm that you are of legal age and that all information you provide is true and accurate.',
                    ],
                    [
                        'heading' => 'Account Security',
                        'body' => 'You are responsible for keeping your login credentials secure and for all activity under your account. You must notify us immediately of any unauthorized use.',
                    ],
                    [
                        'heading' => 'AI-Assisted Recommendations',
                        'body' => 'SunnyTrips uses artificial intelligence to generate personalized travel recommendations, itineraries, and responses. AI outputs may contain errors, outdated information, or incomplete details. You agree to independently verify prices, availability, schedules, travel advisories, safety conditions, and supplier terms before booking or traveling.',
                    ],
                    [
                        'heading' => 'Bookings and Third-Party Suppliers',
                        'body' => 'When you book through SunnyTrips, your contract may be directly with the travel supplier. Supplier terms, including cancellation, refund, and rebooking policies, will apply. SunnyTrips is not liable for supplier overbooking, cancellations, changes, or failure to perform.',
                    ],
                    [
                        'heading' => 'Payments and Refunds',
                        'body' => 'Payment terms are shown at checkout. Refunds, cancellations, and rebooking are governed by the supplier\'s policy and these Terms.',
                    ],
                    [
                        'heading' => 'User Conduct',
                        'body' => 'You agree not to use SunnyTrips for fraudulent, unlawful, abusive, harassing, or harmful purposes, including misuse of the AI chatbot or booking system. We may suspend or terminate accounts that violate these rules.',
                    ],
                    [
                        'heading' => 'Limitation of Liability',
                        'body' => 'To the fullest extent permitted by law, SunnyTrips and its operators are not liable for any indirect, incidental, consequential, special, or punitive damages arising from your use of the platform, AI recommendations, or third-party travel services.',
                    ],
                    [
                        'heading' => 'Binding Arbitration Clause',
                        'body' => 'Any dispute, claim, or controversy arising from or relating to these Terms, your use of SunnyTrips, or any booking arranged through the platform shall be resolved exclusively through <strong>binding arbitration</strong> in the Philippines, administered by the <strong>Philippine Dispute Resolution Center, Inc. (PDRCI)</strong> or any successor arbitration body mutually agreed upon by the parties.<br><ul><li>Both you and SunnyTrips waive the right to a trial by jury, class action, or collective proceeding.</li><li>Arbitration shall be conducted in English or Filipino, in Metro Manila, Philippines, unless both parties agree otherwise.</li><li>The arbitrator\'s decision shall be final and binding, and judgment may be entered in any court of competent jurisdiction.</li></ul>',
                    ],
                    [
                        'heading' => 'Governing Law',
                        'body' => 'These Terms are governed by the laws of the <strong>Republic of the Philippines</strong>, without regard to conflict-of-law principles.',
                    ],
                    [
                        'heading' => 'Changes to Terms',
                        'body' => 'We may update these Terms from time to time. If you continue to use SunnyTrips after a material change, you will be deemed to have accepted the revised Terms. For major changes, we may require you to re-confirm your consent.',
                    ],
                ],
                'updated_by' => 'system',
            ],

            'privacy' => [
                'title' => 'Privacy Policy',
                'version' => '1.0',
                'content' => [
                    [
                        'heading' => 'Information We Collect',
                        'body' => 'We collect personal information that you provide when creating an account, booking travel, or interacting with our AI features. This includes your name, email address, phone number, address, account credentials, travel preferences, chatbot history, and a mathematical representation of your preferences used to personalize recommendations.',
                    ],
                    [
                        'heading' => 'How We Use Your Information',
                        'body' => 'We use your information to provide and improve SunnyTrips services, generate AI-assisted travel recommendations, process bookings, record your consent (including IP address and time), and respond to your inquiries.',
                    ],
                    [
                        'heading' => 'Sharing of Information',
                        'body' => 'We share your information with travel suppliers and payment processors only as necessary to complete your bookings. We do not sell your personal information to third parties.',
                    ],
                    [
                        'heading' => 'Data Security',
                        'body' => 'We protect your personal data using reasonable administrative, technical, and physical security measures. No online service can guarantee absolute security.',
                    ],
                    [
                        'heading' => 'Your Rights',
                        'body' => 'Depending on applicable law, you may have the right to access, correct, or delete your personal information. To exercise these rights, please contact us through the support channels provided in the platform.',
                    ],
                    [
                        'heading' => 'Data Disclosure Summary',
                        'body' => 'By creating an account, you agree that SunnyTrips may collect and store your name, email, phone number, address, and account credentials; record your consent including your IP address and the time of consent; process your travel preferences and chatbot interactions to generate AI-assisted recommendations; and share booking details with travel suppliers and payment processors only as necessary. We do not sell your personal information.',
                    ],
                    [
                        'heading' => 'Changes to This Policy',
                        'body' => 'We may update this Privacy Policy from time to time. If we make material changes, we will notify you and may ask you to renew your consent.',
                    ],
                ],
                'updated_by' => 'system',
            ],

            'ai_disclosure' => [
                'title' => 'AI Usage Disclosure',
                'version' => '1.0',
                'content' => [
                    [
                        'heading' => 'AI-Assisted Features',
                        'body' => 'SunnyTrips uses artificial intelligence to help you plan trips. The AI may recommend destinations, accommodations, activities, itineraries, and respond to your travel questions.',
                    ],
                    [
                        'heading' => 'Data Used by AI',
                        'body' => 'To personalize your experience, the AI may process your stated travel preferences, budget, and interests; your chatbot conversation history; your search and interaction history on SunnyTrips; and a mathematical summary (embedding) of your preferences stored in our database.',
                    ],
                    [
                        'heading' => 'AI Limitations',
                        'body' => 'AI-generated content may be inaccurate, incomplete, outdated, or not suitable for your situation. SunnyTrips does not guarantee that AI recommendations are correct, safe, or currently available.',
                    ],
                    [
                        'heading' => 'Your Responsibility',
                        'body' => 'Always verify important details such as prices, availability, travel advisories, weather, health requirements, and supplier terms before booking or making travel decisions.',
                    ],
                    [
                        'heading' => 'Human Support',
                        'body' => 'For urgent issues, booking disputes, or cancellations, please contact our human support team through the channels provided in the platform.',
                    ],
                    [
                        'heading' => 'Consent',
                        'body' => 'By using SunnyTrips, you acknowledge that our recommendations are AI-assisted and that your preference data may be processed to improve your experience.',
                    ],
                ],
                'updated_by' => 'Administrator',
            ],
        ];

        foreach ($documents as $key => $data) {
            LegalDocument::updateOrCreate(
                ['key' => $key],
                [
                    'title' => $data['title'],
                    'content' => $data['content'],
                    'version' => $data['version'],
                    'updated_by' => $data['updated_by'] ?? 'system',
                ]
            );
        }
    }
}
