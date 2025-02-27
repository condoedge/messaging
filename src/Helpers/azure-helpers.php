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