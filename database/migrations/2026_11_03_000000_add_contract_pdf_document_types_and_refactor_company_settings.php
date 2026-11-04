<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('documents', function (Blueprint $table): void {
            if (! Schema::hasColumn('documents', 'document_type')) {
                $table->string('document_type', 50)->default('supporting')->index();
            }
        });

        Schema::table('company_settings', function (Blueprint $table): void {
            // These values brand legal agreements generally. The legacy lease
            // names were misleading once Contracts became the legal authority.
            if (Schema::hasColumn('company_settings', 'lease_background')) {
                $table->renameColumn('lease_background', 'contract_background');
            }
            if (Schema::hasColumn('company_settings', 'signature')) {
                $table->renameColumn('signature', 'contract_signature');
            }
            if (Schema::hasColumn('company_settings', 'lease_terms')) {
                $table->renameColumn('lease_terms', 'contract_terms');
            }
            if (Schema::hasColumn('company_settings', 'lease_footer_text')) {
                $table->renameColumn('lease_footer_text', 'contract_footer_text');
            }
            if (Schema::hasColumn('company_settings', 'lease_header_color')) {
                $table->renameColumn('lease_header_color', 'contract_header_color');
            }
            if (Schema::hasColumn('company_settings', 'show_company_stamp')) {
                $table->renameColumn('show_company_stamp', 'show_contract_stamp');
            }
            if (! Schema::hasColumn('company_settings', 'contract_stamp')) {
                $table->string('contract_stamp')->nullable()->after('contract_signature');
            }
        });
    }

    public function down(): void
    {
        Schema::table('company_settings', function (Blueprint $table): void {
            if (Schema::hasColumn('company_settings', 'contract_stamp')) {
                $table->dropColumn('contract_stamp');
            }
            if (Schema::hasColumn('company_settings', 'show_contract_stamp')) {
                $table->renameColumn('show_contract_stamp', 'show_company_stamp');
            }
            if (Schema::hasColumn('company_settings', 'contract_header_color')) {
                $table->renameColumn('contract_header_color', 'lease_header_color');
            }
            if (Schema::hasColumn('company_settings', 'contract_footer_text')) {
                $table->renameColumn('contract_footer_text', 'lease_footer_text');
            }
            if (Schema::hasColumn('company_settings', 'contract_terms')) {
                $table->renameColumn('contract_terms', 'lease_terms');
            }
            if (Schema::hasColumn('company_settings', 'contract_signature')) {
                $table->renameColumn('contract_signature', 'signature');
            }
            if (Schema::hasColumn('company_settings', 'contract_background')) {
                $table->renameColumn('contract_background', 'lease_background');
            }
        });

        Schema::table('documents', function (Blueprint $table): void {
            if (Schema::hasColumn('documents', 'document_type')) {
                $table->dropIndex(['document_type']);
                $table->dropColumn('document_type');
            }
        });
    }
};
