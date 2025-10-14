<?php

namespace Condoedge\Messaging\Models\CustomInbox;

use Condoedge\Utils\Models\Model;

class Signature extends Model
{
    protected $casts = [
    	'image' => 'array'
    ];

    protected $fillable = [
        'is_auto_insert',
    ];

    /* ACTIONS */
    public static function appendToMessage($message)
    {
        $signature = null;

        if(request('signature_id')){
            $signature = currentMailbox()->signatures()->where('id', request('signature_id'))->first();
        } else if (currentMailbox()->getAutoInsertSignature()) {
            $signature = currentMailbox()->getAutoInsertSignature();
        }

        $message->html .= $signature ? $signature->toHtml() : '';
    }

    public function toHtml()
    {
        $maxWidth = 100;
        $maxHeight = 70;

    	return '<table style="margin-top:1rem"><tr>'.
    		'<td style="vertical-align:top; padding-right:0.8rem">'.
                (
                    $this->image ?

                    '<img src="'.$this->getImageUrl().'" style="margin-right:10px;'.$this->getDimensionsStyle().'" alt="signature-image">' :

                    ''
                ).
            '</td>'.
    		($this->only_image ? '' : ('<td>'.$this->html.'</td>')).
    		'</tr></table>';
    }

    public function getImageUrl()
    {
        $disk = $this->image['disk'] ?? null;
        $path = $this->image['path'] ?? null;

        if (!$disk || !$path) {
            return;
        }

        return \Storage::disk($disk)->url($path);
    }

    protected function getDimensionsStyle()
    {
        if ($this->width) {
            return 'width:'.$this->width.'px';
        }

        return $this->only_image ? 'width:80vw;max-width:768px' : 'max-width:100px;max-height:70px';
    }

    public function deletable()
    {
        return $this->email_account_id == currentMailboxId();
    }

    /* ELEMENTS */
}
