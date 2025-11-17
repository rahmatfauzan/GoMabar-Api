<?php

use App\Http\Controllers\AdminBookingController;
use App\Http\Controllers\SportCategoryController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\BookingController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\FieldBlockController;
use App\Http\Controllers\FieldController;
use App\Http\Controllers\FieldOperatingHoursController;
use App\Http\Controllers\MabarParticipantController;
use App\Http\Controllers\MabarSessionController;
use App\Http\Controllers\MidtransWebhookController;
use App\Http\Controllers\UserController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::prefix('auth')->group(function () {
    Route::post('/login', [AuthController::class, 'login']);
    Route::post('/register', [AuthController::class, 'register']);
});


Route::get('/sport-categories', [SportCategoryController::class, 'index']);
Route::get('/sport-categories/{sportCategory}', [SportCategoryController::class, 'show']);
Route::get('/fields', [FieldController::class, 'index']);
Route::get('/fields/name', [FieldOperatingHoursController::class, 'name']);
Route::get('/fields/{field}', [FieldController::class, 'show']);
Route::get('/fields/{field}/availability', [FieldController::class, 'getAvailability']);
Route::get('/mabar-sessions', [MabarSessionController::class, 'index']);
Route::get('/mabar-sessions/{mabarSession}', [MabarSessionController::class, 'show']);

// RUTE PERLU LOGIN (auth:sanctum)
Route::post('/auth/logout', [AuthController::class, 'logout']);
Route::post('/midtrans/notification', [MidtransWebhookController::class, 'handleNotification']);
Route::middleware('auth:sanctum')->group(function () {

    Route::prefix('bookings')->group(function () {
        Route::post('/', [BookingController::class, 'createBooking']);
        // Route::post('/${invoiceNumber}/regenerate-payment', [BookingController::class, 'regeneratePayment']);
        Route::get('/invoice/{invoiceNumber}', [BookingController::class, 'getByInvoice']);
        Route::get('/{invoiceNumber}/payment-token', [BookingController::class, 'getToken']);
        Route::get('/{id}', [BookingController::class, 'show']);
        Route::post('/{id}/cancel', [BookingController::class, 'cancel']);
    });

    Route::prefix('my-bookings')->group(function () {
        Route::get('/', [BookingController::class, 'myBookings']); // All with filter
        Route::get('/active', [BookingController::class, 'activeBookings']); // Active only
        Route::get('/pending', [BookingController::class, 'pendingBookings']); // ← NEW!
        Route::get('/completed', [BookingController::class, 'completedBookings']);
        Route::get('/failed', [BookingController::class, 'bookingFailed']); // Completed/cancelled
        Route::get('/statistics', [BookingController::class, 'getStatistics']); // Stats for dashboard
    });

    Route::get('/user', [UserController::class, 'profile']);

    Route::post('/user/profile', [UserController::class, 'updateProfile']);
    Route::post('/user/password', [UserController::class, 'updatePassword']);

    Route::post('/bookings', [BookingController::class, 'store']);
    Route::get('/bookings/{booking}/payment-token', [BookingController::class, 'getPaymentToken']);
    Route::get('/user/bookings', [BookingController::class, 'userBookings']);

    Route::post('/mabar-sessions', [MabarSessionController::class, 'store']);
    Route::delete('/mabar-sessions/{mabarSession}', [MabarSessionController::class, 'destroy']);
    Route::put('/mabar-sessions/{mabarSession}', [MabarSessionController::class, 'update']);
    Route::get('/user/mabar-sessions', [MabarSessionController::class, 'userSessions']);
    Route::get('/user/joined-sessions', [MabarSessionController::class, 'userJoinedSessions']);

    Route::post('/mabar-sessions/{mabarSession}/join', [MabarParticipantController::class, 'join']);
    Route::post('/mabar-participants/upload-proof', [MabarParticipantController::class, 'uploadProof']);
    Route::post('/mabar-participants/cancel', [MabarParticipantController::class, 'cancelParticipation']);

    Route::delete('/mabar-participants/{mabarParticipant}', [MabarParticipantController::class, 'destroy']);

    Route::patch('/mabar-participants/{mabarParticipant}/status', [MabarParticipantController::class, 'updateStatus']);
    Route::post('/mabar-sessions/{mabarSession}/add-manual', [MabarParticipantController::class, 'addManual']);
    Route::get('/bookings/{booking}', [BookingController::class, 'show']);
});

// RUTE KHUSUS ADMIN (Perlu Login & Role Admin)
Route::prefix('admin')->middleware(['auth:sanctum', 'role:admin'])->group(function () {

    Route::get('/dashboard/stats', [DashboardController::class, 'getStats']);
    Route::post('/fields', [FieldController::class, 'store']);
    Route::put('/fields/{field}', [FieldController::class, 'update']);
    Route::delete('/fields/{field}', [FieldController::class, 'destroy']);
    Route::get('/fields/{field}/blocks', [FieldBlockController::class, 'index']);
    Route::post('/fields/{field}/blocks', [FieldBlockController::class, 'store']);
    Route::delete('/blocks/{fieldBlock}', [FieldBlockController::class, 'destroy']);

    Route::get('/fields/{field}/operating-hours', [FieldOperatingHoursController::class, 'index']);
    Route::put('/fields/{field}/operating-hours', [FieldOperatingHoursController::class, 'update']);

    Route::post('/sport-categories', [SportCategoryController::class, 'store']);
    Route::put('/sport-categories/{sportCategory}', [SportCategoryController::class, 'update']);
    Route::delete('/sport-categories/{sportCategory}', [SportCategoryController::class, 'destroy']);

    Route::get('/users', [UserController::class, 'index']);
    Route::post('/users', [UserController::class, 'store']);
    Route::put('/users/{user}', [UserController::class, 'update']); // <-- UPDATE
    Route::delete('/users/{user}', [UserController::class, 'destroy']); // <-- DELETE

    Route::get('/bookings', [AdminBookingController::class, 'index']);
    Route::post('/manual-booking', [AdminBookingController::class, 'manualStore']);

    Route::get('/dashboard/stats', [DashboardController::class, 'getStats']);
    Route::get('/dashboard/recent-bookings', [DashboardController::class, 'getRecentBookings']);
    Route::get('/dashboard/upcoming-mabar', [DashboardController::class, 'getUpcomingMabar']);
});
