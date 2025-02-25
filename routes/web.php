<?php

use Condoedge\Messaging\Http\Controllers\GoogleSsoController;

//Call them in own project
Route::layout('layouts.dashboard')->middleware(['auth'])->group(function(){

    //Custom Inbox Routes
    //Route::get('my-inbox', Condoedge\Messaging\Kompo\CustomInbox\InboxView::class)->class('custom-inbox');

});



//GOOGLE ROUTES
Route::get('google-sso', [GoogleSsoController::class, 'redirectToSso'])->name('google-sso');
Route::get('google-sso-return', [GoogleSsoController::class, 'returnFromSso'])->name('google-sso-return');
Route::get('google-sso-signout', [GoogleSsoController::class, 'signout'])->name('google-sso-signout');
Route::get('change-google-token/{id}', [GoogleSsoController::class, 'changeGoogleToken'])->name('change-google-token');
Route::get('reset-google-token', [GoogleSsoController::class, 'resetGoogleToken'])->name('reset-google-token');