<?php

namespace Condoedge\Messaging\Models\CustomInbox;

use Kompo\Auth\Models\Model;

class Distribution extends Model
{    
    use \Condoedge\Messaging\Models\CustomInbox\Traits\BelongsToEmailAccountTrait;
    use \Condoedge\Messaging\Models\CustomInbox\Traits\BelongsToMessageTrait;

    public const TYPE_RECIP_TO = 1;
    public const TYPE_RECIP_CC = 2;
    public const TYPE_RECIP_BCC = 3;

    /* RELATIONS */

    /* SCOPES */
    public function scopeForTo($query)
    {
        $query->where('type_recip', static::TYPE_RECIP_TO);
    }

    public function scopeForCc($query)
    {
        $query->where('type_recip', static::TYPE_RECIP_CC);
    }

    public function scopeForBcc($query)
    {
        $query->where('type_recip', static::TYPE_RECIP_BCC);
    }

    /* CALCULATED FIELDS */
    
}
