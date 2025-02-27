<?php

namespace Condoedge\Messaging\Kompo\CustomInbox;

class MessageReplyAllForm extends MessageReplyForm
{
	protected $editorPanelId = 'message-reply-all-editor';
	protected $signaturePanelId = 'message-reply-all-signature';

	protected function initialRecipients()
	{
		return $this->parentMessage->recipients->concat([$this->parentMessage->sender])->map(function($e) {

            if (!$e->belongsToAuthUser()) {
                return RecipientsMultiSelect::recipientOptionValue($e->entity ?: $e);
            }

        })->filter();
	}
}