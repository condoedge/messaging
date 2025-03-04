<?php

use Illuminate\Support\Facades\Route;
use Condoedge\Messaging\Http\Controllers\GoogleSsoController;

//Call them in own project
Route::layout('layouts.dashboard')->middleware(['auth'])->group(function(){

    //Custom Inbox Routes
    Route::get('my-inbox/{thread_id?}', Condoedge\Messaging\Kompo\CustomInbox\InboxView::class)->name('custom-inbox');
    
    Route::get('my-inbox-new-thread', Condoedge\Messaging\Kompo\CustomInbox\ThreadForm::class)->name('new.thread');
    
    Route::get('thread-settings/{id?}', Condoedge\Messaging\Kompo\CustomInbox\ThreadSettingsForm::class)->name('thread-settings.form');

});

Route::middleware(['auth'])->group(function(){
    Route::post('enableThreadSettingsOpen', fn() => enableThreadSettingsOpen());
    Route::post('disableThreadSettingsOpen', fn() => disableThreadSettingsOpen());

    Route::get('inbox/message/{id}', Condoedge\Messaging\Kompo\CustomInbox\InboxMessages::class)->name('inbox.message');

    Route::get('message-reply/{parent_id}', Condoedge\Messaging\Kompo\CustomInbox\MessageReplyForm::class)->name('message-reply.form');
    Route::get('message-reply-all/{parent_id}', Condoedge\Messaging\Kompo\CustomInbox\MessageReplyAllForm::class)->name('message-reply-all.form');
    Route::get('message-forward/{parent_id}', Condoedge\Messaging\Kompo\CustomInbox\MessageForwardForm::class)->name('message-forward.form');
    Route::get('message-draft/{id}', Condoedge\Messaging\Kompo\CustomInbox\MessageDraftForm::class)->name('message-draft.form');

    Route::get('thread-groups', Condoedge\Messaging\Kompo\CustomInbox\ThreadGroupsForm::class)->name('thread-groups');

    Route::get('attm-download/{id}', Condoedge\Messaging\Http\Controllers\AttachmentDownloadController::class)->name('attm.download');

    Route::get('mail-debug-single/{id}', Condoedge\Messaging\Http\Controllers\MailParseDebugController::class)->name('mail-debug-single');
});



//GOOGLE ROUTES
Route::get('google-sso', [GoogleSsoController::class, 'redirectToSso'])->name('google-sso');
Route::get('google-sso-return', [GoogleSsoController::class, 'returnFromSso'])->name('google-sso-return');
Route::get('google-sso-signout', [GoogleSsoController::class, 'signout'])->name('google-sso-signout');
Route::get('change-google-token/{id}', [GoogleSsoController::class, 'changeGoogleToken'])->name('change-google-token');
Route::get('reset-google-token', [GoogleSsoController::class, 'resetGoogleToken'])->name('reset-google-token');