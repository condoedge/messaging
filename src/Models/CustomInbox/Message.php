<?php

namespace Condoedge\Messaging\Models\CustomInbox;

use App\Models\Messaging\Thread;
use App\Models\Messaging\Message as AppMessage;
use App\Models\Messaging\Attachment;
use Kompo\Auth\Models\Files\File;

use Condoedge\Messaging\Mail\ExternalEmailNotification;
use Kompo\Auth\Models\Model;

class Message extends Model
{
    const DEFAULT_TYPE = 1;
    const REPLY_TYPE = 2;
    const FORWARD_TYPE = 3;
    const INCOMING_TYPE = 4;

    public function save(array $options = [])
    {
        if (!$this->sender_id && !app()->runningInConsole()) {
            $this->sender_id = currentMailboxId();
        }

        if (!$this->summary) {
            $this->setSummaryFrom($this->html);
        }

        if (!$this->text || $this->is_draft) {
            $this->text = prepareForSearch($this->html); //for search
        }

        if (!$this->id && $this->isIncoming()) {
            $this->html = $this->html ? htmlentities($this->html) : null; //sanitizing Html For DB
        }

        if (!$this->uuid) {
            $this->uuid = \Str::uuid()->toString();
        }

        $this->addPrefix();

        parent::save();

        if (!app()->runningInConsole() && auth()->user()) {

            $this->markRead(); //mark as read for the sender
        }
    }

    /* RELATIONS */
    public function thread()
    {
        return $this->belongsTo(Thread::class);
    }

    public function sender()
    {
        return $this->belongsTo(EmailAccount::class, 'sender_id')->withTrashed();
    }

    public function message() //if reply or forward
    {
        return $this->belongsTo(AppMessage::class);
    }

    public function messages()
    {
        return $this->hasMany(AppMessage::class);
    }

    public function attachments()
    {
        return $this->hasMany(Attachment::class);
    }

    public function distributions()
    {
        return $this->hasMany(Distribution::class);
    }

    public function recipients()
    {
        return $this->belongsToMany(EmailAccount::class, 'distributions');
    }

    public function reads()
    {
        return $this->hasMany(MessageRead::class);
    }

    public function read()
    {
    	return $this->hasOne(MessageRead::class)->where('email_account_id', currentMailboxId());
    }

    /* SCOPES */
    public function scopeAuthUserIncluded($query)
    {
        $query->authUserAsSender()->orWhere(fn($q) => $q->authUserInDistributions());
    }

    public function scopeAuthUserAsSender($query)
    {
        $query->where('sender_id', currentMailboxId());
    }

    public function scopeAuthUserInDistributions($query)
    {
        $query->whereHas('distributions', fn($q) => $q->where('email_account_id', currentMailboxId()));
    }

    public function scopeIsDraft($query)
    {
        $query->whereNotNull('is_draft');
    }

    public function scopeIsNotDraft($query)
    {
        $query->whereNull('is_draft');
    }

    /* CALCULATED FIELDS */
    public function isDefault()
    {
        return $this->type == static::DEFAULT_TYPE;
    }

    public function isReply()
    {
        return $this->type == static::REPLY_TYPE;
    }

    public function isForward()
    {
        return $this->type == static::FORWARD_TYPE;
    }

    public function isIncoming()
    {
        return $this->type == static::INCOMING_TYPE;
    }

    public function getHtmlToDisplay()
    {
        $html = $this->getDirectDisplayedHtml();

        $html .= $this->getParentMessageExtraHtml(true);

        return $html;
    }

    protected function getDirectDisplayedHtml()
    {
        return $this->isIncoming() ? displayMailHtmlInIframe($this->html) : $this->html;
    }

    public function getParentMessageExtraHtml($toDisplay = false)
    {
        $parentMessage = $this->message;
        $html = !$parentMessage ? '' : '<div style="max-height:500px;overflow-y:auto" onclick="this.style.maxHeight = \'none\'">';

        //add email history
        while ($parentMessage) {
            $html .= $parentMessage->getMessageInfos().'<br>';
            $html .= $toDisplay ? $parentMessage->getDirectDisplayedHtml() : $parentMessage->getHtmlToAppend();

            $parentMessage = $parentMessage->message;
        }

        $html .= (!$parentMessage ? '' : '</div>');

        return $html;
    }

    public function getHtmlToAppend()
    {
        if ($this->isIncoming()) {
            $newHtml = html_entity_decode($this->html);
            if (safeTruncate($newHtml, 9) == '<!DOCTYPE') {
                //dd(str_replace('<', 'TAG', $newHtml));
                preg_match("/<body[^>]*>(.*?)<\/body>/is", $newHtml, $matches);
                $newHtml = strip_tags($matches[1]);
            }
            return $newHtml;
        }

        return $this->html;
    }

    protected function getMessageInfos()
    {
        $html = '<br><div style="border:1px solid gainsboro; height:1px"></div><br>';
        $html .= $this->senderString().'<br>';
        $html .= 'Sent: '.$this->created_at.'<br>';
        $html .= $this->recipientsString().'<br>';
        //$html .= __('CC').': '; //TODO CC:....
        $html .= __('Subject').': '.$this->subject.'<br>';

        return $html;
    }

    public function senderString()
    {
        return __('From').': '.$this->sender->getRecipientString();
    }

    public function recipientsString($showBcc = false, $delimiter = '; ')
    {
        if ($this->bcc && !$showBcc) {
            return __('messaging.bcc-multiple');
        }

        return $this->recipientsPrefixString().$this->recipients->map(
            fn($recipient) => $recipient->getRecipientString()
        )->implode($delimiter);
    }

    public function recipientsPrefixString()
    {
        return __($this->bcc ? 'messaging.BCC' : 'To').': ';
    }

    public function hasDifferentDistributions($recipientEmailAccountIds, $newSenderId = null)
    {
        $newSenderId = $newSenderId ?: currentMailboxId();
        $newMessageRecipients = $recipientEmailAccountIds->concat([$newSenderId]);

        $parentMessageRecipients = $this->recipients->pluck('id')->concat([$this->sender_id]);

        //dd($newMessageRecipients, $parentMessageRecipients, $this->id);

        if ($parentMessageRecipients->diff($newMessageRecipients)->count()) {
            return true;
        }

        return false;
    }

    public static function transformRecipientsToEmailAccounts($recipients)
    {
        return collect($recipients)->map(fn($email) => EmailAccount::findOrCreateFromEmail($email));
    }

    /* ACTIONS */
    public function addDistribution($emailAccount)
    {
        $distribution = new Distribution();
        $distribution->emailAccount()->associate($emailAccount);
        $this->distributions()->save($distribution);
    }

    public function addDistributionFromEmail($email)
    {
        $this->addDistribution(EmailAccount::findOrCreateFromEmail($email));
    }

    public function addParticipantsToReply($replyMessage)
    {
        return $this->recipients->concat([$this->sender])->each(function($e) use($replyMessage) {

            if (!$e->belongsToAuthUser()) {
                $replyMessage->addDistribution($e);
            }

        });
    }

    public function addLinkedAttachments()
    {
        collect(request('selected_files'))->each(function($fileId){
            $file = File::find($fileId);
            Attachment::createAttachmentFromFile(
                $this,
                $file->name,
                $file->mime_type,
                $file->path
            );
        });

        $this->load('attachments');
    }

    public function sendExternalEmail($url = null)
    {
        $distributions = $this->getValidDistributions();

        if ($this->bcc) {

            $action = ($distributions->count() >= 3) ? 'queue' : 'send';

            \DB::transaction(
                fn() => $distributions->each(function($entity, $toEmail) use ($url, $action) {

                    $url = $url === false ? null : (
                        !$entity ? ($url ?: null) : $entity->invitationUrl($this->thread_id, $url)
                    );

                    \Mail::to(trim($toEmail))
                        ->{$action}(new ExternalEmailNotification($this, $url));
                })
            );

        } else {
            \Mail::to($distributions->map(fn($entity, $toEmail) => $toEmail))
                ->send(new ExternalEmailNotification($this, $url));
        }
    }

    protected function getValidDistributions()
    {
        $emailsAndEntities = [];

        foreach ($this->distributions()->with('emailAccount.entity')->get() as $distribution) {
            
            $emailAccount = $distribution->emailAccount;
            $entity = $emailAccount->entity;
            $toEmail = trim($emailAccount->mainEmail());

            if (!$toEmail || $emailAccount->belongsToAuthUser()) {
                continue;
            }

            if ($emailAccount->is_mailbox) {
                continue;
            }

            if ($entity instanceOf \App\Models\Contact\Contact) {
                $emails = $entity->emails()->where('send_to_or_not', 1)->get();
                foreach ($emails as $email) {
                    $emailsAndEntities[trim($email->value)] = $entity;
                }
            } else {

                $emailsAndEntities[$toEmail] = $entity;

            }
        }

        return collect($emailsAndEntities);
    }

    public function markRead()
    {
        if ($this->read()->first()) {
            return;
        }

        $mr = new MessageRead();
        $mr->message_id = $this->id;
        $mr->read_at = now();
        $mr->email_account_id = currentMailboxId();
        $mr->save();

        $emailAccount = currentMailbox();
        $emailAccount->unread_count = max(0, $emailAccount->unread_count - 1);
        $emailAccount->save();
    }

    public function markUnread()
    {
        $messageRead = $this->read()->first();

        if ($messageRead) {

            $emailAccount = EmailAccount::findFromType($messageRead->entity_type, $messageRead->entity_id);
            $emailAccount->unread_count = ($emailAccount->unread_count ?: 0) + 1;
            $emailAccount->save();

            $messageRead->delete();
        }
    }

    public function setSummaryFrom($text)
    {
        $this->summary = safeTruncate($text);
    }

    public function delete()
    {
        $this->attachments->each->delete();
        $this->messages->each->delete();
        $this->distributions()->delete();
        $this->reads()->delete();

        parent::delete();
    }

    public function addPrefix()
    {
        if ($this->isReply()) {
            $this->subject = 'RE: '.$this->subject;
        }

        if ($this->isForward()) {
            $this->subject = 'FW: '.$this->subject;
        }
    }

    /* ELEMENTS */
    public static function editor()
    {
        return _CKEditor()->name('html')->prependToolbar(['fontColor', 'fontBackgroundColor', 'imageUpload', 'imageResize'])
            ->class('email-ckeditor mb-0');
    }

    public static function sendDropdown($action = null)
    {
        $button = _SubmitButton('Send');

        return $action ? $action($button) : $button;
    }

    public static function draftButton($action = null)
    {
        $link = _Link()->icon(_Sax('document-text'))->balloon('mail.save-as-draft', 'up')
            ->submitWith([
                'is_draft' => 1,
            ])->alert('messaging.draft-saved');

        return $action ? $action($link) : $link;
    }
}
