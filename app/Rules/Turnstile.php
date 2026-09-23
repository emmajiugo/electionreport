<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Translation\PotentiallyTranslatedString;

class Turnstile implements ValidationRule
{
    public const string VERIFY_URL = 'https://challenges.cloudflare.com/turnstile/v0/siteverify';

    public function __construct(private ?string $remoteIp = null) {}

    /**
     * Run the validation rule.
     *
     * @param  Closure(string, ?string=): PotentiallyTranslatedString  $fail
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value) || $value === '') {
            $fail('Complete the security check to continue.');

            return;
        }

        try {
            $response = Http::asForm()->timeout(5)->post(self::VERIFY_URL, [
                'secret' => config('services.turnstile.secret_key'),
                'response' => $value,
                'remoteip' => $this->remoteIp,
            ]);
        } catch (ConnectionException $exception) {
            // Fail open when Cloudflare is unreachable: blocking every voter on election day is worse
            // than letting a few bots through, and duplicate-VIN checks and rate limits still apply.
            Log::warning('Turnstile verification unreachable', ['error' => $exception->getMessage()]);

            return;
        }

        if ($response->serverError()) {
            Log::warning('Turnstile verification unavailable', ['status' => $response->status()]);

            return;
        }

        if ($response->json('success') !== true) {
            Log::info('Turnstile verification rejected', ['errors' => $response->json('error-codes')]);

            $fail('The security check failed. Please try again.');
        }
    }
}
