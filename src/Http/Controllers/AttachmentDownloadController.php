<?php

namespace Condoedge\Messaging\Http\Controllers;

class AttachmentDownloadController extends AttachmentController
{
    public function __invoke($id)
    {
        $attm = $this->findAttachmentAndRunSecurity($id);

    	return $attm->storageDisk()->download($attm->storagePath(), $attm->display);
    }
}
