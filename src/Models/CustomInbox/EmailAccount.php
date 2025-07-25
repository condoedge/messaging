<?php

namespace Condoedge\Messaging\Models\CustomInbox;

use Condoedge\Utils\Models\Model;

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

    protected function defaultProfilePhotoUrl()
    {
        return avatarFromText($this->mainEmail());
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

    public function shouldSendExternally()
    {
        if (!$this->mainEmail() || $this->belongsToAuthUser() || $this->is_mailbox) {
            return false;
        }

        return true;
    }

    public function recalculateUnreadCount()
    {
        $this->unread_count = Thread::notAssociatedToAnyBox()
            ->whereHas('messages', fn($q) => $q->authUserInDistributions()
                ->whereDoesntHave('reads', fn($q) => $q->where('email_account_id', currentMailboxId()))
            )->count();

        $this->save();

        return $this->unread_count;
    }

    /* ELEMENTS */
    public function getEmailOption()
    {
    	return $this->email_adr; //TODO change later
        return $this->entity ? $this->entity->getEmailOption() : _EmailHtml($this->mainEmail());
    }

    public function recipientEmailWithLink()
    {
        return _Html($this->getRecipientString())->class('inline');
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
