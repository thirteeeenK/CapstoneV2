<?php

namespace App\Services\Chat;

use App\Models\KnowledgeArticle;
use App\Services\GeminiService;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Scoped retrieval over reviewed knowledge articles.
 *
 * Only citable articles (published, reviewed, unexpired) for the requested
 * destination or general guidance are candidates. Lexical evidence always
 * runs; semantic evidence is added only for articles whose embedding still
 * matches their current text, so a stale vector can never vouch for edited
 * content. Answers are composed deterministically from the article text:
 * no model call, no cards, no prices or availability.
 */
final class KnowledgeRetrievalService
{
    /**
     * Initial values, not yet calibrated against live embeddings. Lexical
     * matching requires evidence in the safe article text, within the asked
     * category. Re-check both thresholds with `chatbot:coverage --live`.
     */
    public const LEXICAL_THRESHOLD = 0.35;

    public const SEMANTIC_THRESHOLD = 0.75;

    /** Above this, two different sources in one category are both shown. */
    public const AMBIGUITY_MARGIN = 0.05;

    private const MAX_ARTICLES = 2;

    private const MAX_BODY_CHARS = 600;

    /** Words that signal each category, in English and Filipino. */
    public const TOPIC_TERMS = [
        'dining' => ['restaurant', 'restaurants', 'dining', 'eat', 'eats', 'food', 'foods', 'cafe', 'cafes', 'kainan', 'kumain', 'makakain', 'pagkain', 'seafood', 'eatery', 'eateries'],
        'local_transport' => ['around', 'tricycle', 'tricycles', 'transport', 'transportation', 'commute', 'sakay', 'jeep', 'jeepney', 'fare', 'van'],
        'destination_access' => ['get', 'getting', 'reach', 'fly', 'flight', 'airport', 'ferry', 'boat', 'jetty', 'pumunta', 'papunta', 'manila', 'travel'],
        'practical_guidance' => ['fee', 'fees', 'environmental', 'ecotourism', 'visitor', 'season', 'time', 'visit', 'tips', 'packing', 'pack', 'bring', 'advisory', 'ferry', 'suspension'],
        'accessibility' => ['wheelchair', 'accessible', 'accessibility', 'pwd', 'mobility', 'ramp'],
        'activity_requirements' => ['requirement', 'requirements', 'swim', 'age', 'need'],
        'attractions' => ['beach', 'beaches', 'cove', 'viewpoint', 'viewpoints', 'landmark', 'landmarks', 'attraction', 'attractions', 'sights', 'spots'],
    ];

    /** How a missing topic is named to the traveler. */
    private const TOPIC_PHRASES = [
        'dining' => 'places to eat',
        'local_transport' => 'getting around',
        'destination_access' => 'getting there',
        'practical_guidance' => 'that',
        'accessibility' => 'accessibility',
        'activity_requirements' => 'activity requirements',
        'attractions' => 'that place',
    ];

    private const STOPWORDS = ['a', 'an', 'the', 'is', 'are', 'was', 'be', 'do', 'does', 'did', 'can', 'could', 'should', 'would', 'will', 'i', 'we', 'you', 'me', 'my', 'our', 'us', 'there', 'here', 'to', 'of', 'in', 'on', 'at', 'for', 'from', 'and', 'or', 'any', 'some', 'what', 'whats', 'where', 'when', 'how', 'which', 'who', 'about', 'tell', 'it', 'its', 'that', 'this', 'with', 'good', 'best', 'people', 'sa', 'ng', 'ang', 'mga', 'ba', 'po', 'na', 'ko', 'kami', 'tayo', 'saan', 'paano', 'ano', 'may', 'meron', 'doon', 'dito', 'pwede', 'puwede'];

    public function __construct(private GeminiService $gemini) {}

    /**
     * Which knowledge category a clause asks about, if it is a narrative
     * travel-information question rather than a catalog search.
     */
    public static function topic(string $clause): ?string
    {
        $text = mb_strtolower($clause);

        return match (true) {
            (bool) preg_match('/\b(?:restaurants?|dining|where\s+(?:to|can\s+(?:i|we))\s+eat|good\s+to\s+eat|places?\s+to\s+eat|eat\s+(?:in|at|there)|kainan|kumain|makakain|cafes?|food\s+(?:spots?|places?|strip))\b/u', $text) => 'dining',
            (bool) preg_match('/\b(?:get(?:ting)?\s+around|local\s+transport(?:ation)?|tricycles?|commute|paano\s+(?:umikot|mag-?commute)|transportation\s+(?:in|around))\b/u', $text) => 'local_transport',
            (bool) preg_match('/\b(?:how\s+(?:do|can|would)\s+(?:i|we)\s+(?:get|go|travel)\s+to|how\s+to\s+(?:get|go)\s+(?:to|there)|getting\s+(?:to|there)|paano\s+(?:pumunta|pupunta|makapunta)|papunta\s+sa|which\s+airport)\b/u', $text) => 'destination_access',
            (bool) preg_match('/\b(?:environmental\s+fee|visitor\s+fee|ecotourism|terminal\s+fee|best\s+time\s+to\s+(?:visit|go)|when\s+(?:to|should\s+(?:i|we))\s+visit|packing|what\s+to\s+(?:bring|pack)|travel\s+tips|tips\s+for|advisory|ferry\s+(?:suspension|strike|schedule\s+changes?))\b/u', $text) => 'practical_guidance',
            (bool) preg_match('/\b(?:wheelchair|accessib(?:le|ility)|pwd|mobility)\b/u', $text) => 'accessibility',
            (bool) preg_match('/\b(?:secret|hidden|best)\s+(?:beach(?:es)?|coves?|spots?)\b|\b(?:viewpoints?|landmarks?|tourist\s+(?:spots?|attractions?)|attractions)\b/u', $text) => 'attractions',
            default => null,
        };
    }

    /**
     * @return array{status: string, reason: string, mode: string, articles: list<array<string, mixed>>, notes: list<string>}
     */
    public function retrieve(string $query, ?int $destinationId, ?string $topic = null): array
    {
        $candidates = KnowledgeArticle::citable()
            ->forDestination($destinationId)
            ->with('destination:id,name')
            ->limit(200)
            ->get()
            ->filter(fn (KnowledgeArticle $article): bool => $this->excerpt($article->body) !== ''
                && ! KnowledgeArticle::containsUnsafeGuidance($article->title.' '.$article->source_label));
        if ($candidates->isEmpty()) {
            return $this->result('no_match', 'no_reviewed_guidance', 'none');
        }

        $queryTokens = $this->tokens($query, $candidates);
        $scores = [];
        foreach ($candidates as $article) {
            $scores[$article->id] = $this->lexicalScore($queryTokens, $article, $topic);
        }
        $mode = 'lexical';

        $semantic = $this->semanticScores($query, $candidates);
        if ($semantic !== null) {
            $mode = 'hybrid';
            foreach ($semantic as $id => $similarity) {
                // A semantic hit only counts within the asked topic (or when
                // no topic was detected), so a nearby but different subject
                // cannot answer the question.
                $article = $candidates->firstWhere('id', $id);
                if ($similarity >= self::SEMANTIC_THRESHOLD && ($topic === null || $article?->category === $topic)) {
                    $scores[$id] = max($scores[$id] ?? 0.0, $similarity);
                }
            }
        }

        arsort($scores);
        $best = (float) reset($scores);
        $threshold = self::LEXICAL_THRESHOLD;
        if ($best < $threshold) {
            return $this->result('no_match', $topic === null ? 'no_reviewed_guidance' : 'no_reviewed_guidance_for_topic', $mode);
        }

        $selected = [];
        $notes = [];
        foreach ($scores as $id => $score) {
            if (count($selected) >= self::MAX_ARTICLES || $score < $threshold || ($selected !== [] && ($best - $score) > self::AMBIGUITY_MARGIN)) {
                break;
            }
            $selected[] = [$candidates->firstWhere('id', $id), $score];
        }
        $labels = array_unique(array_map(fn (array $pair): string => $pair[0]->source_label, $selected));
        if (count($selected) > 1 && count($labels) > 1 && $selected[0][0]->category === $selected[1][0]->category) {
            $notes[] = 'These come from different sources and may not agree; please confirm the details before relying on them.';
        }

        $retrievedAt = now()->toIso8601String();
        $articles = array_map(fn (array $pair): array => $this->evidence($pair[0], $pair[1], $mode, $retrievedAt), $selected);

        return $this->result('matched', 'reviewed_guidance_found', $mode, $articles, $notes);
    }

    /**
     * Deterministic chat payload for a knowledge task.
     *
     * @return array<string, mixed>
     */
    public function answer(string $query, ?int $destinationId, ?string $destinationName, ?string $topic): array
    {
        try {
            $result = $this->retrieve($query, $destinationId, $topic);
        } catch (Throwable $exception) {
            Log::warning('knowledge_retrieval_failed', ['exception' => $exception::class]);

            return [
                'reply' => 'I could not check SunnyTrips travel guidance right now. Please try this part again shortly.',
                'retrieval_outcome' => RetrievalOutcome::make(RetrievalOutcome::TemporarilyUnavailable, 'knowledge_unavailable'),
            ];
        }

        $where = $destinationName ? ' for '.$destinationName : '';
        $subject = self::TOPIC_PHRASES[$topic] ?? 'this topic';
        if ($result['status'] !== 'matched') {
            return [
                'reply' => "I don't have reviewed SunnyTrips guidance about {$subject}{$where} yet, so I won't guess. I can still help with the hotels, activities, packages and transfers"
                    .($destinationName ? " in {$destinationName}." : '.'),
                'knowledge' => [],
                'retrieval_outcome' => RetrievalOutcome::make(RetrievalOutcome::NoMatch, $result['reason'], suggestedActions: [
                    ['id' => 'search-activities', 'label' => 'Show activities', 'prompt' => 'Show activities'.($destinationName ? ' in '.$destinationName : '')],
                ]),
            ];
        }

        $parts = [];
        foreach ($result['articles'] as $article) {
            $reviewed = $article['reviewed_at'] ? 'reviewed '.$article['reviewed_at'] : 'review date unknown';
            // Admin text is escaped by the widget; the source label cannot
            // break the markdown emphasis it sits in.
            $label = str_replace(['*', '[', ']'], '', $article['source_label']);
            $parts[] = '**'.$article['title'].'** — '.$article['excerpt']."\n*Source: ".$label.' ('.$reviewed.')*'
                .($article['source_url'] ? ' [Reference]('.$article['source_url'].')' : '');
        }
        $parts = [...$parts, ...$result['notes']];
        $parts[] = 'This is general guidance, not a booking, schedule or price confirmation.';

        return [
            'reply' => implode("\n\n", $parts),
            'knowledge' => $result['articles'],
            'retrieval_outcome' => RetrievalOutcome::make(RetrievalOutcome::Matched, 'reviewed_guidance_found'),
            'knowledge_mode' => $result['mode'],
        ];
    }

    /**
     * Re-check stored citations when a conversation is restored, so an old
     * answer whose source was since edited, withdrawn or expired is labelled
     * instead of being presented as newly verified.
     *
     * @param  list<array<string, mixed>>  $citations
     * @return list<array<string, mixed>>
     */
    public function refreshCitations(array $citations): array
    {
        $ids = array_values(array_filter(array_map(fn ($c) => is_array($c) ? (int) ($c['id'] ?? 0) : 0, $citations)));
        if ($ids === []) {
            return $citations;
        }
        $current = KnowledgeArticle::whereKey($ids)->get()->keyBy('id');

        return array_map(function ($citation) use ($current) {
            if (! is_array($citation) || ! isset($citation['id'])) {
                return $citation;
            }
            $article = $current->get((int) $citation['id']);
            $citation['current_status'] = match (true) {
                $article === null || ! $article->isCitable() => 'withdrawn',
                $article->contentHash() !== ($citation['content_hash'] ?? null) => 'changed',
                default => 'current',
            };

            return $citation;
        }, $citations);
    }

    /** @return array<string, mixed> */
    private function evidence(KnowledgeArticle $article, float $score, string $mode, string $retrievedAt): array
    {
        $url = $article->source_url;
        $safeUrl = is_string($url) && preg_match('#^https?://#i', $url) ? $url : null;

        return [
            'id' => $article->id,
            'title' => $article->title,
            'category' => $article->category,
            'destination' => $article->destination?->name,
            'excerpt' => $this->excerpt($article->body),
            'source_label' => $article->source_label,
            'source_url' => $safeUrl,
            'reviewed_at' => $article->reviewed_at?->toDateString(),
            'expires_at' => $article->expires_at?->toDateString(),
            'content_hash' => $article->contentHash(),
            'retrieved_at' => $retrievedAt,
            'score' => round($score, 3),
            'mode' => $mode,
        ];
    }

    /**
     * Suppress recognized English/Filipino instructions and promotions.
     * This conservative pattern filter supplements human content review;
     * it cannot identify every possible adversarial phrasing.
     */
    public function excerpt(string $body): string
    {
        $sentences = preg_split('/(?<=[.!?])\s+|[\r\n]+/u', trim(strip_tags($body))) ?: [];
        $kept = array_filter($sentences, fn (string $sentence): bool => ! KnowledgeArticle::containsUnsafeGuidance($sentence));
        $text = trim(implode(' ', $kept));

        return mb_strlen($text) > self::MAX_BODY_CHARS ? rtrim(mb_substr($text, 0, self::MAX_BODY_CHARS - 1)).'…' : $text;
    }

    /** @param Collection<int, KnowledgeArticle> $candidates
     * @return list<string>
     */
    private function tokens(string $text, Collection $candidates): array
    {
        $destinationWords = [];
        foreach ($candidates as $article) {
            foreach ($this->words((string) $article->destination?->name) as $word) {
                $destinationWords[$word] = true;
            }
        }

        return array_values(array_filter(
            array_unique($this->words($text)),
            fn (string $word): bool => ! isset($destinationWords[$word])
        ));
    }

    /** @return list<string> */
    private function words(string $text): array
    {
        preg_match_all('/[\p{L}\p{N}]+/u', mb_strtolower($text), $matches);

        $words = array_filter($matches[0], fn (string $w): bool => mb_strlen($w) > 1 && ! in_array($w, self::STOPWORDS, true));

        return array_values(array_unique(array_map(static fn (string $word): string => match ($word) {
            'eat', 'eating', 'dining', 'restaurants', 'restaurant', 'kainan', 'kumain', 'makakain', 'eateries', 'eatery' => 'dining',
            'pumunta', 'papunta', 'getting', 'get', 'reach' => 'access',
            'tricycles', 'tricycle' => 'tricycle',
            default => $word,
        }, $words)));
    }

    /** @param list<string> $queryTokens */
    private function lexicalScore(array $queryTokens, KnowledgeArticle $article, ?string $topic): float
    {
        if ($queryTokens === []) {
            return 0.0;
        }
        if ($topic !== null && $article->category !== $topic) {
            return 0.0;
        }
        $articleWords = array_flip($this->words($article->title.' '.$this->excerpt($article->body)));
        $hits = 0.0;
        foreach ($queryTokens as $token) {
            $stem = rtrim($token, 's');
            if (isset($articleWords[$token]) || isset($articleWords[$stem]) || isset($articleWords[$stem.'s'])) {
                $hits += 1.0;
            }
        }
        if ($hits === 0.0) {
            return 0.0;
        }
        $score = $hits / count($queryTokens);
        if ($topic !== null) {
            $score = $article->category === $topic ? min(1.0, $score + 0.25) : $score * 0.5;
        }

        return round($score, 4);
    }

    /**
     * Cosine similarity for articles whose embedding is current; null when
     * the provider or embeddings are unavailable.
     *
     * @param  Collection<int, KnowledgeArticle>  $candidates
     * @return array<int, float>|null
     */
    private function semanticScores(string $query, Collection $candidates): ?array
    {
        $fresh = $candidates->filter(fn (KnowledgeArticle $article): bool => ! $article->embeddingIsStale())->pluck('id')->all();
        if ($fresh === []) {
            return null;
        }
        try {
            $vector = $this->gemini->formatVectorForDb($this->gemini->generateEmbedding($query, 'RETRIEVAL_QUERY'));
            if (! $vector) {
                return null;
            }

            return KnowledgeArticle::whereKey($fresh)
                ->selectRaw('id, 1.0 - (embedding <=> ?) AS similarity', [$vector])
                ->pluck('similarity', 'id')
                ->map(fn ($similarity): float => (float) $similarity)
                ->all();
        } catch (Throwable $exception) {
            Log::warning('knowledge_semantic_failed', ['exception' => $exception::class]);

            return null;
        }
    }

    /**
     * @param  list<array<string, mixed>>  $articles
     * @param  list<string>  $notes
     * @return array{status: string, reason: string, mode: string, articles: list<array<string, mixed>>, notes: list<string>}
     */
    private function result(string $status, string $reason, string $mode, array $articles = [], array $notes = []): array
    {
        return ['status' => $status, 'reason' => $reason, 'mode' => $mode, 'articles' => $articles, 'notes' => $notes];
    }
}
