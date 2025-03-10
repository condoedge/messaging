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

    public function mailbox()
    {
        return $this->emailAccount()->onlyMailboxes();
    }

    public function getEntityMailbox()
    {
        $mailbox = $this->mailbox()->first();

        if (!$mailbox) {
            $this->setupUserMailbox();
            $mailbox = $this->mailbox()->first();
        }

        return $mailbox;
    }

    /* CALCULATED FIELDS */
    public function mainEmail()
    {
        return $this->email;
    }

    /* ACTIONS */
    public function setupUserMailbox()
    {
        $emailPrefix = \Str::before($this->mainEmail(), '@');
        $i = 0;
        while (!$this->createOrUpdateMailbox($emailPrefix)) {
            $i ++;
            $emailPrefix = \Str::before($this->mainEmail(), '@').$i;
        }
    }

    public function createOrUpdateMailbox($emailPrefix)
    {
        if (!$this->isAcceptableMailbox($emailPrefix)) {
            return false;
        }

        if ($mailbox = $this->mailbox()->first()) {
            $mailbox->email_adr = getMailboxEmail($emailPrefix);
            $mailbox->save();
            return $mailbox;
        }

        return $this->createEmailAccount(getMailboxEmail($emailPrefix), 1);
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

        return !EmailAccount::where('email_adr', $fullEmail)->where('is_mailbox', 1)->where('entity_id', '<>', $this->id)->count();
    }

    public function checkCanImpersonateMailbox($mailboxId)
    {
        if (($this->getEntityMailbox()->id != $mailboxId) && !$this->impersonatableMailboxes()->pluck('id')->contains($mailboxId)) {
            return false;
        }

        return true;
    }
}