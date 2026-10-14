<?php

use App\Http\Controllers\Api\DocumentController;
use Illuminate\Support\Facades\Route;

Route::post('/documents', [DocumentController::class, 'store'])
    ->middleware('throttle:30,1');

Route::post('/documents/{document}/verify', [DocumentController::class, 'verify'])
    ->middleware('throttle:30,1');
