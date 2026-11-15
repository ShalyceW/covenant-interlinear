<?php

namespace App\Filament\Resources\Books\Tables;

use App\Filament\Pages\ImportScripture;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class BooksTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('slug')
                    ->searchable(),
                TextColumn::make('name')
                    ->searchable(),
                TextColumn::make('hebrew_name')
                    ->searchable(),
                TextColumn::make('position')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('chapters_count')->counts('chapters')->label('Chapters')->sortable(),
                TextColumn::make('published_chapters_count')->counts('publishedChapters')->label('Published')->sortable(),
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
                Action::make('reader')->label('Open reader')->icon('heroicon-o-book-open')
                    ->url(fn ($record) => $record->publishedChapters()->exists() ? route('scripture.show', [$record->slug, $record->publishedChapters()->min('number')]) : null)
                    ->openUrlInNewTab(),
                Action::make('import')->label('Import chapters')->icon('heroicon-o-arrow-down-tray')
                    ->url(fn ($record) => ImportScripture::getUrl(['book' => $record->slug])),
                ViewAction::make(),
                EditAction::make(),
            ])
            ->defaultSort('position');
    }
}
