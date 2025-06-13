<?php

namespace Condoedge\Messaging\Kompo\CustomInbox;

use App\Models\Messaging\Message;
use Kompo\Form;

class MessagePrint extends Form
{
	public $model = Message::class;

	public function render()
	{
		return _Div(
			_FlexBetween(
                $this->model->sender->recipientEmailWithLink(),
                _Html($this->model->created_at->format('Y-m-d H:i:s'))
            )->class('text-sm font-semibold text-gray-500 py-2'),
            _Div(
	            _Html($this->model->recipientsPrefixString())->class('inline'),
	            ...$this->model->recipients->map(
	                fn($recipient) => $recipient->recipientEmailWithLink()->class('text-gray-700')
	            )
	        )->class('space-x-2 mb-8')
	        ->class('text-gray-700 text-xs'),
			_Html($this->model->getHtmlToAppend())
                ->class('text-sm text-gray-700 mb-4')
                ->style('word-wrap: break-word'), //for long multiline urls for ex.
	    );
	}
}
