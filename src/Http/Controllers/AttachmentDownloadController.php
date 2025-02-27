<?php

namespace Condoedge\Messaging\Http\Controllers;

use App\Models\Messaging\Attachment;
use App\Http\Controllers\Controller;

class AttachmentDownloadController extends Controller
{
    public function __invoke($id)
    {
    	$attm = Attachment::findOrFail($id);

        if (!currentMailbox()) {
            abort(403, __('error.you-cant-download-this-file'));
        }

        if (!$attm->message()->authUserIncluded()->count()) {
            abort(403, __('error.you-cant-download-this-file'));
        }

        if ($attm->existsOnStorage()) {
            abort(404, __('error.file-not-found'));
        }

    	return $attm->storageDisk()->download($attm->storagePath(), $attm->display);
    }
}
