<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('hebrew_words', function (Blueprint $table) {
            $table->json('enrichment')->nullable()->after('morphology');
        });
    }

    public function down(): void
    {
        Schema::table('hebrew_words', function (Blueprint $table) {
            $table->dropColumn('enrichment');
        });
    }
};
