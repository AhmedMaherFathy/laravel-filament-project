<?php

namespace App\Filament\Resources;

use Illuminate\Support\Facades\Log;
use Filament\Forms;
use Filament\Tables;
use App\Models\Order;
use App\Models\Product;
use Filament\Forms\Get;
use Filament\Forms\Set;
use Filament\Forms\Form;
use Filament\Tables\Table;
use Illuminate\Support\Number;
use Filament\Resources\Resource;
use Filament\Forms\Components\Group;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Textarea;
use Filament\Tables\Columns\TextColumn;
use Filament\Forms\Components\TextInput;
use Filament\Tables\Actions\ActionGroup;
use Filament\Tables\Columns\BadgeColumn;
use Filament\Tables\Columns\SelectColumn;
use Illuminate\Database\Eloquent\Builder;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\ToggleButtons;
use App\Filament\Resources\OrderResource\Pages;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use App\Filament\Resources\OrderResource\RelationManagers;
use App\Filament\Resources\OrderResource\RelationManagers\AddressRelationManager;

class OrderResource extends Resource
{
    protected static ?string $model = Order::class;

    protected static ?string $navigationIcon = 'heroicon-o-rectangle-stack';

    protected static ?string $call = null;
    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Group::make()->schema([
                    Section::make('Order Info')->schema([
                        Select::make('user_id')
                            ->required()
                            ->label('Customer')
                            ->relationship('user', 'name')
                            ->preload()
                            ->searchable(),

                        Select::make('payment_method')
                            ->options([
                                'cash' => 'Cash',
                                'bank_transfer' => 'Bank Transfer',
                            ])
                            ->default('cash')
                            ->required(),

                        Select::make('payment_status')
                            ->options([
                                'pending' => 'Pending',
                                'paid' => 'Paid',
                                'cancelled' => 'Cancelled',
                            ])
                            ->default('pending')
                            ->required(),

                        ToggleButtons::make('status')
                            ->options([
                                'new' => 'New',
                                'in_progress' => 'In Progress',
                                'shipped' => 'Shipping',
                                'completed' => 'Completed',
                                'cancelled' => 'Cancelled',
                            ])
                            ->colors([
                                'new' => 'info',
                                'in_progress' => 'warning',
                                'shipped' => 'warning',
                                'completed' => 'success',
                                'cancelled' => 'danger',
                            ])
                            ->icons([
                                'new' => 'heroicon-m-sparkles',
                                'in_progress' => 'heroicon-m-arrow-path',
                                'shipped' => 'heroicon-m-truck',
                                'completed' => 'heroicon-m-check-circle',
                                'cancelled' => 'heroicon-m-x-circle',
                            ])
                            ->inline()
                            ->default('new')
                            ->required(),

                        Select::make('currency')
                            ->options([
                                'USD' => 'USD',
                                'Eg' => 'Eg',
                            ])
                            ->default('Eg'),

                        Select::make('shipping_method')
                            ->options([
                                'dhl' => 'DHL',
                                'fedex' => 'FedEx'
                            ]),

                        Textarea::make('notes')
                            ->columnSpanFull()

                    ])->columns(2),

                    Section::make('Order Items')->schema([
                        Repeater::make('orderItems')
                            ->relationship()
                            ->schema([
                                Select::make('product_id')
                                    ->relationship('product', 'name')
                                    ->searchable()
                                    ->preload()
                                    ->required()
                                    ->distinct()  // to validate that the state of a field is unique across all items in the repeater
                                    ->disableOptionsWhenSelectedInSiblingRepeaterItems() // لو المنتج اخترته مره مينفش اختاره تانى
                                    ->reactive()
                                    ->afterStateUpdated(function (?string $state, Set $set) {
                                        $product = Product::find($state);
                                        $price = $product?->price ?? 0;
                                        $set('unit_amount', $price);
                                        $set('total_amount', $price);
                                        $set('quantity', 1); 
                                    })
                                    // ->afterStateUpdated(fn(?string $state, Set $set) => $set('unit_amount', Product::find($state)->price ?? 0))
                                    ->columnSpan(4),

                                TextInput::make('quantity')
                                    ->required()
                                    ->numeric()
                                    ->default(1)
                                    ->minValue(1)
                                    ->reactive() // عشان لما بغير الكميه السعر الكلى مش بيتغير
                                    ->afterStateUpdated(fn(?string $state, Set $set, Get $get) => $set('total_amount', ($state * $get('unit_amount'))))
                                    ->columnSpan(2),

                                TextInput::make('unit_amount')
                                    ->numeric()
                                    ->disabled()
                                    ->dehydrated() // any field disabled does not store in database I should use dehydrated()
                                    ->columnSpan(3),

                                TextInput::make('total_amount')
                                    ->numeric()
                                    ->disabled()
                                    ->dehydrated()
                                    ->columnSpan(3),

                            ])->columns(12),

                            Placeholder::make('grand_total_placeholder')
                            ->label('Grand total')
                            ->content(
                                function (Set $set, Get $get) {
                                    $grandTotal = 0;
                                    foreach ($get('orderItems') as $item) {
                                        $grandTotal += $item['total_amount'];
                                        }
                                        $set('grand_total', $grandTotal);
                                        return Number::format($grandTotal ,2);
                                }
                            ),
                            
                            Hidden::make('grand_total')
                            ->default(0),
                    ])

                ])->columnSpanFull(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('user.name')
                ->label('Customer')
                ->searchable(),
                TextColumn::make('grand_total')
                ->label('Total Amount'),
                TextColumn::make('payment_method')
                ->label('Payment Method'),
                SelectColumn::make('payment_status')
                    ->label('Payment Status')
                    ->options([
                        'pending' => 'Pending',
                        'paid' => 'Paid',
                        'cancelled' => 'Cancelled',
                    ])
                    // ->live() // Add this line
                ->afterStateUpdated(function($call = null){
                    if(is_null($call)){
                        $call = static::getNavigationBadgeColor();
                        info($call);
                    }
                    info($call);
                    return $call;
                }),
                    // ->color(function ($state) {
                    //     return match ($state) {
                    //         'paid' => 'success',
                    //         'cancelled' => 'danger',
                    //         default => 'warning',
                        // };
                    // }),
                TextColumn::make('status')
                ->label('Status'),

            ])
            ->filters([
                //
            ])
            ->actions([
                ActionGroup::make([
                    Tables\Actions\ViewAction::make(),
                    Tables\Actions\EditAction::make(),
                ])
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            AddressRelationManager::class,
        ];
    }

    public static function getNavigationBadge(): ?string
    {
        $pendingCount = self::$model::where('payment_status', 'pending')->count();
        return $pendingCount > 0 ? (string) $pendingCount : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return Order::where('payment_status', 'pending')->count() > 0 ? 'danger' : null;
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListOrders::route('/'),
            'create' => Pages\CreateOrder::route('/create'),
            'view' => Pages\ViewOrder::route('/{record}'),
            'edit' => Pages\EditOrder::route('/{record}/edit'),
        ];
    }
}
