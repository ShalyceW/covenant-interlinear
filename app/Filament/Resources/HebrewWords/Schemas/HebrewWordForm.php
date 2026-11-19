<?php

namespace App\Filament\Resources\HebrewWords\Schemas;

use App\Models\HebrewWord;
use Filament\Forms\Components\KeyValue;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class HebrewWordForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('passage_id')
                    ->relationship('passage', 'reference')
                    ->searchable()
                    ->required(),
                Select::make('lemma_id')
                    ->relationship('lemma', 'hebrew')
                    ->searchable()
                    ->preload(),
                TextInput::make('position')
                    ->required()
                    ->numeric(),
                TextInput::make('hebrew')
                    ->required()->extraAttributes(['dir' => 'rtl', 'lang' => 'he']),
                TextInput::make('normalized')
                    ->required(),
                TextInput::make('transliteration'),
                TextInput::make('english_gloss'),
                Placeholder::make('passage_hebrew')
                    ->content(fn (?HebrewWord $record): string => $record === null ? '—' : $record->passage->hebrew_text)
                    ->extraAttributes(['dir' => 'rtl', 'lang' => 'he'])
                    ->columnSpanFull(),
                Placeholder::make('passage_english')
                    ->content(fn (?HebrewWord $record): string => $record === null ? '—' : $record->passage->english_text)
                    ->columnSpanFull(),
                Select::make('enrichment.lemma.status')
                    ->options(['confirmed' => 'Confirmed', 'proposed' => 'Proposed', 'unresolved' => 'Unresolved']),
                Select::make('enrichment.gloss.status')
                    ->options(['confirmed' => 'Confirmed', 'proposed' => 'Proposed', 'unresolved' => 'Unresolved']),
                Select::make('enrichment.transliteration.status')
                    ->options(['confirmed' => 'Confirmed', 'proposed' => 'Proposed', 'unresolved' => 'Unresolved']),
                Placeholder::make('annotation_source')
                    ->content(fn (?HebrewWord $record): string => $record === null
                        ? '—'
                        : (string) (data_get($record->enrichment, 'gloss.source') ?? '—')),
                KeyValue::make('morphology')
                    ->columnSpanFull(),
            ]);
    }
}
