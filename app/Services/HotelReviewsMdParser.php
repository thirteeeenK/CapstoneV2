<?php

namespace App\Services;

/**
 * Parses database/seeders/data/hotel_reviews.md into structured rows.
 *
 * Source format (repeated per hotel, hotel-level only — no room data):
 *
 *   ### Hotel Name
 *   - **Reviewer**: Jane
 *   - **Rating**: ★★★★½ (4.5/5) | 4.0/5.0 | 10/10
 *   - **Review**: Free text…
 *
 * Reviewer names are reproduced verbatim as displayed on the source
 * platforms (generic labels like "Guest" are kept, never invented).
 * Fractional ratings are normalised to a /5 scale and rounded half-up to
 * the integer 1–5 range the reviews table requires. The mapping is
 * reported by the import command and must be disclosed in methodology.
 */
class HotelReviewsMdParser
{
    /**
     * @return array{reviews: list<array{hotel: string, reviewer: string, rating_raw: string, rating: int, comment: string}>, errors: list<string>}
     */
    public static function parse(string $path): array
    {
        $reviews = [];
        $errors = [];

        if (! is_file($path)) {
            return ['reviews' => [], 'errors' => ["File not found: {$path}"]];
        }

        $lines = preg_split('/\r\n|\r|\n/', (string) file_get_contents($path)) ?: [];
        $hotel = null;
        $record = null;
        $lineNo = 0;

        $flush = function () use (&$record, &$reviews, &$errors, &$hotel, &$lineNo): void {
            if ($record === null) {
                return;
            }
            if ($hotel === null) {
                $errors[] = "Line {$lineNo}: review block before any '### Hotel' heading — skipped.";
            } elseif ($record['reviewer'] === '') {
                $errors[] = "Line {$lineNo}: missing reviewer for '{$hotel}' — skipped.";
            } elseif (mb_strlen($record['comment']) < 10) {
                $errors[] = "Line {$lineNo}: comment too short for '{$hotel}' — skipped.";
            } elseif (mb_strlen($record['comment']) > 2000) {
                $errors[] = "Line {$lineNo}: comment over 2000 chars for '{$hotel}' — skipped.";
            } elseif ($record['rating'] === null) {
                $errors[] = "Line {$lineNo}: unparseable rating '{$record['rating_raw']}' for '{$hotel}' — skipped.";
            } else {
                $reviews[] = [
                    'hotel' => $hotel,
                    'reviewer' => $record['reviewer'],
                    'rating_raw' => $record['rating_raw'],
                    'rating' => $record['rating'],
                    'comment' => $record['comment'],
                ];
            }
            $record = null;
        };

        foreach ($lines as $i => $line) {
            $lineNo = $i + 1;
            $trimmed = trim($line);

            if (str_starts_with($trimmed, '### ')) {
                $flush();
                $hotel = trim(substr($trimmed, 4));

                continue;
            }

            if (str_starts_with($trimmed, '## ')) {
                $flush();
                $hotel = null; // destination heading — closes hotel context

                continue;
            }

            if (str_starts_with($trimmed, '- **Reviewer**:')) {
                $flush();
                $record = [
                    'reviewer' => trim(substr($trimmed, strlen('- **Reviewer**:'))),
                    'rating_raw' => '',
                    'rating' => null,
                    'comment' => '',
                ];

                continue;
            }

            if ($record !== null && str_starts_with($trimmed, '- **Rating**:')) {
                $record['rating_raw'] = trim(substr($trimmed, strlen('- **Rating**:')));
                $record['rating'] = self::mapRating($record['rating_raw']);

                continue;
            }

            if ($record !== null && str_starts_with($trimmed, '- **Review**:')) {
                $record['comment'] = trim(substr($trimmed, strlen('- **Review**:')));

                continue;
            }
        }
        $flush();

        return ['reviews' => $reviews, 'errors' => $errors];
    }

    /**
     * Normalise "4.5/5", "5.0/5.0", "10/10", "9.4/10" (and star lines
     * carrying a parenthesised decimal) to int 1–5, round half-up.
     */
    public static function mapRating(string $raw): ?int
    {
        if (! preg_match_all('/(\d+(?:\.\d+)?)\s*\/\s*(\d+(?:\.\d+)?)/', $raw, $m)) {
            return null;
        }

        $num = (float) end($m[1]);
        $den = (float) end($m[2]);

        if ($den <= 0) {
            return null;
        }

        $onFive = $den == 10 ? $num / 2 : $num * (5 / $den);

        return max(1, min(5, (int) round($onFive)));
    }
}
