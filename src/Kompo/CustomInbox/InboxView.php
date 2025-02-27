<?php

namespace Condoedge\Messaging\Kompo\CustomInbox;

use App\Models\Messaging\EmailAccount;
use App\Models\Messaging\Thread;
use Kompo\Query;

class InboxView extends Query
{
    public $perPage = 40;
    public $noItemsFound = 'messaging.no-communications';

    public $id = 'inbox-view'; //also used in js()
    protected $moreInboxFilters = 'more-inbox-filters'; //also used in js()
    public $class = 'bg-white';
    public $style = 'height: calc(100vh - 122px); margin-top: -2vh';
    public $containerClass = '';

    //public $activeClass = 'bg-level4 bg-opacity-50 shadow-lg';
    public $activeClass = 'md:scale-x-105 active-thread';
    public $activeIndex = 0;

    protected $mobileButtonClass = 'text-3xl pl-4 ml-4 border-l border-gray-200 rounded-none';

    public $itemsWrapperClass = 'overflow-y-auto mini-scroll';

    public $paginationType = 'Scroll';

    protected $threadId;

    protected $isArchive = false;
    protected $isTrash = false;

    public function created()
    {
        $this->pusherRefresh = Thread::pusherRefresh();

        $this->threadId = $this->parameter('thread_id');

        $this->activeIndex = $this->threadId ? null : 0;

        $this->onLoad(fn($e) => $e->run('activateSwipe'));
    }

    public function query()
    {
        $q = Thread::query();

        $filteredBox = request('filters');

        $cutoffDate = currentTeam()?->created_at;

        if (($content = request('content')) && (strlen($content) >= 3) ) {

            $matchedAccountIds = currentTeam()->getMatchedRecipients($content)->flatMap(
                fn($entity) => $entity instanceOf EmailAccount ? [$entity->id] : $entity->emailAccounts()->pluck('id')
            );
            
            $content = preg_replace('/[^\p{L}0-9\ ]/u', ' ', $content); //MATCH with special characters causes a MYSQL error
            $content = str_replace('  ', ' ', $content);
            $content = str_replace('   ', ' ', $content);
            $content = trim($content);
            //dd($content);
            $fullTextContent = collect(explode(' ', $content))->map(fn($word) => '+'.$word.'*')->implode(' ');

            $q = $q->where(
                fn($q1) => $q1->whereHas('messages',
                    fn($q2) => $q2
                        ->whereRaw("MATCH (`text`, `subject`) AGAINST  (? IN BOOLEAN MODE)", [$fullTextContent])
                        //->where('text', 'LIKE', '%'.$content.'%')
                        //->orWhere('subject', 'LIKE', '%'.$content.'%')
                        ->orWhereHas('distributions', fn($q3) => $q3->whereIn('email_account_id', $matchedAccountIds))
                        ->orWhereIn('sender_id', $matchedAccountIds)
                )
            );

            $filteredBox = 6;

        } else {
            $page = request()->header('X-Kompo-Page');
            if (!$page || $page < 4) { //Also page cutoff should be dynamic
                $cutoffDate = carbon(now())->addMonths(-6);
            }
        }

        $q = $q->where('created_at', '>', $cutoffDate);

        if($currentUnionId = request('current_union_id')) {
            $q = $q->where('union_id', currentUnion()->id);
        }

        switch ($filteredBox) {
            case 2:
                $q = $q->notInTrashBox()->whereHas('messages', function($q) {
                    $q->authUserAsSender()->isNotDraft();
                });
                break;

            case 3:
                $q = $q->forAuthUser()->isArchived();
                $this->isArchive = true;
                break;

            case 4:
                $q = $q->forAuthUser()->isTrashed();
                $this->isTrash = true;
                break;

            case 5:
                $q = $q->forAuthUser()->hasDrafts()->notAssociatedToAnyBox();
                break;

            case 6: //case searching...
                $q = $q->forAuthUser();
                $this->isArchive = null;
                $this->isTrash = null;
                break;

            default:
                $q = $q->notAssociatedToAnyBox()->whereHas('messages', fn($q) => $q->authUserInDistributions());
                break;
        }

        return $q->orderByDesc('last_message_at');
    }

    public function top()
    {
        return _Rows(
            _Select()->name('used_inbox', false)
                ->searchOptions(0, 'searchInboxes', 'retrieveInbox')->class('mb-0 pt-2 px-4')->class('noClear')
                ->value(currentMailboxId())
                ->selfPost('impersonateMailbox')->redirect('inbox'),
            _Rows(
                _ButtonGroup()
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
                    ->href('new.thread')
                    ->inNewTab()->class('mt-2'),
            )->class('px-4 py-2'),
            _FlexBetween(
                _Flex2(
                    _Link()->icon(_Sax('search-normal-1',18))
                        ->class(btnFilterClass())->class('block')
                        ->toggleClass('bg-info text-level1')
                        ->toggleId($this->moreInboxFilters)
                        ->run('focusSearchOnToggle'),
                    $this->htmlFieldFilter('Unread', 'lastMessage.read', 'sms')
                        ->selectedValue(1)
                        ->filter('NULL'),
                    $this->htmlFieldFilter('with-attachments', 'messages.attachments', 'paperclip-2')
                        ->selectedValue(1)
                        ->filter('>='),
                    $this->htmlFieldFilter('current-union', 'current_union_id', 'building-4', false)
                        ->selectedValue(1)
                        ->filter(),
                )->class('py-2'),
                Thread::flagLinkGroup()->class('py-2')->filter(),
            )->class('px-4 flex-wrap')->alignCenter(),
            _Rows(
                _Input('search-messages')->placeholder('messaging.min3-characters')->type('search')
                    ->name('content', false)
                    ->filter()->class('mb-0'),
                _TagsMultiSelect()->filter(),
            )->id($this->moreInboxFilters)
            ->class('pt-2 px-4 space-y-2')
        );
    }

    public function rightOnLoad()
    {
        $displayedThreadId = $this->threadId ?:

                (($firstItem = $this->query->first()) ? $firstItem['attributes']['id'] : null);

        return _Panel(
                new InboxMessages(['thread_id' => $displayedThreadId])
            )->id('inbox-message-panel')
            ->class('border-l border-gray-100 bg-gray-100 ml-0')
            ->class('sm:w-[50vw] md:w-[66vw] xl:w-[60vw] 2xl:w-[66vw]')
            ->closable();
    }

    public function render($thread, $key)
    {
        $firstMessage = $thread->firstMessage()->with('sender.entity')->first();
        $lastMessage = $thread->lastMessage()->first();
        $isRead = $lastMessage?->read()->first();

        $attachmentsCount = $thread->db_attachment_count;

        if($this->threadId == $thread->id){
            $this->activeIndex = $key;
        }

        $isArchive = is_null($this->isArchive) ? $thread->is_archived : $this->isArchive;
        $isTrash = is_null($this->isTrash) ? $thread->is_trashed : $this->isTrash;

        return _Flex(
            _Html()->class('thread-color w-1 absolute inset-y-2 left-1 rounded')->class($thread->color),
            _Rows(
                _Img($firstMessage?->sender->profile_img_url)
                    ->class('h-8 w-8 rounded-full object-cover'),
                ($thread->messages_count > 1) ? _Sax('undo')->class('text-gray-700 mt-2') : null,
            )->class('justify-between items-center h-full mr-2'),
            _Rows(
                !$firstMessage ? null : _FlexBetween(
                    _Html($firstMessage->sender->name)
                        ->class('text-xs leading-5 font-medium text-level1 opacity-60 truncate'),
                    _Html($lastMessage->created_at->translatedFormat('d M Y'))
                        ->class('ml-2 text-xs text-level1 opacity-60 whitespace-nowrap')
                ),

                !$lastMessage ? null :
                    _Html($lastMessage->subject)->class('thread-subject text-level2 text-sm truncate'),

                !$lastMessage ? null :
                    _Html($lastMessage->summary)->class('text-xs text-level2 truncate'),

                _FlexBetween(
                    _Flex(
                        !$attachmentsCount ? null :
                            _HtmlSax($attachmentsCount)->class('text-sm')->icon(_Sax('paperclip-2', 16)),
                        _Flex(
                            _HtmlSax($thread->db_message_count)->class('text-sm')->icon(_Sax('message', 16)),
                        )->class('space-x-2 hidden group-hover:flex'),
                        _Html('&nbsp;'),
                    )->class('space-x-2'),
                    _FlexEnd(
                        Thread::flagLinkGroup('space-x-1', 'w-4 h-4')->selfPost('changeThreadFlagColor', [
                            'id' => $thread->id,
                        ])->run('() => {syncThreadFlagColor(this)}'),
                        $this->boxButton('archive-1', $isArchive ? 'unarchiveThread' : 'archiveThread', $thread, $key),
                        $this->boxButton('trash', $isTrash ? 'untrashThread' : 'trashThread', $thread, $key),
                        $this->unreadButton($thread, false),
                        $this->readButton($thread, false),
                    )->class('inbox-message-buttons space-x-2 hidden group-hover:flex')
                )->class('text-level3')
            )->class('min-w-0 flex-1'),
            _FlexEnd(
                $this->boxButton('archive-1', $isArchive ? 'unarchiveThread' : 'archiveThread', $thread),
                $this->boxButton('trash', $isTrash ? 'untrashThread' : 'trashThread', $thread),
                $this->unreadButton($thread),
                $this->readButton($thread),
            )->class('inbox-mobile-buttons self-stretch')->alignStretch(),
        )->class(
            'inbox-thread rounded-xl mx-2 md:mx-4 my-2 relative px-4 py-2 cursor-pointer group'
        )
        ->alignStart()
        ->class('inbox-message')
        ->class($isRead ? 'read bg-gray-100' : 'bg-gray-200')
        ->get('inbox.message', ['id' => $thread->id])
        ->onSuccess(function($e) use($thread){
            $this->inPanelUpdateHistory($e, $thread->id);
            $e->addClass('read');
            $e->activate();
        });
    }

    public function searchInboxes()
    {
        return auth()->user()->impersonatableMailboxes()->mapWithKeys(fn($mailbox) => [
            $mailbox->id => $mailbox->getUnreadPillHtml().$mailbox->relatedEmail(),
        ]);
    }

    public function retrieveInbox($mailboxId)
    {
        if (!auth()->user()->checkCanImpersonateMailbox($mailboxId)) {
            auth()->user()->resetActiveEmailAccountIds();
            $mailboxId = auth()->user()->mainMailbox()->value('id');
        }

        $mailbox = EmailAccount::where('id', $mailboxId)->firstOrFail();

        return [
            $mailbox->id => _Html($mailbox->getUnreadPillHtml().$mailbox->relatedEmail())->searchableBy($mailbox->relatedEmail()),
        ];
    }

    public function impersonateMailbox($mailboxId)
    {
        if (!auth()->user()->checkCanImpersonateMailbox($mailboxId)) {
            abort(403, __('error.you-cannot-impersonate-this-mailbox'));
        }

        if ($mailboxId == auth()->user()->mainMailbox()->value('id')) {
            auth()->user()->resetActiveEmailAccountIds();
        } else {
            auth()->user()->setActiveEmailAccount($mailboxId);
        }
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

    protected function boxButton($icon, $method, $thread, $key = false)
    {
        $link = _Link()->icon(_Sax($icon,16))->class('text-level3')
            ->balloon(ucfirst(str_replace('Thread', '', $method)), 'up-right')
            ->selfPost($method, [
                'id' => $thread->id,
                'next' => $this->getNextThreadId($key),
            ]);

        if ($key === false) { //mobile button
            return $link->removeSelf()->class($this->mobileButtonClass);
        }

        return $link->onSuccess(function($e) use($key) {
                $this->inPanelUpdateHistory($e, $this->getNextThreadId($key));
                $e->removeSelf();
            })
            ->id('thread-mini-button-'.$icon.'-'.$thread->id);
    }

    protected function unreadButton($thread, $mobile = true)
    {
        $link = $this->readUnreadButton('messaging.mark-unread', 'sms', 'unreadThread', $thread)->class('unreadButton');

        return $mobile ? $link->class($this->mobileButtonClass) : $link;
    }

    protected function readButton($thread, $mobile = true)
    {
        $link = $this->readUnreadButton('messaging.mark-read', 'directbox-notif', 'readThread', $thread)->class('readButton');

        return $mobile ? $link->class($this->mobileButtonClass) : $link;
    }

    protected function readUnreadButton($label, $icon, $method, $thread)
    {
        return _Link()->icon(_Sax($icon,18))->class('text-level3')
            ->balloon($label, 'up-right')
            ->selfPost($method, [
                'id' => $thread->id,
            ])->attr([
                'onClick' => 'message'.$method.'(this)'
            ]);
    }

    protected function inPanelUpdateHistory($e, $threadId)
    {
        $e->inPanel('inbox-message-panel');
        $e->setHistory('my-inbox', [
            'thread_id' => $threadId
        ]);
    }

    protected function getNextThreadId($currentKey)
    {
        if ($currentKey === false) {
            return false;
        }

        $nextThread = $this->query->getCollection()[$currentKey + 1] ?? $this->query->getCollection()[$currentKey - 1] ?? null;

        return $nextThread ? $nextThread->id : false;
    }

    public function archiveThread($id, $next)
    {
        return $this->updateThreadBox($id, 1, $next);
    }

    public function trashThread($id, $next)
    {
        return $this->updateThreadBox($id, 2, $next);
    }

    public function unarchiveThread($id, $next)
    {
        return $this->updateThreadBox($id, 0, $next);
    }

    public function untrashThread($id, $next)
    {
        return $this->updateThreadBox($id, 0, $next);
    }

    protected function updateThreadBox($id, $box, $next)
    {
        if($box){
            Thread::findOrFail($id)->updateBox($box);
        }else{
            Thread::findOrFail($id)->box()->delete();
        }

        if ($next === false) {
            return;
        }

        return redirect()->route('inbox.message', ['id' => $next]);
    }

    public function unreadThread($id)
    {
        $lastMessage = Thread::findOrFail($id)->lastMessage()->first();

        $lastMessage?->markUnread();

        return __('messaging.marked-unread');
    }

    public function readThread($id)
    {
        $lastMessage = Thread::findOrFail($id)->lastMessage()->first();

        $lastMessage?->markRead();

        return __('messaging.marked-read');
    }

    public function changeThreadFlagColor()
    {
        $thread = Thread::findOrFail(request('id'));
        $thread->flag_color = request('flag_color') ?: Thread::FLAG_NONE;
        $thread->save();
    }

    public function js()
    {
        return <<<javascript

var hammerInstances = []
activateSwipe() //for some reason, I had to add this because when Query mounted(), this func is not registered yet...


calculateUnreadMessages()
function calculateUnreadMessages()
{
    $.ajax({
        url: "/calculate-unread-messages",
        type: 'GET',
    });
}

function activateSwipe()
{
    if (window.innerWidth > 576) {
        return
    }

    hammerInstances.forEach(function(i){
        i.off("swipeleft swiperight")
        i.destroy()
    })

    hammerInstances = []

    $('.inbox-message').each(function(index){
        var myElement = $(this)

        hammerInstances[index] = new Hammer(myElement[0])

        var mc = hammerInstances[index]

        // listen to events...
        mc.on("swipeleft", function(ev) {
            myElement.find('.inbox-mobile-buttons').css('display', 'flex')
        });
        mc.on("swiperight", function(ev) {
            myElement.find('.inbox-mobile-buttons').css('display', 'none')
        });
    })
}

function focusSearchOnToggle()
{
    if ($('#$this->moreInboxFilters')[0].style.display == 'none'){
        return;
    }

    let input = $('#$this->id input[name=content]')

    input.focus()
}

function messagereadThread(that)
{
    getClosestInboxMessage(that).addClass('read')
}

function messageunreadThread(that)
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
