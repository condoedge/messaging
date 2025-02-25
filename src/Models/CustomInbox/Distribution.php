<?php

namespace Condoedge\Messaging\Models\CustomInbox;

use App\Models\Model;
use App\Models\Traits\BelongsToTeam;

class Distribution extends Model
{    
    /* RELATIONS */
    public function message()
    {
        return $this->belongsTo(Message::class);
    }

    public function emailAccount()
    {
        return $this->belongsTo(EmailAccount::class);
    }

    /* SCOPES */
    public function scopeAuthUserAsRecipient($query, $allMailboxes = false)
    {
        $query->whereIn('email_account_id', auth()->user()->getActiveEmailAccountIds($allMailboxes))
            ->whereDoesntHave('message', fn($q) => $q->isDraft());
    }
}
