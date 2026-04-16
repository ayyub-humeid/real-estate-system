<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Add performance indexes for frequently filtered columns.
     * Improves speed on status/is_active filters and company lookups.
     */
    public function up(): void
    {
        // Subscription indexes
        Schema::table('subscriptions', function (Blueprint $table) {
            // Filter by status (e.g. "active", "trailing") — most common query
            $table->index('status', 'idx_subscriptions_status');

            // Filter/sort by expiry date for "expires soon" checks
            $table->index('ends_at', 'idx_subscriptions_ends_at');

            // Composite: fastest way to check a specific company's active subscription
            $table->index(['company_id', 'status'], 'idx_subscriptions_company_status');
        });

        // Plan indexes
        Schema::table('plans', function (Blueprint $table) {
            // Filter active/inactive plans in the dropdown
            $table->index('is_active', 'idx_plans_is_active');

            // Filter by billing cycle (monthly vs yearly)
            $table->index('billing_cycle', 'idx_plans_billing_cycle');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('subscriptions', function (Blueprint $table) {
            $table->dropIndex('idx_subscriptions_status');
            $table->dropIndex('idx_subscriptions_ends_at');
            $table->dropIndex('idx_subscriptions_company_status');
        });

        Schema::table('plans', function (Blueprint $table) {
            $table->dropIndex('idx_plans_is_active');
            $table->dropIndex('idx_plans_billing_cycle');
        });
    }
};
