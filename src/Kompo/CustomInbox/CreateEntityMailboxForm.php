<?php

namespace Condoedge\Messaging\Kompo\CustomInbox;

use Kompo\Form;
use App\Models\Messaging\EmailAccount;

class CreateEntityMailboxForm extends Form
{
	public $model = EmailAccount::class;

	protected $entityId;
	protected $entityType;
	protected $entity;

	public function created()
	{
		$this->entityId = $this->prop('entity_id');
		$this->entityType = $this->prop('entity_type');
		$this->entity = findOrFailMorphModel($this->entityId, $this->entityType);
	}

	public function beforeSave()
	{
		if (!$this->entity->isAcceptableMailbox(request('email_adr'))) {
			throwValidationError('email_adr', __('This mailbox is already taken. Please pick another one'));
		}

		$this->model->entity_id = $this->entityId;
		$this->model->entity_type = $this->entityType;
		$this->model->is_mailbox = 1;
		$this->model->email_adr = getMailboxEmail(request('email_adr'));
	}

	public function render()
	{
		return _Rows(
			_Flex2(
				_Input()->name('email_adr', false)->value(removeMailbox($this->model->email_adr)),
				_Input()->name('readonly', false)->value(getMailboxHost()),
			),
			_FlexEnd(
				_SubmitButton(),
			),
		)->class('p-6');
	}

	public function rules()
	{
		return [
			'email_adr' => 'required|min:3',
		];
	}
}
