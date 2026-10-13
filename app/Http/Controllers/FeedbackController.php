<?php

namespace App\Http\Controllers;

use App\Enums\AppMode;
use Inertia\Inertia;
use Inertia\Response;

class FeedbackController extends Controller
{
    public function show(): Response
    {
        abort_unless(AppMode::current()->isTest() && filled(config('electionreport.feedback_url')), 404);

        return Inertia::render('Feedback/Show', [
            'formUrl' => config('electionreport.feedback_url'),
        ]);
    }
}
