<?php

namespace Condoedge\Messaging\Models\CustomInbox;

use Condoedge\Utils\Models\Model;
use App\Models\Messaging\Thread as AppThread;

class Thread extends Model
{
    const TYPE_INTERNAL = 1;
    const TYPE_INCOMING = 4;
    const TYPE_SUPPORT = 8;

    public const FLAG_NONE = 1;
    public const FLAG_SORT = 2;
    public const FLAG_WAIT = 3;
    public const FLAG_REPLY = 4;
    public const FLAG_5 = 5;
    public const FLAG_6 = 6;

    /* RELATIONSHIPS */
    public function firstMessage()
    {
        return $this->hasOne(Message::class)->select('id', 'thread_id', 'sender_id', 'subject', 'summary', 'created_at')->authUserIncluded();
    }

    public function lastMessage()
    {
        return $this->firstMessage()->orderBy('created_at', 'DESC');
    }

    public function newMessage() //It's value is being overwritten manually in the form.
    {
        return $this->hasOne(Message::class);
    }

    public function messages()
    {
        return $this->hasMany(Message::class);
    }

    public function orderedMessages()
    {
        return $this->messages()->with('attachments', 'read', 'sender.entity', 'recipients.entity')
            ->orderBy('created_at', 'DESC');
    }

    public function attachments()
    {
        return $this->hasManyThrough(Attachment::class, Message::class);
    }

    public function boxes()
    {
        return $this->hasMany(ThreadBox::class);
    }

    public function box()
    {
        return $this->hasOne(ThreadBox::class)->where('email_account_id', currentMailboxId());
    }

    public function boxTrash()
    {
        return $this->box()->trash();
    }

    /* ATTRIBUTES */
    public function getColorAttribute()
    {
        return AppThread::flagColors()[$this->flag_color ?: AppThread::FLAG_NONE];
    }

    public function getIsArchivedAttribute()
    {
        return optional($this->box)->is_archived;
    }

    public function getIsTrashedAttribute()
    {
        return optional($this->box)->is_trashed;
    }

    /* CALCULATED FIELDS */
    public function getPreviewRoute()
    {
        return route('custom-inbox', [
            'thread_id' => $this->id
        ]);
    }

    public static function flagLabels()
    {
        return [
            //Thread::FLAG_NONE => __('messaging-no-status'),
            AppThread::FLAG_SORT => __('messaging-to-sort'),
            AppThread::FLAG_WAIT => __('messaging-waiting'),
            AppThread::FLAG_REPLY => __('messaging-to-reply'),
            AppThread::FLAG_5 => __('messaging-custom1'),
            AppThread::FLAG_6 => __('messaging-custom2'),
        ];
    }

    public static function flagColors()
    {
        return [
            AppThread::FLAG_NONE => 'bg-gray-100',
            AppThread::FLAG_SORT => 'bg-infodark',
            AppThread::FLAG_WAIT => 'bg-warning',
            AppThread::FLAG_REPLY => 'bg-info',
            AppThread::FLAG_5 => 'bg-positive',
            AppThread::FLAG_6 => 'bg-danger',
        ];
    }

    public static function defaultFlagBg()
    {
        return AppThread::flagColors()[AppThread::FLAG_NONE];
    }

    public static function flagOptions($dimensions = 'w-6 h-6')
    {
        return collect(AppThread::flagLabels())->mapWithKeys(fn($label, $key) => [
            $key => _Html()->class('rounded-full')->class($dimensions)->class(AppThread::flagColors()[$key])->balloon($label, 'up-right')
        ]);
    }

    public static function flagLinkGroup($spacing = 'space-x-2', $dimensions = 'w-6 h-6')
    {
        return _LinkGroup()->name('flag_color')
            ->containerClass('flex '.$spacing)->optionClass('cursor-pointer !bg-transparent')->class('mb-0')
            ->options(AppThread::flagOptions($dimensions));
    }

    /* ACTIONS */
    public static function pusherBroadcast($teamId = null)
    {
        //muted until we activate pusher
        //broadcast(new MessageSent($teamId ?: currentTeamId()))->toOthers();
    }

    public static function pusherRefresh()
    {
        if (!auth()->user()) {
            return; //the user has disconnected
        }

        return [
            'inbox.'.currentTeam()->id => MessageSent::class
        ];
    }

    public function updateBox($box)
    {
        if(!($cb = $this->box)){
            $cb = new ThreadBox();
            $cb->thread_id = $this->id;
        }
        $cb->box = $box;
        $cb->email_account_id = currentMailboxId();

        $cb->save();
    }

    public function createNewBranch($type = null)
    {
        $newThread = new AppThread();
        $newThread->subject = $this->subject;
        $newThread->type = $type ?: static::TYPE_INTERNAL;
        $newThread->save();

        $newThread->tags()->sync($this->tags()->pluck('tags.id'));

        return $newThread;
    }

    public function delete()
    {
        $this->messages->each->delete();
        $this->boxes()->delete();

        parent::delete();
    }

    public function updateStats()
    {
        $this->last_message_at = now();
        $this->db_message_count = $this->messages()->count();
        $this->db_attachment_count = $this->messages()->withCount('attachments')->value('attachments_count');
        $this->save();
    }

    /* QUERIES */
    public static function withBasicInfo()
    {
        return static::withCount('messages', 'attachments');
    }

    /* SCOPES */
    public function scopeForAuthUser($query, $allMailboxes = false)
    {
        $query->whereHas('messages', fn($q) => $q->authUserIncluded($allMailboxes));
    }

    public function scopeOrdered($query)
    {
        if (auth()->user()?->isContact()) { //Fix because of below bug..
            return $query->latest();
        }

        //For some reason, the line below was removing constraints in HasMany relationships on User (only in the case of a Contact) ?!?!
        $query->withMax('lastMessage', 'created_at')
            ->orderByDesc('last_message_max_created_at');
    }

    public function scopeIsArchived($query)
    {
        $query->whereHas('box', fn($q) => $q->archive());
    }

    public function scopeIsTrashed($query)
    {
        $query->whereHas('box', fn($q) => $q->trash());
    }

    public function scopeHasDrafts($query)
    {
        $query->whereHas('messages', fn($q) => $q->isDraft());
    }

    public function scopeIsNotTrashed($query)
    {
        $query->where(function($q){
            $q->doesntHave('box')
              ->orWhereHas('box', fn($q1) => $q1->notTrash());
        });
    }

    public function scopeNotAssociatedToAnyBox($query)
    {
        $emailAccount = currentMailbox();

        if (!$emailAccount) {
            \Log::warning('BOX: no senderAccount for '.currentMailboxId().' | user id: '.auth()->id());
        }

        $query->whereDoesntHave('boxes',
            fn($q) => $q->where('email_account_id', $emailAccount->id)
        );
    }

    public function scopeNotInTrashBox($query)
    {
        $emailAccount = currentMailbox();

        if (!$emailAccount) {
            \Log::warning('Trash: no senderAccount for '.currentMailboxId().' | user id: '.auth()->id());
        }

        $query->whereDoesntHave('boxes',
            fn($q) => $q->where('email_account_id', $emailAccount->id)
                        ->where('box', ThreadBox::BOX_TRASH)
        );
    }

}
