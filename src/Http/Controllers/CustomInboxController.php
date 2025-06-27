<?php

namespace Condoedge\Messaging\Http\Controllers;

use App\Models\Messaging\Thread;
use App\Http\Controllers\Controller;

class CustomInboxController extends Controller
{
    public function calculateUnreadMessages()
    {
        $emailAccount = currentMailbox();

        $query = Thread::notAssociatedToAnyBox()
            ->whereHas('messages', fn($q) => $q->authUserInDistributions()
                ->whereDoesntHave('reads', fn($q) => $q->where('email_account_id', currentMailboxId()))
            );

        $emailAccount->unread_count = $query->count();

        $emailAccount->save();

        //Thread::pusherBroadcast(); //Todo pusher

        return $emailAccount->unread_count;
    }
}
