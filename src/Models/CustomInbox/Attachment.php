<?php

namespace Condoedge\Messaging\Models\CustomInbox;

use App\Models\Messaging\Attachment as AppAttachment;
use Kompo\Auth\Models\Files\File;
use Kompo\Auth\Models\Model;

class Attachment extends Model
{
    use \Condoedge\Messaging\Models\CustomInbox\Traits\BelongsToMessageTrait;
    use \Kompo\Auth\Models\Files\FileActionsKomponents;

    /* RELATIONS */

    /* CALCULATED FIELDS */
    public function getDisplayRoute()
    {
        return route('attm.display', ['id' => $this->id]);
    }

    /* ACTIONS */
    public function delete()
    {
        if ($this->existsOnStorage() && !File::where('path', $this->storagePath())->count()) {
            $this->storageDisk()->delete($this->storagePath());
        }

        parent::delete();
    }

    public static function createAttachmentFromFile($message, $name, $mime_type, $path)
    {
        $attm = new AppAttachment;
        $attm->name = $name;
        $attm->mime_type = $mime_type;
        $attm->path = $path;
        $message->attachments()->save($attm);

        return $attm;
    }

    /* ELEMENTS */
    public function downloadAction($el)
    {
        return $el->href('attm.download', ['id' => $this->id]);
    }
}
