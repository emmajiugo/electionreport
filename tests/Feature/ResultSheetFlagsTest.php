<?php

use App\Enums\ResultSheetFlag;
use App\Http\Middleware\EnsureVinEntered;
use App\Models\Election;
use App\Models\PollingUnit;
use App\Models\Report;
use App\Models\ResultSheet;
use App\Models\ResultSheetReview;
use App\Support\VoterIdentificationNumber;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;

const FLAGS_VIN = '90F5B1C3E2950888888';

beforeEach(function () {
    Storage::fake('public');
    $this->travelTo(lagosTime('18:00'));
    $this->election = Election::factory()->create(['held_on' => '2027-02-20']);
    $this->pollingUnit = PollingUnit::factory()->create();

    Report::factory()->for($this->election)->for($this->pollingUnit)->create([
        'vin_hash' => VoterIdentificationNumber::fromInput(FLAGS_VIN)->hash(),
    ]);
    $vin = VoterIdentificationNumber::fromInput(FLAGS_VIN);
    $this->withSession([EnsureVinEntered::SESSION_KEY => ['hash' => $vin->hash(), 'masked' => $vin->masked()]]);
});

/**
 * Election-day time in Lagos, in the app timezone: Eloquent stores a timestamp's wall-clock time without converting it.
 */
function lagosTime(string $time): CarbonImmutable
{
    return CarbonImmutable::parse("2027-02-20 {$time}", 'Africa/Lagos')->setTimezone(config('app.timezone'));
}

/**
 * @param  array<string, mixed>  $attributes
 */
function sheetAtFlaggedUnit(array $attributes = [], ?PollingUnit $pollingUnit = null): ResultSheet
{
    return ResultSheet::factory()
        ->for(Report::factory()->for(test()->election)->for($pollingUnit ?? test()->pollingUnit))
        ->create($attributes);
}

/**
 * @param  list<ResultSheetFlag>  $expected
 */
function assertFlags(int $index, array $expected): void
{
    test()->get(route('result-sheets.create'))->assertInertia(fn (Assert $page) => $page->where(
        "sheets.{$index}.flags",
        array_map(fn (ResultSheetFlag $flag): array => ['value' => $flag->value, 'label' => $flag->label()], $expected),
    ));
}

/**
 * @param  array<string, int>  $overrides
 * @return array<string, mixed>
 */
function recordedFigures(array $overrides = []): array
{
    return [
        'registered_voters' => 750,
        'accredited_voters' => 312,
        'ballots_issued' => 750,
        'unused_ballots' => 436,
        'spoiled_ballots' => 2,
        'rejected_ballots' => 10,
        'total_valid_votes' => 302,
        'total_used_ballots' => 314,
        'party_votes' => [1 => 190, 2 => 112],
        'figures_recorded_at' => now(),
        ...$overrides,
    ];
}

test('a sheet with nothing unusual has no flags', function () {
    sheetAtFlaggedUnit(recordedFigures());

    assertFlags(0, []);
});

test('a photo also uploaded for another polling unit is flagged', function () {
    sheetAtFlaggedUnit(['image_hash' => 'aaaaaaaaaaaaaaaa']);
    sheetAtFlaggedUnit(['image_hash' => 'aaaaaaaaaaaaaaaa'], PollingUnit::factory()->create());

    assertFlags(0, [ResultSheetFlag::ReusedImage]);
});

test('the same photo uploaded twice at one polling unit is not flagged as reused', function () {
    sheetAtFlaggedUnit(['image_hash' => 'bbbbbbbbbbbbbbbb']);
    sheetAtFlaggedUnit(['image_hash' => 'bbbbbbbbbbbbbbbb']);

    assertFlags(0, []);
    assertFlags(1, []);
});

test('a sheet uploaded before polls closed is flagged', function (string $uploadedAt, array $expected) {
    sheetAtFlaggedUnit(['created_at' => lagosTime($uploadedAt)]);

    assertFlags(0, $expected);
})->with([
    'a minute before polls closed' => ['14:29', [ResultSheetFlag::EarlyUpload]],
    'a minute after polls closed' => ['14:31', []],
]);

test('confirmations are flagged when they arrive together or from one address', function (Closure $confirmations, array $expected) {
    $sheet = sheetAtFlaggedUnit();

    foreach ($confirmations() as [$time, $ipHash]) {
        ResultSheetReview::factory()->for($sheet)->create(['created_at' => lagosTime($time), 'ip_hash' => $ipHash]);
    }

    assertFlags(0, $expected);
})->with([
    'three within ten minutes' => [fn () => [['16:00', 'ip-1'], ['16:04', 'ip-2'], ['16:10', 'ip-3']], [ResultSheetFlag::ConfirmationBurst]],
    'three spread over hours' => [fn () => [['15:00', 'ip-1'], ['16:00', 'ip-2'], ['17:00', 'ip-3']], []],
    'three from one address, spread out' => [fn () => [['15:00', 'ip-1'], ['16:00', 'ip-1'], ['17:00', 'ip-1']], [ResultSheetFlag::ConfirmationBurst]],
]);

test('figures that do not add up are flagged', function (array $overrides, array $expected) {
    sheetAtFlaggedUnit(recordedFigures($overrides));

    assertFlags(0, $expected);
})->with([
    'party votes short of the total valid votes' => [['total_valid_votes' => 310, 'accredited_voters' => 400], [ResultSheetFlag::PartyVotesDontMatchTotal]],
    'more votes cast than accredited voters' => [['accredited_voters' => 300], [ResultSheetFlag::MoreVotesThanAccredited]],
]);
