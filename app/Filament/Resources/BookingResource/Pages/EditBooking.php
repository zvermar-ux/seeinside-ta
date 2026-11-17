<?php

namespace App\Filament\Resources\BookingResource\Pages;

use App\Filament\Resources\BookingResource;
use App\Models\Booking;
use App\Services\Documents\Documents;
use App\Services\Finance\InvoiceBuilder;
use Filament\Actions;
use Filament\Forms;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Livewire\Attributes\On;

class EditBooking extends EditRecord
{
    protected static string $resource = BookingResource::class;

    protected function getHeaderActions(): array
    {
        return static::documentActions(fn () => $this->record, $this);
    }

    /** Print / e-mail the contract and voucher, issue a proforma or invoice (also used on the view of a booking) */
    public static function documentActions(\Closure $record, ?EditRecord $page = null): array
    {
        return [
            Actions\ActionGroup::make([
                Actions\Action::make('contract')->label(__('Contract'))->icon('heroicon-o-document-text')
                    ->url(fn () => Documents::url('contract', $record()->id), shouldOpenInNewTab: true),
                Actions\Action::make('voucher')->label(__('Voucher'))->icon('heroicon-o-ticket')
                    ->url(fn () => Documents::url('voucher', $record()->id), shouldOpenInNewTab: true),
                Actions\Action::make('qr')->label(__('QR code'))->icon('heroicon-o-qr-code')
                    ->modalHeading(fn () => __('QR code').' '.$record()->reference)->modalSubmitAction(false)->modalCancelActionLabel(__('Close'))->modalWidth('lg')
                    ->modalContent(fn () => new \Illuminate\Support\HtmlString(
                        '<div style="display:grid;grid-template-columns:1fr 1fr;gap:1rem;text-align:center">'
                        .'<div><div style="display:inline-block;background:#fff;padding:6px;border-radius:8px">'.\App\Support\QrCode::svg($record()->verifyUrl(), 170).'</div><p style="font-size:.85rem;margin-top:.4rem"><b>'.e(__('Check-in / verification')).'</b><br>'.e(__('For the hotel, guide or bus')).'</p>'
                        .'<p><a style="color:rgb(var(--primary-600));font-size:.85rem" href="'.e($record()->qrPngUrl()).'" download="'.e($record()->reference).'.png">'.e(__('Download PNG')).'</a> · <a style="color:rgb(var(--primary-600));font-size:.85rem" target="_blank" href="'.e($record()->verifyUrl()).'">'.e(__('Open')).'</a></p></div>'
                        .'<div><div style="display:inline-block;background:#fff;padding:6px;border-radius:8px">'.\App\Support\QrCode::svg(\App\Http\Controllers\PortalController::url($record()), 170).'</div><p style="font-size:.85rem;margin-top:.4rem"><b>'.e(__('My booking')).'</b><br>'.e(__('Client portal')).'</p>'
                        .'<p><a style="color:rgb(var(--primary-600));font-size:.85rem" target="_blank" href="'.e(\App\Http\Controllers\PortalController::url($record())).'">'.e(__('Open')).'</a></p></div></div>')),
                Actions\Action::make('email')->label(__('Send by e-mail'))->icon('heroicon-o-envelope')
                    ->modalHeading(__('Send a document to the client'))
                    ->fillForm(fn () => ['doc' => 'contract', 'to' => $record()->customer_email])
                    ->form([
                        Forms\Components\Radio::make('doc')->label(__('Document'))->inline()->required()
                            ->options(['contract' => __('Contract'), 'voucher' => __('Voucher')]),
                        Forms\Components\TextInput::make('to')->label(__('To'))->email()->required(),
                        Forms\Components\TagsInput::make('cc')->label(__('Copy (CC)'))->placeholder(__('e-mail')),
                        Forms\Components\Textarea::make('message')->label(__('Message'))->rows(4)
                            ->default(__('Dear,').PHP_EOL.__('please find the document attached.')),
                    ])
                    ->action(function (array $data) use ($record) {
                        $err = Documents::email($data['doc'], $record(), $data['to'], $data['message'] ?? null, $data['cc'] ?? []);
                        $err ? Notification::make()->danger()->title(__('The e-mail was not sent'))->body($err)->persistent()->send()
                            : Notification::make()->success()->title(__('Sent to :to', ['to' => $data['to']]))->send();
                    }),
            ])->label(__('Documents'))->icon('heroicon-o-printer')->button()->color('gray'),
            // the old Aries system: send (again) a booking that did not get there
            Actions\Action::make('legacyPush')->label(__('Send to Aries'))->icon('heroicon-o-arrow-up-tray')->color('warning')
                ->visible(fn () => ! $record()->legacy_ref && \App\Services\Mcs\McsService::connectionFor($record()))
                ->requiresConfirmation()
                ->modalDescription(__('The booking is made in the old Aries system with the dates, room and passengers of this booking (the price is the one of the old system).'))
                ->action(function () use ($record, $page) {
                    $r = \App\Services\Mcs\McsService::push($record());
                    \Filament\Notifications\Notification::make()->title($r['message'])->{$r['ok'] ? 'success' : 'danger'}()->persistent()->send();
                    $page?->refreshFormData(['legacy_status', 'legacy_ref']);
                }),
            // hotel from a booking system (Hotelbeds…): book / cancel there
            Actions\Action::make('supplierBook')->label(__('Book at the supplier'))->icon('heroicon-o-bolt')->color('success')
                ->visible(fn () => $record()->isSupplierHotel() && $record()->supplier_status !== 'booked' && $record()->supplier_status !== 'cancelled')
                ->requiresConfirmation()
                ->modalDescription(fn () => __('A real booking at :sys for :price :cur (net :net). The guests are taken from the passengers of the booking.', [
                    'sys' => $record()->supplierConnection()->withoutGlobalScopes()->value('name'), 'price' => $record()->supplier_data['price'] ?? $record()->total,
                    'cur' => $record()->currency, 'net' => $record()->supplier_data['net'] ?? '—',
                ]))
                ->action(function () use ($record, $page) {
                    try {
                        $b = app(\App\Services\Hotels\HotelService::class)->bookAtSupplier($record());
                        Notification::make()->success()->title(__('Booked: :ref', ['ref' => $b->external_ref]))->persistent()->send();
                        $page?->refreshFormData(['status', 'external_ref']);
                    } catch (\Throwable $e) {
                        Notification::make()->danger()->title(__('The supplier refused the booking'))->body($e->getMessage())->persistent()->send();
                    }
                }),
            Actions\Action::make('supplierCancel')->label(__('Cancel at the supplier'))->icon('heroicon-o-x-circle')->color('danger')
                ->visible(fn () => $record()->isSupplierHotel() && $record()->supplier_status === 'booked')
                ->requiresConfirmation()
                ->modalDescription(function () use ($record) {
                    try {
                        $sim = app(\App\Services\Hotels\HotelService::class)->cancelAtSupplier($record(), true);

                        return __('Cancellation fee according to the supplier: :fee', ['fee' => $sim['fee'] !== null ? $sim['fee'].' '.$record()->currency : '—']);
                    } catch (\Throwable $e) {
                        return $e->getMessage();
                    }
                })
                ->action(function () use ($record) {
                    try {
                        app(\App\Services\Hotels\HotelService::class)->cancelAtSupplier($record());
                        Notification::make()->success()->title(__('Cancelled at the supplier'))->send();
                    } catch (\Throwable $e) {
                        Notification::make()->danger()->title(__('Not cancelled'))->body($e->getMessage())->persistent()->send();
                    }
                }),
            Actions\Action::make('issueInvoice')->label(__('Proforma / invoice'))->icon('heroicon-o-document-currency-euro')->color('gray')
                ->visible(fn () => auth()->user()?->isSuperAdmin() || auth()->user()?->can('finance.manage'))
                ->modalHeading(__('Issue a proforma or invoice for this booking'))
                ->fillForm(function () use ($record) {
                    $b = $record();

                    return ['type' => 'proforma', 'amount_mode' => $b->balance() > 0 && $b->balance() < (float) $b->total ? 'balance' : 'total', 'currency' => \App\Models\Agency::find($b->agency_id)?->doc('base_currency', 'MKD')];
                })
                ->form(fn () => [
                    Forms\Components\Radio::make('type')->label(__('Document'))->inline()->required()
                        ->options(['proforma' => __('Proforma invoice'), 'invoice' => __('Invoice')]),
                    Forms\Components\Radio::make('amount_mode')->label(__('Amount'))->live()->required()
                        ->options([
                            'total' => __('Whole amount').' ('.number_format((float) $record()->total, 2, ',', '.').' '.$record()->currency.')',
                            'balance' => __('Remaining balance').' ('.number_format($record()->balance(), 2, ',', '.').' '.$record()->currency.')',
                            'custom' => __('Other amount (e.g. deposit)'),
                        ]),
                    Forms\Components\TextInput::make('amount')->label(__('Amount in').' '.$record()->currency)->numeric()->required()
                        ->visible(fn (Forms\Get $get) => $get('amount_mode') === 'custom'),
                    Forms\Components\Select::make('currency')->label(__('Invoice currency'))->options(['MKD' => 'MKD', 'EUR' => 'EUR'])->required()
                        ->helperText(__('Amounts in EUR are converted with the booking rate.')),
                ])
                ->action(function (array $data) use ($record) {
                    $b = $record();
                    $amount = match ($data['amount_mode']) {
                        'balance' => $b->balance(),
                        'custom' => (float) $data['amount'],
                        default => null,
                    };
                    $inv = InvoiceBuilder::createFromBooking($b, $data['type'], $amount, $data['currency']);
                    Notification::make()->success()->title($inv->title())
                        ->actions([
                            \Filament\Notifications\Actions\Action::make('open')->label(__('Open'))->url(\App\Filament\Resources\InvoiceResource::getUrl('edit', ['record' => $inv])),
                            \Filament\Notifications\Actions\Action::make('print')->label(__('Print'))->url(Documents::url('invoice', $inv->id), shouldOpenInNewTab: true),
                        ])->persistent()->send();
                }),
            Actions\DeleteAction::make(),
        ];
    }

    /** A payment was added / changed below: show the new paid amount and status in the form */
    #[On('refresh-booking')]
    public function refreshPaid(): void
    {
        $this->record->refresh();
        $this->data['paid_amount'] = $this->record->paid_amount;
        $this->data['payment_status'] = $this->record->payment_status?->value;
    }
}
