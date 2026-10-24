<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * One-time repair replacing checkout's read-time fix: activities, add-ons
     * and packages are priced per traveler, so their quantity and traveler
     * count must match. Older basket rows take the larger of the two, which is
     * what the basket already used to price them.
     */
    public function up(): void
    {
        DB::table('cart_items')->whereIn('item_type', ['activity', 'addon', 'package'])
            ->where(fn ($query) => $query->whereColumn('quantity', '!=', 'selected_pax')->orWhere('quantity', '<', 1))
            ->update([
                'quantity' => DB::raw('GREATEST(1, quantity, selected_pax)'),
                'selected_pax' => DB::raw('GREATEST(1, quantity, selected_pax)'),
            ]);
    }

    /**
     * The repaired counts are the ones the basket already priced, so there is nothing to undo.
     */
    public function down(): void
    {
        //
    }
};
