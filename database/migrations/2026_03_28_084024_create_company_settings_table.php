<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('company_settings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            
            $table->string('logo')->nullable();
            $table->string('lease_background')->nullable(); 
            $table->string('signature')->nullable(); 
            
            $table->string('company_legal_name')->nullable(); 
            $table->text('company_address')->nullable();
            $table->string('company_phone')->nullable();
            $table->string('company_email')->nullable();
            $table->string('tax_id')->nullable(); 
            $table->string('registration_number')->nullable(); 
            $table->string('website')->nullable();
            
            $table->text('lease_terms')->nullable(); 
            $table->text('lease_footer_text')->nullable(); 
            $table->string('lease_header_color')->default('#1e40af'); 
            $table->boolean('show_company_stamp')->default(true); 
            
            $table->text('receipt_terms')->nullable();
            $table->string('receipt_header_color')->default('#059669');
            
            $table->integer('payment_grace_period_days')->default(5); 
            $table->decimal('late_payment_fee_percentage', 5, 2)->default(0); 
            
            $table->timestamps();
            
            $table->unique('company_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('company_settings');
    }
};