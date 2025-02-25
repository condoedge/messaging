<?php

namespace Condoedge\Messaging\Models\CustomInbox;

use App\Models\Messaging\Thread;
use Kompo\Auth\Models\Model;

class ThreadBox extends Model
{
    use \Kompo\Auth\Models\Traits\BelongsToUserTrait;

    public const BOX_ARCHIVE = 1;
    public const BOX_TRASH = 2;

    public function thread()
    {
        return $this->belongsTo(Thread::class);
    }

    /* ATTRIBUTES */
    public function getIsArchivedAttribute()
    {
        return $this->box == static::BOX_ARCHIVE;
    }

    public function getIsTrashedAttribute()
    {
        return $this->box == static::BOX_TRASH;
    }

    /* SCOPES */
    public function scopeArchive($query)
    {
        $query->where('box', static::BOX_ARCHIVE);
    }
    
    public function scopeTrash($query)
    {
        $query->where('box', static::BOX_TRASH);
    }
    
    public function scopeNotTrash($query)
    {
        $query->where('box', '<>', static::BOX_TRASH);
    }
}
