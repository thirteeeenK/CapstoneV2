<?php

namespace App\Services\Chat;

use App\Models\Faq;
use App\Services\GeminiService;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

class FaqService
{
    public function __construct(protected GeminiService $gemini) {}

    /**
     * Find the best matching active FAQ for a user query.
     * Hybrid: keyword/lexical first, then embedding fallback.
     * Catalog searches keep their intent: only GENERAL_TALK queries are
     * FAQ-eligible. The semantic winner needs score >= 0.7 with a >= 0.05
     * margin over the runner-up when competing FAQs exist. The old
     * token-overlap veto is gone: genuine paraphrases ("settle my bill"
     * for a payment FAQ) share no tokens yet are the same question.
     */
    public function findBestMatch(string $query, ?string $eligibleIntent = null): ?Faq
    {
        $query = $this->normalize($query);
        if ($query === '') {
            return null;
        }

        $faqs = Faq::where('is_active', true)->get();
        if ($faqs->isEmpty()) {
            return null;
        }

        // A verbatim known question is always answerable, whatever the
        // keyword classifier says ("Does SunnyTrips support airline ticket
        // booking?" classifies ROOM_SEARCH, but the user asked an FAQ).
        // This is not hijack: the user typed the question itself.
        if ($exact = $faqs->first(fn ($faq) => $this->normalize($faq->question) === $query)) {
            return $exact;
        }

        if ($eligibleIntent !== null && $eligibleIntent !== IntentRouter::GENERAL_TALK) {
            return null;
        }

        $queryTokens = $this->tokenize($query);
        if ($queryTokens->count() < 2) {
            // Allowlist bare "discount" (single token) to hit discount FAQ — otherwise "discount" would be GENERAL_TALK and ask destination
            $lowerCheck = mb_strtolower($query);
            if (str_contains($lowerCheck, 'discount')) {
                $discountFaq = Faq::where('question', 'Does SunnyTrips offer discounts?')->where('is_active', true)->first();
                if ($discountFaq) {
                    return $discountFaq;
                }
            }

            return null;
        }

        $best = $this->bestLexicalMatch($query, $faqs, $queryTokens);
        if ($best && $best['score'] >= 0.5) {
            return $best['faq'];
        }

        $semantic = $this->bestSemanticMatch($query, $faqs);
        if ($semantic && $semantic['score'] >= 0.7) {
            return $semantic['faq'];
        }

        return null;
    }

    /**
     * Score each FAQ by token overlap between query and question + keywords.
     */
    protected function bestLexicalMatch(string $query, $faqs, $queryTokens): ?array
    {
        if ($queryTokens->isEmpty()) {
            return null;
        }

        $best = null;
        foreach ($faqs as $faq) {
            $haystack = $this->normalize($faq->question.' '.($faq->keywords ?? ''));
            $haystackTokens = $this->tokenize($haystack);
            if ($haystackTokens->isEmpty()) {
                continue;
            }

            $intersection = $queryTokens->intersect($haystackTokens)->count();
            $union = $queryTokens->merge($haystackTokens)->unique()->count();
            $jaccard = $union > 0 ? $intersection / $union : 0;

            $queryCoverage = $queryTokens->count() > 0 ? $intersection / $queryTokens->count() : 0;
            $score = ($jaccard * 0.6) + ($queryCoverage * 0.4);

            if ($best === null || $score > $best['score']) {
                $best = ['faq' => $faq, 'score' => $score];
            }
        }

        return $best;
    }

    /**
     * Fallback semantic match using pgvector cosine distance.
     * Requires a meaningful token overlap with the FAQ so an unrelated
     * query can never latch onto the closest embedded FAQ.
     */
    protected function bestSemanticMatch(string $query, $faqs): ?array
    {
        if ($faqs->whereNotNull('embedding')->isEmpty()) {
            return null;
        }

        try {
            $scored = $this->gemini->searchFaqs($query, 3);
            if (empty($scored)) {
                return null;
            }

            $top = $scored[0];
            if ($top['score'] < 0.7) {
                return null;
            }

            // Ambiguous winners stay silent: require a margin over the
            // runner-up when competing embedded FAQs exist.
            if (count($scored) >= 2 && ($top['score'] - $scored[1]['score']) < 0.05) {
                return null;
            }

            return [
                'faq' => $top['item'],
                'score' => $top['score'],
            ];
        } catch (\Exception $e) {
            Log::warning('FAQ semantic match failed: '.$e->getMessage());

            return null;
        }
    }

    protected function normalize(string $text): string
    {
        $text = mb_strtolower($text);
        $text = preg_replace('/[^\p{L}\p{N}\s]/u', ' ', $text);

        return trim(preg_replace('/\s+/', ' ', $text));
    }

    protected function tokenize(string $text): Collection
    {
        $stopwords = ['a', 'an', 'the', 'is', 'are', 'was', 'were', 'be', 'been', 'being', 'have', 'has', 'had', 'do', 'does', 'did', 'will', 'would', 'could', 'should', 'may', 'might', 'must', 'can', 'need', 'dare', 'ought', 'used', 'to', 'of', 'in', 'for', 'on', 'with', 'at', 'by', 'from', 'as', 'into', 'through', 'during', 'before', 'after', 'above', 'below', 'between', 'under', 'again', 'further', 'then', 'once', 'here', 'there', 'when', 'where', 'why', 'how', 'all', 'each', 'few', 'more', 'most', 'other', 'some', 'such', 'no', 'nor', 'not', 'only', 'own', 'same', 'so', 'than', 'too', 'very', 'just', 'and', 'but', 'if', 'or', 'because', 'until', 'while', 'sunnytrips', 'sunny', 'trips', 'does', 'it', 'they', 'we', 'you', 'he', 'she', 'this', 'that', 'these', 'those', 'i', 'me', 'my', 'myself'];

        $tokens = collect(explode(' ', $this->normalize($text)))
            ->filter(fn ($t) => $t !== '' && ! in_array($t, $stopwords, true) && mb_strlen($t) >= 2);

        return $tokens->values();
    }
}
