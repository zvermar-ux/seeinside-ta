<?php

namespace App\Services\Payments;

use App\Enums\PaymentStatus;
use App\Models\Agency;
use App\Models\Booking;
use App\Models\BookingPayment;
use App\Models\OnlinePayment;
use App\Models\VisaApplication;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/** The answer of the bank → the payment is registered on the booking / visa application (once) */
class OnlinePayments
{
    /** @return OnlinePayment the payment after the answer */
    public static function handle(OnlinePayment $p, array $post): OnlinePayment
    {
        $agency = Agency::find($p->agency_id);
        $ok = Halk::verify($post, $agency);
        $clean = Arr::except($post, ['HASH', 'hash', 'HASHPARAMS', 'HASHPARAMSVAL', 'pan', 'cv2', 'Ecom_Payment_Card_ExpDate_Year', 'Ecom_Payment_Card_ExpDate_Month']);

        return DB::transaction(function () use ($p, $post, $ok, $clean, $agency) {
            $p = OnlinePayment::withoutGlobalScopes()->lockForUpdate()->findOrFail($p->id);
            if ($p->status === 'approved') {
                return $p;   // the bank (or the browser) sent the answer twice
            }
            $sameOrder = (string) ($post['oid'] ?? $post['ReturnOid'] ?? $p->oid) === $p->oid;
            $sameAmount = ! isset($post['amount']) || abs((float) $post['amount'] - (float) $p->amount) < 0.01;
            $approved = $ok && $sameOrder && $sameAmount && Halk::approved($post);
            $p->fill([
                'response' => $clean,
                'auth_code' => $post['AuthCode'] ?? null,
                'trans_id' => $post['TransId'] ?? null,
                'card_mask' => $post['MaskedPan'] ?? null,
                'status' => $approved ? 'approved' : ($ok ? 'declined' : 'error'),
                'message' => ! $ok ? 'Hash error' : (! $sameOrder || ! $sameAmount ? 'Order / amount mismatch' : mb_substr((string) ($post['ErrMsg'] ?? $post['mdErrorMsg'] ?? ($post['Response'] ?? '')), 0, 250)),
                'paid_at' => $approved ? now() : null,
            ])->save();
            if ($approved) {
                self::register($p);
                DB::afterCommit(function () use ($p, $agency) {
                    try {
                        $p->forceFill(['status_check' => Halk::statusQuery($p, $agency)])->saveQuietly();
                    } catch (\Throwable $e) {
                        report($e);
                    }
                });
            } else {
                Log::info('Halk payment not approved', ['oid' => $p->oid, 'message' => $p->message]);
            }

            return $p;
        });
    }

    /** The money is in: a payment on the booking (in its currency) or on the visa application */
    protected static function register(OnlinePayment $p): void
    {
        $what = $p->payable()->withoutGlobalScopes()->first();
        $note = 'Halk Bank · '.__('online card payment').' · '.number_format((float) $p->amount, 2, ',', '.').' '.$p->currency
            .($p->auth_code ? ' · Auth '.$p->auth_code : '').($p->trans_id ? ' · Trans '.$p->trans_id : '').($p->card_mask ? ' · '.$p->card_mask : '');
        if ($what instanceof Booking) {
            $same = $what->currency === $p->currency;
            BookingPayment::withoutGlobalScopes()->create([
                'agency_id' => $what->agency_id, 'booking_id' => $what->id, 'paid_at' => now()->toDateString(),
                'method' => 'online', 'reference' => $p->oid, 'note' => $note,
                'amount' => (float) $p->booking_amount,
            ] + ($same ? [] : [
                'currency' => $p->currency, 'original_amount' => (float) $p->amount,
                'exchange_rate' => (float) $p->booking_amount > 0 ? round((float) $p->amount / (float) $p->booking_amount, 4) : null,
            ]));
            // paid by card: now it goes to the old Aries system too (it waited for the payment)
            $what->refresh();
            if (! $what->legacy_ref) {
                \App\Services\Mcs\McsService::queue($what);
            }
        } elseif ($what instanceof VisaApplication) {
            $paid = round((float) $what->paid_amount + (float) $p->booking_amount, 2);
            $what->forceFill([
                'paid_amount' => $paid,
                'payment_status' => (float) $what->amount > 0 && $paid + 0.009 < (float) $what->amount ? PaymentStatus::Deposit : PaymentStatus::Paid,
                'internal_notes' => trim(((string) $what->internal_notes)."\n".now()->format('d.m.Y H:i').' · '.$note),
            ])->save();
        }
    }

    /** All attempts of a booking / application, newest first */
    public static function of(Booking|VisaApplication $x)
    {
        return OnlinePayment::withoutGlobalScopes()->where('payable_type', $x->getMorphClass())->where('payable_id', $x->id)->latest('id')->get();
    }
}
