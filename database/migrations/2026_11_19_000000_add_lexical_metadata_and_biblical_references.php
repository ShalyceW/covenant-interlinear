<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('lemmas', function (Blueprint $table) {
            $table->string('base_strongs_number')->nullable()->index()->after('strongs_number');
            $table->string('source_lexical_id')->nullable()->index()->after('base_strongs_number');
            $table->string('lexicon_morphology')->nullable()->after('source_lexical_id');
            $table->json('alternate_definitions')->nullable()->after('definition');
        });

        Schema::create('biblical_references', function (Blueprint $table) {
            $table->id();
            $table->foreignId('lemma_id')->constrained()->cascadeOnDelete();
            $table->string('source');
            $table->text('raw_reference');
            $table->string('normalized_reference')->nullable();
            $table->string('book')->nullable()->index();
            $table->unsignedInteger('chapter')->nullable();
            $table->unsignedInteger('verse')->nullable();
            $table->unsignedInteger('source_position');
            $table->text('source_url')->nullable();
            $table->timestamps();

            $table->unique(['lemma_id', 'source', 'source_position']);
        });

        DB::table('lemmas')->whereNotNull('strongs_number')->orderBy('id')->eachById(function (object $lemma): void {
            $strongs = trim((string) $lemma->strongs_number);
            if (preg_match('/^H?(\d+)/i', $strongs, $matches) === 1) {
                DB::table('lemmas')->where('id', $lemma->id)->update([
                    'base_strongs_number' => 'H'.ltrim($matches[1], '0'),
                ]);
            }
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('biblical_references');

        Schema::table('lemmas', function (Blueprint $table) {
            $table->dropColumn([
                'base_strongs_number',
                'source_lexical_id',
                'lexicon_morphology',
                'alternate_definitions',
            ]);
        });
    }
};
