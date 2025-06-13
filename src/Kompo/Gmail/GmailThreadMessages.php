<?php

namespace Condoedge\Messaging\Kompo\Gmail;

use Condoedge\Messaging\Services\GmailApi\GmailHelper;
use App\Models\User;
use Kompo\Query;

class GmailThreadMessages extends Query
{
    public $noItemsFound = 'messaging.no-messages';

    public $itemsWrapperClass = 'overflow-y-auto mini-scroll';

    public $paginationType = 'Scroll';
    public $perPage = 10;

    public $messageFormPanelId = 'sliding-messages-form';

    public function created()
    {
        $this->gThreadId = $this->prop('gthread_id');
        $this->gThreadUniqId = $this->prop('gthread_uniqid');

        $this->gThread = GmailHelper::requestMessage($this->gThreadId, true);

        //dd($this->gThread);

        $this->isArchived = false; //TODO
        $this->isTrashed = false; //TODO

        $this->id('inbox-message-'.$this->gThreadId);
    }

    public function query()
    {
        if (!$this->gThread) {
            return;
        }

        $messages = $this->gThread->getMessages();

        return $messages;
    }

    public function top()
    {
        $newThreadKomponent = _FlexEnd(
            _NewEmailBtn()
                ->href('new.thread')->inNewTab()
                ->warnBeforeClose('messaging.are-you-sure-you-want-to-close'),
        )->class('bg-gray-100');

        if(!$this->gThread)
            return $newThreadKomponent->class('px-2 py-2');

        $iconClass = 'ml-4 text-gray-500 whitespace-nowrap';

        $attachmentsCount = $this->gThread->gAttachmentsCount;

        return _Rows(
            _FlexBetween(
                _FlexEnd(
                    $newThreadKomponent,
                    !$attachmentsCount ? null :
                        _Link($attachmentsCount)->icon(_Sax('paperclip-2'))
                            ->selfGet('getSettingsForm')
                            ->inPanel('inbox-messages-settings')
                            ->class($iconClass),
                    _Link()->icon(_Sax('setting-2'))->class('text-level1')
                        ->selfGet('getSettingsForm')
                        ->inPanel('inbox-messages-settings')
                        ->class($iconClass)
                        ->post('user-settings', [
                            'key' => User::$threadSetting,
                            'value' => 1,
                        ]),
                )->class('space-x-2 mr-8 sm:mr-0')
            )->class('px-2 py-2'),
            _Html($this->gThread->gSubject)->class('font-semibold px-4 py-2 mb-2 border border-gray-200 rounded-2xl bg-white'),
        );
    }

    public function render($message, $key)
    {
        if ($message->is_draft && $this->ignoreDrafts) {
            return;
        }

        $messageRead = false;

        $messageCollapsed = ($key !== 0 && $messageRead);

        $messageCard = _Rows(

            _Rows(
                _Flex(
                    _Flex2(
                        _ImgFromText($message->gFrom)->class('h-6 w-6')->class('rounded-full object-cover border')->style('margin-top:2px'),
                        _Html($message->gFrom),
                        $messageRead ? null : _Html('New')->class('text-xs text-danger'),
                    )->class('shrink-0'),
                    _Rows(
                        _FlexBetween(
                            $message->is_draft ?

                                _Html('Draft')->class('text-sm font-semibold text-danger') :

                                _Flex(
                                    _Flex(
                                        _Html($message->sender)->class('text-sm font-semibold text-level1 mr-2'),
                                        _Html($message->created_at)
                                            ->class('text-xs text-level1 opacity-70 whitespace-nowrap'),
                                    )->class('flex-wrap'),

                                )->class('space-x-2 py-1')->alignStart(),

                            auth()->user()->isContact() ? null :

                                $this->messageActions($message)->class('absolute top-0 right-0 bg-white pl-4')

                        )->class('relative'),
                        auth()->user()->isMereContact() ? null :

                            $this->recipientsEmails($message)
                    )->class('flex-auto'),
                )->class('space-x-2')
                ->alignStart(),
            )->class('mb-2'),

            _Rows(
                _Html($message->snippet.'...')
                    ->class('text-xs text-level1 truncate')
            )->class('message-summary -mt-1 mb-4 hidden'),

            _Html($message->gBody)
                ->class('ck-displayed text-sm text-level1 my-4 overflow-x-auto md:pl-8')
                ->style('word-wrap: break-word'), //for long multiline urls for ex.

            _GmailMessageAttachments($message),

        )->class('p-4 m-2 border border-gray-200 rounded-2xl bg-white group')
        ->class($messageRead ? '' : 'border-l-2 border-gray-300')
        ->class($messageCollapsed ? 'messageCollapsed' : '')
        ->onClick->removeClass('messageCollapsed')
        ->id('message-card-'.$message->id);

        return $messageCard;
    }

    public function right()
    {
        return _Panel(
            (auth()->user()->threadSettingsOpen() && $this->gThreadId) ?
                $this->getSettingsForm() :
                null
        )->id('inbox-messages-settings')
        ->class('border-l border-gray-200 ml-0');
    }

    public function bottom()
    {
        return _Panel()->id($this->messageFormPanelId);
    }

    public function getSettingsForm()
    {
        $tagsArr = [];

        return _Rows(
            _FlexEnd(
                _Link()->icon(_Sax('close-square',30))
                    ->class('text-2xl text-level1 mb-4')
                    ->selfGet('removeMessageSettings')
                    ->onSuccess(
                        fn($e) => $e->inPanel('inbox-messages-settings')
                    )
                    ->post('user-settings', [
                        'key' => User::$threadSetting,
                        'value' => 0,
                    ]),
            ),
            _GlobalUnionSelect()->class('mb-2')->default($this->gThread->mgh_union_id)
                ->selfPost('setUnionId')->inAlert(),
            _UnitSelect()->class('mb-2')->default($this->gThread->mgh_unit_id)
                ->selfPost('setUnitId')->inAlert(),
            _TagsMultiSelect()->class('mb-0')->default($tagsArr)
                ->selfPost('setTags')->inAlert(),
            _Rows(
                _MiniTitle('quick-actions')->class('mb-2'),
                _Columns(
                    $this->isArchived ?

                        $this->actionButton('messaging.archive-remove', 'archive-1', true)
                            ->class('card-level4 text-level1') :

                        $this->actionButton('messaging.archive', 'archive-1', true)
                            ->class('card-level4 text-level1'),

                    $this->isTrashed ?

                        $this->actionButton('messaging.trash-remove', 'trash', true)
                            ->class('card-level4 text-level1') :

                        $this->actionButton('messaging.trash-move-to', 'trash', true)
                            ->class('card-level4 text-level1'),

                    $this->actionButton('messaging.create-task', 'clipboard-tick')
                        ->selfGet('createTask')->inDrawer()->class('card-level4 text-level1'),
                ),
            )->class('px-4 p-4'),
            _Rows(
                _MiniTitle('Attachments')->class('mb-2'),
                _Panel(
                    
                )->id('attachments-panel-in-settings'),
            )->class('px-4'),
        )->style('w-full');
    }

    protected function recipientsEmails($message)
    {
        return _Div(
            _Html($message->recipientsSting)->class('inline'),
        )->class('space-x-2')
        ->class('message-recipients text-level1 text-xs');
    }

    protected function messageActions($message)
    {
        $printBtn = $this->dropdownActionLink('Print')->icon(_Sax('printer',20))
            ->href('message-print', ['id' => $message->id])
            ->inNewTab();

        return $message->is_draft ?

            _FlexEnd(
                $this->messageActionLink('Edit')->icon('document-text')
                    ->get('message-draft.form', ['id' => $message->id])
                    ->inPanel($this->messageFormPanelId)
            ) :

            _FlexEnd(
                $this->messageActionLink('Reply')->icon('reply')
                    ->get('message-reply.form', ['parent_id' => $message->id])
                    ->outlined()
                    ->inPanel($this->messageFormPanelId),
                _Dropdown()->icon(_Svg('dots-vertical')->class('text-xl text-gray-700'))
                    ->submenu(
                        $this->dropdownActionLink('Forward')->icon(_Sax('sms-tracking',20))
                            ->get('message-forward.form', ['parent_id' => $message->id])
                            ->inPanel($this->messageFormPanelId)
                            ->class('!pt-6'),
                        $this->dropdownActionLink('mail.reply-all')->icon(_Sax('sms-edit',20))
                            ->get('message-reply-all.form', ['parent_id' => $message->id])
                            ->inPanel($this->messageFormPanelId),
                        _Rows(
                            $printBtn
                        )->class('!pb-4'),
                    )->alignRight(),
            )->class('text-sm space-x-2 sm:hidden group-hover:flex font-semibold');
    }

    public function removeMessageSettings()
    {
    }


    protected function messageActionLink($label)
    {
        return _Link($label)->class('whitespace-nowrap hover:text-level3');
    }

    protected function dropdownActionLink($label)
    {
        return $this->messageActionLink($label)->class('px-4 py-2');
    }

    protected function actionButton($label, $icon, $withJsClick = false)
    {
        $button = _Rows(
            _BigButton($label, $icon),
        )->col('col-6');

        return !$withJsClick ? $button : $button->attr([
            'onClick' => 'document.getElementById("thread-mini-button-'.$icon.'-'.$this->gThreadUniqId.'").click()'
        ]);
    }
}
