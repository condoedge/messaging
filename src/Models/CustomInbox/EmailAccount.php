<?php

namespace Condoedge\Messaging\Models\CustomInbox;

use Kompo\Auth\Models\Model;

class EmailAccount extends Model
{
    /* RELATIONS */
    public function entity()
    {
        return $this->morphTo();
    }

    public function distributions()
    {
    	return $this->hasMany(Distribution::class);
    }

    public function signatures()
    {
    	return $this->hasMany(Signature::class);
    }

    /* SCOPES */
    public function scopeOnlyMailboxes($query)
    {
        $query->where('is_mailbox', 1);
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
    	return trim($this->email_adr);
    }

    public function belongsToAuthUser()
    {
    	return ($this->entity_type == 'user') && ($this->entity_id == auth()->user()->id);
    }

    public function getRecipientString()
    {
    	return $this->entity ? ($this->name.' ('.$this->mainEmail().')') : $this->mainEmail();
    }

    public function getAutoInsertSignature()
    {
    	return $this->signatures()->where('is_auto_insert', 1)->first();
    }

    public static function transformRecipientsToEmailAccounts($emails)
    {
        return collect($emails)->map(fn($email) => static::findOrCreateFromEmail($email));
    }

    /* ELEMENTS */
    public function getEmailOption()
    {
    	return $this->email_adr; //TODO change later
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
	public static function findFromEmail($email)
	{
		return EmailAccount::where('email_adr', $email)->first();
	}

	public static function createForEmail($email)
	{
		$emailAccount = new EmailAccount();
		$emailAccount->email_adr = $email;
		$emailAccount->save();

		return $emailAccount;
	}

	public static function findOrCreateFromEmail($email)
	{
		return EmailAccount::findFromEmail($email) ?: EmailAccount::createForEmail($email);
	}
}
