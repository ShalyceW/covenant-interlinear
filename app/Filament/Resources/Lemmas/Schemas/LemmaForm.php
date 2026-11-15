<?php

namespace App\Filament\Resources\Lemmas\Schemas;

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
                Textarea::make('source_reference')
                    ->columnSpanFull(),
            ]);
    }
}
