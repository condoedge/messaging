<?php

namespace Condoedge\Messaging\Http\Controllers;

use Condoedge\Messaging\Services\GmailApi\GmailHelper;
use App\Http\Controllers\Controller;

class GmailDownloadController extends Controller
{
    public function __invoke($messageId, $attId)
    {
    	$attachment = GmailHelper::downloadMessageAttachment($messageId, $attId);

        $content = base64_decode(strtr($attachment->getData(), '-_', '+/')); //Important because Gmail returns Base64url
        $response = \Response::make($content, 200);
        $response->header("Content-Type", request('mimeType'));
        $response->header('Content-disposition', 'attachment; filename="'.request('filename').'"');

        return $response;
    }
}
