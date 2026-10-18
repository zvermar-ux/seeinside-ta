<?php

namespace App\Filament\Resources\BookingResource\RelationManagers;

use App\Models\Booking;
use App\Models\BookingPayment;
use App\Services\Reports\OfferReport;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

/** Client payments (instalments, refunds) of a booking */
class PaymentsRelationManager extends RelationManager
{
    protected static string $relationship = 'payments';

    protected static ?string $icon = 'heroicon-o-banknotes';

    public static function getTitle(Model $ownerRecord, string $pageClass): string
    {
        return __('Payments');
    }

    public static function getModelLabel(): string
    {
        return mb_strtolower(__('Payment'));
    }

    public static function getPluralModelLabel(): string
    {
        return __('Payments');
    }

    public function isReadOnly(): bool
    {
        $u = Auth::user();

        return ! ($u?->isSuperAdmin() || $u?->can('bookings.manage'));
    }

    public function form(Form $form): Form
    {
        /** @var Booking $b */
        $b = $this->getOwnerRecord();

        return $form->columns(2)->schema([
            Forms\Components\TextInput::make('amount')->label(__('Amount'))->numeric()->required()->suffix($b->currency)
                ->default(fn () => $b->balance() ?: null)
                ->helperText(__('Use a negative amount for a refund.')),
            Forms\Components\DatePicker::make('paid_at')->label(__('Date'))->native(false)->displayFormat('d.m.Y')->default(now())->required(),
            Forms\Components\Select::make('method')->label(__('Payment method'))->options(BookingPayment::methods())->default('cash'),
            Forms\Components\TextInput::make('reference')->label(__('Receipt / reference no.'))->maxLength(190),
            Forms\Components\Textarea::make('note')->label(__('Note'))->rows(2)->columnSpanFull(),
        ]);
    }

    public function table(Table $table): Table
    {
        /** @var Booking $b */
        $b = $this->getOwnerRecord();

        return $table
            ->defaultSort('paid_at')
            ->description(fn () => __('Total').': '.OfferReport::money($b->total, $b->currency).' · '.__('Paid').': '.OfferReport::money($b->fresh()->paid_amount, $b->currency).' · '.__('Owes').': '.OfferReport::money($b->fresh()->balance(), $b->currency))
            ->columns([
                Tables\Columns\TextColumn::make('paid_at')->label(__('Date'))->date('d.m.Y'),
                Tables\Columns\TextColumn::make('amount')->label(__('Amount'))->alignEnd()->weight('medium')
                    ->formatStateUsing(fn (BookingPayment $r) => OfferReport::money($r->amount, $b->currency))
                    ->color(fn (BookingPayment $r) => (float) $r->amount < 0 ? 'danger' : 'success'),
                Tables\Columns\TextColumn::make('method')->label(__('Payment method'))->formatStateUsing(fn ($state) => BookingPayment::methods()[$state] ?? $state)->placeholder('—'),
                Tables\Columns\TextColumn::make('reference')->label(__('Receipt / reference no.'))->placeholder('—'),
                Tables\Columns\TextColumn::make('note')->label(__('Note'))->limit(40)->placeholder('—')->toggleable(),
                Tables\Columns\TextColumn::make('creator.name')->label(__('Entered by'))->placeholder('—')->toggleable(),
            ])
            ->headerActions([
                Tables\Actions\CreateAction::make()->label(__('Add payment'))->icon('heroicon-o-plus')
                    ->after(fn () => $this->dispatch('refresh-booking')),
            ])
            ->actions([
                Tables\Actions\EditAction::make()->iconButton()->after(fn () => $this->dispatch('refresh-booking')),
                Tables\Actions\DeleteAction::make()->iconButton()->after(fn () => $this->dispatch('refresh-booking')),
            ])
            ->emptyStateHeading(__('No payments yet'))
            ->paginated(false);
    }
}
