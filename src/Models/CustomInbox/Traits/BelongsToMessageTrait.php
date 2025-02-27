<?php 

namespace Condoedge\Messaging\Models\CustomInbox\Traits;

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