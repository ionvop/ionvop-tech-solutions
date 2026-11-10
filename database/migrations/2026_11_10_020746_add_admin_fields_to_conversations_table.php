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
        Schema::table('conversations', function (Blueprint $table) {
            $table->text('summary')->nullable()->after('contract_renews_at');
            $table->timestamp('summary_generated_at')->nullable()->after('summary');
            $table->boolean('ai_enabled')->default(true)->after('summary_generated_at');
            $table->timestamp('admin_read_at')->nullable()->after('ai_enabled');

            $table->index('last_message_at');
            $table->index('stage');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('conversations', function (Blueprint $table) {
            $table->dropIndex(['last_message_at']);
            $table->dropIndex(['stage']);

            $table->dropColumn(['summary', 'summary_generated_at', 'ai_enabled', 'admin_read_at']);
        });
    }
};
