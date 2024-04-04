<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Authentication\AuthController;
use App\Http\Controllers\QRLogin\QrLoginController;

// Authentication
Route::prefix('auth')->group(function () {
    Route::post('/login', [AuthController::class, 'login']);
    Route::post('/register', [AuthController::class, 'register']);
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::post('/refreshToken', [AuthController::class, 'refreshToken']);
    Route::get('/profile', [AuthController::class, 'userProfile']);
});

// QR Code Login
Route::prefix('qrlogin')->group(function () {
    // create the qr code from web and listen for mobile scan
    Route::post('/create', [QrLoginController::class,'createQrCode']);

    // mobile do scan and send login data
    Route::post('/mobile/scan', [QrLoginController::class,'isMobileQrScan']);
    Route::post('/mobile/login', [QrLoginController::class,'qrCodeDoLogin']);
    
    // web receive data after listen and do login
    Route::post('/web/login', [QrLoginController::class,'isScanQrcodeWeb']);
    Route::post('/web/login/entry', [QrLoginController::class,'webLoginEntry']);
});
