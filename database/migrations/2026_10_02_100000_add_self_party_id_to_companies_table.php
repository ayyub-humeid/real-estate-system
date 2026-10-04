<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('companies', function (Blueprint $table) {
            $table->foreignId('self_party_id')
                ->nullable()
                ->unique()
                ->constrained('parties')
                ->nullOnDelete();
        });

        // Backfill only the unambiguous legacy representation created by the
        // previous implementation: a company-owned Party of type "company"
        // whose name exactly matched the Company name. Ambiguous historical
        // records are deliberately left untouched rather than guessed.
        foreach (DB::table('companies')->select('id', 'name')->orderBy('id')->get() as $company) {
            $partyIds = DB::table('parties')
                ->where('company_id', $company->id)
                ->where('type', 'company')
                ->where('name', $company->name)
                ->limit(2)
                ->pluck('id');

            if ($partyIds->count() === 1) {
                DB::table('companies')->where('id', $company->id)->update([
                    'self_party_id' => $partyIds->first(),
                ]);
            }
        }
    }

    public function down(): void
    {
        Schema::table('companies', function (Blueprint $table) {
            $table->dropUnique(['self_party_id']);
            $table->dropForeign(['self_party_id']);
            $table->dropColumn('self_party_id');
        });
    }
};
