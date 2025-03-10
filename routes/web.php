<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\ProceduralTerrainController;

Route::get('/', function () {
    return response()->file(public_path('index.html'));
});

Route::get('/terrain/info', [ProceduralTerrainController::class, 'info']);

Route::post('api/terrain/generate', [ProceduralTerrainController::class, 'generate']);
