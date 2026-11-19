<?php

namespace App\Console\Commands;

use App\Models\Chapter;
use App\Services\WordEnrichmentService;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('scripture:enrich
    {book : Imported book slug, such as 1nefi}
    {chapter : Imported chapter number}
    {--dry-run : Report changes without writing them}
    {--retry-unresolved : Refresh only unresolved or proposed source lookups}')]
#[Description('Enrich an imported chapter with Hebrew lemmas, surface transliterations, glosses, and morphology')]
class EnrichScripture extends Command
{
    public function handle(WordEnrichmentService $service): int
    {
        $chapter = Chapter::query()
            ->whereHas('book', fn ($query) => $query->where('slug', (string) $this->argument('book')))
            ->where('number', (int) $this->argument('chapter'))
            ->first();

        if ($chapter === null) {
            $this->error('The requested chapter has not been imported.');

            return self::FAILURE;
        }

        $result = $service->enrich(
            $chapter,
            (bool) $this->option('dry-run'),
            (bool) $this->option('retry-unresolved'),
        );
        $this->info($result['dry_run'] ? 'Dry run complete; no records were changed.' : 'Chapter enrichment complete.');
        $this->table(
            ['Metric', 'Before', 'After'],
            [
                ['Hebrew word occurrences', $result['before']['total_words'], $result['after']['total_words']],
                ['Words with transliterations', $result['before']['transliterations'], $result['after']['transliterations']],
                ['Words with contextual glosses', $result['before']['contextual_glosses'], $result['after']['contextual_glosses']],
                ['Confirmed lemma assignments', $result['before']['confirmed_lemmas'], $result['after']['confirmed_lemmas']],
                ['Proposed lemma assignments', $result['before']['proposed_lemmas'], $result['after']['proposed_lemmas']],
                ['Verified Strong’s matches', $result['before']['strongs_matches'], $result['after']['strongs_matches']],
                ['Words without verified Strong’s', $result['before']['words_without_verified_strongs'], $result['after']['words_without_verified_strongs']],
                ['Completed dictionary entries', $result['before']['dictionary_entries_completed'], $result['after']['dictionary_entries_completed']],
                ['Biblical reference evidence rows', $result['before']['biblical_references_imported'], $result['after']['biblical_references_imported']],
                ['Unresolved words', $result['before']['unresolved_words'], $result['after']['unresolved_words']],
                ['Words requiring review', $result['before']['words_requiring_review'], $result['after']['words_requiring_review']],
            ],
        );
        $this->line("Lemma records corrected: {$result['lemma_records_corrected']}");
        $this->line("Word records changed: {$result['records_changed']}");
        if ($result['unresolved_reasons'] !== []) {
            $this->newLine();
            $this->line('Proposed or unresolved assignments by reason:');
            foreach ($result['unresolved_reasons'] as $reason => $count) {
                $this->line("- {$reason}: {$count}");
            }
        }

        return self::SUCCESS;
    }
}
