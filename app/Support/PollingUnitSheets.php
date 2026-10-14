<?php

namespace App\Support;

use App\Enums\ResultSheetFlag;
use App\Enums\ResultSheetStatus;
use App\Enums\ReviewVerdict;
use App\Models\Election;
use App\Models\PollingUnit;
use App\Models\Report;
use App\Models\ResultSheet;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * A polling unit's result sheets as pages show them, for the voter's own page and the public one alike.
 */
final class PollingUnitSheets
{
    /**
     * Best supported first, so a reader sees the leading sheet before any rival.
     *
     * @param  Report|null  $viewer  the signed-in voter's report, which marks their own upload
     * @return array<int, array{id: int, url: string, uploaded_at: ?string, is_yours: bool, confirmations: int, disputes: int, status: string, status_label: string, flags: array<int, array{value: string, label: string}>, figures: array<string, mixed>|null}>
     */
    public static function for(Election $election, int $pollingUnitId, ?Report $viewer = null): array
    {
        $sheets = ResultSheet::query()
            ->where('election_id', $election->id)
            ->where('polling_unit_id', $pollingUnitId)
            ->withCount([
                'reviews as confirmations_count' => fn (Builder $query) => $query->where('verdict', ReviewVerdict::Confirm),
                'reviews as disputes_count' => fn (Builder $query) => $query->where('verdict', ReviewVerdict::Dispute),
            ])
            ->get()
            ->sortBy([['confirmations_count', 'desc'], ['id', 'asc']]);

        $reachedThreshold = $sheets->filter(
            fn (ResultSheet $sheet): bool => $sheet->confirmations_count >= ResultSheetStatus::CORROBORATION_THRESHOLD,
        );

        $flags = ResultSheetFlags::for($election, $sheets);

        return $sheets->map(function (ResultSheet $sheet) use ($viewer, $reachedThreshold, $flags): array {
            $status = ResultSheetStatus::fromReviews(
                $sheet->confirmations_count,
                $sheet->disputes_count,
                $reachedThreshold->contains(fn (ResultSheet $rival): bool => $rival->id !== $sheet->id),
            );

            return [
                'id' => $sheet->id,
                'url' => $sheet->url(),
                'uploaded_at' => $sheet->created_at?->toIso8601String(),
                'is_yours' => $sheet->report_id === $viewer?->id,
                'confirmations' => $sheet->confirmations_count,
                'disputes' => $sheet->disputes_count,
                'status' => $status->value,
                'status_label' => $status->label(),
                'flags' => array_map(
                    fn (ResultSheetFlag $flag): array => ['value' => $flag->value, 'label' => $flag->label()],
                    $flags[$sheet->id] ?? [],
                ),
                'figures' => $sheet->hasFigures()
                    ? [...$sheet->only(array_keys(ResultSheet::FIGURES)), 'party_votes' => $sheet->party_votes]
                    : null,
            ];
        })->values()->all();
    }

    /**
     * @return array{id: int, name: string, code: string, ward: string, lga: string, state: string}
     */
    public static function pollingUnit(PollingUnit $pollingUnit): array
    {
        $pollingUnit->loadMissing('ward.lga.state');

        return [
            'id' => $pollingUnit->id,
            'name' => $pollingUnit->name,
            'code' => $pollingUnit->code,
            'ward' => $pollingUnit->ward->name,
            'lga' => $pollingUnit->ward->lga->name,
            'state' => $pollingUnit->ward->lga->state->name,
        ];
    }

    /**
     * @return list<array{key: string, label: string}>
     */
    public static function figureFields(): array
    {
        return collect(ResultSheet::FIGURES)
            ->map(fn (string $label, string $key): array => ['key' => $key, 'label' => $label])
            ->pipe(fn (Collection $fields): array => array_values($fields->all()));
    }
}
