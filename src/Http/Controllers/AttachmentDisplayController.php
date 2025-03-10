<?php

namespace Condoedge\Messaging\Http\Controllers;

class AttachmentDisplayController extends AttachmentController
{
    public function __invoke($id)
    {
    	$attm = $this->findAttachmentAndRunSecurity($id);

        $file = $attm->storageDisk()->get($attm->storagePath());
        $type = $attm->storageDisk()->mimeType($attm->storagePath());

        $response = \Response::make($file, 200);
        $response->header("Content-Type", $type);

        return $response;
    }
}
