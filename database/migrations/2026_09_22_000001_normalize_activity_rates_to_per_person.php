<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Normalize legacy activity rate strings to explicit per-person markers.
     *
     * Rates are per-person by business rule (unit price × pax everywhere);
     * only explicit flat markers opt out. These rewrites make the stored
     * strings say what the math already does, and drop the unparseable
     * mixed joiner/private tricycle rate in favor of the joiner price.
     */
    public function up(): void
    {
        $pairs = [
            '₱3,700' => '₱3,700/person',
            '₱3,800' => '₱3,800/person',
            '₱850' => '₱850/person',
            '₱750' => '₱750/person',
            '₱600/person (Joiner) | ₱1,500 (Private Tricycle)' => '₱600/person (Joiner)',
        ];

        foreach ($pairs as $old => $new) {
            DB::table('activities')->where('rate', $old)->update(['rate' => $new]);
        }
    }

    public function down(): void
    {
        $pairs = [
            '₱3,700/person' => '₱3,700',
            '₱3,800/person' => '₱3,800',
            '₱850/person' => '₱850',
            '₱750/person' => '₱750',
            '₱600/person (Joiner)' => '₱600/person (Joiner) | ₱1,500 (Private Tricycle)',
        ];

        foreach ($pairs as $new => $old) {
            DB::table('activities')->where('rate', $new)->update(['rate' => $old]);
        }
    }
};
