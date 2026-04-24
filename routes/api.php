<?php

use App\Http\Controllers\Api\Admin\AllocationController;
use App\Http\Controllers\Api\Admin\ActivityLogController;
use App\Http\Controllers\Api\Admin\ProjectController;
use App\Http\Controllers\Api\Admin\ReportController;
use App\Http\Controllers\Api\Admin\UserController;
use App\Http\Controllers\Api\AdminDashboardController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\EmployeeDashboardController;
use App\Http\Controllers\Api\EmployeeLoeReportController;
use App\Http\Controllers\Api\LoeFeedbackController;
use App\Http\Controllers\Api\NotificationController;
use Illuminate\Support\Facades\Route;

Route::prefix('auth')->group(function () {
    Route::post('/employee/login', [AuthController::class, 'employeeLogin']);
    Route::post('/admin/login', [AuthController::class, 'adminLogin']);
    Route::post('/employee/forgot-password', [AuthController::class, 'employeeForgotPassword']);
    Route::post('/admin/forgot-password', [AuthController::class, 'adminForgotPassword']);
    Route::post('/employee/reset-password', [AuthController::class, 'employeeResetPassword']);
    Route::post('/admin/reset-password', [AuthController::class, 'adminResetPassword']);
});

Route::middleware('auth:sanctum')->group(function () {
    Route::get('/auth/me', [AuthController::class, 'me']);
    Route::post('/auth/logout', [AuthController::class, 'logout']);
    Route::get('/notifications', [NotificationController::class, 'index']);
    Route::post('/notifications/read-all', [NotificationController::class, 'markAllAsRead']);
    Route::post('/notifications/{notification}/read', [NotificationController::class, 'markAsRead']);
    Route::get('/loe-reports/{loeReport}/feedback', [LoeFeedbackController::class, 'index']);
    Route::post('/loe-reports/{loeReport}/feedback', [LoeFeedbackController::class, 'store']);

    Route::prefix('employee')->middleware('role:employee')->group(function () {
        Route::get('/dashboard', EmployeeDashboardController::class);
        Route::get('/reports/export', [EmployeeLoeReportController::class, 'export']);
        Route::apiResource('reports', EmployeeLoeReportController::class)
            ->parameters(['reports' => 'employeeLoeReport']);
    });

    Route::prefix('admin')->middleware('role:admin')->group(function () {
        Route::get('/dashboard', AdminDashboardController::class);
        Route::get('/activity-logs', [ActivityLogController::class, 'index']);
        Route::get('/reports', [ReportController::class, 'index']);
        Route::get('/reports/export', [ReportController::class, 'export']);
        Route::get('/users/{user}/loe-reports', [UserController::class, 'loeReports']);
        Route::get('/users/{user}/loe-reports/export', [UserController::class, 'exportLoeReports']);
        Route::apiResource('users', UserController::class);
        Route::apiResource('projects', ProjectController::class);
        Route::apiResource('allocations', AllocationController::class);
    });
});
