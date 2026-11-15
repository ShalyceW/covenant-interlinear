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
        Schema::create('passages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('chapter_id')->constrained()->cascadeOnDelete();
            $table->string('reference');
            $table->unsignedInteger('position');
            $table->longText('hebrew_text');
            $table->longText('english_text');
            $table->string('source_hash', 64)->nullable();
            $table->boolean('manually_edited')->default(false);
            $table->boolean('needs_retokenization')->default(false)->index();
            $table->timestamps();

            $table->unique(['chapter_id', 'position']);
            $table->index(['chapter_id', 'reference']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('passages');
    }
};
