<?php

namespace App\Filament\Resources\Lemmas\Schemas;

use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Schema;

class LemmaInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextEntry::make('hebrew'),
                TextEntry::make('normalized'),
                TextEntry::make('transliteration')
                    ->placeholder('-'),
                TextEntry::make('definition')
                    ->placeholder('-')
                    ->columnSpanFull(),
                TextEntry::make('root')
                    ->placeholder('-'),
                TextEntry::make('strongs_number')
                    ->placeholder('-'),
                TextEntry::make('base_strongs_number')
                    ->placeholder('-'),
                TextEntry::make('source_lexical_id')
                    ->placeholder('-'),
                TextEntry::make('lexicon_morphology')
                    ->placeholder('-'),
                TextEntry::make('occurrences_count')
                    ->counts('occurrences')
                    ->label('Covenant occurrences'),
                TextEntry::make('biblical_references_count')
                    ->counts('biblicalReferences')
                    ->label('Biblical reference rows'),
                TextEntry::make('alternate_definitions')
                    ->formatStateUsing(function (mixed $state): string {
                        if (! is_array($state)) {
                            return '';
                        }

                        return implode("\n", array_filter(array_map(
                            fn (mixed $sense): string => is_array($sense)
                                ? trim(($sense['source_lexical_id'] ?? '').': '.($sense['gloss'] ?? $sense['definition'] ?? ''))
                                : '',
                            $state,
                        )));
                    })
                    ->placeholder('-')
                    ->columnSpanFull(),
                TextEntry::make('source_reference')
                    ->placeholder('-')
                    ->columnSpanFull(),
                TextEntry::make('created_at')
                    ->dateTime()
                    ->placeholder('-'),
                TextEntry::make('updated_at')
                    ->dateTime()
                    ->placeholder('-'),
            ]);
    }
}
