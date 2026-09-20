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
                        'heading' => 'Introduction',
                        'body' => 'Last Updated: May 2026<br>At SUNNYTRIPS TRAVEL SERVICES, we value and respect your privacy. This Privacy Policy explains how we collect, use, store, and protect your personal information when you use our services, including bookings, inquiries, travel planning assistance, and communication with our business.<br>By using our services, you agree to the collection and use of information in accordance with this Privacy Policy.',
                    ],
                    [
                        'heading' => 'Information We Collect',
                        'body' => 'We may collect the following personal information from clients and travelers:<br><ul><li>Full name</li><li>Contact information (mobile number, email address, home address)</li><li>Passport details and valid identification information</li><li>Travel preferences and booking details</li><li>Payment information necessary for processing transactions</li><li>Other information required for visa applications, bookings, or travel arrangements</li></ul>',
                    ],
                    [
                        'heading' => 'How We Use Your Information',
                        'body' => 'Your information may be used for the following purposes:<br><ul><li>Processing travel bookings and reservations</li><li>Assisting with flights, hotels, tours, visas, and other travel services</li><li>Communicating important updates regarding your travel arrangements</li><li>Verifying payments and preventing fraudulent transactions</li><li>Improving our customer service and travel assistance</li><li>Complying with legal and regulatory requirements</li></ul>',
                    ],
                    [
                        'heading' => 'Sharing of Information',
                        'body' => 'SUNNYTRIPS TRAVEL SERVICES may share necessary information with trusted third-party providers such as:<br><ul><li>Airlines</li><li>Hotels and accommodations</li><li>Tour operators</li><li>Visa processing agencies</li><li>Payment providers</li></ul>Information shared will only be limited to what is necessary to complete your travel arrangements. We do not sell, rent, or trade your personal information to unrelated third parties.',
                    ],
                    [
                        'heading' => 'Data Protection and Security',
                        'body' => 'We take reasonable measures to protect your personal information from unauthorized access, misuse, disclosure, alteration, or loss. Personal data is stored securely and accessed only by authorized personnel or service providers involved in your booking and travel arrangements.<br>However, while we strive to protect your information, no method of electronic storage or transmission over the internet is completely secure.',
                    ],
                    [
                        'heading' => 'Retention of Information',
                        'body' => 'We retain personal information only for as long as necessary to fulfill booking services, comply with legal obligations, resolve disputes, and enforce our agreements.',
                    ],
                    [
                        'heading' => 'Client Responsibilities',
                        'body' => 'Clients are responsible for ensuring that all information provided to SUNNYTRIPS TRAVEL SERVICES is accurate, complete, and up to date. Incorrect or incomplete information may result in booking delays, cancellations, or denied travel entry.',
                    ],
                    [
                        'heading' => 'Your Rights',
                        'body' => 'Subject to applicable laws, you may request to:<br><ul><li>Access your personal information</li><li>Correct inaccurate or incomplete information</li><li>Request deletion of personal data where legally permitted</li><li>Withdraw consent for certain uses of your information</li></ul>Requests may be submitted through our official communication channels.',
                    ],
                    [
                        'heading' => 'Cookies and Online Platforms',
                        'body' => 'If our website or online booking platforms use cookies or similar technologies, these may collect basic browsing information to improve user experience and website functionality.',
                    ],
                    [
                        'heading' => 'Third-Party Links',
                        'body' => 'Our website or social media pages may contain links to third-party websites. SUNNYTRIPS TRAVEL SERVICES is not responsible for the privacy practices or content of external websites.',
                    ],
                    [
                        'heading' => 'Changes to this Privacy Policy',
                        'body' => 'SUNNYTRIPS TRAVEL SERVICES reserves the right to update or modify this Privacy Policy at any time. Updated versions will become effective once posted or shared through official platforms.',
                    ],
                    [
                        'heading' => 'Contact Information',
                        'body' => 'For questions, concerns, or requests regarding this Privacy Policy, you may contact:<br>SUNNYTRIPS TRAVEL SERVICES<br>Email: sunnytrips01@gmail.com<br>Contact Number: 09682447153<br>Address: Pili, Camarines Sur',
                    ],
                ],
                'updated_by' => 'system',
            ],

            'ai_disclosure' => [
                'title' => 'AI Usage Disclosure',
                'version' => '1.0',
                'content' => [
                    [
                        'heading' => 'What our AI does',
                        'body' => 'SunnyTrips uses artificial intelligence ("SunnyBot AI", powered by Google Gemini) to help you plan trips. It can recommend Philippine destinations, hotels, rooms, activities, and packages, build day-by-day itineraries, and answer your travel questions. Dashboard suggestions are personalized from your stated preferences.',
                    ],
                    [
                        'heading' => 'How to use the chatbot',
                        'body' => 'You can use the chatbot to ask about prices, destinations, hotels, rooms, activities, and packages — for example, what\'s available in Boracay or El Nido, or which options fit your budget. You can ask follow-up questions and check live room availability. When you\'re ready, tap Add to Trip Basket to proceed — the chatbot itself never completes a booking or payment. Need a person? Tap Talk to Admin anytime to reach a human agent.',
                    ],
                    [
                        'heading' => 'What the AI can\'t do',
                        'body' => 'SunnyBot only helps with Philippine travel planning. It cannot process payments, confirm bookings, issue refunds, or change reservations. It will not give medical, legal, or financial advice, and it won\'t reveal its internal instructions. If you ask something outside travel planning, it will redirect you.',
                    ],
                    [
                        'heading' => 'AI can make mistakes — always double-check',
                        'body' => 'AI-generated content can be inaccurate, incomplete, or outdated. It may hallucinate details, misread your request, or show prices, availability, weather, or travel advisories that have changed. Never treat an AI answer as final — verify prices, availability, schedules, supplier terms, weather, health requirements, and government travel advisories before booking or traveling. AI suggestions are decision-support only, not professional advice.',
                    ],
                    [
                        'heading' => 'Data the AI uses',
                        'body' => 'To personalize results, the AI may process: your stated preferences, budget, and interests; your chatbot conversation history; your search and interaction history on SunnyTrips; and a mathematical summary (embedding) of your preferences stored in our database. Queries and embeddings are sent to Google Gemini to generate matches and answers. How we store and protect this data is explained in our <a href="/privacy-policy">Privacy Policy</a>.',
                    ],
                    [
                        'heading' => 'Your controls and privacy',
                        'body' => 'You can request access, correction, or deletion of your personal data where legally allowed, or withdraw consent for certain uses, by contacting SUNNYTRIPS TRAVEL SERVICES at sunnytrips01@gmail.com / 09682447153 (Pili, Camarines Sur).',
                    ],
                    [
                        'heading' => 'Limits and fair use',
                        'body' => 'Guest chats are limited (15 messages/day per IP + 5/minute burst) and the bot remembers only recent conversation context. Don\'t misuse the chatbot (spam, abuse, fraud, prompt-injection attempts) — we may block access and, for accounts, suspend or ban per our Terms.',
                    ],
                    [
                        'heading' => 'AI vs. human transparency',
                        'body' => 'Messages from SunnyBot AI are AI-generated. Messages under a human-agent banner come from a real support agent and take precedence. Answers about our Terms, Privacy Policy, and this Disclosure are pre-written (deterministic) so they can\'t be hallucinated.',
                    ],
                    [
                        'heading' => 'Human support',
                        'body' => 'For urgent issues, booking disputes, cancellations, or anything you\'re unsure about, contact our human team at sunnytrips01@gmail.com / 09682447153. AI answers never replace confirmation from our staff or the travel supplier.',
                    ],
                    [
                        'heading' => 'Consent and changes',
                        'body' => 'By using SunnyTrips, you acknowledge our recommendations are AI-assisted and your preference data may be processed as described here. Liability for AI recommendations is governed by our <a href="/terms-and-conditions">Terms and Conditions</a>. If we materially update this Disclosure, continued use after posting counts as acceptance.',
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
