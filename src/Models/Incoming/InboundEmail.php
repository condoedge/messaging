<?php

namespace Condoedge\Messaging\Models\Incoming;

use BeyondCode\Mailbox\InboundEmail as BCInboundEmail;
use App\Models\Messaging\Message;

class InboundEmail extends BCInboundEmail
{
	public function save(array $options = [])
    {
        try{

        	parent::save();

        }catch(\Throwable $e){

        	\Log::warning('Encoding issue while saving message '.$this->message_id);

        	//$this->message = __('validation.error-encoding-characters');
        	$this->message = mb_convert_encoding($this->message, 'UTF-8', 'UTF-8'); //Trying this to see if it solves the encoding issue

        	parent::save();
        }
    }

	public function savedMessage()
	{
		return $this->hasOne(Message::class, 'external_id', 'message_id');
	}

	public function getFullHtml()
	{
		$html = '';
		for ($i=0; $i < $this->message()->getHtmlPartCount(); $i++) { 
			$html .= $this->message()->getHtmlPart($i)->getContent();
		}

		return $html;
	}

	public function getRecipientsEmails()
    {
        $recipients = collect($this->to())->concat($this->cc())->map(
            fn($address) => $address->getEmail()
        );

        if ($resentFrom = $this->headerValue('Resent-From')) {
            $recipients = $recipients->push($resentFrom);
        }

        return $recipients->unique();
    }


    public function findUuidInMessage()
    {
        return \Str::before(\Str::after($this->text(), 'ce-uuid-start'), 'ce-uuid-stop');
    }


    public function getTextAndSendgridBounce()
    {
        $text = $this->text();

        if ($this->from() == 'no-reply@sendgrid.net') { //NOTE: for sendgrid bounces, email->html() is null

            $text = str_replace('>', '', str_replace('<', '', $text), $text);
            
            foreach($this->attachments() as $attm){ //Optional... just to get the subject
                if ($this->findUuidInMessage($attm->getContent())) {
                    $text .= '<br>'.InboundEmail::fromMessage($attm->getContent())->subject();
                }
            }
        }

        return $text;
    }
}
