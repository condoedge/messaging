<?php

namespace Condoedge\Messaging\Http\Controllers;

use Condoedge\Messaging\Services\MicrosoftGraph\GraphHelper;
use App\Http\Controllers\Controller;

class OutlookDownloadController extends Controller
{
    public function __invoke($messageId, $attId)
    {
    	$attachment = GraphHelper::downloadMessageAttachment($messageId, $attId);

        $content = base64_decode($attachment->getContentBytes());
        $response = \Response::make($content, 200);
        $response->header("Content-Type", $attachment->getContentType());
        $response->header('Content-disposition', 'attachment; filename="'.$attachment->getName().'"');

        return $response;
    }
}
