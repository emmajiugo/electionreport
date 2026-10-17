<?php

use App\Http\Controllers\FeedbackController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\LocationController;
use App\Http\Controllers\PollingUnitResultSheetController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\ResultsController;
use App\Http\Controllers\ResultSheetController;
use App\Http\Controllers\ResultSheetFiguresController;
use App\Http\Controllers\ResultSheetReviewController;
use App\Http\Controllers\VinController;
use App\Http\Middleware\EnsureVinEntered;
use Illuminate\Support\Facades\Route;

Route::get('/', [HomeController::class, 'show'])->name('home');
Route::get('/vin', [VinController::class, 'create'])->name('vin.create');
Route::post('/vin', [VinController::class, 'store'])->middleware('throttle:vin')->name('vin.store');
Route::get('/results', [ResultsController::class, 'index'])->name('results');
Route::get('/result-sheets', [PollingUnitResultSheetController::class, 'index'])->name('result-sheets.index');
Route::get('/polling-units/{pollingUnit}/result-sheets', [PollingUnitResultSheetController::class, 'show'])->name('polling-units.result-sheets');
Route::get('/reports/submitted', [ReportController::class, 'submitted'])->name('reports.submitted');
Route::get('/feedback', [FeedbackController::class, 'show'])->name('feedback');

Route::middleware(EnsureVinEntered::class)->group(function () {
    Route::get('/report', [ReportController::class, 'create'])->name('reports.create');
    Route::get('/play', [ReportController::class, 'play'])->name('reports.play');
    Route::post('/reports', [ReportController::class, 'store'])->middleware('throttle:reports')->name('reports.store');
    Route::post('/report/another', [ReportController::class, 'another'])->name('reports.another');
    Route::get('/reports/check/{pollingUnit}', [ReportController::class, 'check'])->middleware('throttle:locations')->name('reports.check');

    Route::get('/result-sheet', [ResultSheetController::class, 'create'])->name('result-sheets.create');
    Route::post('/result-sheets', [ResultSheetController::class, 'store'])->middleware('throttle:result-sheets')->name('result-sheets.store');
    Route::post('/result-sheet/figures', [ResultSheetFiguresController::class, 'store'])->middleware('throttle:result-sheets')->name('result-sheets.figures.store');
    Route::post('/result-sheets/{sheet}/reviews', [ResultSheetReviewController::class, 'store'])->middleware('throttle:result-sheets')->name('result-sheets.reviews.store');

    Route::middleware('throttle:locations')->prefix('locations')->name('locations.')->group(function () {
        Route::get('/states/{state}/lgas', [LocationController::class, 'lgas'])->name('lgas');
        Route::get('/lgas/{lga}/wards', [LocationController::class, 'wards'])->name('wards');
        Route::get('/wards/{ward}/polling-units', [LocationController::class, 'pollingUnits'])->name('polling-units');
    });
});
