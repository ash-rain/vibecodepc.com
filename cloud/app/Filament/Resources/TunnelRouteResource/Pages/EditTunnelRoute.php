<?php

declare(strict_types=1);

namespace App\Filament\Resources\TunnelRouteResource\Pages;

use App\Filament\Resources\TunnelRouteResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditTunnelRoute extends EditRecord
{
    protected static string $resource = TunnelRouteResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
