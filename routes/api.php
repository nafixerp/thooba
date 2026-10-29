<?php

use App\Http\Controllers\Api\AgentApiController;
use App\Http\Controllers\Api\AgentAuthController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\OperationalApiController;
use App\Http\Controllers\Api\PaymentGatewayController;
use App\Http\Controllers\Api\SchemeApiController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Mobile App API  (Laravel Sanctum token auth)
|--------------------------------------------------------------------------
|
|  Base URL : {APP_URL}/api/v1
|  Header   : Authorization: Bearer {token}
|             Accept: application/json
|
|  Two token audiences share the same Sanctum table:
|    auth:sanctum        -> customer tokens (customers table)
|    auth:agent          -> agent tokens    (agents table)
*/

Route::prefix('v1')->group(function () {

    /* ================= PUBLIC ================= */

    // Shown on the pre-login landing screen: banners, offers, rates, schemes.
    Route::get('/app-config', [SchemeApiController::class, 'appConfig']);
    Route::get('/home', [SchemeApiController::class, 'home']);
    Route::get('/rates', [SchemeApiController::class, 'rates']);
    Route::get('/schemes', [SchemeApiController::class, 'schemes']);
    Route::get('/products', [SchemeApiController::class, 'products']);
    Route::get('/categories', [SchemeApiController::class, 'categories']);
    Route::get('/banks', [SchemeApiController::class, 'banks']);
    Route::get('/branches', [OperationalApiController::class, 'branches']);
    Route::get('/app-version', [OperationalApiController::class, 'appVersion']);
    Route::get('/content/{type?}', [SchemeApiController::class, 'content']);

    /* ---- Customer auth ---- */
    Route::post('/auth/register', [AuthController::class, 'register']);
    Route::post('/auth/login', [AuthController::class, 'login']);
    Route::post('/auth/otp/request', [AuthController::class, 'requestOtp'])->middleware('throttle:12,1');
    Route::post('/auth/otp/verify', [AuthController::class, 'verifyOtp'])->middleware('throttle:20,1');
    Route::post('/auth/otp/register', [AuthController::class, 'registerWithOtp'])->middleware('throttle:20,1');
    Route::post('/auth/forgot-password', [AuthController::class, 'resetPassword'])->middleware('throttle:20,1');

    /* ---- Agent auth ---- */
    Route::post('/agent/login', [AgentAuthController::class, 'login'])->middleware('throttle:20,1');

    /* ---- Gateway server callback (signed, not token authed) ---- */
    Route::post('/payment/webhook', [PaymentGatewayController::class, 'webhook']);

    /* ================= CUSTOMER (auth:sanctum) ================= */
    Route::middleware(['auth:sanctum', 'customer.active'])->group(function () {
        Route::get('/auth/me', [AuthController::class, 'me']);
        Route::put('/auth/profile', [AuthController::class, 'updateProfile']);
        Route::post('/auth/profile', [AuthController::class, 'updateProfile']); // multipart
        Route::post('/auth/change-password', [AuthController::class, 'changePassword']);
        Route::post('/auth/logout', [AuthController::class, 'logout']);

        Route::get('/my/schemes', [SchemeApiController::class, 'mySchemes']);
        Route::get('/my/schemes/{id}', [SchemeApiController::class, 'schemeDetail']);
        Route::post('/my/schemes/join', [SchemeApiController::class, 'join']);

        Route::get('/my/payments', [SchemeApiController::class, 'myPayments']);
        Route::get('/my/wallet', [SchemeApiController::class, 'wallet']);
        Route::get('/my/points', [SchemeApiController::class, 'points']);
        Route::post('/my/points/redeem', [SchemeApiController::class, 'redeemPoints']);
        Route::get('/my/passbook', [SchemeApiController::class, 'passbook']);
        Route::get('/my/ledger', [OperationalApiController::class, 'ledger']);
        Route::post('/my/terms/accept', [OperationalApiController::class, 'acceptTerms']);

        Route::get('/my/kyc-documents', [OperationalApiController::class, 'kycDocuments']);
        Route::post('/my/kyc-documents', [OperationalApiController::class, 'uploadKyc']);

        Route::get('/my/refunds', [OperationalApiController::class, 'refunds']);
        Route::post('/my/refunds', [OperationalApiController::class, 'requestRefund']);
        Route::get('/my/scheme-closures', [OperationalApiController::class, 'closures']);
        Route::post('/my/schemes/{id}/closure-request', [OperationalApiController::class, 'requestClosure']);

        Route::get('/my/tickets', [OperationalApiController::class, 'tickets']);
        Route::post('/my/tickets', [OperationalApiController::class, 'createTicket']);
        Route::get('/my/tickets/{id}', [OperationalApiController::class, 'ticket']);
        Route::post('/my/tickets/{id}/replies', [OperationalApiController::class, 'replyTicket']);

        Route::get('/notifications', [OperationalApiController::class, 'notifications']);
        Route::post('/notifications/{id}/read', [OperationalApiController::class, 'readNotification']);

        Route::post('/ai/gold-savings-calculator', [OperationalApiController::class, 'calculator']);
        Route::get('/ai/customer-insights', [OperationalApiController::class, 'insights']);

        /* ---- Online payment (Razorpay) ---- */
        Route::get('/payment/methods', [PaymentGatewayController::class, 'methods']);
        Route::post('/payment/order', [PaymentGatewayController::class, 'createOrder']);
        Route::post('/payment/verify', [PaymentGatewayController::class, 'verify']);
        Route::post('/payment/failed', [PaymentGatewayController::class, 'failed']);
        // Polled when the app returns from a UPI app, where no checkout
        // callback fires.
        Route::get('/payment/order/{orderNo}', [PaymentGatewayController::class, 'orderStatus']);

        /* ---- Direct UPI intent, no aggregator ---- */
        Route::post('/payment/upi/intent', [PaymentGatewayController::class, 'upiIntent']);
        Route::post('/payment/upi/confirm', [PaymentGatewayController::class, 'upiConfirm']);
    });

    /* ================= AGENT (auth:agent) ================= */
    Route::middleware(['auth:agent', 'agent.active'])->prefix('agent')->group(function () {
        Route::get('/me', [AgentAuthController::class, 'me']);
        Route::post('/logout', [AgentAuthController::class, 'logout']);
        Route::post('/change-password', [AgentAuthController::class, 'changePassword']);

        Route::get('/dashboard', [AgentApiController::class, 'dashboard']);
        Route::get('/customers', [AgentApiController::class, 'customers']);
        Route::post('/customers', [AgentApiController::class, 'storeCustomer']);
        Route::get('/customers/{id}', [AgentApiController::class, 'customerDetail']);

        Route::post('/schemes/join', [AgentApiController::class, 'joinScheme']);

        Route::get('/collections', [AgentApiController::class, 'collections']);
        Route::post('/collections', [AgentApiController::class, 'collect']);
        Route::get('/due-list', [AgentApiController::class, 'dueList']);
        Route::get('/commission', [AgentApiController::class, 'commission']);
        Route::get('/tickets', [OperationalApiController::class, 'tickets']);
        Route::post('/tickets', [OperationalApiController::class, 'createTicket']);
        Route::get('/tickets/{id}', [OperationalApiController::class, 'ticket']);
        Route::post('/tickets/{id}/replies', [OperationalApiController::class, 'replyTicket']);
        Route::get('/notifications', [OperationalApiController::class, 'notifications']);
        Route::post('/notifications/{id}/read', [OperationalApiController::class, 'readNotification']);
    });
});
