<?php

namespace App\Services\Mail;

use App\Models\Agency;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Throwable;

/** E-mail on behalf of an agency: its own SMTP server when set, otherwise the application's mailer */
class AgencyMail
{
    /**
     * @param  array<int, array{0:string,1:string,2?:string}>  $attachments  [content, filename, mime]
     * @return string|null error message, null when sent
     */
    public static function send(?Agency $agency, string|array $to, string $subject, string $html, array $cc = [], array $attachments = [], ?string $replyTo = null): ?string
    {
        $to = array_values(array_filter((array) $to));
        if (! $to) {
            return __('No e-mail address.');
        }
        try {
            $mailer = Mail::mailer();
            if ($agency && filled($agency->mail_host) && filled($agency->mail_username)) {
                $mailer = Mail::build([
                    'transport' => 'smtp',
                    'host' => $agency->mail_host,
                    'port' => (int) ($agency->mail_port ?: 587),
                    'encryption' => $agency->mail_encryption ?: null,
                    'username' => $agency->mail_username,
                    'password' => $agency->mail_password,
                    'timeout' => 20,
                ]);
            }
            $from = $agency?->mail_from_address ?: $agency?->email ?: config('mail.from.address');
            $mailer->html($html, function ($m) use ($to, $cc, $subject, $attachments, $from, $agency, $replyTo) {
                $m->to($to)->subject($subject);
                if ($from) {
                    $m->from($from, $agency?->name ?? config('mail.from.name'));
                }
                if ($cc = array_values(array_filter($cc))) {
                    $m->cc($cc);
                }
                if ($replyTo ?: $agency?->email) {
                    $m->replyTo($replyTo ?: $agency->email);
                }
                foreach ($attachments as $a) {
                    $m->attachData($a[0], $a[1], ['mime' => $a[2] ?? 'text/html']);
                }
            });

            return null;
        } catch (Throwable $e) {
            Log::warning('Agency e-mail failed: '.$e->getMessage());

            return $e->getMessage();
        }
    }
}
