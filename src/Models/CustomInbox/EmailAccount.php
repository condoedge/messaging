<?php

namespace Condoedge\Messaging\Models\CustomInbox;

use App\Models\Messaging\MessageForward;
use App\Models\Model;
use App\Models\Traits\BelongsToTeam;
use Illuminate\Database\Eloquent\SoftDeletes;
use Laravel\Jetstream\HasProfilePhoto;

class EmailAccount extends Model
{
    use SoftDeletes,
        BelongsToTeam,
        HasProfilePhoto;

    /* RELATIONS */
    public function entity()
    {
        return $this->morphTo();
    }

    public function distributions()
    {
    	return $this->hasMany(Distribution::class);
    }

    /* ATTRIBUTES */
	public function getProfileImgUrlAttribute()
	{
		return $this->entity ? $this->entity->profile_photo_url : $this->defaultProfilePhotoUrl();
	}

	public function getNameAttribute()
	{
		return $this->entity ? ($this->entity->email_display ?: $this->entity->name) : $this->email;
	}

    /* CALCULATED FIELDS */
    public function mainEmail()
    {
    	return trim($this->entity ? $this->entity->mainEmail() : $this->email);
    }

    public function relatedEmail()
    {
    	return $this->is_mailbox ? static::getMailboxEmail($this->email) : ($this->email ?: $this->mainEmail());
    }

    public function belongsToAuthUser()
    {
    	return ($this->entity_type == 'user') && ($this->entity_id == auth()->user()->id);
    }

    public function getRecipientString()
    {
    	return $this->entity ? ($this->name.' ('.$this->relatedEmail().')') : $this->relatedEmail();
    }

    public static function isNotAcceptableMailbox($emailPrefix, $excludeUserId)
    {
    	if (strlen($emailPrefix) < 2) {
    		return true;
    	}

    	return static::where('is_mailbox', 1)->where('email', $emailPrefix)->where('entity_id', '<>', $excludeUserId)->count();
    }

    public static function isMailbox($email)
    {
    	return strpos($email, static::mailboxHost()) > -1;
    }

    public static function getMailboxPrefix($email)
    {
    	return str_replace(static::mailboxHost(), '', $email);
    }

    public static function getMailboxEmail($emailPrefix)
    {
    	if (!$emailPrefix) {
    		return;
    	}

    	return $emailPrefix.(static::mailboxHost());
    }

    public static function mailboxHost()
    {
    	return '@'.config('mailbox.email_incoming_host');
    }

    public static function getMailboxTeamId($address)
    {
    	if (!static::isMailbox($address)) {
    		return;
    	}

    	$mailbox = static::getMailboxPrefix($address);

    	return static::where('is_mailbox', 1)->where('email', $mailbox)->value('team_id');
    }

    /* QUERIES */
    public static function getUnionsMailboxes()
    {
    	return static::whereIn('team_id', userTeamIds())->where('is_mailbox', 1)
    		->where('entity_type', 'union')->whereIn('entity_id', userUnionIds());
    }

    public static function getTeammatesMailboxes()
    {
    	return static::whereIn('team_id', userTeamIds())->where('is_mailbox', 1)
    		->where('entity_type', 'user')->whereIn('entity_id', userColleagues()->pluck('id'))
    		->where('is_impersonatable', 1);
    }

    //TODO DELETE? Rendered useless by team impersonation
    public static function getForwardedMailboxes()
    {
    	$userIds = MessageForward::currentlyActive()->where('forward_to_id', auth()->id())->pluck('id');

    	return static::where('team_id', currentTeamId())->where('is_mailbox', 1)
    		->where('entity_type', 'user')->whereIn('entity_id', $userIds);
    }

    /* ELEMENTS */
    public function getEmailOption()
    {
        return $this->entity ? $this->entity->getEmailOption() : _EmailHtml($this->mainEmail());
    }

    public function recipientEmailWithLink($threadId = null)
    {
        return ($this->entity_type || !$threadId || !$this->email) ?
            _Html($this->getRecipientString())->class('inline') :
            _Link($this->getRecipientString())
                ->class('cursor-pointer hover:underline hover:text-level3')
                ->get('email-link-entity', [
                    'email' => $this->email,
                    'thread_id' => $threadId,
                ])->inModal();
    }

    public function getUnreadPillHtml()
    {
    	if (!$this->unread_count) {
    		return '';
    	}

    	return '<span class="rounded-full px-2 text-xs bg-danger text-white">'.$this->unread_count.'</span> ';
    }

    /* ACTIONS */
	public static function findOrCreate($entityOrEmail, $teamId = null)
	{
		if(is_string($entityOrEmail)){
			return static::where('team_id', $teamId)->where('email', $entityOrEmail)->first() ?:
				static::createWithoutEntity($entityOrEmail, $teamId);
		}

		return $entityOrEmail->mainMailbox()->first() ?: (
			$entityOrEmail->mainEmailAccount($teamId)->first() ?: ( //TODO DELETE AFTER REMOVING THEM FROM DB
				$entityOrEmail->emailAccount($teamId)->first() ?: 
					$entityOrEmail->createEmailAccount($teamId)
			)
		);
	}

	public static function findOrCreateFromType($type, $idOrEmail, $teamId = null)
	{
		if ($type == 'email-account') {
			return static::find($idOrEmail);
		}

		if ($emailAccount = static::findFromType($type, $idOrEmail, $teamId) ) {
			return $emailAccount;
		}

		if ($type) {
			$emailAccount = new static();
			$emailAccount->setTeamId();
			$emailAccount->entity_type = $type;
			$emailAccount->entity_id = $idOrEmail;
			$emailAccount->save();

			return $emailAccount;

		}

        return static::createWithoutEntity($idOrEmail);
	}

    public static function haveGlobalEmailAccount($type)
    {
        return in_array($type, [
            'user',
            'union',
        ]);
    }

	public static function findFromType($type, $idOrEmail, $teamId = null)
	{
		return static::when(!static::haveGlobalEmailAccount($type), 
                fn($q) => $q->where('team_id', $teamId ?: currentUnion()->team_id)
            )
			->where('entity_type', $type)->where('entity_id', $idOrEmail)
			->orderByDesc('is_main') //this selects mainmailbox first
			->first();
	}

	public static function createWithoutEntity($email, $prefilledTeam = null)
	{
		$emailAccount = new static();

		if($prefilledTeam){
			$emailAccount->team_id = $prefilledTeam;
		}else{
			$emailAccount->setTeamId();
		}

		$emailAccount->email = $email;
		$emailAccount->save();

		return $emailAccount;
	}

	public static function associateEmailToEntity($email, $entity)
	{
		$emailAccount = static::findNonEntityEmail($email);

		if (!$emailAccount) {
			$emailAccount = static::createWithoutEntity(null); //null because it will because primary mail account
		}

		$emailAccount->entity()->associate($entity);

		$emailAccount->save();
	}

	public static function findNonEntityEmail($email)
	{
		return currentTeam()->emailAccounts()->whereNull('entity_type')->where('email', $email)->first();
	}
}
