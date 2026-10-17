<?php

namespace App\Http\Controllers;

use App\Models\Election;
use Inertia\Inertia;
use Inertia\Response;

class HomeController extends Controller
{
    public function show(): Response
    {
        return Inertia::render('Home', [
            'election' => Election::current()?->only('id', 'name'),
        ]);
    }
}
