<?php

use App\Http\Middleware\EnsureVinEntered;
use App\Models\Election;
use App\Models\Report;
use App\Rules\Turnstile;
use App\Support\VoterIdentificationNumber;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;

const ENTERED_VIN = ['vin_first' => '90F5', 'vin_last' => '3456'];

beforeEach(function () {
    $this->election = Election::factory()->create();

    // Http::fake matches the first stub registered for a URL, so tests swap this response instead of re-faking.
    $this->turnstileResponse = Http::response(['success' => true]);
    Http::fake([Turnstile::VERIFY_URL => fn (Request $request) => value($this->turnstileResponse, $request)]);
});

test('the security check token is verified with Cloudflare', function () {
    config(['services.turnstile.secret_key' => 'secret']);

    $this->post(route('vin.store'), ['turnstile_token' => 'token', ...ENTERED_VIN])->assertRedirect(route('reports.create'));

    Http::assertSent(fn (Request $request) => $request->url() === Turnstile::VERIFY_URL
        && $request['secret'] === 'secret'
        && $request['response'] === 'token'
        && $request['remoteip'] === '127.0.0.1');
});

test('a VIN is refused without a passed security check', function (array $input) {
    $this->turnstileResponse = Http::response(['success' => false, 'error-codes' => ['invalid-input-response']]);

    $this->post(route('vin.store'), [...ENTERED_VIN, ...$input])
        ->assertSessionHasErrors('turnstile_token')
        ->assertSessionMissing(EnsureVinEntered::SESSION_KEY);
})->with([
    'missing token' => [[]],
    'rejected token' => [['turnstile_token' => 'forged']],
]);

test('a VIN is accepted when Cloudflare cannot be reached', function ($outage) {
    $this->turnstileResponse = $outage;

    $this->post(route('vin.store'), ['turnstile_token' => 'token', ...ENTERED_VIN])
        ->assertRedirect(route('reports.create'));
})->with([
    'connection failure' => fn () => Http::failedConnection(),
    'server error' => fn () => Http::response(status: 503),
]);
test('the home page introduces the open election', function () {
    $this->get(route('home'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Home')
            ->where('election.id', $this->election->id)
            ->where('election.name', $this->election->name));
});

test('the home page shows no election when none is accepting reports', function () {
    $this->election->update(['is_accepting_reports' => false]);

    $this->get(route('home'))->assertInertia(fn (Assert $page) => $page->component('Home')->where('election', null));
});

test('the VIN page asks for a VIN for the open election', function () {
    $this->get(route('vin.create'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Report/Vin')
            ->where('election.id', $this->election->id)
            ->where('turnstileSiteKey', config('services.turnstile.site_key')));
});

test('a valid VIN is kept in the session only as a hash and mask', function () {
    $this->post(route('vin.store'), ['turnstile_token' => 'token', 'vin_first' => '90f-5', 'vin_last' => ' 34 56'])
        ->assertRedirect(route('reports.create'))
        ->assertSessionHas(EnsureVinEntered::SESSION_KEY, [
            'hash' => VoterIdentificationNumber::fromParts('90F5', '3456')->hash(),
            'masked' => '90F5***********3456',
            'report_id' => null,
        ]);

    expect(json_encode(session()->all()))->not->toContain('90F53456');
});

test('a malformed VIN part is rejected', function (array $input, string $field) {
    $this->post(route('vin.store'), ['turnstile_token' => 'token', ...ENTERED_VIN, ...$input])
        ->assertSessionHasErrors($field)
        ->assertSessionMissing(EnsureVinEntered::SESSION_KEY);
})->with([
    'first too short' => [['vin_first' => '90F'], 'vin_first'],
    'first too long' => [['vin_first' => '90F5B'], 'vin_first'],
    'last too long' => [['vin_last' => '123456'], 'vin_last'],
    'last with a symbol in place of a character' => [['vin_last' => '34$6'], 'vin_last'],
    'first empty' => [['vin_first' => ''], 'vin_first'],
    'last missing' => [['vin_last' => null], 'vin_last'],
]);

test('a VIN that reported the election held comes back to add the result sheet', function () {
    $report = Report::factory()->for($this->election)->create([
        'vin_hash' => VoterIdentificationNumber::fromParts('90F5', '3456')->hash(),
    ]);

    $this->post(route('vin.store'), ['turnstile_token' => 'token', 'vin_first' => '90f5', 'vin_last' => '3456'])
        ->assertRedirect(route('result-sheets.create'))
        ->assertSessionHas(EnsureVinEntered::REPORT_KEY, $report->id);
});

test('a partial VIN matching held reports at two polling units goes to the report form', function () {
    Report::factory()->for($this->election)->count(2)->create([
        'vin_hash' => VoterIdentificationNumber::fromParts('90F5', '3456')->hash(),
    ]);

    $this->post(route('vin.store'), ['turnstile_token' => 'token', ...ENTERED_VIN])
        ->assertRedirect(route('reports.create'))
        ->assertSessionHas(EnsureVinEntered::SESSION_KEY, fn (array $vin) => $vin['report_id'] === null);
});

test('a VIN that reported the election did not hold can go on to report another polling unit', function () {
    Report::factory()->electionNotHeld()->for($this->election)->create([
        'vin_hash' => VoterIdentificationNumber::fromParts('90F5', '3456')->hash(),
    ]);

    $this->post(route('vin.store'), ['turnstile_token' => 'token', ...ENTERED_VIN])
        ->assertRedirect(route('reports.create'))
        ->assertSessionHas(EnsureVinEntered::SESSION_KEY, fn (array $vin) => $vin['report_id'] === null);
});

test('a VIN that reported in a past election can report in the current one', function () {
    Report::factory()->for(Election::factory()->closed())->create([
        'vin_hash' => VoterIdentificationNumber::fromParts('90F5', '3456')->hash(),
    ]);

    $this->post(route('vin.store'), ['turnstile_token' => 'token', ...ENTERED_VIN])->assertRedirect(route('reports.create'));
});

test('a VIN cannot be entered when no election is accepting reports', function () {
    $this->election->update(['is_accepting_reports' => false]);

    $this->post(route('vin.store'), ['turnstile_token' => 'token', ...ENTERED_VIN])
        ->assertSessionHasErrors('election')
        ->assertSessionMissing(EnsureVinEntered::SESSION_KEY);
});

test('VIN entry is rate limited per session, not per shared IP', function () {
    $this->withCookie(config('session.cookie'), Str::random(40));

    foreach (range(1, 10) as $attempt) {
        $this->post(route('vin.store'), ['turnstile_token' => 'token', ...ENTERED_VIN]);
    }

    $this->post(route('vin.store'), ['turnstile_token' => 'token', ...ENTERED_VIN])->assertTooManyRequests();

    $this->withCookie(config('session.cookie'), Str::random(40))
        ->post(route('vin.store'), ['turnstile_token' => 'token', ...ENTERED_VIN])
        ->assertRedirect(route('reports.create'));
});
