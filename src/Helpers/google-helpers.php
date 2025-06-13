<?php 

use Google\Client;
use Google\Service;

function initGClient()
{
    $client = new Client();

    $client->setClientId(env('GMAIL_CLIENT_ID'));
    $client->setClientSecret(env('GMAIL_CLIENT_SECRET'));
    $client->setRedirectUri(route('google-sso-return'));

    $client->addScope(Service\Gmail::MAIL_GOOGLE_COM);
    $client->addScope(Service\Oauth2::USERINFO_EMAIL);
    $client->setAccessType('offline');

    return $client;
}

function getGClient()
{
    $client = new Client();
    $client->setAccessToken(getCurrentGoogleAccessToken());

    return new \Google\Service\Gmail($client);
}

function getCurrentGoogleToken()
{
    return auth()->user()?->currentGoogleToken;
}

function getCurrentGoogleAccessToken()
{
    return getCurrentGoogleToken()?->getOrRefreshToken() ?: '';
}

/* ACTIONS */

/* ELEMENTS */
function _GmailMessageAttachments($message)
{
    if (!$message->gAttachmentsCount) {
        return;
    }

    return _Flex(
        collect($message->gAttachments)->map(fn($attachment) => _GmailMessageAttachment($message->getId(), $attachment))
    )->class('flex-wrap mt-2');
}

function _GmailMessageAttachment($messageId, $attachment)
{
    return _ThumbWrapper([
        thumbStyle(
            _Html('<i class="'.getIconFromMimeType($attachment['mimeType']).'"></i>')->class('text-2xl text-center text-gray-700')
        )->class('mb-2 flex flex-center group2-hover:hidden'),
        thumbStyle(
            _Flex2(
                _Link()->icon('document-add')->balloon('save-as', 'down-left')
                    ->get('gmail-attachment.save', [
                        'message_id' => $messageId,
                        'att_id' => $attachment['id'],
                    ])
                    ->inPopup(),
                _Link()->icon('download')->balloon('Download', 'down-right')->class('text-xl')
                    ->href('gmail.download', [
                        'message_id' => $messageId,
                        'att_id' => $attachment['id'],
                        'filename' => $attachment['filename'],
                        'mimeType' => $attachment['mimeType'],
                    ])->inNewTab(),
            ),
        )->class('mb-2 hidden flex-center group2-hover:flex'),

        _Html($attachment['filename'])->class('text-xs font-semibold truncate'),

        _Html(getReadableSize($attachment['size']))->class('text-xs text-gray-700 font-bold'),

    ])->class('p-2 border border-gray-100')
    ->balloon($attachment['filename'], 'right');
}