<?php

use App\Support\ContactNormalizer;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Lets a lead be matched to an existing client by phone or email.
 * Not unique: two real clients can share a family phone, so a match is
 * resolved by a person, never silently merged.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('parties', function (Blueprint $table): void {
            $table->string('normalized_phone', 32)->nullable()->after('phone');
            $table->string('normalized_email')->nullable()->after('email');
            $table->index(['company_id', 'normalized_phone'], 'parties_company_phone_idx');
            $table->index(['company_id', 'normalized_email'], 'parties_company_email_idx');
        });

        // Backfill from the trustworthy values already stored.
        DB::table('parties')->select(['id', 'phone', 'email'])->orderBy('id')->chunkById(200, function ($parties): void {
            foreach ($parties as $party) {
                DB::table('parties')->where('id', $party->id)->update([
                    'normalized_phone' => ContactNormalizer::phone($party->phone),
                    'normalized_email' => ContactNormalizer::email($party->email),
                ]);
            }
        });
    }

    public function down(): void
    {
        Schema::table('parties', function (Blueprint $table): void {
            $table->dropIndex('parties_company_phone_idx');
            $table->dropIndex('parties_company_email_idx');
            $table->dropColumn(['normalized_phone', 'normalized_email']);
        });
    }
};
