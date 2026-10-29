<?php

namespace App\Services;

use App\Models\OtpCode;
use Illuminate\Support\Facades\Hash;
use RuntimeException;

/**
 * OTP issue + verify. Codes are stored hashed and single use.
 */
class OtpService
{
    public function __construct(protected SmsService $sms) {}

    /**
     * Issue an OTP for a phone number.
     *
     * @return array{otp_id:int, expires_in:int, resend_after:int, code:?string}
     */
    public function issue(string $phone, string $purpose = 'LOGIN', ?string $ip = null): array
    {
        $phone = $this->sms->normalize($phone);
        $cfg = config('mobile.otp');

        $this->guardRateLimit($phone, $purpose);

        $code = $this->generateCode((int) $cfg['length']);

        // Any earlier unused code for this phone/purpose is retired.
        OtpCode::usable($phone, $purpose)->update(['expires_at' => now()]);

        $otp = OtpCode::create([
            'phone' => $phone,
            'code_hash' => Hash::make($code),
            'purpose' => $purpose,
            'expires_at' => now()->addSeconds((int) $cfg['ttl_seconds']),
            'ip' => $ip,
        ]);

        $this->sms->send(
            $phone,
            config('mobile.app_name').': Your OTP is '.$code.'. Valid for '
                .max(1, (int) round($cfg['ttl_seconds'] / 60)).' minutes. Do not share it with anyone.',
            ['otp' => $code]
        );

        return [
            'otp_id' => $otp->id,
            'expires_in' => (int) $cfg['ttl_seconds'],
            'resend_after' => (int) $cfg['resend_after'],
            // Only exposed while OTP_EXPOSE / APP_DEBUG is on, for testing.
            'code' => $cfg['expose_in_response'] ? $code : null,
        ];
    }

    /**
     * Verify a code. Throws RuntimeException with a user-safe message on failure.
     */
    public function verify(string $phone, string $code, string $purpose = 'LOGIN'): OtpCode
    {
        $phone = $this->sms->normalize($phone);

        $otp = OtpCode::usable($phone, $purpose)->latest('id')->first();

        if (! $otp) {
            throw new RuntimeException('OTP expired or not requested. Please request a new one.');
        }

        if ($otp->attempts >= (int) config('mobile.otp.max_attempts')) {
            throw new RuntimeException('Too many wrong attempts. Please request a new OTP.');
        }

        if (! $otp->matches($code)) {
            $otp->increment('attempts');

            throw new RuntimeException('The OTP you entered is incorrect.');
        }

        $otp->update(['verified_at' => now()]);

        return $otp;
    }

    /** Throttle how many OTPs one number can pull in an hour, and how fast. */
    protected function guardRateLimit(string $phone, string $purpose): void
    {
        $cfg = config('mobile.otp');

        $recent = OtpCode::where('phone', $phone)
            ->where('created_at', '>=', now()->subHour())
            ->count();

        if ($recent >= (int) $cfg['max_per_hour']) {
            throw new RuntimeException('Too many OTP requests. Please try again after an hour.');
        }

        $last = OtpCode::where('phone', $phone)->where('purpose', $purpose)->latest('id')->first();

        if ($last && $last->created_at->diffInSeconds(now()) < (int) $cfg['resend_after']) {
            $wait = (int) $cfg['resend_after'] - (int) $last->created_at->diffInSeconds(now());

            throw new RuntimeException("Please wait {$wait} seconds before requesting another OTP.");
        }
    }

    protected function generateCode(int $length): string
    {
        $min = (int) str_pad('1', $length, '0');
        $max = (int) str_repeat('9', $length);

        return (string) random_int($min, $max);
    }
}
