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
        Schema::create('conversations', function (Blueprint $table) {
            $table->id();
            $table->string('reference', 32)->unique();
            $table->string('client_name')->nullable();
            $table->string('client_email')->nullable();
            $table->string('label')->nullable();
            $table->string('stage')->default('inquiry');
            $table->string('currency', 3)->default('PHP');
            $table->unsignedInteger('first_month_amount')->nullable();
            $table->unsignedInteger('monthly_amount')->nullable();
            $table->unsignedInteger('amount_paid')->nullable();
            $table->unsignedInteger('amount_due')->nullable();
            $table->string('contract_status')->nullable();
            $table->date('contract_started_at')->nullable();
            $table->date('contract_renews_at')->nullable();
            $table->timestamp('last_message_at')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('conversations');
    }
};
