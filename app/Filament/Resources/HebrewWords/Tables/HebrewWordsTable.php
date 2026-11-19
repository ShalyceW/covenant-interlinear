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
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;

class HebrewWordsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('passage.reference')->label('Context')->searchable()->sortable()->limit(28),
                TextColumn::make('lemma.hebrew')
                    ->label('Lemma')
                    ->placeholder('Suggested below')
                    ->url(fn (HebrewWord $record): string => self::readerReviewUrl($record))
                    ->color(fn (HebrewWord $record): string => $record->hasConfirmedLemma() ? 'gray' : 'danger')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('position')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('hebrew')
                    ->searchable()->sortable(),
                TextColumn::make('normalized')
                    ->searchable()->sortable(),
                TextColumn::make('transliteration')
                    ->searchable()->sortable(),
                TextColumn::make('english_gloss')
                    ->placeholder('Suggested below')
                    ->url(fn (HebrewWord $record): string => self::readerReviewUrl($record))
                    ->color(fn (HebrewWord $record): string => data_get($record->enrichment, 'gloss.status') === 'confirmed' ? 'gray' : 'danger')
                    ->searchable()->sortable(),
                TextColumn::make('enrichment.lemma.status')
                    ->label('Lemma status')
                    ->badge()
                    ->sortable()
                    ->toggleable(),
                TextColumn::make('enrichment.gloss.status')
                    ->label('Gloss status')
                    ->badge()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
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
                SelectFilter::make('lemma_status')
                    ->label('Lemma status')
                    ->options(['confirmed' => 'Confirmed', 'proposed' => 'Proposed', 'unresolved' => 'Unresolved'])
                    ->query(fn ($query, array $data) => $query->when($data['value'] ?? null, fn ($query, $value) => $query->where('enrichment->lemma->status', $value))),
                SelectFilter::make('gloss_status')
                    ->label('Gloss status')
                    ->options(['confirmed' => 'Confirmed', 'proposed' => 'Proposed', 'unresolved' => 'Unresolved'])
                    ->query(fn ($query, array $data) => $query->when($data['value'] ?? null, fn ($query, $value) => $query->where('enrichment->gloss->status', $value))),
                TernaryFilter::make('missing_lemma')
                    ->label('Missing lemma')
                    ->nullable()
                    ->attribute('lemma_id'),
                TernaryFilter::make('missing_gloss')
                    ->label('Missing gloss')
                    ->queries(
                        true: fn ($query) => $query->where(fn ($query) => $query->whereNull('english_gloss')->orWhere('english_gloss', '')),
                        false: fn ($query) => $query->whereNotNull('english_gloss')->where('english_gloss', '!=', ''),
                    ),
            ])
            ->recordActions([
                Action::make('lookupLexicon')
                    ->label('Resolve lexicon')
                    ->icon('heroicon-o-book-open')
                    ->visible(fn (HebrewWord $record): bool => ! $record->hasConfirmedLemma())
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

    private static function readerReviewUrl(HebrewWord $word): string
    {
        $word->loadMissing('passage.chapter.book');

        return route('scripture.show', [
            $word->passage->chapter->book->slug,
            $word->passage->chapter->number,
        ]).'?word='.$word->id.'#passage-'.$word->passage_id;
    }
}
