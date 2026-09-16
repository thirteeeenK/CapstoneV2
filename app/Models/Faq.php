<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

#[Fillable([
    'question',
    'answer',
    'keywords',
    'category',
    'sort_order',
    'is_active',
])]
class Faq extends Model
{
    use HasFactory;

    protected $casts = [
        'is_active' => 'boolean',
        'sort_order' => 'integer',
    ];

    /**
     * Render the Markdown answer as safe HTML (bold, bullet lists, paragraphs).
     * The raw text is escaped first so admin-entered HTML can never execute.
     */
    protected function renderedAnswer(): Attribute
    {
        return Attribute::get(fn () => self::renderMarkdown($this->answer ?? ''));
    }

    public static function renderMarkdown(string $text): string
    {
        $escaped = (string) preg_replace(
            '/\*\*(.+?)\*\*/s',
            '<strong>$1</strong>',
            e($text)
        );

        $lines = preg_split('/\r\n|\r|\n/', $escaped) ?: [];
        $html = '';
        $inList = false;
        $paragraph = [];

        $flushParagraph = function () use (&$html, &$paragraph): void {
            $content = trim(implode(' ', $paragraph));
            $paragraph = [];
            if ($content !== '') {
                $html .= '<p class="mb-2 last:mb-0">'.$content.'</p>';
            }
        };

        foreach ($lines as $line) {
            $trimmed = trim((string) $line);

            if (str_starts_with($trimmed, '- ')) {
                $flushParagraph();
                if (! $inList) {
                    $html .= '<ul class="list-disc pl-5 space-y-1 my-2">';
                    $inList = true;
                }
                $html .= '<li>'.ltrim(substr($trimmed, 2)).'</li>';
            } elseif ($trimmed === '') {
                $flushParagraph();
                if ($inList) {
                    $html .= '</ul>';
                    $inList = false;
                }
            } else {
                if ($inList) {
                    $html .= '</ul>';
                    $inList = false;
                }
                $paragraph[] = $trimmed;
            }
        }

        $flushParagraph();
        if ($inList) {
            $html .= '</ul>';
        }

        return $html;
    }
}
