<?php

namespace App\Filament\Resources\Lemmas\Pages;

use App\Filament\Resources\Lemmas\LemmaResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListLemmas extends ListRecords
{
    protected static string $resource = LemmaResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
