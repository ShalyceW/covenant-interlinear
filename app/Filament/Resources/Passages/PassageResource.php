<?php

namespace App\Filament\Resources\Passages;

use App\Filament\Resources\Passages\Pages\CreatePassage;
use App\Filament\Resources\Passages\Pages\EditPassage;
use App\Filament\Resources\Passages\Pages\ListPassages;
use App\Filament\Resources\Passages\Pages\ViewPassage;
use App\Filament\Resources\Passages\Schemas\PassageForm;
use App\Filament\Resources\Passages\Schemas\PassageInfolist;
use App\Filament\Resources\Passages\Tables\PassagesTable;
use App\Models\Passage;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class PassageResource extends Resource
{
    protected static ?string $model = Passage::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    protected static string|\UnitEnum|null $navigationGroup = 'Scripture';

    protected static ?int $navigationSort = 30;

    public static function form(Schema $schema): Schema
    {
        return PassageForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return PassageInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return PassagesTable::configure($table);
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
            'index' => ListPassages::route('/'),
            'create' => CreatePassage::route('/create'),
            'view' => ViewPassage::route('/{record}'),
            'edit' => EditPassage::route('/{record}/edit'),
        ];
    }
}
