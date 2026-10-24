<?php

namespace App\Console\Commands;

use App\Enums\BookingStatus;
use App\Models\Agency;
use App\Models\Booking;
use App\Models\Invoice;
use App\Models\Message;
use App\Services\Messaging\Messenger;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

/**
 * Daily reminders (runs every morning from the scheduler):
 * payment due tomorrow, invoice due tomorrow, option expires tomorrow, trip tomorrow, review after the trip,
 * and a summary of due / overdue payments for the agency.
 */
class SendReminders extends Command
{
    protected $signature = 'reminders:send {--agency= : only this agency id} {--dry : only list, send nothing}';

    protected $description = 'Send payment, invoice, option and trip reminders to clients (e-mail / WhatsApp / Viber)';

    public function handle(): int
    {
        $tomorrow = today()->addDay();
        $active = [BookingStatus::Inquiry->value, BookingStatus::Pending->value, BookingStatus::Confirmed->value];
        $agencies = Agency::query()->where('is_active', true)->when($this->option('agency'), fn ($q, $id) => $q->whereKey($id))->get();
        $total = 0;

        foreach ($agencies as $a) {
            $bq = fn () => Booking::withoutGlobalScopes()->where('agency_id', $a->id);
            $sent = 0;

            // 1) payment of a booking due tomorrow
            foreach ($bq()->whereIn('status', $active)->whereDate('payment_due', $tomorrow)->whereColumn('paid_amount', '<', 'total')->get() as $b) {
                $sent += $this->fire('payment_reminder', $b);
            }
            // 2) invoice due tomorrow
            foreach (Invoice::withoutGlobalScopes()->where('agency_id', $a->id)->where('type', 'invoice')->whereIn('status', ['issued', 'partial'])->whereDate('due_date', $tomorrow)->get() as $i) {
                $sent += $this->fire('invoice_reminder', $i->booking, $i);
            }
            // 3) reservation (option) expires tomorrow and nothing paid yet
            foreach ($bq()->whereIn('status', [BookingStatus::Inquiry->value, BookingStatus::Pending->value])->whereDate('option_until', $tomorrow)->get() as $b) {
                $sent += $this->fire('option_expiring', $b);
            }
            // 4) trip starts tomorrow
            foreach ($bq()->where('status', BookingStatus::Confirmed->value)->where(fn ($q) => $q->whereDate('date_from', $tomorrow)
                ->orWhere(fn ($q) => $q->whereNull('date_from')->whereHas('departure', fn ($d) => $d->whereDate('start_date', $tomorrow))))->get() as $b) {
                $sent += $this->fire('trip_reminder', $b, once: true);
            }
            // 5) the day after the trip: ask for a review
            foreach ($bq()->whereIn('status', [BookingStatus::Confirmed->value, BookingStatus::Completed->value])->whereDate('date_to', today()->subDay())->get() as $b) {
                $sent += $this->fire('review_request', $b, once: true);
            }
            // 6) summary for the agency: due tomorrow + overdue
            $sent += $this->digest($a, $tomorrow, $active);

            if ($sent) {
                $this->line($a->name.': '.$sent);
            }
            $total += $sent;
        }
        $this->info('Sent: '.$total);

        return self::SUCCESS;
    }

    /** One reminder; never twice on the same day (or ever, with $once) */
    protected function fire(string $event, ?Booking $b, ?Invoice $i = null, bool $once = false): int
    {
        if (! $b && ! $i) {
            return 0;
        }
        $dup = Message::withoutGlobalScopes()->where('event', $event)->where('status', 'sent')
            ->when($i, fn ($q) => $q->where('invoice_id', $i->id), fn ($q) => $q->where('booking_id', $b->id))
            ->when(! $once, fn ($q) => $q->where('created_at', '>=', today()))->exists();
        if ($dup) {
            return 0;
        }
        if ($this->option('dry')) {
            $this->line(' - '.$event.' '.($b?->reference ?? $i?->number));

            return 1;
        }

        return Messenger::notify($event, $b, $i);
    }

    protected function digest(Agency $a, Carbon $tomorrow, array $active): int
    {
        if (Message::withoutGlobalScopes()->where('agency_id', $a->id)->where('event', 'agency:reminders_digest')->where('created_at', '>=', today())->exists()) {
            return 0;
        }
        $lines = [];
        $due = Booking::withoutGlobalScopes()->where('agency_id', $a->id)->whereIn('status', $active)->whereNotNull('payment_due')
            ->whereDate('payment_due', '<=', $tomorrow)->whereColumn('paid_amount', '<', 'total')->orderBy('payment_due')->limit(50)->get();
        foreach ($due as $b) {
            $lines[] = ($b->payment_due->lt(today()) ? '❗ ' : '• ').$b->payment_due->format('d.m').' · '.$b->reference.' · '.$b->customer_name.' · '.$b->itemTitle().' · '.number_format($b->balance(), 2, ',', '.').' '.$b->currency;
        }
        $inv = Invoice::withoutGlobalScopes()->where('agency_id', $a->id)->where('type', 'invoice')->whereIn('status', ['issued', 'partial'])
            ->whereDate('due_date', '<=', $tomorrow)->orderBy('due_date')->limit(50)->get();
        foreach ($inv as $i) {
            $lines[] = ($i->due_date->lt(today()) ? '❗ ' : '• ').$i->due_date->format('d.m').' · '.__('Invoice').' '.$i->number.' · '.$i->client_name.' · '.number_format($i->balance(), 2, ',', '.').' '.$i->currency;
        }
        $opt = Booking::withoutGlobalScopes()->where('agency_id', $a->id)->whereIn('status', [BookingStatus::Inquiry->value, BookingStatus::Pending->value])
            ->whereNotNull('option_until')->whereDate('option_until', '<=', $tomorrow)->limit(50)->get();
        foreach ($opt as $b) {
            $lines[] = '⏳ '.$b->option_until->format('d.m').' · '.__('Option').' '.$b->reference.' · '.$b->customer_name.' · '.$b->itemTitle();
        }
        if (! $lines || $this->option('dry')) {
            foreach ($this->option('dry') ? $lines : [] as $l) {
                $this->line('   '.$l);
            }

            return 0;
        }
        $text = __('Payments and deadlines due tomorrow or already late (❗ = late):')."\n\n".implode("\n", $lines)."\n\n".url('/admin/cash-journal');

        return Messenger::notify('reminders_digest', null, null, ['{message}' => $text], channels: null, agency: $a);
    }
}
