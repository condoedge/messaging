<?php

namespace Condoedge\Messaging\Kompo\CustomInbox;

use App\Models\Messaging\Thread;
use Condoedge\Utils\Kompo\Common\Query;

class InboxAttachments extends Query
{
    public $layout = 'Grid';

    public $perPage = 10;
    public $noItemsFound = ' ';

    public $class = 'bg-white';

    public $itemsWrapperClass = 'container overflow-y-auto mini-scroll';
    public $itemsWrapperStyle = 'height: 300px';

    public $paginationType = 'Scroll';

    protected $threadId;
    protected $thread;

    public function created()
    {
        $this->threadId = $this->store('thread_id');
        $this->thread = Thread::find($this->threadId);
    }

    public function query()
    {
        if(!$this->thread)
            return;

        return $this->thread->attachments();
    }

    public function render($attachment)
    {
        try {
            $thumbnail = $attachment->fileThumbnail('main', 'link-to', 'download');
        } catch (\Throwable $e) {

            \Log::critical('Thumbnail error for attachment '.$attachment->id);
            
            $thumbnail = _Rows(
                _Html('error-thumbnail-error')->class('font-bold'),
                _Html($attachment->name),
            )->class('text-xs p-2 border border-gray-100 text-center rounded-2xl');
        }
        return _Rows(
            $thumbnail
        )->class('col-6')->style('padding-left:0;padding-right:0');
    }

}
