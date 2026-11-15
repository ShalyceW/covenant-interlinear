<?php

namespace App\Filament\Resources\Lemmas\Tables;

use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class LemmasTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('hebrew')
                    ->searchable(),
                TextColumn::make('normalized')
                    ->searchable(),
                TextColumn::make('transliteration')
                    ->searchable(),
                TextColumn::make('root')
                    ->searchable(),
                TextColumn::make('strongs_number')
                    ->searchable(),
                TextColumn::make('occurrences_count')->counts('occurrences')->label('Occurrences')->sortable(),
                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                //
            ])
            ->recordActions([
                ViewAction::make(),
                EditAction::make(),
            ])
            ->defaultSort('hebrew');
    }
}
