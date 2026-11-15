<?php

namespace App\Filament\Resources\Lemmas\Pages;

use App\Filament\Resources\Lemmas\LemmaResource;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewLemma extends ViewRecord
{
    protected static string $resource = LemmaResource::class;

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make(),
        ];
    }
}
