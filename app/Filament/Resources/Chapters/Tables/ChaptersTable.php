<?php

namespace App\Filament\Resources\Chapters\Tables;

use App\Filament\Pages\ImportScripture;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class ChaptersTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('book.name')
                    ->searchable(),
                TextColumn::make('number')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('source_url')
                    ->searchable(),
                TextColumn::make('imported_at')
                    ->dateTime()
                    ->sortable(),
                TextColumn::make('published_at')
                    ->dateTime()
                    ->sortable(),
                TextColumn::make('passages_count')->counts('passages')->label('Passages')->sortable(),
                TextColumn::make('words_count')->state(fn ($record) => $record->passages()->withCount('hebrewWords')->get()->sum('hebrew_words_count'))->label('Words'),
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
                SelectFilter::make('book')->relationship('book', 'name')->searchable()->preload(),
            ])
            ->recordActions([
                Action::make('reader')->icon('heroicon-o-book-open')->url(fn ($record) => route('scripture.show', [$record->book->slug, $record->number]))->openUrlInNewTab()->visible(fn ($record) => $record->published_at !== null),
                Action::make('publish')->icon('heroicon-o-eye')->color('success')->visible(fn ($record) => $record->published_at === null)->action(fn ($record) => $record->update(['published_at' => now()])),
                Action::make('unpublish')->icon('heroicon-o-eye-slash')->color('gray')->visible(fn ($record) => $record->published_at !== null)->requiresConfirmation()->action(fn ($record) => $record->update(['published_at' => null])),
                Action::make('reimport')->icon('heroicon-o-arrow-path')->requiresConfirmation()->url(fn ($record) => ImportScripture::getUrl(['book' => $record->book->slug, 'chapter' => $record->number])),
                ViewAction::make(),
                EditAction::make(),
            ])
            ->defaultSort('book_id');
    }
}
