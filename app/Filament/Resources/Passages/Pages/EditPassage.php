<?php

namespace App\Filament\Resources\Passages\Pages;

use App\Filament\Resources\Passages\PassageResource;
use App\Models\Passage;
use Filament\Actions\DeleteAction;
use Filament\Actions\ViewAction;
use Filament\Resources\Pages\EditRecord;

class EditPassage extends EditRecord
{
    protected static string $resource = PassageResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ViewAction::make(),
            DeleteAction::make(),
        ];
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        $record = $this->getRecord();
        if (! $record instanceof Passage) {
            throw new \LogicException('The passage record is unavailable.');
        }

        $hebrewChanged = array_key_exists('hebrew_text', $data) && $data['hebrew_text'] !== $record->hebrew_text;
        $sourceChanged = $hebrewChanged || (array_key_exists('english_text', $data) && $data['english_text'] !== $record->english_text);
        if ($sourceChanged) {
            $data['manually_edited'] = true;
        }
        if ($hebrewChanged) {
            $data['needs_retokenization'] = true;
        }

        return $data;
    }
}
