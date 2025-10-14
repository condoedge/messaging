<?php 

/* ELEMENTS */
function getSignatureActionButtons($signaturePanelId)
{
    return _Flex4(
        _Panel(
            _Toggle('messaging-include-signature')->name('signature', false)->class('mb-0 w-48')
                ->default(currentMailbox()->getAutoInsertSignature()),
        )->id($signaturePanelId),
        _Link('messaging-edit')->class('text-xs text-level1 underline hidden md:block')
            ->get('message-signatures', ['panel_id' => $signaturePanelId])
            ->inModal(),
    )->id('message-signature-panel')->class('mt-2');
}


function showSignatureOptionsLink()
{
    return _Link()->icon(_Sax('pen-add'))->balloon('messaging-include-signature', 'up')->attr(['data-balloon-length' => 'medium'])
        ->toggleId('message-signature-panel');
}