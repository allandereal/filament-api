<?php

use Allandereal\FilamentApi\Http\Controllers\ApiController;
use Illuminate\Support\Facades\Route;

Route::prefix('api')
    ->any('/{resource}/{id?}', ApiController::class);
