<?php

namespace Condoedge\Messaging\Models\CustomInbox;

use App\Mail\CommunicationNotification;
use App\Mail\CondoedgeTeamNotification;
use App\Mail\MeetingNoticeMail;
use App\Mailboxes\ThreadMaker;
use App\Models\Contact\Contact;
use App\Models\File;
use App\Models\Messaging\EmailAccount;
use App\Models\Messaging\MessageForward;
use App\Models\Model;
use App\Models\Review;
use App\Models\User;
use App\View\Messaging\RecipientsMultiSelect;
use App\View\Messaging\ThreadGroupsForm;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

class Message extends Model
{
    const DEFAULT_TYPE = 1;
    const REPLY_TYPE = 2;
    const FORWARD_TYPE = 3;
    const INCOMING_TYPE = 4;

    public function save(array $options = [])
    {
        if (!$this->sender_id && !app()->runningInConsole()) {
            $this->sender_id = auth()->user()->getSenderAccountId();
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
            $this->uuid = Str::uuid()->toString();
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
        return $this->belongsTo(Message::class);
    }

    public function messages()
    {
        return $this->hasMany(Message::class);
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
        $emailAccount = auth()->user()->getSenderAccount();

    	return $this->hasOne(MessageRead::class)
            ->where('entity_id', $emailAccount->entity_id)
            ->where('entity_type', $emailAccount->entity_type);
    }

    public function review()
    {
        return $this->hasOne(Review::class)->withTrashed();
    }

    /* SCOPES */
    public function scopeAuthUserAsSender($query, $allMailboxes = false)
    {
        $query->whereIn('sender_id', auth()->user()->getActiveEmailAccountIds($allMailboxes));
    }

    public function scopeAuthUserIncluded($query, $allMailboxes = false)
    {
        $query->authUserAsSender($allMailboxes)
            ->orWhereHas('distributions', fn($q) => $q->authUserAsRecipient($allMailboxes));
    }

    public function scopeAuthUserInDistributions($query, $allMailboxes = false)
    {
        $query->whereHas('distributions', fn($q) => $q->authUserAsRecipient($allMailboxes));
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
        $newSenderId = $newSenderId ?: auth()->user()->getSenderAccountId();
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
        return collect($recipients)->map(function($p){

            [$type, $idOrEmail] = explode('|', $p);

            return EmailAccount::findOrCreateFromType($type, $idOrEmail);

        });
    }

    public static function checkInvalidEmailAddresses()
    {
        $recipients = Message::getRequestRecipients();

        $invalidEmails = Message::transformRecipientsToEmailAccounts($recipients)
            ->filter(fn($emailAccount) => !filter_var(trim($emailAccount->mainEmail()), FILTER_VALIDATE_EMAIL))
            ->map(fn($emailAccount) => $emailAccount->mainEmail());

        if ($invalidEmails->count()) {
            abort(403, '"'.$invalidEmails->first().'" '.__('is not a valid email address! Please correct it and try again.'));
        }
    }

    public static function getRequestRecipients()
    {
        if ($group = request('massive_recipients_group')) {
            $recipients = ThreadGroupsForm::getMatchingRecipients($group);
            return RecipientsMultiSelect::getValidEmailOptionsFromGroup($recipients, $group);
        }

        return request('recipients');
    }

    /* ACTIONS */
    protected function createDistribution($emailAccount)
    {
        $distribution = new Distribution();
        $distribution->emailAccount()->associate($emailAccount);
        $this->distributions()->save($distribution);
    }

    public function addDistribution($emailAccount)
    {
        if ($emailAccount->entity_type == 'user') {
            $this->checkIfMessageIsForwarded($emailAccount);
        }

        $this->createDistribution($emailAccount);
    }

    public function addDistributionFromType($type, $idOrEmail)
    {
        $this->addDistribution(
            EmailAccount::findOrCreateFromType($type, $idOrEmail)
        );
    }

    public function addParticipantsToReply($replyMessage)
    {
        return $this->recipients->concat([$this->sender])->each(function($e) use($replyMessage) {

            if (!$e->belongsToAuthUser()) {
                $replyMessage->addDistribution($e);
            }

        });
    }

    protected function checkIfMessageIsForwarded($emailAccount)
    {
        if ($forward = MessageForward::currentlyActive()->where('user_id', $emailAccount->entity_id)->first()) {

            $message = new static();
            $message->subject = __('email.out-of-office').': '.$emailAccount->mainEmail();
            $message->html = $forward->message;

            if ($forward->forward_to_id) {

                $emailAccount = EmailAccount::findOrCreateFromType('user', $forward->forward_to_id, $this->thread->team_id);

                $this->createDistribution($emailAccount);

            }

            if (trim($message->html)) {
                Mail::to($this->sender->mainEmail())->send(new CondoedgeTeamNotification($message));
            }
        }
    }

    public function addLinkedAttachments()
    {
        collect(request('selected_files'))->each(function($fileId){
            $file = File::find($fileId);
            ThreadMaker::createMessageAttachment(
                $this,
                currentTeam()->id,
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

                    Mail::to(trim($toEmail))
                        ->{$action}(new CommunicationNotification($this, $url));
                })
            );

        }else{
            Mail::to($distributions->map(fn($entity, $toEmail) => $toEmail))
                ->send(new CommunicationNotification($this, $url));
        }
    }

    public function sendMeetingNotice($meeting)
    {
        $distributions = $this->getValidDistributions();

        \DB::transaction(
            fn() => $distributions->each(function($entity, $toEmail) use ($meeting) {

                $entityName = $entity?->name ?: $toEmail;

                $ownedUnitIds = [];

                if ($entity instanceOf User) {
                    $ownedUnitIds = $entity->currentContactUnits()->map(fn($cu) => $cu->unit_id);
                }

                if ($entity instanceOf Contact) {
                    $ownedUnitIds = $entity->currentContactUnits()->pluck('unit_id');
                }

                $voterId = $meeting->voters()->where('parent_type', 'unit')->whereIn('parent_id', $ownedUnitIds)->first();

                if (!$voterId) {
                    \Log::warning('No voter id for entity '.($entity?->id ?: $toEmail).' while sending meeting notice id: '.$meeting->id);
                }

                Mail::to($toEmail)
                    ->queue(new MeetingNoticeMail($this, $voterId, $entityName));
            })
        );
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

            if ($emailAccount->is_mailbox && ($emailAccount->team_id == currentTeam()->id)) {
                //Do not send external mail to same team mailbox...
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
        $mr->setUserId();
        $mr->message_id = $this->id;
        $mr->read_at = now();
        $emailAccount = auth()->user()->getSenderAccount();
        $mr->entity_id = $emailAccount->entity_id;
        $mr->entity_type = $emailAccount->entity_type;
        $mr->save();

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

    public static function bccCheckbox($default = 1)
    {
        return _Checkbox('messaging.bcc')->name('bcc')->default($default)
            ->labelClass('whitespace-nowrap text-lg')
            ->style('transform:scale(0.9);transform-origin:100%;z-index:5')
            ->hint('messaging.bcc-toggle', 'down-right');
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
