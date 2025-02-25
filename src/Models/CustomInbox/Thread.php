<?php

namespace Condoedge\Messaging\Models\CustomInbox;

use Kompo\Auth\Models\Model;

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
        return $this->messages()->with('attachments', 'read', 'review', 'sender.entity', 'recipients.entity')
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
        $emailAccount = auth()->user()->getSenderAccount();

        return $this->hasOne(ThreadBox::class)
            ->where('entity_id', $emailAccount->entity_id)
            ->where('entity_type', $emailAccount->entity_type);
    }

    public function boxTrash()
    {
        return $this->box()->trash();
    }

    /* ATTRIBUTES */
    public function getColorAttribute()
    {
        return Thread::flagColors()[$this->flag_color ?: Thread::FLAG_NONE];
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
    public static function flagLabels()
    {
        return [
            //Thread::FLAG_NONE => __('messaging.no-status'),
            Thread::FLAG_SORT => __('messaging.to-sort'),
            Thread::FLAG_WAIT => __('messaging.waiting'),
            Thread::FLAG_REPLY => __('messaging.to-reply'),
            Thread::FLAG_5 => __('messaging.to-reply'),
            Thread::FLAG_6 => __('messaging.to-reply'),
        ];
    }

    public static function flagColors()
    {
        return [
            Thread::FLAG_NONE => 'bg-gray-100',
            Thread::FLAG_SORT => 'bg-level2',
            Thread::FLAG_WAIT => 'bg-warning',
            Thread::FLAG_REPLY => 'bg-info',
            Thread::FLAG_5 => 'bg-positive',
            Thread::FLAG_6 => 'bg-danger',
        ];
    }

    public static function defaultFlagBg()
    {
        return Thread::flagColors()[Thread::FLAG_NONE];
    }

    public static function flagOptions($dimensions = 'w-6 h-6')
    {
        return collect(Thread::flagLabels())->mapWithKeys(fn($label, $key) => [
            $key => _Html()->class('rounded-full')->class($dimensions)->class(Thread::flagColors()[$key])->balloon($label, 'up-right')
        ]);
    }

    public static function flagLinkGroup($spacing = 'space-x-2', $dimensions = 'w-6 h-6')
    {
        return _LinkGroup()->name('flag_color')
            ->containerClass('flex '.$spacing)->optionClass('cursor-pointer !bg-transparent')->class('mb-0')
            ->options(Thread::flagOptions($dimensions));
    }

    /* ACTIONS */
    public static function pusherBroadcast($teamId = null)
    {
        broadcast(new MessageSent($teamId ?: currentTeamId()))->toOthers();
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
            $cb->setUserId();
        }
        $cb->box = $box;

        $emailAccount = auth()->user()->getSenderAccount();
        $cb->entity_id = $emailAccount->entity_id;
        $cb->entity_type = $emailAccount->entity_type;

        $cb->save();
    }

    public function createNewBranch($teamId = null, $type = null)
    {
        $newThread = new Thread();
        $newThread->team_id = $teamId ?: $this->team_id; //In case of cross-team emails like support request..
        $newThread->union_id = $this->union_id;
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
        $emailAccount = auth()->user()->getSenderAccount();

        if (!$emailAccount) {
            \Log::warning('BOX: no senderAccount for '.auth()->user()?->getSenderAccountId().' | user id: '.auth()->user()?->id);
        }

        $query->whereDoesntHave('boxes',
            fn($q) => $q->where('entity_id', $emailAccount->entity_id)
                        ->where('entity_type', $emailAccount->entity_type)
        );
    }

    public function scopeNotInTrashBox($query)
    {
        $emailAccount = auth()->user()->getSenderAccount();

        if (!$emailAccount) {
            \Log::warning('Trash: no senderAccount for '.auth()->user()?->getSenderAccountId().' | user id: '.auth()->user()?->id);
        }

        $query->whereDoesntHave('boxes',
            fn($q) => $q->where('entity_id', $emailAccount->entity_id)
                        ->where('entity_type', $emailAccount->entity_type)
                        ->where('box', ThreadBox::BOX_TRASH)
        );
    }

}
