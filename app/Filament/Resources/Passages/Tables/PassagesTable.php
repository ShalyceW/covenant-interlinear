<?php

namespace App\Filament\Resources\Passages\Tables;

use App\Models\Passage;
use App\Services\ScriptureImporter;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class PassagesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('chapter.book.name')->label('Book')->searchable(),
                TextColumn::make('chapter.number')->label('Chapter')->sortable(),
                TextColumn::make('reference')
                    ->searchable(),
                TextColumn::make('position')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('source_hash')
                    ->searchable(),
                IconColumn::make('manually_edited')
                    ->boolean(),
                IconColumn::make('needs_retokenization')
                    ->boolean(),
                TextColumn::make('hebrew_words_count')->counts('hebrewWords')->label('Words'),
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
                SelectFilter::make('chapter')->relationship('chapter', 'number'),
            ])
            ->recordActions([
                Action::make('retokenize')
                    ->label('Retokenize')
                    ->icon('heroicon-o-arrow-path')
                    ->color('warning')
                    ->visible(fn (Passage $record): bool => $record->needs_retokenization)
                    ->requiresConfirmation()
                    ->modalDescription('This rebuilds the word list from the current Hebrew text. Annotations are preserved only when the word remains unchanged in the same position.')
                    ->action(fn (Passage $record, ScriptureImporter $importer) => $importer->retokenize($record)),
                ViewAction::make(),
                EditAction::make(),
            ])
            ->defaultSort('position');
    }
}
