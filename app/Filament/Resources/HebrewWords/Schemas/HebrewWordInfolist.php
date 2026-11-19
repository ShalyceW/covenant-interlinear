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
                TextEntry::make('passage.hebrew_text')
                    ->label('Hebrew context')
                    ->extraAttributes(['dir' => 'rtl', 'lang' => 'he'])
                    ->columnSpanFull(),
                TextEntry::make('passage.english_text')
                    ->label('Covenant of Christ English context')
                    ->columnSpanFull(),
                TextEntry::make('enrichment.lemma.status')->label('Lemma review status')->placeholder('-'),
                TextEntry::make('enrichment.gloss.status')->label('Gloss review status')->placeholder('-'),
                TextEntry::make('enrichment.transliteration.status')->label('Transliteration review status')->placeholder('-'),
                TextEntry::make('enrichment.gloss.source')->label('Gloss source')->placeholder('-'),
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
