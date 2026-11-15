<?php

namespace App\Filament\Resources\Passages\Schemas;

use Filament\Infolists\Components\IconEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Schema;

class PassageInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextEntry::make('chapter.id')
                    ->label('Chapter'),
                TextEntry::make('reference'),
                TextEntry::make('position')
                    ->numeric(),
                TextEntry::make('hebrew_text')
                    ->columnSpanFull(),
                TextEntry::make('english_text')
                    ->columnSpanFull(),
                TextEntry::make('source_hash')
                    ->placeholder('-'),
                IconEntry::make('manually_edited')
                    ->boolean(),
                IconEntry::make('needs_retokenization')
                    ->boolean(),
                TextEntry::make('created_at')
                    ->dateTime()
                    ->placeholder('-'),
                TextEntry::make('updated_at')
                    ->dateTime()
                    ->placeholder('-'),
            ]);
    }
}
