<?php

namespace Condoedge\Messaging\Models\CustomInbox;

use Kompo\Auth\Models\Model;

class Distribution extends Model
{    
    use \Condoedge\Messaging\Models\CustomInbox\Traits\BelongsToEmailAccountTrait;
    use \Condoedge\Messaging\Models\CustomInbox\Traits\BelongsToMessageTrait;

    /* RELATIONS */

    /* SCOPES */
}
