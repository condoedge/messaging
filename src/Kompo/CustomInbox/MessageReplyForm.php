<?php

namespace Condoedge\Messaging\Kompo\CustomInbox;

use App\Models\File;
use App\Models\Messaging\Message;
use App\Models\Messaging\Thread;
use App\Models\Messaging\Signature;
use Condoedge\Utils\Kompo\Common\Form;

class MessageReplyForm extends Form
{
	use RecipientsMultiselectTrait;

	public $model = Message::class;

	protected $editorPanelId = 'message-reply-editor';
	protected $signaturePanelId = 'message-reply-signature';

	protected $parentMessageId;
	protected $parentMessage;
	protected $thread;

	protected $messageType = Message::REPLY_TYPE;

	public function created()
	{
		$this->parentMessageId = $this->parameter('parent_id') ?: $this->store('reply_to_message_id');
		$this->parentMessage = Message::with('recipients.entity', 'sender.entity')->findOrFail($this->parentMessageId);
		$this->thread = $this->parentMessage->thread;
	}

	protected function initialRecipients()
	{
		$sender = $this->parentMessage->sender;

		if ($sender->id == currentMailboxId()) {
			$sender = $this->parentMessage->recipients->first();
		}

		return [
			$sender->email_adr
		];
	}

	protected function getSubject()
	{
		return $this->parentMessage->subject;
	}

	public function beforeSave()
	{
		checkRecipientsAreValid();

		$currentThread = $this->thread;

		if ($this->parentMessage && $this->parentMessage->hasDifferentDistributions(getRequestRecipients())) {
			$currentThread = $currentThread->createNewBranch();
		}

		$this->model->thread_id = $currentThread->id;
		$this->model->subject = $this->getSubject();
		$this->model->type = $this->messageType;
		$this->model->message_id = $this->parentMessageId;

		$this->model->is_draft = request('is_draft');

		if (request('is_draft')) {
			return;
		}

		Signature::appendToMessage($this->model);
	}

	public function afterSave()
	{
		$this->model->addAllDistributionsFromRequest();
	}

	public function completed()
	{
		$this->dealWithAttachments();

		if (request('is_draft')) {
			return;
		}

		$this->model->sendExternalEmail();

		$this->model->thread->boxes()->delete();
		
		$this->model->thread->updateStats();

		Thread::pusherBroadcast();
	}

	protected function dealWithAttachments()
	{
		$this->model->addLinkedAttachments();
	}

	public function response()
	{
		return;
		return redirect()->route('inbox', [
			'thread_id' => $this->model->thread_id,
		]);
	}

	public function render()
	{
		[$attachmentsLink, $attachmentsBox] = _FileUploadLinkAndBox('attachments', !$this->model->attachments->count());

		return [
			_Rows(
	        	_Panel(
		        	_RecipientsMultiSelect()
			        	->value($this->initialRecipients()),
				    _CcToggle(),
		        )->id('new-thread-recipients'),
		        _FlexBetween(
			        _Link('messaging-send-to-group')
			        	->icon('user-group')
			        	->class('text-sm text-level3 justify-end')
			        	->get('thread-groups')
			        	->inModal(),
		       	),
				$this->getSubjectKomponent(),
	        )->class('px-2 py-2 border-t border-gray-200')
	        ->style('box-shadow: 0 -2px 5px -5px #333;'),
	        _Rows(
				_Rows(
					_Panel(
					    Message::editor()
					    	->insertCustomText('messaging-insert-previous-message', $this->parentMessage?->getHtmlToAppend())
		        			->focusOnLoad(),
			        )->id($this->editorPanelId)
				    ->class('email-ckeditor-delayed'),
			        _Flex2(
						Message::sendDropdown()->refresh('inbox-message-'.$this->thread->id)->alert('message-sent'),
						$attachmentsLink,
						//Message::draftButton()->refresh('inbox-message-'.$this->thread->id),
				        showSignatureOptionsLink(),
				        //TemplatesQuery::templatesModalLink($this->editorPanelId),
					)->class('mt-2')
				)->class('relative p-2'),
				$attachmentsBox,
				getSignatureActionButtons($this->signaturePanelId)->class('px-2'),
			)
	    ];
	}

	protected function getSubjectKomponent()
	{
		return;
	}

	public function rules()
	{
		return array_merge(request('is_draft') ? [] : [
			'recipients' => 'required_without:massive_recipients_group',
			'html' => 'required_without:attachments',
		], [
			'attachments.*' => 'max:20000',
			'attachments' => [new \Condoedge\Messaging\Rules\FilesTotalUploadSize(20000)],
		]);
	}
}
