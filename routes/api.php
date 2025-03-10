<?php

use App\Http\Controllers\ProceduralTerrainController;

Route::get('/terrain/info', [ProceduralTerrainController::class, 'info']);

Route::post('/terrain/generate', [ProceduralTerrainController::class, 'generate']);
