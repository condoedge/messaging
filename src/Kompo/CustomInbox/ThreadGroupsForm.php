<?php

namespace Condoedge\Messaging\Kompo\CustomInbox;

use App\Models\Condo\Union;
use App\Models\Messaging\Message;
use App\Models\User;
use App\View\Messaging\RecipientsMultiSelect;
use Kompo\Modal;

class ThreadGroupsForm extends Modal
{
	protected $_Title = 'messaging.select-group';
	protected $_Icon = 'user-group';

	public $class = 'max-w-2xl overflow-y-auto mini-scroll';
	public $style = 'max-height:95vh';

	public function body()
	{
		return [
			_Columns(
				_Div(
					$this->mainSelectEl(),
					_Html('send-to')->class('vlFormLabel'),
					_Panel(
						$this->secondaryButtonGroupEl(),
					)->id('secondary-button-group-panel')
				)->col('col-sm-6'),
				_Div(
					_Panel(
						_Dashedbox('messaging.matching-recipients-show')->class('px-4')
					)->id('group-recipients')
				)->col('col-sm-6'),
			)
		];
	}

	protected function mainSelectEl()
	{
		return _Select('current-union')
			->name('union_id')
			->options(currentTeam()->unionOptions())
			->default(currentUnion()->id)
			->selfPost('switchUnion')
			->onSuccess(function($e){
				$e->inAlert();
			});
	}

	protected function secondaryButtonGroupEl()
	{
		return _CeButtonGroup()->name('group')
			->optionsFromField('union_id', 'sendOptions')
			->vertical()
			->selfGet('showRecipientConfirmation')
			->inPanel('group-recipients', true);
	}

	public function showRecipientConfirmation($group)
	{
		$recipients = static::getMatchingRecipients($group);
		$recipientRows = _Rows(
			$recipients->map(
				fn($r) => _Html($r->email)->class('p-2 border-b border-gray-100')
			)
		);

		return _Rows(
            !$group ? null : _Button('Confirm')->class('my-4')
				->selfGet('getRecipientsMultiselect', [
					'group' => $group,
				])
				->inPanel('new-thread-recipients', true)
				->closeModal(),
			$recipientRows->class('overflow-y-auto mini-scroll')
			->style('max-height:400px'),

			_Html('validation.you-may-still-remove-recipient')
				->icon('icon-question-circle')
				->class('text-gray-700 text-xs text-center mt-2')
		);
	}

	public function getRecipientsMultiselect($group)
	{
		$recipients = static::getMatchingRecipients($group);
		return _RecipientsMultiSelect()
			->options($recipients->pluck('name', 'email'))
			->default($recipients->pluck('email'));
	}

	public static function getMatchingRecipients($group)
	{
		$recipients = collect();

		if ($group == 'team') {
			$recipients = currentTeam()->nonContactUsers();
		}

		if ($group == 'board') {
			$recipients = currentUnion()->currentBoard;

			if (currentUnion()->emailAccount) {
				$recipients = $recipients->prepend(currentUnion()->emailAccount);
			}
		}

		if($group == 'owners')
			$recipients = currentUnion()->owners();

		if($group == 'contacts')
			$recipients = currentUnion()->relatedContacts();

		if($group == 'occupants')
			$recipients = currentUnion()->ownerOccupants();

		if($group == 'renters')
			$recipients = currentUnion()->renters();

		if($group == 'all-unions-owners')
			$recipients = currentTeam()->unions->flatMap(fn($union) => $union->owners());

		return $recipients->reject(
			fn($r) => ($r instanceOf User && $r->id == auth()->user()->id)
		);
	}

	public function sendOptions($mainSelectValue = null)
	{
		if (!$mainSelectValue) {
			return;
		}

		$defaultOptions = collect([
			'team' => ['messaging.my-team', 'messaging.my-team-text'],
			'board' => ['messaging.boardmembers', 'messaging.boardmembers-text'],
			'owners' => ['messaging.all-owners', 'messaging.all-owners-text'],
			'occupants' => ['messaging.all-occupants', 'messaging.all-occupants-text'],
			'contacts' => ['messaging.all-contacts', 'messaging.all-contacts-text'],
			'renters' => ['messaging.all-renters', 'messaging.all-renters-text'],
		])->mapWithKeys(
			fn($label, $key) => [
				$key => static::groupOptions($label[0], $label[1]),
			]
		);

		if (auth()->user()->isManager()) {
			$defaultOptions = $defaultOptions->union([
				'all-unions-owners' => static::groupOptions('condo.all-unions-owners', 'condo.all-unions-owners-sub1')->class('bg-red-700 text-white')
			]);
		}

		return $defaultOptions;
	}

	protected static function groupOptions($label, $description)
	{
		return _Rows(
			_Html($label)->class('font-semibold'),
			_Html($description)->class('text-xs italic')
		)->class('p-2 mb-2 cursor-pointer');
	}

	public function rules()
	{
		return [

		];
	}
}
