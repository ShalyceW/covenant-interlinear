<?php

namespace App\Filament\Resources\Lemmas\Schemas;

use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class LemmaForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('hebrew')
                    ->required()->extraAttributes(['dir' => 'rtl', 'lang' => 'he']),
                TextInput::make('normalized')
                    ->required(),
                TextInput::make('transliteration'),
                Textarea::make('definition')
                    ->columnSpanFull(),
                TextInput::make('root'),
                TextInput::make('strongs_number')->unique(ignoreRecord: true),
                TextInput::make('base_strongs_number'),
                TextInput::make('source_lexical_id'),
                TextInput::make('lexicon_morphology'),
                Repeater::make('alternate_definitions')
                    ->schema([
                        TextInput::make('source_lexical_id'),
                        TextInput::make('unified_strongs'),
                        TextInput::make('gloss'),
                        Textarea::make('definition')->columnSpanFull(),
                    ])
                    ->columnSpanFull(),
                Textarea::make('source_reference')
                    ->columnSpanFull(),
            ]);
    }
}
