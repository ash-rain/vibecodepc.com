<?php

declare(strict_types=1);

namespace App\Filament\Resources;

use App\Filament\Resources\DeviceResource\Pages;
use App\Models\Device;
use BackedEnum;
use Filament\Forms;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;
use VibecodePC\Common\Enums\DeviceStatus;

class DeviceResource extends Resource
{
    protected static ?string $model = Device::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-cpu-chip';

    protected static ?string $navigationLabel = 'Devices';

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Forms\Components\TextInput::make('uuid')
                    ->label('Device UUID')
                    ->required()
                    ->unique(ignoreRecord: true)
                    ->maxLength(36),
                Forms\Components\Select::make('status')
                    ->options([
                        'unclaimed' => 'Unclaimed',
                        'claimed' => 'Claimed',
                        'deactivated' => 'Deactivated',
                    ])
                    ->required()
                    ->default('unclaimed'),
                Forms\Components\Select::make('user_id')
                    ->relationship('user', 'email')
                    ->searchable()
                    ->nullable(),
                Forms\Components\TextInput::make('hardware_serial')
                    ->maxLength(255),
                Forms\Components\TextInput::make('firmware_version')
                    ->maxLength(255),
                Forms\Components\TextInput::make('ip_hint')
                    ->label('IP Hint')
                    ->maxLength(255),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('uuid')
                    ->label('UUID')
                    ->searchable()
                    ->limit(12)
                    ->tooltip(fn (Device $record) => $record->uuid),
                Tables\Columns\TextColumn::make('status')
                    ->badge()
                    ->color(fn (DeviceStatus $state) => match ($state) {
                        DeviceStatus::Unclaimed => 'gray',
                        DeviceStatus::Claimed => 'success',
                        DeviceStatus::Deactivated => 'danger',
                    })
                    ->sortable(),
                Tables\Columns\TextColumn::make('user.email')
                    ->label('Owner')
                    ->searchable()
                    ->placeholder('Unclaimed'),
                Tables\Columns\TextColumn::make('hardware_serial')
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('firmware_version')
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('ip_hint')
                    ->label('IP')
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('paired_at')
                    ->dateTime()
                    ->sortable()
                    ->placeholder('Not paired'),
                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->options([
                        'unclaimed' => 'Unclaimed',
                        'claimed' => 'Claimed',
                        'deactivated' => 'Deactivated',
                    ]),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListDevices::route('/'),
            'create' => Pages\CreateDevice::route('/create'),
            'edit' => Pages\EditDevice::route('/{record}/edit'),
        ];
    }
}
