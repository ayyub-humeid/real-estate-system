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
        // 1. First, modify enum to allow BOTH 'trailing' and 'trialing' to prevent truncation
        if (DB::getDriverName() === 'pgsql') {
            DB::statement("ALTER TABLE subscriptions DROP CONSTRAINT IF EXISTS subscriptions_status_check");
            DB::statement("ALTER TABLE subscriptions ALTER COLUMN status SET DEFAULT 'trialing'");
            DB::statement("ALTER TABLE subscriptions ADD CONSTRAINT subscriptions_status_check CHECK (status IN ('active', 'expired', 'canceled', 'trailing', 'trialing', 'past_due'))");
        } else {
            DB::statement("ALTER TABLE subscriptions MODIFY COLUMN status ENUM('active', 'expired', 'canceled', 'trailing', 'trialing', 'past_due') NOT NULL DEFAULT 'trialing'");
        }

        // 2. Update existing data to the correct spelling
        DB::table('subscriptions')->where('status', 'trailing')->update(['status' => 'trialing']);

        // 3. Finally, modify enum to REMOVE the typo 'trailing' entirely
        if (DB::getDriverName() === 'pgsql') {
            DB::statement("ALTER TABLE subscriptions DROP CONSTRAINT IF EXISTS subscriptions_status_check");
            DB::statement("ALTER TABLE subscriptions ADD CONSTRAINT subscriptions_status_check CHECK (status IN ('active', 'expired', 'canceled', 'trialing', 'past_due'))");
        } else {
            DB::statement("ALTER TABLE subscriptions MODIFY COLUMN status ENUM('active', 'expired', 'canceled', 'trialing', 'past_due') NOT NULL DEFAULT 'trialing'");
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // 1. First, modify enum to allow BOTH
        if (DB::getDriverName() === 'pgsql') {
            DB::statement("ALTER TABLE subscriptions DROP CONSTRAINT IF EXISTS subscriptions_status_check");
            DB::statement("ALTER TABLE subscriptions ALTER COLUMN status SET DEFAULT 'trailing'");
            DB::statement("ALTER TABLE subscriptions ADD CONSTRAINT subscriptions_status_check CHECK (status IN ('active', 'expired', 'canceled', 'trailing', 'trialing', 'past_due'))");
        } else {
            DB::statement("ALTER TABLE subscriptions MODIFY COLUMN status ENUM('active', 'expired', 'canceled', 'trailing', 'trialing', 'past_due') NOT NULL DEFAULT 'trailing'");
        }

        // 2. Revert any 'trialing' records back to 'trailing'
        DB::table('subscriptions')->where('status', 'trialing')->update(['status' => 'trailing']);

        // 3. Revert the enum values to remove 'trialing'
        if (DB::getDriverName() === 'pgsql') {
            DB::statement("ALTER TABLE subscriptions DROP CONSTRAINT IF EXISTS subscriptions_status_check");
            DB::statement("ALTER TABLE subscriptions ADD CONSTRAINT subscriptions_status_check CHECK (status IN ('active', 'expired', 'canceled', 'trailing', 'past_due'))");
        } else {
            DB::statement("ALTER TABLE subscriptions MODIFY COLUMN status ENUM('active', 'expired', 'canceled', 'trailing', 'past_due') NOT NULL DEFAULT 'trailing'");
        }
    }
};
