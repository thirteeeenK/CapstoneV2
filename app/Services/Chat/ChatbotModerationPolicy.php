<?php

namespace App\Services\Chat;

class ChatbotModerationPolicy
{
    /**
     * Single source of truth for prohibited chat content.
     *
     * Labels for the three pre-existing authenticated categories are preserved
     * so moderation reports keep their current category names.
     *
     * @return array<string, list<string>>
     */
    public static function categories(): array
    {
        return [
            'Sexual/Inappropriate' => [
                'nsfw',
                'porn',
                'naked',
                'nude',
                'sexual',
                'sex',
                'strip',
                'erotic',
                'boobs',
                'penis',
                'vagina',
            ],
            'Sensitive/Prohibited' => [
                'suicide',
                'bomb',
                'terrorist',
                'hack bank',
                'credit card fraud',
                'illegal drugs',
                'kill',
                'murder',
            ],
            'Prompt Injection' => [
                'ignore previous instructions',
                'ignore all rules',
                'system prompt',
                'you are now DAN',
                'bypass restriction',
                'developer mode',
                'forget everything',
                'act as a developer',
                'print prompt',
                'output your instructions',
                'jailbreak',
                'do anything now',
                'system message',
                'admin mode',
                'override commands',
            ],
            'Hate/Profanity' => [
                'putangina',
                'gago',
                'bobo',
                'tanga',
                'ulol',
                'inamo',
                'hayop ka',
                'tarantado',
                'fuck',
                'what the fuck',
                'wtf',
                'shit',
                'bitch',
                'asshole',
                'cunt',
                'retard',
                'bastard',
                'stupid',
                'idiot',
                'bullshit',
                'bull****',
                'pakyu',
                'leche',
                'lintik',
                'buwisit',
                'bwiset',
                'hinayupak',
                'walanghiya',
                'siraulo',
                'sira-ulo',
                'gaga',
                'yawa',
                'buang',
                'atay',
            ],
            'Explicit/NSFW' => [
                'bold',
                'hubad',
                'bastos',
                'kantot',
                'iyot',
                'pepe',
                'titi',
                'pokpok',
                'escort',
                'prostitute',
                'onlyfans',
                'sugar daddy',
                'sugar baby',
                'burat',
                'tite',
                'suso',
                'dede',
                'jakol',
                'tamod',
                'libog',
                'malibog',
                'tsupa',
                'landian',
            ],
            'Violence/Illegal' => [
                'magpakamatay',
                'patayin',
                'saksak',
                'baril',
                'droga',
                'shabu',
                'adik',
                'weapon',
                'shoot',
                'self-harm',
                'cut myself',
                'rape',
                'pumatay',
                'papatay',
                'saksakin',
                'barilin',
                'holdap',
                'holdaper',
                'magnakaw',
                'ninakaw',
                'rugby',
            ],
            'Scam/Travel Abuse' => [
                'human trafficking',
                'smuggle',
                'fake passport',
                'fake visa',
                'bypass immigration',
                'tnt',
                'tago ng tago',
                'peke na ticket',
                'scam',
                'money laundering',
            ],
        ];
    }

    /**
     * Boundary-aware match against the shared vocabulary.
     *
     * Normalizes case/whitespace; single words use word boundaries so `sex`
     * does not match unrelated substrings. Multi-word phrases and hyphenated
     * terms match literally.
     *
     * @return array{category: string, term: string, reason: string}|null
     */
    public static function match(string $message): ?array
    {
        $normalized = mb_strtolower(trim((string) preg_replace('/\s+/', ' ', $message)));

        if ($normalized === '') {
            return null;
        }

        foreach (self::categories() as $category => $terms) {
            foreach ($terms as $term) {
                $pattern = '/\b'.preg_quote(mb_strtolower($term), '/').'\b/i';

                if (preg_match($pattern, $normalized)) {
                    return [
                        'category' => $category,
                        'term' => $term,
                        'reason' => "Query contains prohibited phrase: '{$term}'",
                    ];
                }
            }
        }

        return null;
    }
}
