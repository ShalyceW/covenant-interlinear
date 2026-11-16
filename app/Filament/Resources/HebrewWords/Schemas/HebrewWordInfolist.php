<?php

namespace App\Filament\Resources\HebrewWords\Schemas;

use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Schema;

class HebrewWordInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextEntry::make('passage.id')
                    ->label('Passage'),
                TextEntry::make('lemma.id')
                    ->label('Lemma')
                    ->placeholder('-'),
                TextEntry::make('position')
                    ->numeric(),
                TextEntry::make('hebrew'),
                TextEntry::make('normalized'),
                TextEntry::make('transliteration')
                    ->placeholder('-'),
                TextEntry::make('english_gloss')
                    ->placeholder('-'),
                TextEntry::make('morphology')
                    ->placeholder('-')
                    ->columnSpanFull(),
                TextEntry::make('enrichment')
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
