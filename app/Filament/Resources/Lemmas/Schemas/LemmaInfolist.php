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
