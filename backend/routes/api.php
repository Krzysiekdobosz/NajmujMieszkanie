<?php

use Illuminate\Support\Facades\Route;

use App\Http\Controllers\Api\Auth\AuthController;
use App\Http\Controllers\Api\Public\PropertyController as PublicPropertyController;
use App\Http\Controllers\Api\Public\BlogPostController as PublicBlogPostController;
use App\Http\Controllers\Api\Public\BlogCategoryController;
use App\Http\Controllers\Api\Public\PageController;
use App\Http\Controllers\Api\Public\ContactMessageController;
use App\Http\Controllers\Api\User\ProfileController;
use App\Http\Controllers\Api\User\FavoriteController;
use App\Http\Controllers\Api\User\NotificationController;
use App\Http\Controllers\Api\User\PropertyViewingController as UserViewingController;
use App\Http\Controllers\Api\Shared\ConversationController;
use App\Http\Controllers\Api\Shared\MessageController;
use App\Http\Controllers\Api\Owner\PropertyController as OwnerPropertyController;
use App\Http\Controllers\Api\Owner\PropertyImageController;
use App\Http\Controllers\Api\Owner\ViewingController as OwnerViewingController;
use App\Http\Controllers\Api\Owner\ContractController;
use App\Http\Controllers\Api\Owner\PaymentController;
use App\Http\Controllers\Api\Admin\UserController as AdminUserController;
use App\Http\Controllers\Api\Admin\PropertyController as AdminPropertyController;
use App\Http\Controllers\Api\Admin\ContactMessageController as AdminContactMessageController;
use App\Http\Controllers\Api\Admin\BlogPostController as AdminBlogPostController;
use App\Http\Controllers\Api\Admin\PageController as AdminPageController;
use App\Http\Controllers\Api\Admin\SettingController;
use App\Http\Controllers\Api\Admin\ActivityLogController;

Route::prefix('v1')->group(function () {

    // ─── AUTH (publiczne) ───────────────────────────────────────────────────
    Route::prefix('auth')->group(function () {
        Route::post('register',        [AuthController::class, 'register'])->middleware('throttle:10,1');
        Route::post('login',           [AuthController::class, 'login'])->middleware('throttle:10,1');
        Route::post('forgot-password', [AuthController::class, 'forgotPassword'])->middleware('throttle:5,1');
        Route::post('reset-password',  [AuthController::class, 'resetPassword'])->middleware('throttle:5,1');
    });

    // ─── PUBLICZNE ──────────────────────────────────────────────────────────
    Route::get('properties',        [PublicPropertyController::class, 'index']);
    Route::get('properties/{slug}', [PublicPropertyController::class, 'show']);

    Route::get('blog-posts',           [PublicBlogPostController::class, 'index']);
    Route::get('blog-posts/{slug}',    [PublicBlogPostController::class, 'show']);
    Route::get('blog-categories',      [BlogCategoryController::class, 'index']);

    Route::get('pages/{slug}',         [PageController::class, 'show']);

    Route::post('contact-messages',    [ContactMessageController::class, 'store'])
         ->middleware('throttle:5,1');

    // ─── ZALOGOWANY ─────────────────────────────────────────────────────────
    Route::middleware('auth:sanctum')->group(function () {

        Route::post('auth/logout', [AuthController::class, 'logout']);
        Route::get('auth/me',      [AuthController::class, 'me']);

        Route::put('profile', [ProfileController::class, 'update']);

        // Ulubione
        Route::get('favorites',        [FavoriteController::class, 'index']);
        Route::post('favorites',       [FavoriteController::class, 'store']);
        Route::delete('favorites/{id}', [FavoriteController::class, 'destroy']);

        // Konwersacje
        Route::get('conversations',                           [ConversationController::class, 'index']);
        Route::post('conversations',                          [ConversationController::class, 'store']);
        Route::get('conversations/{id}',                      [ConversationController::class, 'show']);
        Route::post('conversations/{id}/messages',            [MessageController::class, 'store'])->middleware('throttle:30,1');
        Route::patch('conversations/{id}/messages/read',      [MessageController::class, 'markRead']);

        // Rezerwacje oglądania (użytkownik)
        Route::get('property-viewings',        [UserViewingController::class, 'index']);
        Route::post('property-viewings',       [UserViewingController::class, 'store']);
        Route::delete('property-viewings/{id}', [UserViewingController::class, 'destroy']);

        // Powiadomienia
        Route::get('notifications',               [NotificationController::class, 'index']);
        Route::post('notifications/read-all',     [NotificationController::class, 'readAll']);
        Route::patch('notifications/{id}/read',   [NotificationController::class, 'read']);

        // ─── PANEL WŁAŚCICIELA ───────────────────────────────────────────
        Route::middleware('role:owner|admin')->prefix('owner')->group(function () {
            Route::apiResource('properties', OwnerPropertyController::class);
            Route::post('properties/{id}/publish',            [OwnerPropertyController::class, 'publish']);
            Route::post('properties/{id}/images',             [PropertyImageController::class, 'store']);
            Route::patch('properties/{id}/images/{imageId}/main', [PropertyImageController::class, 'setMain']);
            Route::delete('properties/{id}/images/{imageId}', [PropertyImageController::class, 'destroy']);
            Route::patch('properties/{id}/images/reorder',     [PropertyImageController::class, 'reorder']);

            Route::get('viewings',                [OwnerViewingController::class, 'index']);
            Route::patch('viewings/{id}/status',  [OwnerViewingController::class, 'updateStatus']);

            Route::get('contracts',               [ContractController::class, 'index']);
            Route::post('contracts',              [ContractController::class, 'store']);
            Route::get('contracts/{id}',          [ContractController::class, 'show']);
            Route::get('contracts/{id}/download', [ContractController::class, 'download']);
            Route::patch('contracts/{id}/status', [ContractController::class, 'updateStatus']);

            Route::get('payments',                [PaymentController::class, 'index']);
            Route::post('payments',               [PaymentController::class, 'store']);
            Route::get('payments/{id}',           [PaymentController::class, 'show']);
            Route::patch('payments/{id}/status',  [PaymentController::class, 'updateStatus']);
        });

        // ─── PANEL ADMINISTRATORA ────────────────────────────────────────
        Route::middleware('role:admin')->prefix('admin')->group(function () {
            Route::get('users',                          [AdminUserController::class, 'index']);
            Route::get('users/{id}',                     [AdminUserController::class, 'show']);
            Route::patch('users/{id}/status',            [AdminUserController::class, 'updateStatus']);
            Route::patch('users/{id}/role',              [AdminUserController::class, 'updateRole']);

            Route::get('properties',                     [AdminPropertyController::class, 'index']);
            Route::patch('properties/{id}/status',       [AdminPropertyController::class, 'updateStatus']);

            Route::get('contact-messages',               [AdminContactMessageController::class, 'index']);
            Route::get('contact-messages/{id}',          [AdminContactMessageController::class, 'show']);
            Route::patch('contact-messages/{id}/status', [AdminContactMessageController::class, 'updateStatus']);

            Route::get('blog-posts',                     [AdminBlogPostController::class, 'index']);
            Route::post('blog-posts',                    [AdminBlogPostController::class, 'store']);
            Route::get('blog-posts/{id}',                [AdminBlogPostController::class, 'show']);
            Route::put('blog-posts/{id}',                [AdminBlogPostController::class, 'update']);
            Route::delete('blog-posts/{id}',             [AdminBlogPostController::class, 'destroy']);

            Route::get('pages',                          [AdminPageController::class, 'index']);
            Route::get('pages/{id}',                     [AdminPageController::class, 'show']);
            Route::put('pages/{id}',                     [AdminPageController::class, 'update']);

            Route::get('settings',                       [SettingController::class, 'index']);
            Route::put('settings',                       [SettingController::class, 'update']);

            Route::get('activity-logs',                  [ActivityLogController::class, 'index']);
        });
    });
});
