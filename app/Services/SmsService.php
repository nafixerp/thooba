<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Pluggable SMS sender.
 *
 * Driver is chosen with SMS_DRIVER in .env:
 *   log     - writes to storage/logs/laravel.log (default, no credentials)
 *   msg91   - MSG91 flow API
 *   twilio  - Twilio REST API
 *
 * Adding a provider = one more send<Name>() method plus a match arm.
 */
class SmsService
{
    /** Send a plain text SMS. Returns true when the provider accepted it. */
    public function send(string $phone, string $message, array $vars = []): bool
    {
        $phone = $this->normalize($phone);
        $driver = config('mobile.sms.driver', 'log');

        try {
            return match ($driver) {
                'msg91' => $this->sendMsg91($phone, $message, $vars),
                'twilio' => $this->sendTwilio($phone, $message),
                default => $this->sendLog($phone, $message),
            };
        } catch (\Throwable $e) {
            Log::error('SMS send failed', [
                'driver' => $driver,
                'phone' => $phone,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    /**
     * Can this install actually put an SMS on a phone?
     *
     * False means every OTP quietly lands in the log file instead of the
     * customer's handset. The two drivers already fall back to the log when
     * their credentials are incomplete, which keeps the API answering "OTP
     * sent" while nothing is sent - so the app asks this before offering an
     * OTP login at all, rather than leaving people waiting for a code that
     * was never going anywhere.
     */
    public function configured(): bool
    {
        return match (config('mobile.sms.driver', 'log')) {
            'msg91' => filled(config('mobile.sms.msg91.auth_key')),
            'twilio' => filled(config('mobile.sms.twilio.sid'))
                && filled(config('mobile.sms.twilio.token'))
                && filled(config('mobile.sms.twilio.from')),
            // The log driver is for development; it delivers to nobody.
            default => false,
        };
    }

    /** Strip spaces/dashes and drop a leading +91 or 0 so we store 10 digits. */
    public function normalize(string $phone): string
    {
        $p = preg_replace('/[^0-9]/', '', $phone) ?? '';

        if (strlen($p) === 12 && str_starts_with($p, '91')) {
            $p = substr($p, 2);
        }
        if (strlen($p) === 11 && str_starts_with($p, '0')) {
            $p = substr($p, 1);
        }

        return $p;
    }

    protected function sendLog(string $phone, string $message): bool
    {
        Log::channel(config('logging.default'))->info("[SMS] to +91{$phone}: {$message}");

        return true;
    }

    protected function sendMsg91(string $phone, string $message, array $vars): bool
    {
        $cfg = config('mobile.sms.msg91');

        if (empty($cfg['auth_key'])) {
            Log::warning('MSG91 auth key missing, falling back to log driver');

            return $this->sendLog($phone, $message);
        }

        // Flow API (DLT template) when a template id is configured.
        if (! empty($cfg['template_id'])) {
            $res = Http::asJson()
                ->withHeaders(['authkey' => $cfg['auth_key']])
                ->post('https://control.msg91.com/api/v5/flow/', [
                    'template_id' => $cfg['template_id'],
                    'short_url' => '0',
                    'recipients' => [array_merge(
                        ['mobiles' => $cfg['country'].$phone],
                        $vars
                    )],
                ]);

            return $this->msg91Succeeded($res, $phone, 'flow');
        }

        $res = Http::get('https://api.msg91.com/api/sendhttp.php', [
            'authkey' => $cfg['auth_key'],
            'mobiles' => $cfg['country'].$phone,
            'message' => $message,
            'sender' => config('mobile.sms.sender'),
            'route' => $cfg['route'],
            'country' => $cfg['country'],
        ]);

        return $this->msg91Succeeded($res, $phone, 'sendhttp');
    }

    /**
     * Did MSG91 actually accept the message?
     *
     * A plain $res->successful() is not enough: MSG91 answers HTTP 200 and
     * puts the refusal in the body - no credits, template not approved, sender
     * id not registered. Trusting the status code made every one of those look
     * like a delivered SMS, so an OTP that never arrived left no trace at all.
     */
    protected function msg91Succeeded(\Illuminate\Http\Client\Response $res, string $phone, string $api): bool
    {
        $body = trim((string) $res->body());
        $json = $res->json();

        $failed = ! $res->successful()
            || (is_array($json) && ($json['type'] ?? null) === 'error')
            || str_starts_with(strtolower($body), '{"type":"error"');

        if ($failed) {
            Log::error('MSG91 refused the message', [
                'api' => $api,
                'phone' => $phone,
                'status' => $res->status(),
                // The reason is in the body - keep it, it is the only clue the
                // shop gets when customers say the OTP never came.
                'response' => mb_substr($body, 0, 300),
            ]);

            return false;
        }

        return true;
    }

    protected function sendTwilio(string $phone, string $message): bool
    {
        $cfg = config('mobile.sms.twilio');

        // TWILIO_FROM matters as much as the credentials: without a sending
        // number Twilio rejects every request, and an undelivered OTP looks
        // exactly like a broken login. Fall back to the log driver instead.
        if (empty($cfg['sid']) || empty($cfg['token']) || empty($cfg['from'])) {
            Log::warning('Twilio not fully configured, falling back to log driver', [
                'sid' => ! empty($cfg['sid']),
                'token' => ! empty($cfg['token']),
                'from' => ! empty($cfg['from']),
            ]);

            return $this->sendLog($phone, $message);
        }

        $res = Http::withBasicAuth($cfg['sid'], $cfg['token'])
            ->asForm()
            ->post("https://api.twilio.com/2010-04-01/Accounts/{$cfg['sid']}/Messages.json", [
                'From' => $cfg['from'],
                'To' => '+91'.$phone,
                'Body' => $message,
            ]);

        if (! $res->successful()) {
            // Twilio explains itself properly (21608 unverified trial number,
            // 21606 bad From, 21408 region not enabled). Swallowing that left
            // nothing to debug from.
            Log::error('Twilio rejected the message', [
                'status' => $res->status(),
                'code' => $res->json('code'),
                'message' => $res->json('message'),
            ]);

            return false;
        }

        return true;
    }
}
