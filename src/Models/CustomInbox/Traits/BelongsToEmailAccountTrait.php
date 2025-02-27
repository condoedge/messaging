<?php 

namespace Condoedge\Messaging\Models\CustomInbox\Traits;

use App\Models\Messaging\EmailAccount;

trait BelongsToEmailAccountTrait
{
    /* RELATIONS */
    public function emailAccount()
    {
        return $this->belongsTo(EmailAccount::class);
    }

    /* ACTIONS */

    /* SCOPES */
}