<?php

namespace App\Support;

use App\Enums\ResultSheetFlag;
use App\Enums\ReviewVerdict;
use App\Models\Election;
use App\Models\ResultSheet;
use App\Models\ResultSheetReview;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Collection;

/**
 * Works out the flags for a polling unit's sheets on every read, like their status, so thresholds
 * can change without touching stored data.
 */
final class ResultSheetFlags
{
    /**
     * Shared IP hashes are expected (carrier-grade NAT puts many voters on one address), so this
     * only flags clusters, never blocks them.
     */
    private const int BURST_CONFIRMATIONS = 3;

    private const int BURST_WINDOW_SECONDS = 10 * 60;

    private const string NIGERIA_TIMEZONE = 'Africa/Lagos';

    /**
     * @param  EloquentCollection<int, ResultSheet>  $sheets  sheets from one polling unit in the election
     * @return array<int, list<ResultSheetFlag>> flags keyed by sheet id
     */
    public static function for(Election $election, EloquentCollection $sheets): array
    {
        if ($sheets->isEmpty()) {
            return [];
        }

        $reusedHashes = self::hashesUsedAtOtherPollingUnits($election, $sheets);
        $pollsClose = CarbonImmutable::parse(
            $election->held_on->toDateString().' '.config('electionreport.results_not_before'),
            self::NIGERIA_TIMEZONE,
        );
        $confirmations = ResultSheetReview::query()
            ->whereIn('result_sheet_id', $sheets->modelKeys())
            ->where('verdict', ReviewVerdict::Confirm)
            ->get(['result_sheet_id', 'ip_hash', 'created_at'])
            ->groupBy('result_sheet_id');

        return $sheets->mapWithKeys(fn (ResultSheet $sheet): array => [
            $sheet->id => array_values(array_filter([
                in_array($sheet->image_hash, $reusedHashes, true) ? ResultSheetFlag::ReusedImage : null,
                $sheet->created_at?->lt($pollsClose) ? ResultSheetFlag::EarlyUpload : null,
                self::isBurst($confirmations->get($sheet->id, collect())) ? ResultSheetFlag::ConfirmationBurst : null,
                ...self::figureFlags($sheet),
            ])),
        ])->all();
    }

    /**
     * Exact matches only: an indexed lookup that scales to election day. Near-identical copies need
     * a Hamming-distance search, which can come later.
     *
     * @param  EloquentCollection<int, ResultSheet>  $sheets
     * @return array<int, string>
     */
    private static function hashesUsedAtOtherPollingUnits(Election $election, EloquentCollection $sheets): array
    {
        return ResultSheet::query()
            ->where('election_id', $election->id)
            ->whereIn('image_hash', $sheets->map(fn (ResultSheet $sheet): string => $sheet->image_hash)->all())
            ->whereNotIn('polling_unit_id', $sheets->map(fn (ResultSheet $sheet): int => $sheet->polling_unit_id)->unique()->all())
            ->get(['image_hash'])
            ->map(fn (ResultSheet $sheet): string => $sheet->image_hash)
            ->unique()
            ->values()
            ->all();
    }

    /**
     * @param  Collection<int, ResultSheetReview>  $confirmations
     */
    private static function isBurst(Collection $confirmations): bool
    {
        if ($confirmations->countBy('ip_hash')->contains(fn (int $count): bool => $count >= self::BURST_CONFIRMATIONS)) {
            return true;
        }

        $times = $confirmations
            ->map(fn (ResultSheetReview $review): int => (int) $review->created_at?->getTimestamp())
            ->sort()
            ->values();

        for ($i = 0; $i + self::BURST_CONFIRMATIONS - 1 < $times->count(); $i++) {
            if ($times[$i + self::BURST_CONFIRMATIONS - 1] - $times[$i] <= self::BURST_WINDOW_SECONDS) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return list<ResultSheetFlag>
     */
    private static function figureFlags(ResultSheet $sheet): array
    {
        if (! $sheet->hasFigures()) {
            return [];
        }

        $flags = [];

        if (array_sum($sheet->party_votes ?? []) !== $sheet->total_valid_votes) {
            $flags[] = ResultSheetFlag::PartyVotesDontMatchTotal;
        }

        if ($sheet->total_valid_votes + $sheet->rejected_ballots > $sheet->accredited_voters) {
            $flags[] = ResultSheetFlag::MoreVotesThanAccredited;
        }

        return $flags;
    }
}
