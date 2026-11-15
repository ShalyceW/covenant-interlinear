<?php

namespace App\Filament\Resources\HebrewWords\Tables;

use App\Models\Chapter;
use App\Models\HebrewWord;
use App\Services\ScriptureImporter;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class HebrewWordsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('passage.reference')->label('Context')->searchable()->limit(28),
                TextColumn::make('lemma.hebrew')->label('Lemma')->searchable(),
                TextColumn::make('position')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('hebrew')
                    ->searchable(),
                TextColumn::make('normalized')
                    ->searchable(),
                TextColumn::make('transliteration')
                    ->searchable(),
                TextColumn::make('english_gloss')
                    ->searchable(),
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
                SelectFilter::make('chapter')->query(fn ($query, array $data) => $query->when($data['value'] ?? null, fn ($query, $value) => $query->whereHas('passage', fn ($passage) => $passage->where('chapter_id', $value))))
                    ->options(fn () => Chapter::with('book')->get()->mapWithKeys(fn (Chapter $chapter) => [$chapter->id => "{$chapter->book->name} {$chapter->number}"])),
            ])
            ->recordActions([
                Action::make('lookupLexicon')
                    ->label('Lookup lexicon')
                    ->icon('heroicon-o-book-open')
                    ->action(function (HebrewWord $record, ScriptureImporter $importer): void {
                        $found = $importer->lookupLexicon($record);
                        Notification::make()
                            ->title($found ? 'Lexicon data linked' : 'No verified source entry found')
                            ->color($found ? 'success' : 'warning')
                            ->send();
                    }),
                ViewAction::make(),
                EditAction::make(),
            ])
            ->defaultSort('id');
    }
}
