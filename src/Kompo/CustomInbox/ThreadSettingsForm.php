<?php

namespace Condoedge\Messaging\Kompo\CustomInbox;

use App\Models\Messaging\Thread;
use App\Models\Messaging\ThreadBox;
use Condoedge\Utils\Kompo\Common\Form;;

class ThreadSettingsForm extends Form
{
	public $model = Thread::class;

	public $class = 'bg-white w-full overflow-y-auto mini-scroll';
	public $style = 'max-width:300px;height: calc(100vh - 122px)';

	public function render()
	{
		if (!$this->model->id)
			return;

		return [
			_Link()->icon(_Sax('close-square',30))
				->class('text-2xl text-level1 absolute top-2 right-4')
				->closePanel()
				->post('disableThreadSettingsOpen'),
			_Rows(
	        	_MiniTitle('messaging-quick-actions')->class('mb-2'),
				_Columns(
					$this->model->isArchived ?

						$this->actionButton('messaging-archive-remove', 'archive-1', true)
							->selfPost('unarchiveThread')
							->inAlert()
							->refresh()
                            ->class('card-level4 text-level1') :

						$this->actionButton('messaging-archive-action', 'archive-1', true)
							->selfPost('archiveThread')
							->inAlert()
							->refresh()
                            ->class('card-level4 text-level1'),

					$this->model->isTrashed ?

						$this->actionButton('messaging-trash-remove', 'trash', true)
							->selfPost('untrashThread')
							->inAlert()
                            ->class('card-level4 text-level1') :

						$this->actionButton('messaging-trash-move-to', 'trash', true)
							->selfPost('trashThread')
							->inAlert()
                            ->class('card-level4 text-level1'),

					$this->actionButton('messaging-create-task', 'clipboard-tick')
						->selfGet('createTask')->inDrawer()->class('card-level4 text-level1'),
				),
			)->class('px-4 p-4'),
			_Rows(
				_MiniTitle('messaging-attachments')->class('mb-2'),
				new InboxAttachments([
					'thread_id' => $this->model->id,
				])
			)->class('px-4'),

		];
	}

	protected function actionButton($label, $icon, $withJsClick = false)
	{
		$button = _Rows(
			_BigButton($label, $icon),
		)->col('col-6');

		return !$withJsClick ? $button : $button->attr([
			'onClick' => 'document.getElementById("thread-mini-button-'.$icon.'-'.$this->model->id.'").click()'
		]);
	}

    public function archiveThread()
    {
        $this->model->updateBox(ThreadBox::BOX_ARCHIVE);
        return __('messaging-thread-archived');
    }

    public function trashThread()
    {
        $this->model->updateBox(ThreadBox::BOX_TRASH);
        return __('messaging-thread-trashed');
    }

    public function unarchiveThread()
    {
        optional($this->model->box)->delete();
        return __('messaging-thread-unarchived');
    }

    public function untrashThread()
    {
        optional($this->model->box)->delete();
        return __('messaging-thread-untrashed');
    }

    public function createTask()
    {
    	return new \Kompo\Tasks\Components\Tasks\TaskForm([
    		'thread_id' => $this->model->id,
    	]);
    }
}
