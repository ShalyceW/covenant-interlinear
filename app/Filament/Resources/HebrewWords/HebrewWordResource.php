<?php

namespace App\Filament\Resources\HebrewWords;

use App\Filament\Resources\HebrewWords\Pages\CreateHebrewWord;
use App\Filament\Resources\HebrewWords\Pages\EditHebrewWord;
use App\Filament\Resources\HebrewWords\Pages\ListHebrewWords;
use App\Filament\Resources\HebrewWords\Pages\ViewHebrewWord;
use App\Filament\Resources\HebrewWords\Schemas\HebrewWordForm;
use App\Filament\Resources\HebrewWords\Schemas\HebrewWordInfolist;
use App\Filament\Resources\HebrewWords\Tables\HebrewWordsTable;
use App\Models\HebrewWord;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class HebrewWordResource extends Resource
{
    protected static ?string $model = HebrewWord::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    protected static string|\UnitEnum|null $navigationGroup = 'Hebrew Dictionary';

    protected static ?string $navigationLabel = 'Word Glosses';

    protected static ?int $navigationSort = 20;

    public static function form(Schema $schema): Schema
    {
        return HebrewWordForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return HebrewWordInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return HebrewWordsTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListHebrewWords::route('/'),
            'create' => CreateHebrewWord::route('/create'),
            'view' => ViewHebrewWord::route('/{record}'),
            'edit' => EditHebrewWord::route('/{record}/edit'),
        ];
    }
}
