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

const ENTERED_VIN = '90F5B1C3E2950123456';

beforeEach(function () {
    $this->election = Election::factory()->create();

    // Http::fake matches the first stub registered for a URL, so tests swap this response instead of re-faking.
    $this->turnstileResponse = Http::response(['success' => true]);
    Http::fake([Turnstile::VERIFY_URL => fn (Request $request) => value($this->turnstileResponse, $request)]);
});

test('the security check token is verified with Cloudflare', function () {
    config(['services.turnstile.secret_key' => 'secret']);

    $this->post(route('vin.store'), ['turnstile_token' => 'token', 'vin' => ENTERED_VIN])->assertRedirect(route('reports.create'));

    Http::assertSent(fn (Request $request) => $request->url() === Turnstile::VERIFY_URL
        && $request['secret'] === 'secret'
        && $request['response'] === 'token'
        && $request['remoteip'] === '127.0.0.1');
});

test('a VIN is refused without a passed security check', function (array $input) {
    $this->turnstileResponse = Http::response(['success' => false, 'error-codes' => ['invalid-input-response']]);

    $this->post(route('vin.store'), ['vin' => ENTERED_VIN, ...$input])
        ->assertSessionHasErrors('turnstile_token')
        ->assertSessionMissing(EnsureVinEntered::SESSION_KEY);
})->with([
    'missing token' => [[]],
    'rejected token' => [['turnstile_token' => 'forged']],
]);

test('a VIN is accepted when Cloudflare cannot be reached', function ($outage) {
    $this->turnstileResponse = $outage;

    $this->post(route('vin.store'), ['turnstile_token' => 'token', 'vin' => ENTERED_VIN])
        ->assertRedirect(route('reports.create'));
})->with([
    'connection failure' => fn () => Http::failedConnection(),
    'server error' => fn () => Http::response(status: 503),
]);

test('the home page asks for a VIN for the open election', function () {
    $this->get(route('home'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Report/Vin')
            ->where('election.id', $this->election->id)
            ->where('turnstileSiteKey', config('services.turnstile.site_key')));
});

test('the home page shows no election when none is accepting reports', function () {
    $this->election->update(['is_accepting_reports' => false]);

    $this->get(route('home'))->assertInertia(fn (Assert $page) => $page->where('election', null));
});

test('a valid VIN is kept in the session only as a hash and mask', function () {
    $this->post(route('vin.store'), ['turnstile_token' => 'token', 'vin' => '90f5 b1c3 e295 0123 456'])
        ->assertRedirect(route('reports.create'))
        ->assertSessionHas(EnsureVinEntered::SESSION_KEY, [
            'hash' => VoterIdentificationNumber::fromInput(ENTERED_VIN)->hash(),
            'masked' => '90F5***********3456',
        ]);

    expect(json_encode(session()->all()))->not->toContain(ENTERED_VIN);
});

test('a malformed VIN is rejected', function (string $vin) {
    $this->post(route('vin.store'), ['turnstile_token' => 'token', 'vin' => $vin])
        ->assertSessionHasErrors('vin')
        ->assertSessionMissing(EnsureVinEntered::SESSION_KEY);
})->with(['too short' => '90F5B1C3', 'too long' => ENTERED_VIN.'X', 'symbols' => '90F5B1C3E29501234$6', 'empty' => '']);

test('a VIN that already reported is turned away before the form', function () {
    Report::factory()->for($this->election)->create([
        'vin_hash' => VoterIdentificationNumber::fromInput(ENTERED_VIN)->hash(),
    ]);

    $this->post(route('vin.store'), ['turnstile_token' => 'token', 'vin' => strtolower(ENTERED_VIN)])
        ->assertSessionHasErrors(['vin' => Election::duplicateVinMessage()])
        ->assertSessionMissing(EnsureVinEntered::SESSION_KEY);
});

test('a VIN that reported in a past election can report in the current one', function () {
    Report::factory()->for(Election::factory()->closed())->create([
        'vin_hash' => VoterIdentificationNumber::fromInput(ENTERED_VIN)->hash(),
    ]);

    $this->post(route('vin.store'), ['turnstile_token' => 'token', 'vin' => ENTERED_VIN])->assertRedirect(route('reports.create'));
});

test('a VIN cannot be entered when no election is accepting reports', function () {
    $this->election->update(['is_accepting_reports' => false]);

    $this->post(route('vin.store'), ['turnstile_token' => 'token', 'vin' => ENTERED_VIN])
        ->assertSessionHasErrors('election')
        ->assertSessionMissing(EnsureVinEntered::SESSION_KEY);
});

test('VIN entry is rate limited per session, not per shared IP', function () {
    $this->withCookie(config('session.cookie'), Str::random(40));

    foreach (range(1, 10) as $attempt) {
        $this->post(route('vin.store'), ['turnstile_token' => 'token', 'vin' => ENTERED_VIN]);
    }

    $this->post(route('vin.store'), ['turnstile_token' => 'token', 'vin' => ENTERED_VIN])->assertTooManyRequests();

    $this->withCookie(config('session.cookie'), Str::random(40))
        ->post(route('vin.store'), ['turnstile_token' => 'token', 'vin' => ENTERED_VIN])
        ->assertRedirect(route('reports.create'));
});
