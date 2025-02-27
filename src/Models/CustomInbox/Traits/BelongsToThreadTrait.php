<?php 

namespace Condoedge\Messaging\Models\CustomInbox\Traits;

use App\Models\Messaging\Thread;

trait BelongsToThreadTrait
{
    /* RELATIONS */
    public function message()
    {
        return $this->belongsTo(Thread::class);
    }

    /* ACTIONS */

    /* SCOPES */
}