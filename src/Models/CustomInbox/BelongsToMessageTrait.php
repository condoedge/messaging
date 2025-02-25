<?php 

namespace Condoedge\Messaging\Models\CustomInbox;

use App\Models\Messaging\Message;

trait BelongsToMessageTrait
{
    /* RELATIONS */
    public function message()
    {
        return $this->belongsTo(Message::class);
    }

    /* ACTIONS */

    /* SCOPES */
}