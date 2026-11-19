<?php

namespace App\Console\Commands;

use App\Services\StepBibleLexiconImporter;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('lexicon:import-tbesh {file : Path to the published TBESH tab-separated file}')]
#[Description('Import the STEPBible TBESH Hebrew lexicon without overwriting curated lemma fields')]
class ImportHebrewLexicon extends Command
{
    public function handle(StepBibleLexiconImporter $importer): int
    {
        $result = $importer->import((string) $this->argument('file'));
        $this->info('TBESH Hebrew lexicon import complete.');
        $this->table(['Extended entries', 'Created', 'Updated', 'Preserved'], [[
            $result['read'], $result['created'], $result['updated'], $result['preserved'],
        ]]);

        return self::SUCCESS;
    }
}
