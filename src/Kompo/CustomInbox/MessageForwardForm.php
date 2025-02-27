<?php

namespace Condoedge\Messaging\Kompo\CustomInbox;

use App\Models\Messaging\Message;

class MessageForwardForm extends MessageReplyForm
{
	protected $editorPanelId = 'message-forward-editor';
	protected $signaturePanelId = 'message-forward-signature';

	protected $messageType = Message::FORWARD_TYPE;

	protected function initialRecipients()
	{
		return [];
	}

	protected function dealWithAttachments()
	{
		parent::dealWithAttachments();

		$this->parentMessage->attachments->each(function($attm){
            $newAttm = $attm->replicate();
            $newAttm->message_id = $this->model->id;
            $newAttm->save();
		});
	}
}