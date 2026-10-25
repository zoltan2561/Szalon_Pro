<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('salons', function (Blueprint $table): void {
            $table->text('billing_agent_key')->nullable();
            $table->string('billing_vat', 10)->nullable();
            $table->string('billing_receipt_prefix', 10)->nullable();
            $table->boolean('billing_sending_enabled')->default(false);
            $table->dateTime('billing_approved_at')->nullable();
        });
        Schema::create('fiscal_documents', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('booking_id')->unique()->constrained()->restrictOnDelete();
            $table->foreignId('created_by_user_id')->constrained('users')->restrictOnDelete();
            $table->string('kind', 10);
            $table->string('status', 20)->default('pending');
            $table->string('external_id', 64)->unique();
            $table->text('payload');
            $table->string('document_number')->nullable();
            $table->longText('pdf')->nullable();
            $table->string('pdf_link')->nullable();
            $table->string('error_code', 80)->nullable();
            $table->unsignedInteger('attempts')->default(0);
            $table->dateTime('last_attempt_at')->nullable();
            $table->dateTime('issued_at')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('fiscal_documents');
        Schema::table('salons', fn (Blueprint $table) => $table->dropColumn(['billing_agent_key', 'billing_vat', 'billing_receipt_prefix', 'billing_sending_enabled', 'billing_approved_at']));
    }
};
