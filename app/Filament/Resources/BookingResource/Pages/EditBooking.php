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
