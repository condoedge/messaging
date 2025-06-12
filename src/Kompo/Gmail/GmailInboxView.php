<?php

namespace Condoedge\Messaging\Kompo\Gmail;

use Condoedge\Messaging\Services\GmailApi\GmailHelper;
use Kompo\Query;

class GmailInboxView extends Query
{
    public $class = 'bg-white';
    public $style = 'height: calc(100vh - 74px); margin-top: -2vh';
    public $containerClass = '';
    public $itemsWrapperClass = 'overflow-y-auto mini-scroll';

    protected $moreInboxFilters = 'more-inbox-filters'; //also used in js()

    public $paginationType = 'Scroll';
    public $perPage = 10;

    protected $displayedThreadId;
    protected $gClient;

	public function created()
	{
        $this->gClient = getGClient();
    }

    public function query()
    {
        $params = [
            'maxResults' => $this->perPage,            
        ];

        $page = request()->header('X-Kompo-Page') ?: 1;

        if (($page > 1) && session('nextPageToken')) {
            $params['pageToken'] = session('nextPageToken');
            session()->forget('nextPageToken');
        }

        $filteredBox = request('filters');

        $gFilters = [];

        if (($content = request('content')) && (strlen($content) >= 3) ) {

            $params['q'] = $content;


            $filteredBox = 6;

        }

        if(request('current_union_id')) {
            $gFilters[] = "singleValueExtendedProperties/Any(ep: ep/id eq 'String {66f5a359-4659-4830-9070-00040ec6ac6e} Name Union' and ep/value eq '".currentUnionId()."')";

            $filteredBox = 6;
        }

        if(request('unread_messages')) {
            $gFilters[] = "isRead eq false";
        }

        if(request('has_attachments')) {
            $gFilters[] = "hasAttachments eq true";
        }

        if (count($gFilters)) {
            $params['$filter'] =  collect($gFilters)->implode(' and ');
        }

        switch ($filteredBox) {
            case 2:
                $params['labelIds[]'] = 'SENT';
                break;

            case 3:
                $params['labelIds[]'] = '?? ARCHIVE TODO';
                break;

            case 4:
                $params['labelIds[]'] = 'TRASH';
                break;

            case 5:
                $params['labelIds[]'] = 'TRASH';
                break;

            case 6: //case searching...

                break;

            default:
                $params['labelIds'] = 'INBOX';
                break;
        }

        $threadList = $this->gClient->users_threads->listUsersThreads('me', $params);

        $gThreads = collect($threadList->getThreads());

        if ($thread = $gThreads->first()) {
            $this->displayedThreadId = $thread->getId();
        }

        //Hack for pagination to work with kompo
        for ($i=0; $i < ($page -1) * $this->perPage; $i++) { 
            $gThreads = $gThreads->prepend('add whatever for pagination');
        }

        if ($threadList->nextPageToken) {
            $gThreads = $gThreads->push('add whatever for pagination');

            session([
                'nextPageToken' => $threadList->nextPageToken,
            ]);
        }

        return $gThreads;
	}

    public function top()
    {
        return _Rows(
            /*_Select()->name('used_inbox', false)
                ->searchOptions(0, 'searchOutlookTokens', 'retrieveOutlookToken')->class('mb-0 pt-2 px-4')->class('noClear')
                ->value(getCurrentUserToken()?->id),*/
            _Rows(
                _CeButtonGroup()
                    ->name('filters', false)
                    ->options([
                        1 => $this->iconFilter('direct-inbox', 'Inbox'),
                        2 => $this->iconFilter('sms-tracking', 'sent-items'),
                        3 => $this->iconFilter('archive-1', 'Archive'),
                        4 => $this->iconFilter('trash', 'Trash'),
                        5 => $this->iconFilter('document-text', 'Draft', 'down-right'),
                    ])
                    ->default(1)
                    ->filter()
                    ->class('mb-0'),
                _NewEmailBtn()->class('sm:hidden')
                    ->href('new.outlook')->inNewTab()
                    ->class('mt-2'),
            )->class('px-4 py-2'),
            _FlexBetween(
                _Flex2(
                    _Link()->icon(_Sax('search-normal-1',18))
                        ->class(btnFilterClass())->class('block')
                        ->toggleClass('bg-info text-level1')
                        ->toggleId($this->moreInboxFilters)
                        ->run('focusSearchOnToggle'),
                    $this->htmlFieldFilter('Unread', 'unread_messages', 'sms', false)
                        ->selectedValue(1)
                        ->filter('NULL'),
                    $this->htmlFieldFilter('with-attachments', 'has_attachments', 'paperclip-2', false)
                        ->selectedValue(1)
                        ->filter('>='),
                    $this->htmlFieldFilter('current-union', 'current_union_id', 'building-4', false)
                        ->selectedValue(1)
                        ->filter(),
                )->class('py-2'),
                //Thread::flagLinkGroup()->class('py-2')->filter(),
            )->class('px-4 flex-wrap')->alignCenter(),
            _Rows(
                _Input('search-messages')->placeholder('messaging.min3-characters')->type('search')
                    ->name('content', false)
                    ->filter()->class('mb-0'),
                _GlobalUnionSelect()->filter(),
                _UnitSelect()->filter(),
                _TagsMultiSelect()->filter(),
            )->id($this->moreInboxFilters)
            ->class('pt-2 px-4 space-y-2')
        );
    }

    public function rightOnLoad()
    {
        return _Panel(
            new GmailThreadMessages(['gthread_id' => $this->displayedThreadId])
        )->id('inbox-message-panel')
        ->class('ml-0 h-full overflow-y-auto mini-scroll')
        ->class('sm:w-1/2vw md:w-2/3vw xl:w-3/5vw 2xl:w-2/3vw')
        ->closable();
    }

    public function render($thread, $key)
    {
        //if this becomes heavy, you will have to use a workaround with batches
        // https://github.com/googleapis/google-api-php-client/blob/main/examples/batch.php
        $optParamsGet2['format'] = 'full';
        $gThread = $this->gClient->users_threads->get('me', $thread->getId(), $optParamsGet2);
        $messages = $gThread->getMessages();
        $lastMessage = end($messages);

        $gMessage = GmailHelper::parseMessage($lastMessage);

    	$sender = explode('<', $gMessage->gFrom); //$sender[1] is email
    	$flag = ''; //TODO

        $isRead = 1; //TODO

        $hasAttachments = $gMessage->gAttachmentsCount;

        $isArchived = false;
        $isTrashed = false;

        return _Flex(
            _Html()->class('thread-color w-1 absolute inset-y-2 left-1 rounded')->class($flag),
            _Rows(
                _ImgFromText($sender[0])->class('h-8 w-8 rounded-full object-cover'),
            )->class('justify-between items-center h-full mr-2'),
            _Rows(
                _FlexBetween(
                    _Html($sender[0])
                        ->class('text-xs leading-5 font-medium text-level1 opacity-60 truncate'),
                    _Html($gMessage->gDate)
                        ->class('ml-2 text-xs text-level1 opacity-60 whitespace-nowrap')
                ),

                _Html($gMessage->gSubject)->class('thread-subject text-level2 text-sm truncate'),

                _Html($gMessage->getSnippet())->class('text-xs text-level2 truncate'),

                _FlexBetween(
                    _Flex(
                        !$hasAttachments ? null :
                            _Html()->class('text-sm')->icon(_Sax('paperclip-2', 16)),
                        _Html('&nbsp;'),
                    )->class('space-x-2'),
                    _FlexEnd(
                        /*Thread::flagLinkGroup('space-x-1', 'w-4 h-4')->selfPost('changeThreadFlagColor', [
                            'id' => $gMessage->getId(),
                        ])->run('() => {syncThreadFlagColor(this)}'),*/
                        $this->boxButton('archive-1', $isArchived ? 'unarchiveThread' : 'archiveThread', $gMessage, $key),
                        $this->boxButton('trash', $isTrashed ? 'untrashThread' : 'trashThread', $gMessage, $key),
                        $this->unreadButton($gMessage, false),
                        $this->readButton($gMessage, false),
                    )->class('inbox-message-buttons space-x-2 hidden group-hover:flex')
                )->class('text-level3')
            )->class('min-w-0 flex-1'),
            /*_FlexEnd(
                $this->boxButton('archive-1', $isArchive ? 'unarchiveThread' : 'archiveThread', $message),
                $this->boxButton('trash', $isTrash ? 'untrashThread' : 'trashThread', $message),
                $this->unreadButton($message),
                $this->readButton($message),
            )->class('inbox-mobile-buttons self-stretch')->alignStretch(),*/
        )->class(
            'inbox-thread rounded-xl mx-2 md:mx-4 my-2 relative px-4 py-2 hover:bg-gray-200 cursor-pointer group'
        )
        ->alignStart()
        ->class('inbox-message')
        ->class($isRead ? 'read bg-gray-100' : 'bg-gray-300')
        ->selfGet('getGmailMessageView', [
            'gthread_id' => $gThread->getId(),
            'gthread_uniqid' => $gThread->internal_uniqid,
        ])
        ->onSuccess(function($e) use ($gThread) {
            $this->inPanelUpdateHistory($e, $gThread->getId());
            $e->addClass('read');
            $e->activate();
        });
    }

    public function getGmailMessageView($gId, $uniqId)
    {
        return new GmailThreadMessages([
            'gthread_id' => $gId,
            'gthread_uniqid' => $uniqId,
        ]);
    }

    public function searchOutlookTokens()
    {
        return auth()->user()->outlookTokens->mapWithKeys(fn($outlookToken) => [
            $outlookToken->id => _Link($outlookToken->user_email)->href('change-outlook-token', ['id' => $outlookToken->id]),
        ])->push(_Link('Connect new mailbox')->href('reset-outlook-token'));
    }

    public function retrieveOutlookToken($id)
    {
        $unreadCount = GraphHelper::getUnreadCount();

        if ($outlookToken = OutlookToken::find($id)) {
            return [
                $outlookToken->id => $outlookToken->user_email.' ('.$unreadCount.')',
            ];
        }    
    }

    public function archiveThread($gMessageId, $nextMessageId, $nextUniqId)
    {
        GraphHelper::archiveMessage($gMessageId);
        return $this->postMoveDisplay($nextMessageId, $nextUniqId);
    }

    public function trashThread($gMessageId, $nextMessageId, $nextUniqId)
    {
        GraphHelper::trashMessage($gMessageId);
        return $this->postMoveDisplay($nextMessageId, $nextUniqId);
    }

    public function unarchiveThread($gMessageId, $nextMessageId, $nextUniqId)
    {
        GraphHelper::unarchiveMessage($gMessageId);
        return $this->postMoveDisplay($nextMessageId, $nextUniqId);
    }

    public function untrashThread($gMessageId, $nextMessageId, $nextUniqId)
    {
        GraphHelper::untrashMessage($gMessageId);
        return $this->postMoveDisplay($nextMessageId, $nextUniqId);
    }

    protected function postMoveDisplay($nextMessageId, $nextUniqId)
    {
        if (!$nextMessageId || !$nextUniqId) {
            return;
        }

        return $this->getOutlookMessageView($nextMessageId, $nextUniqId);
    }

    protected function boxButton($icon, $method, $gMessage, $key = false)
    {
        $nextMessage = $this->getNextMessage($key);

        $link = _Link()->icon(_Sax($icon,16))->class('text-level3')
            ->balloon(ucfirst(str_replace('Message', '', $method)), 'up-right')
            ->id('thread-mini-button-'.$icon.'-'.$gMessage->internal_uniqid)
            ->selfPost($method, [
                'id' => $gMessage->getId(),
                'nextId' => $nextMessage?->getId(),
                'nextUniqId' => $nextMessage?->internal_uniqid,
            ]);

        if ($key === false) { //mobile button
            return $link->removeSelf()->class($this->mobileButtonClass);
        }

        if (!$nextMessage) {
            return $link->removeSelf();
        }

        return $link->onSuccess(function($e) use($nextMessage) {
                $this->inPanelUpdateHistory($e, $nextMessage->getId());
                $e->removeSelf();
            });
    }

    protected function getNextMessage($currentKey)
    {
        if ($currentKey === false) {
            return null;
        }

        $nextMessage = $this->query->getCollection()[$currentKey + 1] ?? $this->query->getCollection()[$currentKey - 1] ?? null;

        return $nextMessage;
    }

    protected function inPanelUpdateHistory($e, $gThread)
    {
        $e->inPanel('inbox-message-panel');
        $e->setHistory('gmail-inbox', [
            'gthread_id' => $gThread
        ]);
    }

    public function readMessage($gMessageId)
    {
        GraphHelper::markMessageAsRead($gMessageId);
    }

    public function unreadMessage($gMessageId)
    {
        GraphHelper::markMessageAsUnread($gMessageId);
    }

    protected function unreadButton($thread, $mobile = true)
    {
        $link = $this->readUnreadButton('messaging.mark-unread', 'sms', 'unreadMessage', $thread)->class('unreadButton');

        return $mobile ? $link->class($this->mobileButtonClass) : $link;
    }

    protected function readButton($thread, $mobile = true)
    {
        $link = $this->readUnreadButton('messaging.mark-read', 'directbox-notif', 'readMessage', $thread)->class('readButton');

        return $mobile ? $link->class($this->mobileButtonClass) : $link;
    }

    protected function readUnreadButton($label, $icon, $method, $gMessage)
    {
        return _Link()->icon(_Sax($icon,18))->class('text-level3')
            ->balloon($label, 'up-right')
            ->selfPost($method, [
                'id' => $gMessage->getId(),
            ])->attr([
                'onClick' => 'message'.$method.'(this)'
            ]);
    }

    protected function iconFilter($icon, $label, $placement = 'down-left')
    {
        return _Sax($icon)->class('flex-center py-1 px-2 rounded-full cursor-pointer')
            ->balloon($label, $placement);
    }

    protected function htmlFieldFilter($label, $name, $icon, $relatedToModel = true)
    {
        return _HtmlFieldFilter()->name($name, $relatedToModel)->icon(_Sax($icon,18))
            ->balloon($label, 'up-right');
    }

    public function js()
    {
        return <<<javascript

function focusSearchOnToggle()
{
    if ($('#$this->moreInboxFilters')[0].style.display == 'none'){
        return;
    }

    let input = $('#$this->id input[name=content]')

    input.focus()
}

function messagereadMessage(that)
{
    getClosestInboxMessage(that).addClass('read')
}

function messageunreadMessage(that)
{
    getClosestInboxMessage(that).removeClass('read')
}

function getClosestInboxMessage(that)
{
    return $(that).closest('.inbox-message').eq(0)
}

javascript;
    }
}