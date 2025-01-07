<?php 

use Microsoft\Graph\Graph;
use App\Models\Team;

function getGraph()
{
    $graph = new Graph();
    $graph->setAccessToken(getCurrentUserAccessToken());
    return $graph;
}

function getCurrentUserToken()
{
    return auth()->user()?->currentOutlookToken;
}

function getCurrentUserAccessToken()
{
    return getCurrentUserToken()?->getOrRefreshToken() ?: '';
}

/* ACTIONS */
function checkRecipientsAreValid()
{
    $emails = Message::getRequestRecipients();

    $invalidEmails = collect($emails)->filter(fn($email) => !isValidEmail($email));

    if ($invalidEmails->count()) {
        abort(403, '"'.$invalidEmails->first().'" '.__('is not a valid email address! Please correct it and try again.'));
    }
}

function isValidEmail($email)
{
    return filter_var(trim($email), FILTER_VALIDATE_EMAIL);
}

function searchOutlookRecipients($search)
{
    $existing = Team::newMatchEmails($search);

    if (!$existing->count() && filter_var($search, FILTER_VALIDATE_EMAIL)){
        return [
            $search => _Html($search)
        ];
    }

    return $existing->mapWithKeys(
        fn($entity) => [
            $entity->mainEmail() => $entity->getEmailOption()
        ]
    );
}

/* ELEMENTS */
function _RecipientsMultiSelect()
{
    return _MultiSelect()->placeholder('messaging.search-recipients')->class('recipients-multiselect mb-2')
        ->name('recipients', false)
        ->searchOptions(2);
}
function _CcToggle()
{
    return _Rows(
        _Link('CC / BCC')->toggleId('cc-bb-recipients')->class('mb-2 text-gray-700 text-xs'),
        _Rows(
            _CcRecipientsMultiSelect(),
            _BccRecipientsMultiSelect(),
        )->id('cc-bb-recipients'),
    );
}
function _CcRecipientsMultiSelect()
{
    return _MultiSelect()->placeholder('cc:')->class('recipients-multiselect mb-2')
        ->name('cc_recipients', false)
        ->searchOptions(2);
}
function _BccRecipientsMultiSelect()
{
    return _MultiSelect()->placeholder('bcc:')->class('recipients-multiselect mb-4')
        ->name('bcc_recipients', false)
        ->searchOptions(2);
}

function _MessageAttachments($messageId, $attachments)
{
    if (!$attachments || !$attachments->count()) {
        return;
    }
    return _Flex(
        $attachments->map(fn($attachment) => _MessageAttachment($messageId, $attachment))
    )->class('flex-wrap mt-2');
}

function _MessageAttachment($messageId, $attachment)
{
    return _ThumbWrapper([
        thumbStyle(
            _Html('<i class="'.getIconFromMimeType($attachment->getContentType()).'"></i>')->class('text-2xl text-center text-gray-700')
        )->class('mb-2 flex flex-center group2-hover:hidden'),
        thumbStyle(
            _Flex2(
                _Link()->icon('document-add')->balloon('save-as', 'down-left')
                    ->get('outlook-attachment.save', [
                        'message_id' => $messageId,
                        'att_id' => $attachment->getId(),
                    ])
                    ->inPopup(),
                _Link()->icon('download')->balloon('Download', 'down-right')->class('text-xl')
                    ->href('outlook.download', [
                        'message_id' => $messageId,
                        'att_id' => $attachment->getId(),
                    ])->inNewTab(),
            ),
        )->class('mb-2 hidden flex-center group2-hover:flex'),

        _Html($attachment->getName())->class('text-xs font-semibold truncate'),

        _Html(getReadableSize($attachment->getSize()))->class('text-xs text-gray-700 font-bold'),

    ])->class('p-2 border border-gray-100')
    ->balloon($attachment->getName(), 'right');
}