<?php

namespace Condoedge\Messaging\Kompo\CustomInbox;

use Condoedge\Messaging\Models\CustomInbox\Signature;
use Condoedge\Utils\Kompo\Common\Form;

class SignatureForm extends Form
{
    public $model = Signature::class;

    protected $refresh = true;

	public function beforeSave()
	{
		$this->model->email_account_id = currentMailboxId();

        if (request('is_auto_insert')) {
            currentMailbox()->signatures()->update(['is_auto_insert' => 0]);
            $this->model->is_auto_insert = 1;
        }

	}

    public function render()
    {
        return _Rows(
            _Input()->placeholder('messaging-signature-name')->name('name'),
            _Html('messaging-signature-elements')->class('text-sm font-semibold text-level3 mb-2'),
            _Rows(
                _Columns(
                	_Image('messaging-signature-image')->name('image')->resize(1024)->thumbHeight('6.7rem')->col('col-md-4')
                        ->comment('messaging-signature-image-sub1'),
                    /* Does not work in outlook :( outlook overwrites it */
                    //_InputNumber('messaging-width-in-pixels-optional')->name('width')->rIcon('<span class="text-gray-300">px</span>')->col('col-md-4'),
                    _Rows(
                        _CKEditor('messaging-signature-body')->name('html')
                    		->toolbar([
                    			'bold', 'italic', 'underline', 'alignment',
                    			'|', 'heading', 'link',
                    			'|', 'fontColor', 'fontBackgroundColor', 'fontSize',
                    		])->id('signature-editor'),
                        _Toggle('messaging-remove-text-only-image')->name('only_image')
                            ->toggleId('signature-editor', $this->model->only_image),    
                        _Toggle('messaging-is-default-signature')->name('is_auto_insert'),                    
                    )->col('col-md-8'),
                ),
            )->class('border border-gray-300 rounded-lg px-6 py-2 mb-4'),
            _SubmitButton('Save')
            	->browse('message-signatures-query')
        );
    }

    public function rules()
    {
        return [
            'width' => 'nullable|numeric',
        ];
    }

}
