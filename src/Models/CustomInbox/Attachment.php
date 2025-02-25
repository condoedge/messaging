<?php

namespace Condoedge\Messaging\Models\CustomInbox;

use App\Models\File;
use App\Models\Model;
use App\Models\Traits\BelongsToTeam;
use App\Models\Traits\FileActionsKomponents;
use App\Models\Traits\MorphRelations;

class Attachment extends Model
{
    use BelongsToTeam,
        MorphRelations,
        FileActionsKomponents;

    public $fileType = 'attachment';

    /* RELATIONS */
    public function message()
    {
        return $this->belongsTo(Message::class);
    }

    /* ACTIONS */
    public function delete()
    {
        if (\Storage::disk('local')->exists($this->path) && !File::where('path', $this->path)->count()) {
            \Storage::disk('local')->delete($this->path);
        }

        parent::delete();
    }
}
