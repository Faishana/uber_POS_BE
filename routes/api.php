<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\OrderController;
use App\Http\Controllers\UberWebhookController;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "api" middleware group. Make something great!
|
*/

Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
    return $request->user();
});

Route::post('/orders', [OrderController::class, 'store']);        // create
Route::get('/orders', [OrderController::class, 'index']);         // list
Route::patch('/orders/{id}', [OrderController::class, 'updateStatus']); // update
Route::get('/uber-orders', [OrderController::class, 'fetchUberOrders']); // optional


Route::post('/uber/webhook', [UberWebhookController::class, 'handle']);
