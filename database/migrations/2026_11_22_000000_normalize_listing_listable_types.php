<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Listings must store the short type (`unit`, `planned_unit`) from the morph map. Some rows were
 * saved with the full class name, which made accepted offers unable to find their unit.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('listings')->where('listable_type', 'App\Models\Unit')->update(['listable_type' => 'unit']);
        DB::table('listings')->where('listable_type', 'App\Models\ProjectPlannedUnit')->update(['listable_type' => 'planned_unit']);
    }

    public function down(): void
    {
        // Nothing to undo: the short names are the correct values.
    }
};
