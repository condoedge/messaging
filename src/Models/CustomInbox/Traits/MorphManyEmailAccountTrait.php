<?php 

namespace Condoedge\Messaging\Models\CustomInbox\Traits;

use App\Models\Messaging\EmailAccount;

trait MorphManyEmailAccountTrait
{
    /* RELATIONS */
    public function emailAccounts()
    {
        return $this->morphMany(EmailAccount::class, 'entity');
    }

    public function emailAccount()
    {
        return $this->morphOne(EmailAccount::class, 'entity');
    }

    public function getEntityEmailAccount()
    {
        $emailAccount = $this->emailAccount()->first();

        if (!$emailAccount) {
            $this->setupUserMailbox();
            $emailAccount = $this->emailAccount()->first();
        }

        return $emailAccount;
    }

    /* CALCULATED FIELDS */
    public function mainEmail()
    {
        return $this->email;
    }

    /* ACTIONS */
    public function setupUserMailbox()
    {
        $emailPrefix = \Str::before($this->email, '@');
        $i = 0;
        while (!$this->createOrUpdateMailbox($emailPrefix)) {
            $i ++;
            $emailPrefix = \Str::before($this->email, '@').$i;
        }
    }

    public function createOrUpdateMailbox($emailPrefix)
    {
        if ($this->isAcceptableMailbox($emailPrefix)) {
            return false;
        }

        if ($mailbox = $this->getEntityEmailAccount()) {
            $mailbox->email_adr = getMailboxEmail($emailPrefix);
            $mailbox->save();
            return $mailbox;
        }

        return $this->createEmailAccount($emailPrefix, 1);
    }

    public function createEmailAccount($email = null, $isMailbox = null)
    {
        $emailAccount = new EmailAccount();
        $emailAccount->email_adr = $email;
        $emailAccount->is_mailbox = $isMailbox;
        $this->emailAccounts()->save($emailAccount);

        return $emailAccount;
    }

    /* MAILBOX RELATED */
    public function isAcceptableMailbox($emailPrefix)
    {
        if (strlen($emailPrefix) <= 2) {
            return false;
        }

        $fullEmail = getMailboxEmail($emailPrefix);

        return EmailAccount::where('email_adr', $fullEmail)->where('is_mailbox', 1)->where('entity_id', '<>', $this->id)->count();
    }

    public function checkCanImpersonateMailbox($mailboxId)
    {
        if (($this->getEntityEmailAccount()->id != $mailboxId) && !$this->impersonatableMailboxes()->pluck('id')->contains($mailboxId)) {
            return false;
        }

        return true;
    }
}