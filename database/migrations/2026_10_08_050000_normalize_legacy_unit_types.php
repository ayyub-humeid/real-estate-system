<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Normalise values created before UnitType became the shared catalogue.
     * Unknown legacy labels become the explicit `other` type; null stays null
     * for incomplete draft Units.
     */
    public function up(): void
    {
        $canonical = ['apartment', 'studio', 'duplex', 'penthouse', 'villa', 'townhouse', 'office', 'retail', 'warehouse', 'parking', 'storage', 'other'];

        DB::table('units')->select(['id', 'type'])->orderBy('id')->each(function (object $unit) use ($canonical): void {
            if ($unit->type === null) {
                return;
            }

            $normalised = strtolower(trim((string) $unit->type));
            $normalised = preg_replace('/\s+/', ' ', str_replace(['-', '_'], ' ', $normalised));
            $normalised = match ($normalised) {
                'shop' => 'retail',
                'parking space' => 'parking',
                'storage room' => 'storage',
                default => in_array($normalised, $canonical, true) ? $normalised : 'other',
            };

            if ($normalised !== $unit->type) {
                DB::table('units')->where('id', $unit->id)->update(['type' => $normalised]);
            }
        });
    }

    public function down(): void
    {
        // A data-quality normalisation is intentionally irreversible.
    }
};
