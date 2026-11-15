<?php

namespace App\Filament\Resources\HebrewWords\Pages;

use App\Filament\Resources\HebrewWords\HebrewWordResource;
use Filament\Actions\DeleteAction;
use Filament\Actions\ViewAction;
use Filament\Resources\Pages\EditRecord;

class EditHebrewWord extends EditRecord
{
    protected static string $resource = HebrewWordResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ViewAction::make(),
            DeleteAction::make(),
        ];
    }
}
