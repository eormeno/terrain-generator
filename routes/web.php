<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\ProceduralTerrainController;

Route::get('/', function () {
    return response()->file(public_path('index.html'));
});
