<?php

use App\Http\Controllers\Api\ApprovalActionController;
use App\Http\Controllers\Api\ApprovalCommentController;
use App\Http\Controllers\Api\ApprovalController;
use App\Http\Controllers\Api\MasterController;
use Illuminate\Support\Facades\Route;

Route::get('/health', function () {
    return response()->json([
        'status' => 'ok',
        'service' => 'approval-system-backend',
        'timestamp' => now()->toIso8601String(),
    ]);
});

Route::get('/masters', [MasterController::class, 'index']);

Route::get('/approvals', [ApprovalController::class, 'index']);
Route::post('/approvals', [ApprovalController::class, 'store']);
Route::get('/approvals/{id}', [ApprovalController::class, 'show'])->whereNumber('id');
Route::get('/approvals/{id}/files/{fileId}', [ApprovalController::class, 'downloadFile'])
    ->whereNumber(['id', 'fileId']);
Route::put('/approvals/{id}', [ApprovalController::class, 'update'])->whereNumber('id');

Route::post('/approvals/{id}/withdraw', [ApprovalActionController::class, 'withdraw'])->whereNumber('id');
Route::post('/approvals/{id}/approve', [ApprovalActionController::class, 'approve'])->whereNumber('id');
Route::post('/approvals/{id}/remand', [ApprovalActionController::class, 'remand'])->whereNumber('id');

Route::post('/approvals/{id}/comments', [ApprovalCommentController::class, 'store'])->whereNumber('id');
Route::patch('/approvals/{id}/comments/{commentId}/status', [ApprovalCommentController::class, 'updateStatus'])
    ->whereNumber(['id', 'commentId']);

