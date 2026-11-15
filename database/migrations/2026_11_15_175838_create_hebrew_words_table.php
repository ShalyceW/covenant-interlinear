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
        Schema::create('hebrew_words', function (Blueprint $table) {
            $table->id();
            $table->foreignId('passage_id')->constrained()->cascadeOnDelete();
            // The lemma migration runs immediately after this one; an indexed nullable
            // key remains MySQL-compatible without requiring deferred foreign keys.
            $table->unsignedBigInteger('lemma_id')->nullable()->index();
            $table->unsignedInteger('position');
            $table->string('hebrew');
            $table->string('normalized')->index();
            $table->string('transliteration')->nullable()->index();
            $table->string('english_gloss')->nullable();
            $table->json('morphology')->nullable();
            $table->timestamps();

            $table->unique(['passage_id', 'position']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('hebrew_words');
    }
};
