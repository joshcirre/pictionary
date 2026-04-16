<?php

declare(strict_types=1);

use App\Http\Controllers\StrokeController;
use Illuminate\Support\Facades\Route;

Route::livewire('/', 'pages::home')->name('home');

Route::livewire('/room/{room:code}', 'pages::room')->name('room');

Route::get('/rooms/{code}/strokes', [StrokeController::class, 'index'])->name('rooms.strokes.index');
Route::post('/rooms/{code}/strokes', [StrokeController::class, 'store'])->name('rooms.strokes');
