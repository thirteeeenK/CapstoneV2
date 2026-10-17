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
     * Phrasings the literal terms cannot cover: plurals and rewordings of
     * override attempts ("disregard the system prompts", "do what I tell
     * you", "pretend you are ChatGPT"). Each requires an AI-control object
     * (instructions, prompts, programming…) or an AI persona, so travel
     * requests like "ignore the previous hotel" or "act as my tour guide"
     * never match.
     *
     * @return array<string, list<string>>
     */
    public static function patterns(): array
    {
        $override = '(?:ignore|disregard|forget|override|bypass)';

        return [
            'Prompt Injection' => [
                '/\b'.$override.'\s+(?:(?:all|any|every|the|your|my|these|those|previous|prior|above|earlier|preceding|original|initial|existing|current|system|safety|chatbot|bot|ai|of)\s+)*(?:instructions?|prompts?|programming|directives?|guardrails?|safeguards?)\b/iu',
                '/\b'.$override.'\s+(?:all\s+(?:of\s+)?)?(?:your|previous|prior|above|earlier|these|all)\s+(?:(?:safety|content|chatbot|bot|ai|system)\s+)?(?:rules|polic(?:y|ies)|guidelines|restrictions|limitations|filters|limits|constraints)\b/iu',
                '/\bsystem\s+(?:prompts?|messages?)\b/iu',
                '/\b(?:do|follow)\s+(?:exactly\s+)?what(?:ever)?\s+i\s+(?:tell|command|order)\s+you\b/iu',
                '/\bobey\s+(?:me|my\s+(?:orders?|commands?))\b/iu',
                '/\b(?:you\s+are\s+now|from\s+now\s+on\s+you\s+are|pretend\s+(?:to\s+be|you\s*(?:\'re|are))|act\s+as|roleplay\s+as)\s+(?:an?\s+)?(?:chatgpt|gpt[\s-]?\d*|gemini|claude|copilot|llama|grok)\b/iu',
                '/\b(?:unrestricted|unfiltered|uncensored|jailbroken)\s+(?:ai|assistant|bot|chatbot|mode|model|version)\b/iu',
                '/\b(?:huwag|wag)\s+(?:mong?\s+)?(?:sundin|pansinin)\s+(?:ang\s+)?(?:mga\s+)?(?:rules|instructions?|patakaran|system\s+prompts?)\b/iu',
            ],
        ];
    }

    /**
     * Boundary-aware match against the shared vocabulary.
     *
     * Normalizes case/whitespace; single words use word boundaries so `sex`
     * does not match unrelated substrings. Multi-word phrases and hyphenated
     * terms match literally. Categories are checked in order, each against
     * its literal terms and then its phrase patterns.
     *
     * @return array{category: string, term: string, reason: string}|null
     */
    public static function match(string $message): ?array
    {
        $normalized = mb_strtolower(trim((string) preg_replace('/\s+/', ' ', $message)));

        if ($normalized === '') {
            return null;
        }

        $patterns = self::patterns();

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

            foreach ($patterns[$category] ?? [] as $pattern) {
                if (preg_match($pattern, $normalized, $found)) {
                    return [
                        'category' => $category,
                        'term' => $found[0],
                        'reason' => "Query contains prohibited phrase: '{$found[0]}'",
                    ];
                }
            }
        }

        return null;
    }
}
