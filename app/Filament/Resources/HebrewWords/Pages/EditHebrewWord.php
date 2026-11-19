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
        $submittedEnrichment = is_array($data['enrichment'] ?? null) ? $data['enrichment'] : [];
        $enrichment = array_replace_recursive($record->enrichment ?? [], $submittedEnrichment);
        foreach (['lemma_id' => 'lemma', 'transliteration' => 'transliteration', 'english_gloss' => 'gloss', 'morphology' => 'morphology'] as $field => $key) {
            if (array_key_exists($field, $data) && $data[$field] !== $record->{$field}) {
                $enrichment[$key] = array_merge(is_array($enrichment[$key] ?? null) ? $enrichment[$key] : [], [
                    'status' => 'confirmed',
                    'source' => 'editorial',
                ]);
            }
        }
        foreach (['lemma', 'gloss', 'transliteration'] as $key) {
            $submittedStatus = data_get($submittedEnrichment, "{$key}.status");
            if ($submittedStatus !== null && $submittedStatus !== data_get($record->enrichment, "{$key}.status")) {
                $enrichment[$key]['source'] = 'editorial';
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
