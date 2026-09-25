<?php

namespace Tests\Support;

/**
 * Pure metric functions for the offline chatbot eval harness.
 * Each result row: ['expect_intent' => ?string, 'expect_destination' => ?string,
 *   'expect_ids' => int[], 'forbid_ids' => int[], 'intent' => ?string,
 *   'retrieved_ids' => int[], 'destinations' => string[], 'latency_ms' => ?float].
 */
class ChatEvalMetrics
{
    public static function intentAccuracy(array $results): ?float
    {
        $scoped = array_values(array_filter($results, fn ($r) => ! empty($r['expect_intent'])));
        if ($scoped === []) {
            return null;
        }
        $hits = count(array_filter($scoped, fn ($r) => ($r['intent'] ?? null) === $r['expect_intent']));

        return $hits / count($scoped);
    }

    public static function recallAt(array $results, int $k): ?float
    {
        $scoped = array_values(array_filter($results, fn ($r) => ! empty($r['expect_ids'])));
        if ($scoped === []) {
            return null;
        }
        $hits = 0;
        foreach ($scoped as $r) {
            $top = array_slice($r['retrieved_ids'] ?? [], 0, $k);
            foreach ((array) $r['expect_ids'] as $id) {
                if (in_array($id, $top, true)) {
                    $hits++;
                    break;
                }
            }
        }

        return $hits / count($scoped);
    }

    public static function mrr(array $results): ?float
    {
        $scoped = array_values(array_filter($results, fn ($r) => ! empty($r['expect_ids'])));
        if ($scoped === []) {
            return null;
        }
        $sum = 0.0;
        foreach ($scoped as $r) {
            $rank = null;
            foreach ((array) ($r['retrieved_ids'] ?? []) as $i => $id) {
                if (in_array($id, (array) $r['expect_ids'], true)) {
                    $rank = $i + 1;
                    break;
                }
            }
            $sum += $rank === null ? 0.0 : 1.0 / $rank;
        }

        return $sum / count($scoped);
    }

    public static function destinationMatchRate(array $results): ?float
    {
        $scoped = array_values(array_filter($results, fn ($r) => ! empty($r['expect_destination'])));
        if ($scoped === []) {
            return null;
        }
        $hits = count(array_filter($scoped, fn ($r) => in_array($r['expect_destination'], $r['destinations'] ?? [], true)));

        return $hits / count($scoped);
    }

    public static function wrongDestinationRate(array $results): ?float
    {
        $scoped = array_values(array_filter($results, fn ($r) => ! empty($r['expect_destination']) && ! empty($r['destinations'] ?? [])));
        if ($scoped === []) {
            return null;
        }
        $bad = count(array_filter($scoped, fn ($r) => count(array_filter(
            (array) ($r['destinations'] ?? []),
            fn ($d) => mb_strtolower(trim((string) $d)) !== mb_strtolower(trim((string) $r['expect_destination']))
        )) > 0));

        return $bad / count($scoped);
    }

    public static function noResultRate(array $results): float
    {
        if ($results === []) {
            return 0.0;
        }
        $empty = count(array_filter($results, fn ($r) => empty($r['retrieved_ids'])));

        return $empty / count($results);
    }

    public static function constraintViolationRate(array $results): ?float
    {
        return self::booleanFailureRate($results, 'constraint_compliant');
    }

    public static function unsupportedClaimRate(array $results): ?float
    {
        return self::booleanFailureRate($results, 'claims_grounded');
    }

    public static function validAbstentionRate(array $results): ?float
    {
        $scoped = array_values(array_filter($results, fn ($row) => ($row['expect_abstention'] ?? null) !== null));
        if ($scoped === []) {
            return null;
        }
        $hits = count(array_filter($scoped, fn ($row) => (bool) ($row['abstained'] ?? false) === (bool) $row['expect_abstention']));

        return $hits / count($scoped);
    }

    public static function multiTurnConsistencyRate(array $results): ?float
    {
        $scoped = array_values(array_filter($results, fn ($row) => ($row['scope_consistent'] ?? null) !== null));
        if ($scoped === []) {
            return null;
        }

        return count(array_filter($scoped, fn ($row) => $row['scope_consistent'] === true)) / count($scoped);
    }

    public static function latencyP50(array $results): ?float
    {
        return self::percentile(array_values(array_filter(array_column($results, 'latency_ms'), fn ($v) => $v !== null)), 50);
    }

    public static function latencyP95(array $results): ?float
    {
        return self::percentile(array_values(array_filter(array_column($results, 'latency_ms'), fn ($v) => $v !== null)), 95);
    }

    protected static function percentile(array $values, float $p): ?float
    {
        if ($values === []) {
            return null;
        }
        sort($values);
        $index = (int) ceil($p / 100 * count($values)) - 1;

        return (float) $values[max(0, min($index, count($values) - 1))];
    }

    protected static function booleanFailureRate(array $results, string $field): ?float
    {
        $scoped = array_values(array_filter($results, fn ($row) => array_key_exists($field, $row)));
        if ($scoped === []) {
            return null;
        }

        return count(array_filter($scoped, fn ($row) => $row[$field] === false)) / count($scoped);
    }
}
