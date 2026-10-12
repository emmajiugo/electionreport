<?php

namespace App\Support;

use RuntimeException;

final readonly class VoterIdentificationNumber
{
    public const int LENGTH = 19;

    private function __construct(public string $value) {}

    /**
     * Normalise user input: VINs are printed in spaced groups and people type them in any case.
     */
    public static function normalize(string $input): string
    {
        return strtoupper((string) preg_replace('/[^A-Za-z0-9]/', '', $input));
    }

    public static function isValid(string $normalized): bool
    {
        return preg_match('/^[A-Z0-9]{'.self::LENGTH.'}$/', $normalized) === 1;
    }

    public static function fromInput(string $input): self
    {
        return new self(self::normalize($input));
    }

    /**
     * Keyed rather than salted so the same VIN always maps to the same hash, which is what
     * lets the unique index enforce one report per VIN per election.
     */
    public function hash(): string
    {
        return self::hmac($this->value);
    }

    public function masked(): string
    {
        return substr($this->value, 0, 4).str_repeat('*', max(strlen($this->value) - 8, 0)).substr($this->value, -4);
    }

    /**
     * Also used for IP addresses so abuse can be clustered without storing raw IPs.
     */
    public static function hmac(string $value): string
    {
        $key = config('electionreport.hash_key');

        if (! is_string($key) || $key === '') {
            throw new RuntimeException('ELECTIONREPORT_HASH_KEY must be set.');
        }

        return hash_hmac('sha256', $value, $key);
    }
}
