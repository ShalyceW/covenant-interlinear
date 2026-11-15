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
        Schema::create('lemmas', function (Blueprint $table) {
            $table->id();
            $table->string('hebrew');
            $table->string('normalized')->index();
            $table->string('transliteration')->nullable()->index();
            $table->text('definition')->nullable();
            $table->string('root')->nullable();
            $table->string('strongs_number')->nullable()->unique();
            $table->text('source_reference')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('lemmas');
    }
};
