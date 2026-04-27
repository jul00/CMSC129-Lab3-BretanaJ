<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\MovieController;
use App\Http\Controllers\ChatBotController;

Route::get('/', [MovieController::class,'index']);
Route::resource('movies', MovieController::class);

Route::get('/trash',[MovieController::class,'trash'])->name('movies.trash');
Route::patch('/movies/{id}/restore',[MovieController::class,'restore'])->name('movies.restore');
Route::delete('/force-delete/{id}',[MovieController::class,'forceDelete'])->name('movies.forceDelete');

Route::post('/chat', [ChatBotController::class, 'chat']);
