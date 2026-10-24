<?php

namespace App\Filament\Pages;

use App\Enums\BookingStatus;
use App\Models\Agency;
use App\Models\Booking;
use App\Models\Invoice;
use App\Models\SupplierInvoice;
use App\Services\Documents\Documents;
use App\Services\Finance\Finance;
use Filament\Pages\Page;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Url;

/** Дневник на благајна: opening balance, receipts, payouts and closing balance of a register per day / period + debts overview */
class CashJournal extends Page
{
    use \App\Filament\Concerns\CustomizableNavigation;

    protected static ?string $navigationIcon = 'heroicon-o-book-open';

    protected static ?string $navigationGroup = 'finance';

    protected static ?int $navigationSort = 4;

    protected static ?string $slug = 'cash-journal';

    protected static string $view = 'filament.pages.cash-journal';

    #[Url]
    public ?int $agency = null;

    #[Url]
    public ?string $register = null;

    #[Url]
    public ?string $from = null;

    #[Url]
    public ?string $to = null;

    public static function defaultNavigationLabel(): string
    {
        return __('Cash journal');
    }

    public function getTitle(): string
    {
        return __('Cash journal');
    }

    public static function canAccess(): bool
    {
        if (! \App\Models\Agency::moduleOn('finance')) {
            return false;
        }
        $u = Auth::user();

        return (bool) ($u && ($u->isSuperAdmin() || $u->can('finance.view')));
    }

    public function mount(): void
    {
        $this->from ??= today()->toDateString();
        $this->to ??= $this->from;
        $this->register ??= Finance::registerFor('cash', $this->agencyModel());
    }

    public function agencyModel(): ?Agency
    {
        $u = Auth::user();
        $id = $u->isSuperAdmin() ? ($this->agency ?: Agency::query()->orderBy('name')->value('id')) : $u->agency_id;

        return $id ? Agency::find($id) : null;
    }

    public function shift(int $days): void
    {
        $from = \Carbon\Carbon::parse($this->from)->addDays($days);
        $len = \Carbon\Carbon::parse($this->from)->diffInDays(\Carbon\Carbon::parse($this->to));
        $this->from = $from->toDateString();
        $this->to = $from->copy()->addDays($len)->toDateString();
    }

    public function preset(string $p): void
    {
        [$this->from, $this->to] = match ($p) {
            'week' => [today()->startOfWeek()->toDateString(), today()->endOfWeek()->toDateString()],
            'month' => [today()->startOfMonth()->toDateString(), today()->endOfMonth()->toDateString()],
            'year' => [today()->startOfYear()->toDateString(), today()->endOfYear()->toDateString()],
            default => [today()->toDateString(), today()->toDateString()],
        };
    }

    protected function getViewData(): array
    {
        $agency = $this->agencyModel();
        if (! $agency) {
            return ['agencyModel' => null];
        }
        $from = $this->from ?: today()->toDateString();
        $to = max($from, $this->to ?: $from);
        $journal = Finance::journal($agency->id, (string) $this->register, $from, $to);

        // what clients owe (active bookings), unpaid invoices, what the agency owes suppliers
        $active = [BookingStatus::Pending->value, BookingStatus::Confirmed->value, BookingStatus::Completed->value];
        $debtors = Booking::query()->where('bookings.agency_id', $agency->id)->whereIn('status', $active)->whereColumn('paid_amount', '<', 'total')
            ->selectRaw('currency, COUNT(*) n, SUM(total - paid_amount) s')->groupBy('currency')->get();
        $invoices = Invoice::query()->where('agency_id', $agency->id)->where('type', 'invoice')->whereIn('status', ['issued', 'partial'])
            ->selectRaw('currency, COUNT(*) n, SUM(total - paid_amount) s, SUM(CASE WHEN due_date < ? THEN total - paid_amount ELSE 0 END) overdue', [today()->toDateString()])->groupBy('currency')->get();
        $suppliers = SupplierInvoice::query()->where('agency_id', $agency->id)->whereColumn('paid_amount', '<', 'amount')
            ->selectRaw('currency, COUNT(*) n, SUM(amount - paid_amount) s')->groupBy('currency')->get();

        return [
            'agencyModel' => $agency,
            'agencies' => Auth::user()->isSuperAdmin() ? Agency::orderBy('name')->pluck('name', 'id') : collect(),
            'registers' => Finance::registers($agency),
            'journal' => $journal,
            'printUrl' => Documents::url('journal', $agency->id, ['register' => $this->register, 'from' => $from, 'to' => $to]),
            'debtors' => $debtors, 'invoices' => $invoices, 'suppliers' => $suppliers,
        ];
    }
}
