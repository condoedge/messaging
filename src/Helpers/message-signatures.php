<?php 

/* ACTIONS */
function setAutoInsertSignature($id)
{
	$signature = Signature::findOrFail($id);
	$signature->is_auto_insert = 1;
	$signature->save();
}

function removeAutoInsertSignatures()
{
	currentMailbox()->signatures()->update([
		'is_auto_insert' => null,
	]);
}

/* ELEMENTS */
function getSignatureActionButtons($signaturePanelId)
{
    return _Flex4(
        _Panel(
            _Toggle('messaging.include-signature')->name('signature', false)->class('mb-0 w-48')
                ->default(currentMailbox()->getAutoInsertSignature()),
        )->id($signaturePanelId),
        _Link('Edit')->class('text-xs text-level1 underline hidden md:block')
            ->get('message-signatures', ['panel_id' => $signaturePanelId])
            ->inModal(),
    )->id('message-signature-panel');
}


function showSignatureOptionsLink()
{
    return _Link()->icon(_Sax('pen-add'))->balloon('messaging.include-signature', 'up')
        ->toggleId('message-signature-panel');
}