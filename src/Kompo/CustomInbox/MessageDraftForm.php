<?php

namespace Condoedge\Messaging\Kompo\CustomInbox;

use App\Models\Messaging\Message;
use App\View\Messaging\RecipientsMultiSelect;

class MessageDraftForm extends MessageReplyForm
{
	protected $editorPanelId = 'message-draft-editor';
	protected $signaturePanelId = 'message-draft-signature';

	public function created()
	{
		$this->parentMessageId = $this->model->message_id;
		$this->parentMessage = null; //not used
		$this->thread = $this->model->thread;

		$this->messageType = $this->model->type;
	}

	protected function initialRecipients()
	{
		return $this->model->recipients->map(function($e) {

            return RecipientsMultiSelect::recipientOptionValue($e->entity ?: $e);

        })->filter();
	}

	protected function getSubject()
	{
		return $this->model->subject;
	}

	protected function getSubjectKomponent()
	{
		return $this->model->isDefault() ? _Input()->placeholder('Subject')->name('subject')->class('mt-1 mb-0') : null;
	}

	public function afterSave()
	{
		$this->model->distributions->each->forceDelete();

		parent::afterSave();
	}
}