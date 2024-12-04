<?php

use Condoedge\Messaging\Http\Controllers\GoogleSsoController;

//TODO KEEP AN EXAMPLE LIKE THIS WHEN YOU FINISH MIGRATING
Route::layout('layouts.dashboard')->middleware(['auth'])->group(function(){

    //Call them in own project
    //Route::get('surveys-list', Condoedge\Surveys\Kompo\SurveyEditor\SurveysList::class)->name('surveys.list');
    //Route::get('survey-edit/{id}', Condoedge\Surveys\Kompo\SurveyEditor\SurveyFormPage::class)->name('survey.edit');

});



//GOOGLE ROUTES
Route::get('google-sso', [GoogleSsoController::class, 'redirectToSso'])->name('google-sso');
Route::get('google-sso-return', [GoogleSsoController::class, 'returnFromSso'])->name('google-sso-return');
Route::get('google-sso-signout', [GoogleSsoController::class, 'signout'])->name('google-sso-signout');
Route::get('change-google-token/{id}', [GoogleSsoController::class, 'changeGoogleToken'])->name('change-google-token');
Route::get('reset-google-token', [GoogleSsoController::class, 'resetGoogleToken'])->name('reset-google-token');