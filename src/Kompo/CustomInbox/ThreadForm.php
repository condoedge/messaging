<?php

namespace Condoedge\Messaging\Kompo\CustomInbox;

use App\Models\Messaging\Message;
use App\Models\Messaging\Thread;
use Condoedge\Utils\Kompo\Common\Form;

class ThreadForm extends Form
{
	use RecipientsMultiselectTrait;

	public $model = Message::class;

	public $style = 'min-width: 75vw;max-width: 1060px';
    public $class = 'bg-white p-4';
    public $containerClass = 'container-fluid';

	protected $prefilledRecipient;

	protected $editorPanelId = 'new-thread-editor';
	protected $signaturePanelId = 'new-thread-signature';

	protected $threadId;
	protected $thread;

	protected $boxClasses = 'card-gray-100';

	protected $maxFilesSize = 20000; // 20 MB

	public function created()
	{
		$this->threadId = $this->parameter('id');
		$this->thread = $this->threadId ? Thread::findOrFail($this->threadId) : null;

		$this->prefilledRecipient = $this->prop('prefilled_to');

		$this->model(new Message());
	}

	public function beforeSave()
	{
		checkRecipientsAreValid();
dd(request()->all());
		if($this->threadId){
			$this->model->thread_id = $this->threadId;
			$this->model->subject = 'RE: '.$this->thread->lastMessage->subject;
		}else{

			$thread = new Thread();
			$thread->subject = request('subject');
			$thread->save();

			collect(request('tags'))->each(fn($tagId) => $thread->tags()->attach($tagId));

			$this->model->thread_id = $thread->id;
		}

		$this->model->is_draft = request('is_draft');

		if (request('is_draft')) {
			return;
		}

		//Signature::appendToMessage($this->model);
	}

	public function afterSave()
	{
		if($this->threadId) {
			$this->thread->lastMessage->addParticipantsToReply($this->model);
		}else{

			$this->model->addAllDistributionsFromRequest();
		}
	}

	public function completed()
	{
		$this->model->addLinkedAttachments();

		if (request('is_draft')) {
			return;
		}

		$this->sendTheEmail();

		$this->model->thread->boxes()->delete();

		$this->model->thread->updateStats();

		Thread::pusherBroadcast();
	}

	protected function sendTheEmail()
	{
		$this->model->sendExternalEmail();
	}

	public function response()
	{
		return redirect($this->model->thread->getPreviewRoute());
	}

	public function render()
	{
		[$attachmentsLink, $attachmentsBox] = _FileUploadLinkAndBox('attachments', maxFilesSize: 20000);

		return _Rows(
			_PageTitle($this->thread?->subject ?: 'messaging-create-communication')->class('mb-6')
				->icon('annotation'),
			_Columns(
				_Rows(
			        _Rows(
				        $this->threadId ?

				        	new ThreadParticipations([
				                'thread_id' => $this->threadId
				            ]) :

					        _Rows(
					        	_RecipientsMultiSelect()->value($this->prefilledRecipient ? [$this->prefilledRecipient] : null),

							    _CcToggle(),

						        _Button('messaging-send-to-group')
						        	->class('justify-center text-sm vlBtn')
						        	->get('thread-groups')
						        	->inModal(),
					        )
					)->class('px-2')->class($this->boxClasses),
				)->col('col-md-4'),
				_Rows(
					_Panel(
						_Rows(
					        $this->threadId ? null : $this->subjectInput(),
						    _Panel(
						    	Message::editor(),
				        	)->id($this->editorPanelId)
				        	->class('email-ckeditor-delayed'),
					        _Flex2(
								$this->sendMessageButton(),
								$attachmentsLink,
								//Message::draftButton()->closeSlidingPanel(),
					        	showSignatureOptionsLink(),
					        	//TemplatesQuery::templatesModalLink($this->editorPanelId),
							)->class('mt-2'),
						),
						$attachmentsBox,
						getSignatureActionButtons($this->signaturePanelId)->class('px-2'),
					)->id('sliding-messages-form')->class('px-4')->class($this->boxClasses),

					$this->threadId ? $this->threadHistory() : null

				)->col('col-md-8'),
			),
		);
	}

	protected function sendMessageButton()
	{
		return Message::sendDropdown()->alert('messaging-message-sent')->closeSlidingPanel();
	}

	protected function subjectInput()
	{
		return _Input()->placeholder('messaging-subject')->name('subject');
	}

	protected function threadHistory()
	{
		return new ThreadMessages([
			'thread_id' => $this->threadId,
			'ignore_drafts' => true,
		]);
	}

	public function rules()
	{
		return array_merge(request('is_draft') ? [] : [
			'recipients' => $this->threadId ? '' : 'required_without:massive_recipients_group',
			'subject' => $this->threadId ? '' : 'required|max:1000',
			'html' => 'required_without:attachments',
		], [
			'attachments.*' => 'max:' . $this->maxFilesSize . '|mimes:' . implode(',', attachmentsValidTypes()),
			'attachments' => [new \Condoedge\Messaging\Rules\FilesTotalUploadSize($this->maxFilesSize), 'max:20'],
		]);
	}

}
