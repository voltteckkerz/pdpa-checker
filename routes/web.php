<?php

use App\Http\Controllers\AnalyseController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return response()->file(public_path('index.html'));
});

Route::post('/analyse', [AnalyseController::class, 'analyse']);
Route::post('/extract-pdf', [AnalyseController::class, 'extractPdf']);
