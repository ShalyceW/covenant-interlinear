<?php

namespace App\Filament\Resources\HebrewWords\Pages;

use App\Filament\Resources\HebrewWords\HebrewWordResource;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewHebrewWord extends ViewRecord
{
    protected static string $resource = HebrewWordResource::class;

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make(),
        ];
    }
}
