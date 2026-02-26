<?php

declare(strict_types=1);

namespace App\Filament\Exporters;

use App\Models\Lead;
use Filament\Actions\Exports\ExportColumn;
use Filament\Actions\Exports\Exporter;
use Filament\Actions\Exports\Models\Export;

class LeadExporter extends Exporter
{
    protected static ?string $model = Lead::class;

    public static function getColumns(): array
    {
        return [
            ExportColumn::make('email'),
            ExportColumn::make('source'),
            ExportColumn::make('ip_address'),
            ExportColumn::make('subscribed_at'),
            ExportColumn::make('created_at'),
        ];
    }

    public static function getCompletedNotificationBody(Export $export): string
    {
        return 'Your lead export has completed. '.number_format($export->successful_rows).' rows exported.';
    }
}
