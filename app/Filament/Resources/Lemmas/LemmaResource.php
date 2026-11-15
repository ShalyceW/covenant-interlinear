<?php

namespace App\Filament\Resources\Lemmas;

use App\Filament\Resources\Lemmas\Pages\CreateLemma;
use App\Filament\Resources\Lemmas\Pages\EditLemma;
use App\Filament\Resources\Lemmas\Pages\ListLemmas;
use App\Filament\Resources\Lemmas\Pages\ViewLemma;
use App\Filament\Resources\Lemmas\Schemas\LemmaForm;
use App\Filament\Resources\Lemmas\Schemas\LemmaInfolist;
use App\Filament\Resources\Lemmas\Tables\LemmasTable;
use App\Models\Lemma;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class LemmaResource extends Resource
{
    protected static ?string $model = Lemma::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    protected static string|\UnitEnum|null $navigationGroup = 'Hebrew Dictionary';

    protected static ?int $navigationSort = 10;

    public static function form(Schema $schema): Schema
    {
        return LemmaForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return LemmaInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return LemmasTable::configure($table);
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
            'index' => ListLemmas::route('/'),
            'create' => CreateLemma::route('/create'),
            'view' => ViewLemma::route('/{record}'),
            'edit' => EditLemma::route('/{record}/edit'),
        ];
    }
}
