<?php

namespace Condoedge\Messaging\Kompo\CustomInbox;

use Kompo\Form;
use App\Models\Messaging\EmailAccount;

class EmailAccountInboxOption extends Form
{
	public $model = EmailAccount::class;

	public $id = 'email-account-inbox-option';
	public $class = 'vlInputWrapper py-2 px-4 mx-4 mt-2';

	protected $unreadCount;

	public function render()
	{
		$this->unreadCount = $this->model->recalculateUnreadCount();

		$unreadPill = !$this->unreadCount ? '' : ('<span class="rounded-full px-2 text-xs bg-danger text-white">'.$this->unreadCount.'</span> ');

		return _Html($unreadPill.$this->model->mainEmail());
	}
}
