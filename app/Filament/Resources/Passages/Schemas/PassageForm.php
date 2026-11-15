<?php

namespace App\Filament\Resources\Passages\Schemas;

use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

class PassageForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('chapter_id')
                    ->relationship('chapter', 'number')
                    ->getOptionLabelFromRecordUsing(fn ($record) => "{$record->book->name} {$record->number}")
                    ->searchable()
                    ->preload()
                    ->required(),
                TextInput::make('reference')
                    ->required(),
                TextInput::make('position')
                    ->required()
                    ->numeric(),
                Textarea::make('hebrew_text')
                    ->required()
                    ->extraAttributes(['dir' => 'rtl', 'lang' => 'he', 'style' => 'font-size:1.3rem; line-height:2;'])
                    ->columnSpanFull(),
                Textarea::make('english_text')
                    ->required()
                    ->columnSpanFull(),
                TextInput::make('source_hash')->disabled()->dehydrated(false),
                Toggle::make('manually_edited')
                    ->disabled(),
                Toggle::make('needs_retokenization')
                    ->disabled(),
            ]);
    }
}
