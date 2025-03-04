<?php

namespace Condoedge\Messaging\Http\Controllers;

use Condoedge\Messaging\IncomingEmail\CatchIncomingEmails;
use Condoedge\Messaging\Models\Incoming\InboundEmail;
use App\Http\Controllers\Controller;

class MailParseDebugController extends Controller
{
    public function __invoke($id)
    {
    	$email = InboundEmail::find($id);

    	return CatchIncomingEmails::handleIncomingEmail($email);
    }
}
