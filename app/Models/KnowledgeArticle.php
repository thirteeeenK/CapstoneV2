<?php

namespace App\Models;

use Database\Factories\KnowledgeArticleFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Curated, non-bookable travel guidance. An article can substantiate a chat
 * answer only while it is published, reviewed and unexpired; its text is
 * untrusted data and never becomes a card, price or availability claim.
 */
#[Fillable([
    'title',
    'body',
    'category',
    'destination_id',
    'source_label',
    'source_url',
    'reviewed_at',
    'expires_at',
    'status',
    'reviewed_by',
])]
#[Hidden(['embedding'])]
class KnowledgeArticle extends Model
{
    /** @use HasFactory<KnowledgeArticleFactory> */
    use HasFactory;

    public const STATUSES = ['draft', 'published', 'archived'];

    public const CATEGORIES = [
        'destination_access' => 'Destination access',
        'local_transport' => 'Local transport',
        'dining' => 'Dining',
        'attractions' => 'Attractions',
        'practical_guidance' => 'Practical guidance',
        'accessibility' => 'Accessibility guidance',
        'activity_requirements' => 'Activity requirements',
    ];

    protected $casts = [
        'reviewed_at' => 'date',
        'expires_at' => 'date',
        'embedded_at' => 'datetime',
    ];

    public function destination(): BelongsTo
    {
        return $this->belongsTo(DestinationModel::class, 'destination_id');
    }

    /** Published, reviewed and not past its expiry date. */
    public function scopeCitable(Builder $query): Builder
    {
        return $query->where('status', 'published')
            ->whereNotNull('reviewed_at')
            ->where(fn (Builder $q) => $q->whereNull('expires_at')->orWhereDate('expires_at', '>=', today()));
    }

    /** Articles for one destination plus general (destination-less) guidance. */
    public function scopeForDestination(Builder $query, ?int $destinationId): Builder
    {
        return $destinationId === null
            ? $query->whereNull('destination_id')
            : $query->where(fn (Builder $q) => $q->where('destination_id', $destinationId)->orWhereNull('destination_id'));
    }

    public function isCitable(): bool
    {
        return $this->status === 'published'
            && $this->reviewed_at !== null
            && ($this->expires_at === null || $this->expires_at->gte(today()));
    }

    /** Hash of the text a citation or embedding was made from. */
    public function contentHash(): string
    {
        return hash('sha256', $this->title."\n".$this->body);
    }

    public function embeddingText(): string
    {
        return implode("\n", array_filter([
            'Title: '.$this->title,
            'Category: '.(self::CATEGORIES[$this->category] ?? $this->category),
            $this->destination?->name ? 'Destination: '.$this->destination->name : null,
            $this->body,
        ]));
    }

    public function embeddingIsStale(): bool
    {
        return $this->embedding === null
            || $this->embedding_source_hash !== sha1($this->embeddingText().config('services.gemini.embedding_model'));
    }

    /**
     * Instruction-like text inside an article (prompt injection) that admins
     * should remove; such sentences are never quoted to travelers.
     */
    public function hasInstructionLikeText(): bool
    {
        return self::containsUnsafeGuidance($this->title.' '.$this->body.' '.$this->source_label);
    }

    /** Recognized English/Filipino instruction and promotion patterns; not a universal injection detector. */
    public static function containsUnsafeGuidance(string $text): bool
    {
        $text = html_entity_decode(strip_tags($text), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $text = (string) preg_replace('/[\x{200B}-\x{200D}\x{FEFF}]/u', '', $text);

        return preg_match(self::INSTRUCTION_PATTERN, $text) === 1
            || preg_match('/\b\d+\s*%\s*(?:off|discount|diskwento)\b|\b(?:discount|diskwento|promo\s*(?:code|kod(?:e|igo))|for\s+free|libre(?:ng)?)\b/iu', $text) === 1;
    }

    public const INSTRUCTION_PATTERN = '/\b(?:system|assistant|developer)\s*:|\bignore\s+(?:all\s+)?(?:previous|prior|above)\b|\b(?:tell|inform)\s+(?:the\s+)?users?\b|\byou\s+must\s+(?:say|tell|answer)\b|\b(?:huwag|wag|huwag\s+nang|wag\s+nang)\s+pansinin\b|\b(?:balewalain|kalimutan|ignora(?:hin)?)\b.{0,80}\b(?:utos|tagubilin|panuto|instructions?|rules?)\b|\b(?:sabihin|isagot|ipaalam)\b.{0,40}\b(?:user|gumagamit|customer|kustomer)\b/iu';
}
