<?php

namespace App\Filament\Resources\Lemmas\Pages;

use App\Filament\Resources\Lemmas\LemmaResource;
use Filament\Actions\DeleteAction;
use Filament\Actions\ViewAction;
use Filament\Resources\Pages\EditRecord;

class EditLemma extends EditRecord
{
    protected static string $resource = LemmaResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ViewAction::make(),
            DeleteAction::make(),
        ];
    }
}
