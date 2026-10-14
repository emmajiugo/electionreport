<?php

namespace App\Enums;

/**
 * How far other voters at the polling unit back a result sheet. Worked out from review counts on
 * every read rather than stored, so changing the threshold needs no data migration. Labels state
 * what voters said; none of them claims the sheet is verified.
 */
enum ResultSheetStatus: string
{
    case SingleSource = 'single_source';
    case PartlyConfirmed = 'partly_confirmed';
    case Corroborated = 'corroborated';
    case Contested = 'contested';

    public const int CORROBORATION_THRESHOLD = 3;

    /**
     * @param  bool  $rivalReachedThreshold  whether another sheet at the same polling unit also has enough confirmations
     */
    public static function fromReviews(int $confirmations, int $disputes, bool $rivalReachedThreshold): self
    {
        $reachedThreshold = $confirmations >= self::CORROBORATION_THRESHOLD;

        return match (true) {
            $disputes > 0 && $disputes >= $confirmations,
            $reachedThreshold && $rivalReachedThreshold => self::Contested,
            $reachedThreshold => self::Corroborated,
            $confirmations > 0 => self::PartlyConfirmed,
            default => self::SingleSource,
        };
    }

    public function label(): string
    {
        return match ($this) {
            self::SingleSource => 'Not yet confirmed by other voters',
            self::PartlyConfirmed => 'Confirmed by some voters',
            self::Corroborated => 'Confirmed by several voters',
            self::Contested => 'Disputed by voters',
        };
    }
}
