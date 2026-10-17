<?php

namespace App\Support;

use RuntimeException;

/**
 * Only the first and last few characters of a VIN: voters never give us the full number.
 */
final readonly class VoterIdentificationNumber
{
    /**
     * Length of the full VIN printed on a PVC, which the masked form keeps.
     */
    public const int LENGTH = 19;

    /**
     * How many characters voters give from each end of their VIN.
     */
    public const int PART_LENGTH = 4;

    private function __construct(public string $first, public string $last) {}

    /**
     * Normalise user input: VINs are printed in spaced groups and people type them in any case.
     */
    public static function normalize(string $input): string
    {
        return strtoupper((string) preg_replace('/[^A-Za-z0-9]/', '', $input));
    }

    public static function fromParts(string $first, string $last): self
    {
        return new self(self::normalize($first), self::normalize($last));
    }

    /**
     * Keyed rather than salted so the same characters always map to the same hash, which is what
     * lets the unique index enforce one report per partial VIN per polling unit.
     */
    public function hash(): string
    {
        return self::hmac($this->first.$this->last);
    }

    public function masked(): string
    {
        return $this->first.str_repeat('*', self::LENGTH - strlen($this->first) - strlen($this->last)).$this->last;
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
