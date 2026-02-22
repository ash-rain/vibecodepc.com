<?php

declare(strict_types=1);

namespace App\Filament\Resources;

use App\Filament\Resources\TunnelRouteResource\Pages;
use App\Models\TunnelRoute;
use BackedEnum;
use Filament\Actions;
use Filament\Forms;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;
use UnitEnum;

class TunnelRouteResource extends Resource
{
    protected static ?string $model = TunnelRoute::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-globe-alt';

    protected static ?string $navigationLabel = 'Tunnel Routes';

    protected static UnitEnum|string|null $navigationGroup = 'Infrastructure';

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Forms\Components\Select::make('device_id')
                    ->relationship('device', 'uuid')
                    ->searchable()
                    ->required(),
                Forms\Components\TextInput::make('subdomain')
                    ->required()
                    ->maxLength(63),
                Forms\Components\TextInput::make('path')
                    ->required()
                    ->default('/')
                    ->maxLength(255),
                Forms\Components\TextInput::make('target_port')
                    ->required()
                    ->numeric()
                    ->minValue(1)
                    ->maxValue(65535),
                Forms\Components\TextInput::make('project_name')
                    ->maxLength(255),
                Forms\Components\Toggle::make('is_active')
                    ->default(true),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('device.uuid')
                    ->label('Device')
                    ->limit(12)
                    ->searchable(),
                Tables\Columns\TextColumn::make('subdomain')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('path')
                    ->sortable(),
                Tables\Columns\TextColumn::make('target_port')
                    ->label('Port')
                    ->sortable(),
                Tables\Columns\TextColumn::make('project_name')
                    ->label('Project')
                    ->placeholder('-')
                    ->searchable(),
                Tables\Columns\IconColumn::make('is_active')
                    ->label('Active')
                    ->boolean()
                    ->sortable(),
                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                Tables\Filters\TernaryFilter::make('is_active')
                    ->label('Active Status'),
            ])
            ->actions([
                Actions\EditAction::make(),
                Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Actions\BulkActionGroup::make([
                    Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListTunnelRoutes::route('/'),
            'create' => Pages\CreateTunnelRoute::route('/create'),
            'edit' => Pages\EditTunnelRoute::route('/{record}/edit'),
        ];
    }
}
