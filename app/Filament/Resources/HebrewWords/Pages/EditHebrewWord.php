<?php

namespace App\Filament\Resources\HebrewWords\Pages;

use App\Filament\Resources\HebrewWords\HebrewWordResource;
use App\Models\HebrewWord;
use Filament\Actions\DeleteAction;
use Filament\Actions\ViewAction;
use Filament\Resources\Pages\EditRecord;

class EditHebrewWord extends EditRecord
{
    protected static string $resource = HebrewWordResource::class;

    /** @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeSave(array $data): array
    {
        $record = $this->getRecord();
        if (! $record instanceof HebrewWord) {
            return $data;
        }
        $enrichment = is_array($data['enrichment'] ?? null) ? $data['enrichment'] : ($record->enrichment ?? []);
        foreach (['lemma_id' => 'lemma', 'transliteration' => 'transliteration', 'english_gloss' => 'gloss', 'morphology' => 'morphology'] as $field => $key) {
            if (array_key_exists($field, $data) && $data[$field] !== $record->{$field}) {
                $enrichment[$key] = array_merge(is_array($enrichment[$key] ?? null) ? $enrichment[$key] : [], [
                    'status' => 'confirmed',
                    'source' => 'editorial',
                ]);
            }
        }
        $data['enrichment'] = $enrichment;

        return $data;
    }

    protected function getHeaderActions(): array
    {
        return [
            ViewAction::make(),
            DeleteAction::make(),
        ];
    }
}
