<?php

namespace Condoedge\Messaging\Mail;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class ExternalEmailNotification extends Mailable
{
    use Queueable, SerializesModels;

    protected $message;

    protected $url;
    protected $sendFromMail;
    protected $sendFromName;

    public function __construct($message, $url = null)
    {
        $this->message = $message;

        $this->url = $url;

        $this->setFromInfo();
    }

    protected function setFromInfo()
    {
        $senderEntity = $this->message->sender;

        $this->sendFromMail = $this->message->sender->email_adr;
        $this->sendFromName = $this->message->sender->entity->name;

        if (!$this->sendFromMail) {
            \Log::warning('No mailbox for user '.auth()->id().' while sending message id: '.$this->message->id);
        }
    }

    /**
     * Build the message.
     *
     * @return $this
     */
    public function build()
    {
        $this->addAttachments($this->message);

        $html = $this->message->html;

        //Add button if needed
        if ($this->url) {
            $html .= view('vendor.mail.html.button', [
                'url' => $this->url,
                'slot' => __('mail.view-on-condoedge-portal'),
            ])->render();
        }
        $html .= '<div style="font-size:12px;color:gray;margin:5px 0;text-align:center">'.__('mail.you-can-also-reply-directly').'</div>';

        $html .= $this->message->getParentMessageExtraHtml();

        return $this->subject($this->message->subject)
            ->markdown('condoedge-messaging::mails.communication-notification')
            ->from($this->sendFromMail, $this->sendFromName)
            ->with([
                'subject' => $this->message->subject,
                'html' => $html,
                'url' => $this->url,
                'uuid' => $this->message->uuid,
            ]);
    }

    protected function addAttachments($message)
    {
        $message->load('attachments');
        
        return $message->attachments->each(function($attm){
            $this->attach(\Storage::disk($attm->disk)->path($attm->storagePath()), ['as' => $attm->name]);
        });
    }
}
