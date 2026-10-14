<?php

use App\Enums\ResultSheetStatus;

test('a sheet\'s status follows its confirmations, disputes and any rival sheet', function (int $confirmations, int $disputes, bool $rival, ResultSheetStatus $status) {
    expect(ResultSheetStatus::fromReviews($confirmations, $disputes, $rival))->toBe($status);
})->with([
    'no reviews' => [0, 0, false, ResultSheetStatus::SingleSource],
    'one confirmation' => [1, 0, false, ResultSheetStatus::PartlyConfirmed],
    'just under the threshold' => [2, 0, false, ResultSheetStatus::PartlyConfirmed],
    'confirmations outnumber a dispute' => [2, 1, false, ResultSheetStatus::PartlyConfirmed],
    'at the threshold' => [3, 0, false, ResultSheetStatus::Corroborated],
    'past the threshold despite disputes' => [4, 3, false, ResultSheetStatus::Corroborated],
    'a dispute with no confirmations' => [0, 1, false, ResultSheetStatus::Contested],
    'disputes match confirmations' => [3, 3, false, ResultSheetStatus::Contested],
    'disputes outnumber confirmations' => [1, 2, false, ResultSheetStatus::Contested],
    'a rival sheet also reached the threshold' => [3, 0, true, ResultSheetStatus::Contested],
    'a rival sheet does not matter below the threshold' => [2, 0, true, ResultSheetStatus::PartlyConfirmed],
]);
