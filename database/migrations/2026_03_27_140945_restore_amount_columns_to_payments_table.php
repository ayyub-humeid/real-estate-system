<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->decimal('amount', 10, 2)->default(0)->after('lease_id');
            $table->decimal('remaining_amount', 10, 2)->default(0)->after('paid_amount');
        });

        // Try to populate initial 'amount' from lease rent_amount for existing records
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('UPDATE payments SET amount = leases.rent_amount, remaining_amount = GREATEST(0, leases.rent_amount - payments.paid_amount) FROM leases WHERE payments.lease_id = leases.id');
        } else {
            DB::table('payments')
                ->join('leases', 'payments.lease_id', '=', 'leases.id')
                ->update([
                    'payments.amount' => DB::raw('leases.rent_amount'),
                    'payments.remaining_amount' => DB::raw('GREATEST(0, leases.rent_amount - payments.paid_amount)')
                ]);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->dropColumn(['amount', 'remaining_amount']);
        });
    }
};
