<?php

namespace Condoedge\Messaging\Kompo\CustomInbox;

use App\Models\Messaging\Thread;
use Condoedge\Utils\Kompo\Common\Query;

class ThreadMessages extends Query
{
    use \Kompo\Komponents\Traits\ScrollToOnLoadTrait;

    public $noItemsFound = 'messaging-no-messages';

    public $itemsWrapperClass = 'overflow-y-auto mini-scroll';

    public $paginationType = 'Scroll';
    public $perPage = 10;

    protected $thread;
    protected $threadId;

    public $messageFormPanelId = 'sliding-messages-form';

    protected $ignoreDrafts;

    public function created()
    {
        $this->isFromTask = request()->route()->getName() == 'inbox.signed';

        $this->threadId = $this->parameter('id') ?: $this->store('thread_id');
        $this->thread = Thread::find($this->threadId);

        $this->id('inbox-message-'.$this->threadId);

        $this->pusherRefresh = Thread::pusherRefresh();



        if (!auth()->user() || ($this->thread && !$this->thread->orderedMessages()->authUserIncluded()->count())) {
            abort(403);
        }

        $this->ignoreDrafts = $this->store('ignore_drafts');
    }

    public function query()
    {
        if (!$this->thread) {
            return;
        }

        $messages = $this->thread->orderedMessages();

        return $messages->authUserIncluded();
    }

    public function render($message, $key)
    {
        if ($message->is_draft && $this->ignoreDrafts) {
            return;
        }

        $messageRead = $message->read()->first();

        $messageCollapsed = ($key !== 0 && $messageRead);

        $messageCard = _Rows(

            _Rows(
                _Flex(
                    _Rows(
                        _ProfileImg($message->sender, 'h-6 w-6')?->style('margin-top:2px'),
                        $messageRead ? null : _Html('messaging-new')->class('text-xs text-danger'),
                    )->class('shrink-0'),
                    _Rows(
                        _FlexBetween(
                            $message->is_draft ?

                                _Html('messaging-draft')->class('text-sm font-semibold text-danger') :

                                _Flex(
                                    _Flex(
                                        $message->sender->recipientEmailWithLink()
                                            ->class('text-sm font-semibold text-level1 mr-2'),
                                        _Html($message->created_at->format('d M Y H:i'))
                                            ->class('text-xs text-level1 opacity-70 whitespace-nowrap'),
                                    )->class('flex-wrap'),

                                )->class('space-x-2 py-1')->alignStart(),

                            $this->messageActions($message)->class('absolute top-0 right-0 bg-white pl-4')

                        )->class('relative'),
                        $this->recipientsEmails($message)
                    )->class('flex-auto'),
                )->class('space-x-2')
                ->alignStart(),
            )->class('mb-2'),

            _Rows(
                _Html($message->summary.'...')
                    ->class('text-xs text-level1 truncate')
            )->class('message-summary -mt-1 mb-4 hidden'),

            _Html($message->getHtmlToDisplay())
                ->class('ck-displayed text-sm text-level1 my-4 overflow-x-auto md:pl-8')
                ->style('word-wrap: break-word'), //for long multiline urls for ex.

            !$message->attachments->count() ? null :

                _Flex(
                    $message->attachments->map(function($attachment){
                        return $attachment->fileThumbnail('main', 'preview', 'download');
                    })
                )->class('flex-wrap mt-2')
                ->class('message-attachments'),


        )->class('p-4 m-2 border border-gray-200 rounded-2xl bg-white group')
        ->class($messageRead ? '' : 'border-l-2 border-gray-300')
        ->class($messageCollapsed ? 'messageCollapsed' : '')
        ->onClick->removeClass('messageCollapsed')
        ->id('message-card-'.$message->id);

        if (!$messageRead) {
            $this->scrollToId = $this->scrollToId ?: $message->id;
            $message->markRead();
        }

        return $messageCard;
    }

    protected function recipientsEmails($message)
    {
        return _Div(
            _Html($message->recipientsPrefixString())->class('inline'),
            ...$message->recipients->map(
                fn($recipient) => $recipient->recipientEmailWithLink()->class('text-level1 opacity-70')
            )
        )->class('space-x-2')
        ->class('message-recipients text-level1 text-xs');
    }

    protected function messageActions($message)
    {
        $printBtn = $this->dropdownActionLink('messaging-print')->icon(_Sax('printer',20))
            ->href('message-print', ['id' => $message->id])
            ->inNewTab();

        if ($this->isFromTask) {
            return $printBtn;
        }

        return $message->is_draft ?

            _FlexEnd(
                $this->messageActionLink('messaging-edit')->icon('document-text')
                    ->get('message-draft.form', ['id' => $message->id])
                    ->inPanel($this->messageFormPanelId)
            ) :

            _FlexEnd(
                $this->messageActionLink('messaging-reply')->icon('reply')
                    ->get('message-reply.form', ['parent_id' => $message->id])
                    ->outlined()
                    ->inPanel($this->messageFormPanelId),
                _Dropdown()->icon(_Svg('dots-vertical')->class('text-xl text-gray-700'))
                    ->submenu(
                        $this->dropdownActionLink('messaging-forward')->icon(_Sax('sms-tracking',20))
                            ->get('message-forward.form', ['parent_id' => $message->id])
                            ->inPanel($this->messageFormPanelId)
                            ->class('!pt-6'),
                        $this->dropdownActionLink('messaging-reply-all')->icon(_Sax('sms-edit',20))
                            ->get('message-reply-all.form', ['parent_id' => $message->id])
                            ->inPanel($this->messageFormPanelId),
                        _Rows(
                            $printBtn
                        )->class('!pb-4'),
                    )->alignRight(),
            )->class('text-sm space-x-2 sm:hidden group-hover:flex font-semibold');
    }


    protected function messageActionLink($label)
    {
        return _Link($label)->class('whitespace-nowrap hover:text-level3');
    }

    protected function dropdownActionLink($label)
    {
        return $this->messageActionLink($label)->class('px-4 py-2');
    }

}
