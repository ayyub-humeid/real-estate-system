<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('contract_signatures', function (Blueprint $table): void {
            if (! Schema::hasColumn('contract_signatures', 'signature_image_path')) {
                $table->string('signature_image_path')->nullable();
            }
        });
    }

    public function down(): void
    {
        Schema::table('contract_signatures', function (Blueprint $table): void {
            if (Schema::hasColumn('contract_signatures', 'signature_image_path')) {
                $table->dropColumn('signature_image_path');
            }
        });
    }
};
