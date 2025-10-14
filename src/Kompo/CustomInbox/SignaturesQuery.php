<?php

namespace Condoedge\Messaging\Kompo\CustomInbox;

use Condoedge\Messaging\Models\CustomInbox\Signature;
use Condoedge\Utils\Kompo\Common\Query;

class SignaturesQuery extends Query
{
    public $perPage = 15;
    public $noItemsFound = '';

    public $class = 'overflow-y-auto mini-scroll';
    public $style = 'max-height: 95vh; width:95vw;max-width:960px';

    protected $signaturePanelId;

    public function created()
    {
        $this->id('message-signatures-query');

        $this->signaturePanelId = $this->parameter('panel_id');
    }

    public function query()
    {
        return currentMailbox()->signatures();
    }

    public function top()
    {
        return _ModalHeader(
            _Html('messaging-signatures')->miniTitle()
        );
    }

    public function bottom()
    {
        return _Button('messaging-add-signature')
            ->icon('icon-plus')->outlined()
            ->selfCreate('getMessageSignatureForm')
            ->inPanel('signature-preview-panel')
            ->class('m-4');
    }

    public function right()
    {
        return _Rows(
            _Panel(
                _DashedBox('messaging-preview-edit-signature')->id('main-dashed-box')
            )->id('signature-preview-panel')
            ->class('p-4')
        )->class('ml-0')
        ->style('width: 50vw;max-width:600px');
    }

    public function render($signature)
    {
        return _FlexBetween(
            _Flex2(
                $signature->is_auto_insert ?
                    _Html()->icon('star')
                        ->balloon('messaging.default-signature', 'right') : _Html('&nbsp; &nbsp; &nbsp;'),
                _Link($signature->name)
                    ->selfGet('setSignatureId', ['id' => $signature->id])
                    ->inPanel($this->signaturePanelId, true)
                    ->closeModal(),
            ),
            _FlexEnd4(
                _Link('Edit')->icon('icon-edit')
                    ->class('text-gray-700')
                    ->selfUpdate('getMessageSignatureForm', ['id' => $signature->id])
                    ->inPanel('signature-preview-panel'),
                _DeleteLink()->byKey($signature)->class('text-gray-700'),
            )
        )->class('px-4 py-2');
    }

    public function setSignatureId($id)
    {
        $s = Signature::find($id);

        return _Rows(
            _Input('Signature')->icon('pencil')
                ->name('useless_name', false)->readonly()
                ->class('w-40 mb-0')
                ->value($s->name),
            _Hidden('signature_id', false)->value($s->id),
        );
    }

    public function getMessageSignatureForm($id = null)
    {
        return new SignatureForm($id);
    }
}
