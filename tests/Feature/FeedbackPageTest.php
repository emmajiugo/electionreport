<?php

use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    config(['electionreport.feedback_url' => 'https://forms.example/feedback']);
});

test('testers can open the feedback form', function () {
    config(['electionreport.mode' => 'test']);

    $this->get(route('feedback'))->assertInertia(fn (Assert $page) => $page
        ->component('Feedback/Show')
        ->where('formUrl', 'https://forms.example/feedback'));
});

test('the feedback page is hidden once the app is live', function () {
    config(['electionreport.mode' => 'live']);

    $this->get(route('feedback'))->assertNotFound();
});

test('the feedback page is hidden when no form is configured', function () {
    config(['electionreport.mode' => 'test', 'electionreport.feedback_url' => null]);

    $this->get(route('feedback'))->assertNotFound();
});
