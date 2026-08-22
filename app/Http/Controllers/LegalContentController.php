<?php

namespace App\Http\Controllers;

use App\Models\LegalDocument;
use Illuminate\View\View;

class LegalContentController extends Controller
{
    protected array $keyMap = [
        'terms' => 'terms',
        'privacy-policy' => 'privacy',
        'ai-disclosure' => 'ai_disclosure',
    ];

    public function show(string $slug): View
    {
        $key = $this->keyMap[$slug] ?? $slug;

        $document = LegalDocument::where('key', $key)->first();

        // Fallback to the default seeded document if no row exists.
        if (! $document) {
            $document = $this->fallback($slug);
        }

        return view('legal.show', compact('document'));
    }

    protected function fallback(string $slug): LegalDocument
    {
        $defaults = [
            'terms' => [
                'title' => 'Terms and Conditions',
                'version' => config('legal.documents.terms.version', '1.0'),
                'content' => [
                    ['heading' => 'About SunnyTrips', 'body' => 'SunnyTrips is an AI-assisted travel decision-support and booking platform focused on Philippine destinations.'],
                ],
            ],
            'privacy' => [
                'title' => 'Privacy Policy',
                'version' => config('legal.documents.privacy.version', '1.0'),
                'content' => [
                    ['heading' => 'Information We Collect', 'body' => 'We collect personal information you provide when creating an account or booking travel.'],
                ],
            ],
            'ai_disclosure' => [
                'title' => 'AI Usage Disclosure',
                'version' => config('legal.documents.ai_disclosure.version', '1.0'),
                'content' => [
                    ['heading' => 'AI-Powered Features', 'body' => 'SunnyTrips uses artificial intelligence to help you plan trips.'],
                ],
            ],
        ];

        $key = $this->keyMap[$slug] ?? $slug;
        $data = $defaults[$key] ?? array_values($defaults)[0];

        return new LegalDocument($data);
    }
}
