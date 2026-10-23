<?php

use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return response()->json(['service' => 'Onfly Travel API', 'health' => '/up']);
});
