<?php

use Illuminate\Support\Facades\Route;

Route::view('/', 'home')->name('home');
Route::view('/chat', 'chat')->name('chat');
Route::view('/admin', 'admin')->name('admin');
