<?php 

use App\Models\Messaging\EmailAccount;

function currentMailboxId()
{
    if (!auth()->user()) {
        abort(419);
    }

    if ($emailAccountId = auth()->user()->current_mailbox_id) {
        return $emailAccountId;
    }

    $emailAccount = auth()->user()->getEntityMailbox();
    return $emailAccount->id;
}

function currentMailbox()
{
    return EmailAccount::find(currentMailboxId());
}

function getMailboxEmail($emailPrefix)
{
    return $emailPrefix.getMailboxHost();
}

function getMailboxHost()
{
    return '@'.config('condoedge-messaging.email_incoming_host');
}

function isMailbox($email)
{
    return strpos($email, getMailboxHost()) > -1;
}

function threadSettingsOpen()
{
    if (!auth()->id()) {
        return false;
    }
    
    return \Cache::get('thread-settings-open-'.auth()->id());
}

function enableThreadSettingsOpen()
{
    if (!auth()->id()) {
        return false;
    }
    
    return \Cache::put('thread-settings-open-'.auth()->id(), 1);
}

function disableThreadSettingsOpen()
{
    if (!auth()->id()) {
        return false;
    }
    
    return \Cache::forget('thread-settings-open-'.auth()->id());
}

/* ACTIONS */

/* ELEMENTS */
function _NewEmailBtn()
{
    return _Link('messaging-new')->button()->class('bg-level1')->style('padding:0.5rem 1rem');
}

function btnFilterClass()
{
    return 'cursor-pointer px-2 py-2 text-level1 border border-level1 rounded-full shrink-0';
}

function _HtmlFieldFilter()
{
    return _HtmlField()
        ->class(btnFilterClass())
        ->selectedClass('bg-info text-level1');
}

if (!function_exists('_BigButton')) {
    function _BigButton($label, $icon)
    {
        return _Rows(
            _Html()->icon(_Sax($icon,36))->class('mb-1'),
            _Html($label)->class('text-sm font-medium opacity-70'),
        )->class('justify-center h-24 text-center items-center p-2 cursor-pointer');
    }
}