<?php

namespace App\Filament\Resources\HebrewWords\Pages;

use App\Filament\Resources\HebrewWords\HebrewWordResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListHebrewWords extends ListRecords
{
    protected static string $resource = HebrewWordResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
