<?php

use App\Http\Middleware\EnsureVinEntered;
use App\Http\Requests\StoreResultSheetRequest;
use App\Models\Election;
use App\Models\PollingUnit;
use App\Models\Report;
use App\Models\ResultSheet;
use App\Support\VoterIdentificationNumber;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;

const SHEET_VIN = '90F5B1C3E2950123456';

beforeEach(function () {
    $this->election = Election::factory()->create();
    $this->pollingUnit = PollingUnit::factory()->create();
});

function enterVinForSheet(string $vin = SHEET_VIN): void
{
    $vin = VoterIdentificationNumber::fromInput($vin);

    test()->withSession([EnsureVinEntered::SESSION_KEY => ['hash' => $vin->hash(), 'masked' => $vin->masked()]]);
}

function reportFromSheetVin(): Report
{
    return Report::factory()->for(test()->election)->for(test()->pollingUnit)->create([
        'vin_hash' => VoterIdentificationNumber::fromInput(SHEET_VIN)->hash(),
    ]);
}

/**
 * A JPEG with a horizontal gradient, so its perceptual fingerprint has real content. Flipping it gives a different photo.
 */
function sheetPhoto(int $width = 800, int $height = 600, bool $flipped = false): string
{
    $image = imagecreatetruecolor($width, $height);

    for ($x = 0; $x < $width; $x++) {
        $shade = (int) (255 * $x / ($width - 1));
        $shade = $flipped ? 255 - $shade : $shade;
        imagefilledrectangle($image, $x, 0, $x, $height - 1, (int) imagecolorallocate($image, $shade, $shade, $shade));
    }

    ob_start();
    imagejpeg($image);

    return (string) ob_get_clean();
}

function sheetUpload(string $jpeg): UploadedFile
{
    return UploadedFile::fake()->createWithContent('sheet.jpg', $jpeg);
}

test('result sheet routes cannot be reached without entering a VIN', function () {
    $this->get(route('result-sheets.create'))->assertRedirect(route('home'));
    $this->post(route('result-sheets.store'), ['result_sheet' => sheetUpload(sheetPhoto())])->assertRedirect(route('home'));
});

test('a voter who has not reported yet is sent to the report form', function () {
    enterVinForSheet();

    $this->get(route('result-sheets.create'))->assertRedirect(route('reports.create'));
});

test('the page shows the voter\'s polling unit and the sheets already uploaded there', function () {
    Storage::fake('public');
    enterVinForSheet();
    reportFromSheetVin();
    $neighbour = ResultSheet::factory()->for(Report::factory()->for($this->election)->for($this->pollingUnit))->create();
    ResultSheet::factory()->for(Report::factory()->for($this->election))->create();

    $this->get(route('result-sheets.create'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('ResultSheet/Create')
            ->where('pollingUnit.name', $this->pollingUnit->name)
            ->where('pollingUnit.code', $this->pollingUnit->code)
            ->has('sheets', 1)
            ->where('sheets.0.id', $neighbour->id)
            ->where('sheets.0.url', Storage::disk('public')->url($neighbour->path))
            ->where('sheets.0.is_yours', false)
            ->where('hasUploaded', false));
});

test('a voter can upload a photo of their polling unit\'s result sheet', function () {
    Storage::fake('public');
    enterVinForSheet();
    $report = reportFromSheetVin();

    $this->post(route('result-sheets.store'), ['result_sheet' => sheetUpload(sheetPhoto())])
        ->assertRedirect(route('result-sheets.create'));

    $sheet = ResultSheet::sole();

    expect($sheet->report_id)->toBe($report->id)
        ->and($sheet->polling_unit_id)->toBe($this->pollingUnit->id)
        ->and($sheet->election_id)->toBe($this->election->id)
        ->and($sheet->path)->toStartWith("live/result-sheets/{$this->election->id}/")
        ->and(getimagesizefromstring(Storage::disk('public')->get($sheet->path))['mime'])->toBe('image/jpeg');

    $this->get(route('result-sheets.create'))
        ->assertInertia(fn (Assert $page) => $page->where('sheets.0.is_yours', true)->where('hasUploaded', true));
});

test('photo metadata such as the GPS position is not kept', function () {
    Storage::fake('public');
    enterVinForSheet();
    reportFromSheetVin();

    $marker = 'GPS-POSITION-OF-THE-VOTER';
    $exif = "Exif\0\0".$marker;
    $original = sheetPhoto();
    $withExif = substr($original, 0, 2)."\xFF\xE1".pack('n', strlen($exif) + 2).$exif.substr($original, 2);

    $this->post(route('result-sheets.store'), ['result_sheet' => sheetUpload($withExif)]);

    expect(Storage::disk('public')->get(ResultSheet::sole()->path))->not->toContain($marker);
});

test('the same photo gets the same fingerprint even after resizing, and a different photo does not', function () {
    Storage::fake('public');
    $upload = function (string $vin, PollingUnit $pollingUnit, string $jpeg): ResultSheet {
        Report::factory()->for($this->election)->for($pollingUnit)->create([
            'vin_hash' => VoterIdentificationNumber::fromInput($vin)->hash(),
        ]);
        enterVinForSheet($vin);
        $this->post(route('result-sheets.store'), ['result_sheet' => sheetUpload($jpeg)]);

        return ResultSheet::query()->latest('id')->firstOrFail();
    };

    $original = $upload('AAAAAAAAAAAAAAAAAA1', $this->pollingUnit, sheetPhoto());
    $resized = $upload('AAAAAAAAAAAAAAAAAA2', PollingUnit::factory()->create(), sheetPhoto(400, 300));
    $different = $upload('AAAAAAAAAAAAAAAAAA3', PollingUnit::factory()->create(), sheetPhoto(flipped: true));

    expect($resized->image_hash)->toBe($original->image_hash)
        ->and($different->image_hash)->not->toBe($original->image_hash);
});

test('a voter can upload only one result sheet', function () {
    Storage::fake('public');
    enterVinForSheet();
    ResultSheet::factory()->for(reportFromSheetVin())->create();

    $this->post(route('result-sheets.store'), ['result_sheet' => sheetUpload(sheetPhoto())])
        ->assertSessionHasErrors(['result_sheet' => StoreResultSheetRequest::alreadyUploadedMessage()]);

    expect(ResultSheet::count())->toBe(1);
});

test('a voter who reported that the election did not hold has no sheet to upload', function () {
    Storage::fake('public');
    enterVinForSheet();
    Report::factory()->electionNotHeld()->for($this->election)->for($this->pollingUnit)->create([
        'vin_hash' => VoterIdentificationNumber::fromInput(SHEET_VIN)->hash(),
    ]);

    $this->get(route('result-sheets.create'))->assertRedirect(route('home'));
    $this->post(route('result-sheets.store'), ['result_sheet' => sheetUpload(sheetPhoto())])->assertForbidden();

    expect(ResultSheet::count())->toBe(0);
});

test('a voter cannot upload once the election stops accepting reports', function () {
    Storage::fake('public');
    enterVinForSheet();
    reportFromSheetVin();
    $this->election->update(['is_accepting_reports' => false]);

    $this->post(route('result-sheets.store'), ['result_sheet' => sheetUpload(sheetPhoto())])->assertForbidden();
});

test('an unusable photo is rejected', function (Closure $file, string $message) {
    Storage::fake('public');
    enterVinForSheet();
    reportFromSheetVin();

    $this->post(route('result-sheets.store'), ['result_sheet' => $file()])
        ->assertSessionHasErrors(['result_sheet' => $message]);

    expect(ResultSheet::count())->toBe(0);
})->with([
    'missing' => [fn () => null, 'Take or choose a photo of the result sheet.'],
    'not an image' => [fn () => UploadedFile::fake()->create('sheet.pdf', 100, 'application/pdf'), 'Upload a photo of the result sheet.'],
    'too many pixels' => [fn () => sheetUpload(sheetPhoto(2001, 100)), 'This photo is too large. Please try again.'],
    'file too big' => [fn () => UploadedFile::fake()->image('sheet.jpg', 800, 600)->size(2049), 'This photo is too large. Please try again.'],
]);

test('result sheet uploads are rate limited per session, not per shared IP', function () {
    Storage::fake('public');
    enterVinForSheet();
    reportFromSheetVin();
    $this->withCookie(config('session.cookie'), Str::random(40));

    foreach (range(1, 5) as $attempt) {
        $this->post(route('result-sheets.store'));
    }

    $this->post(route('result-sheets.store'))->assertTooManyRequests();

    $this->withCookie(config('session.cookie'), Str::random(40))
        ->post(route('result-sheets.store'))
        ->assertSessionHasErrors('result_sheet');
});
