<?php

namespace App\Enums;

enum AppMode: string
{
    case Test = 'test';
    case Live = 'live';

    /**
     * Anything other than an explicit "live" means test mode: a forgotten or mistyped setting then shows
     * a TEST badge to everyone, instead of quietly mixing test reports into the real election data.
     */
    public static function current(): self
    {
        return config('electionwatch.mode') === self::Live->value ? self::Live : self::Test;
    }

    public function isTest(): bool
    {
        return $this === self::Test;
    }
}
