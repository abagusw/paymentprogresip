<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\BillingController;
use App\Http\Controllers\WebhookController;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| is assigned the "api" middleware group. Enjoy building your API!
|
*/
Route::get('/', [BillingController::class, 'index']);

Route::post('billing', [BillingController::class, 'ProcessPayment']);

Route::prefix('payment')->group(function () {
    Route::post('/callback', [WebhookController::class, 'CallbackInvoice']);
});