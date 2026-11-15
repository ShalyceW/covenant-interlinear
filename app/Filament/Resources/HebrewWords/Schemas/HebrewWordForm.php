<?php

namespace App\Filament\Resources\HebrewWords\Schemas;

use Filament\Forms\Components\KeyValue;
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
                KeyValue::make('morphology')
                    ->columnSpanFull(),
            ]);
    }
}
