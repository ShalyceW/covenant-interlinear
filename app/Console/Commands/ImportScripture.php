<?php

namespace App\Console\Commands;

use App\Services\ScriptureImporter;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('scripture:import
    {book : Verified Brit HaMashiach book slug, such as 1nefi}
    {chapter? : Chapter number for a single-chapter import}
    {--all : Import every verified chapter in the book}
    {--file= : Import a local Brit HaMashiach chapter JSON payload}
    {--draft : Leave newly imported chapters unpublished}')]
#[Description('Import scripture from Brit HaMashiach or a local JSON payload')]
class ImportScripture extends Command
{
    /**
     * Execute the console command.
     */
    public function handle(ScriptureImporter $importer): int
    {
        $book = (string) $this->argument('book');
        $publish = ! $this->option('draft');

        if ($this->option('all')) {
            if ($this->option('file')) {
                $this->error('A local JSON file can only contain a single chapter.');

                return self::FAILURE;
            }

            $results = $importer->importBook($book, $publish, function (int $number, array $result, ?\Throwable $error): void {
                $error
                    ? $this->error("Chapter {$number}: {$error->getMessage()}")
                    : $this->info("Chapter {$number}: {$result['passages_created']} passages created, {$result['passages_updated']} updated.");
            });

            return collect($results)->contains(fn (array $result): bool => isset($result['error']))
                ? self::FAILURE
                : self::SUCCESS;
        }

        $chapter = (int) $this->argument('chapter');
        if ($chapter < 1) {
            $this->error('Provide a chapter number or use --all.');

            return self::INVALID;
        }

        $result = $importer->importChapter($book, $chapter, $this->option('file'), $publish);
        $this->table(['Book', 'Chapter', 'Passages created', 'Passages updated', 'Words created', 'Skipped'], [[
            $result['book'], $result['chapter'], $result['passages_created'], $result['passages_updated'],
            $result['words_created'], $result['passages_skipped'],
        ]]);

        return self::SUCCESS;
    }
}
