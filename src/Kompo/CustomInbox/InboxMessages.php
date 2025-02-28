<?php

namespace Condoedge\Messaging\Kompo\CustomInbox;

use App\Models\User;

class InboxMessages extends ThreadMessages
{
    public $itemsWrapperClass = 'overflow-y-auto mini-scroll inbox-messages-wrapper';
    //public $itemsWrapperStyle = 'height: calc(100vh - 393px)';
    public $class = 'h-full';
    public $itemsWrapperStyle = '';

    public $topPagination = true;
    public $bottomPagination = false;

    public $messageFormPanelId = 'inbox-messages-form';

    public function booted()
    {
        \Cache::put('lastViewedThread'.auth()->id(), $this->threadId, 3600);

        $this->activateScroll('#message-card-', '.inbox-messages-wrapper');
    }

    public function top()
    {
        $newThreadKomponent = _FlexEnd(
            _NewEmailBtn()
                ->href('new.thread')->inNewTab()
                ->warnBeforeClose('messaging-are-you-sure-you-want-to-close'),
        )->class('bg-gray-100');

        if(!$this->thread)
            return $newThreadKomponent->class('px-2 py-2');

        $iconClass = 'ml-4 text-gray-500 whitespace-nowrap';

        $attachmentsCount = $this->thread->attachments()->count();

        return _Rows(
            _FlexBetween(
                _FlexEnd(
                    $newThreadKomponent,
                    !$attachmentsCount ? null :
                        _Link($attachmentsCount)->icon(_Sax('paperclip-2'))
                            ->get('thread-settings', ['id' => $this->threadId])
                            ->inPanel('inbox-messages-settings')
                            ->class($iconClass),
                    _Link()->icon(_Sax('setting-2'))->class('text-level1')
                        ->get('thread-settings', ['id' => $this->threadId])
                        ->inPanel('inbox-messages-settings')
                        ->class($iconClass)
                        ->post('enableThreadSettingsOpen'),
                )->class('space-x-2 mr-8 sm:mr-0')
            )->class('px-2 py-2'),
            _FlexBetween(
                _Html($this->thread->subject)->class('font-semibold'),
                $this->thread->is_trashed ?
                    $this->actionButton('messaging-trash-remove', 'trash')
                        ->selfPost('untrashThread')
                        ->inAlert() :

                    $this->actionButton('messaging-trash-move-to', 'trash')
                        ->selfPost('trashThread')
                        ->inAlert()

            )->class('px-4 py-2 m-2 border border-gray-200 rounded-2xl bg-white')
        );
    }

    public function right()
    {
        return _Panel(
            (threadSettingsOpen() && $this->threadId) ?
                new ThreadSettingsForm($this->threadId) :
                null
        )->id('inbox-messages-settings')
        ->class('border-l border-gray-200 ml-0');
    }

    public function bottom()
    {
        return _Panel()->id($this->messageFormPanelId);
    }

    public function trashThread()
    {
        $this->thread->updateBox(2);
        return __('messaging-thread-trashed');
    }

    public function untrashThread()
    {
        optional($this->thread->box)->delete();
        return __('messaging-thread-untrashed');
    }

    protected function actionButton($label, $icon)
    {
        return _Link()->icon(_Sax($icon,20))->class('text-level1 opacity-60 ml-4')->balloon($label, 'down-right')->attr([
            'onClick' => 'document.getElementById("thread-mini-button-'.$icon.'-'.$this->threadId.'").click()'
        ]);
    }
}
