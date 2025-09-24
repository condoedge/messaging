<?php

namespace Condoedge\Messaging\Services\MicrosoftGraph;

use Microsoft\Graph\Model;

class GraphHelper
{
    public const CUSTOM_PROP_PREFIX = 'String {66f5a359-4659-4830-9070-00040ec6ac6e} Name ';
    public const CUSTOM_PROP_UNION = 'Union';
    public const CUSTOM_PROP_UNIT = 'Unit';
    public const CUSTOM_PROP_TAGS = 'Tags';

    public function __construct()
    {
    }

    /* READ MESSAGE */
    public static function requestMessage($gMessageId, $select = null)
    {
        if (!$gMessageId) {
            return;
        }

        $url = '/me/messages/'.$gMessageId;

        $params['$expand'] = 'singleValueExtendedProperties($filter=id eq \''.static::CUSTOM_PROP_PREFIX.static::CUSTOM_PROP_UNION.'\' or id eq \''.static::CUSTOM_PROP_PREFIX.static::CUSTOM_PROP_UNIT.'\' or id eq \''.static::CUSTOM_PROP_PREFIX.static::CUSTOM_PROP_TAGS.'\')';

        if ($select) {
            $params['$select'] = $select;
        }
        $url .= '?'.http_build_query($params);

        $message = getGraph()->createRequest('GET', $url)
            ->addHeaders(array("Content-Type" => "application/json"))
            ->setReturnType(Model\Message::class)
            ->execute();

        $message->mgh_sender = $message->getSender()?->getEmailAddress();
        $message->mgh_sender_name = $message->mgh_sender?->getName();
        $message->mgh_sender_email = $message->mgh_sender?->getAddress();

        $customProperties = collect($message->getSingleValueExtendedProperties())->mapWithKeys(fn($cp) => [
            $cp['id'] => $cp['value'],
        ]);

        $message->mgh_union_id = $customProperties[static::CUSTOM_PROP_PREFIX.static::CUSTOM_PROP_UNION] ?? null;
        $message->mgh_unit_id = $customProperties[static::CUSTOM_PROP_PREFIX.static::CUSTOM_PROP_UNIT] ?? null;
        $message->mgh_tags_id = $customProperties[static::CUSTOM_PROP_PREFIX.static::CUSTOM_PROP_TAGS] ?? null;

        return $message;
    }

    public static function requestMessageHeaders($gMessageId)
    {
        $headersResp = static::requestMessage($gMessageId, 'InternetMessageHeaders');

        return collect($headersResp->getInternetMessageHeaders())->mapWithKeys(fn($arr) => [$arr['name'] => $arr['value']]);
    }

    /* CREATE MESSAGE */
    protected static function createOutlookMessage()
    {
        $message = new Model\Message();
        
        if (request('subject')) {
            $message->setSubject(request('subject'));
        }
        
        if (request('html')) {
            $messageBody = new Model\ItemBody();
            $messageBody->setContentType(new Model\BodyType('html'));
            $messageBody->setContent(request('html'));
            $message->setBody($messageBody);
        }

        if (request('recipients')) {
            $toRecipients = [];
            foreach (request('recipients') ?: [] as $email) {
                
                $toRecipients[] = static::createRecipient($email);
            }
            $message->setToRecipients($toRecipients);
            //$message->setCcRecipients($ccRecipients);
        }

        if (request('cc_recipients')) {
            $ccRecipients = [];
            foreach (request('cc_recipients') ?: [] as $email) {
                
                $ccRecipients[] = static::createRecipient($email);
            }
            $message->setCcRecipients($ccRecipients);
        }

        if (request('bcc_recipients')) {
            $bccRecipients = [];
            foreach (request('bcc_recipients') ?: [] as $email) {
                
                $bccRecipients[] = static::createRecipient($email);
            }
            $message->setBccRecipients($bccRecipients);
        }

        if (request('attachments')) {
            $attachments = [];
            foreach (request('attachments') ?: [] as $uploadFile) {     
                $att = new Model\FileAttachment();
                $att->setOdataType('#microsoft.graph.fileAttachment');
                $att->setName($uploadFile->getClientOriginalName());
                $att->setContentType($uploadFile->getClientMimeType());
                $att->setContentBytes(base64_encode(file_get_contents($uploadFile->getRealPath())));
                $attachments []= $att;
            }
            $message->setAttachments($attachments);
        }

        $messageHeaders = [];
        if (request('union_id')) {
            $messageHeaders[] = static::createHeaderTag('x-ce-union-id', request('union_id'));
        }
        if (request('unit_id')) {
            $messageHeaders[] = static::createHeaderTag('x-ce-unit-id', request('unit_id'));
        }
        if (count($messageHeaders)) {
            $message->setInternetMessageHeaders($messageHeaders);
        }

        //dd($message);

        return $message;
    }

    public static function sendEmail()
    {
        $message = static::createOutlookMessage();

        $sentMessage = getGraph()->createRequest("POST", "/me/sendmail")
            ->addHeaders(array("Content-Type" => "application/json"))
            ->attachBody(['message' => $message])
            ->execute();

        return $sentMessage;
    }

    public static function createDraft()
    {
        $message = static::createOutlookMessage();

        $draftMessage = getGraph()->createRequest("POST", "/me/messages")
            ->addHeaders(array("Content-Type" => "application/json"))
            ->attachBody($message)
            ->execute();

        return $draftMessage;
    }


    protected static function createRecipient($email)
    {
        $toRecipient = new Model\Recipient();
        $toRecipientEmail = new Model\EmailAddress();
        $toRecipientEmail->setAddress($email);
        $toRecipient->setEmailAddress($toRecipientEmail);

        return $toRecipient;
    }

    protected static function createHeaderTag($key, $value)
    {
        $tag = new Model\InternetMessageHeader();
        $tag->setName($key);
        $tag->setValue($value);

        return $tag;
    }

    public static function checkEmailRecipients()
    {
        $recipients = collect(request('recipients') ?: [])->concat(request('cc_recipients') ?: [])->concat(request('bcc_recipients') ?: [])->filter();

        $invalidEmails = collect($recipients)->filter(fn($email) => !filter_var(trim($email), FILTER_VALIDATE_EMAIL));

        if ($invalidEmails->count()) {
            abort(403, '"'.$invalidEmails->first().'" '.__('messaging-is-not-a-valid-email-address'));
        }
    }

    /* UPDATE MESSAGE */
    public static function updateMessage($gMessageId, $key, $value)
    {
        if (!$gMessageId) {
            return;
        }

        $url = '/me/messages/'.$gMessageId;

        return getGraph()->createRequest('PATCH', $url)
            ->addHeaders(array("Content-Type" => "application/json"))
            ->attachBody([$key => $value])
            ->execute();
    }

    public static function markMessageAsRead($gMessageId)
    {
        return static::updateMessage($gMessageId, 'isRead', true);
    }

    public static function markMessageAsUnread($gMessageId)
    {
        return static::updateMessage($gMessageId, 'isRead', false);
    }

    /*** MAILBOXES ***/
    public static function getMailboxes()
    {
        $mailboxes = getGraph()->createRequest('GET', '/me/mailFolders')
            ->setReturnType(Model\MailFolder::class)
            ->execute();

        return collect($mailboxes);
    }

    public static function getUnreadCount($mailFolder = 'Inbox')
    {
        $url = '/me/mailFolders/'.$mailFolder;
        $params['$select'] = "unreadItemCount";

        $url .= '?'.http_build_query($params);

        $unread = getGraph()->createRequest('GET', $url)->execute();

        return $unread->getBody()['unreadItemCount'] ?? 'N/A';
    }


    /*** CUSTOM PROPS ***/
    public static function setUnionCustomProperty($gMessageId, $value)
    {
        return static::createCustomProperty($gMessageId, static::CUSTOM_PROP_UNION, $value);
    }

    public static function setUnitCustomProperty($gMessageId, $value)
    {
        return static::createCustomProperty($gMessageId, static::CUSTOM_PROP_UNIT, $value);
    }

    public static function setTagsCustomProperty($gMessageId, $value)
    {
        return static::createCustomProperty($gMessageId, static::CUSTOM_PROP_TAGS, $value);
    }

    public static function createCustomProperty($gMessageId, $key, $value)
    {   
        //MORE INFOS
        //https://stackoverflow.com/questions/74304476/filter-mail-by-custom-internetmessageheaders
        return static::updateMessage($gMessageId, 'singleValueExtendedProperties', [
            [
                'id' => 'String {66f5a359-4659-4830-9070-00040ec6ac6e} Name '.$key,
                'value' => $value,
            ]
        ]);
    }

    /*** MOVING MESSAGES (Archive / Delete) ***/
    public static function archiveMessage($gMessageId)
    {
        return static::moveMessage($gMessageId, 'Archive');
    }
    public static function unarchiveMessage($gMessageId)
    {
        return static::moveMessage($gMessageId, 'Inbox');
    }
    public static function trashMessage($gMessageId)
    {
        return static::moveMessage($gMessageId, 'DeletedItems');
    }
    public static function untrashMessage($gMessageId)
    {
        return static::moveMessage($gMessageId, 'Inbox');
    }

    public static function moveMessage($gMessageId, $destinationFolder)
    {
        if (!$gMessageId) {
            return;
        }

        $url = '/me/messages/'.$gMessageId.'/move';

        return getGraph()->createRequest('POST', $url)
            ->addHeaders(array("Content-Type" => "application/json"))
            ->attachBody(['destinationId' => $destinationFolder])
            ->execute();
    }

    /*** ATTACHMENTS ***/    
    public static function requestMessageAttachments($gMessageId)
    {
        if (!$gMessageId) {
            return;
        }

        $url = '/me/messages/'.$gMessageId.'/attachments';

        $attachments = getGraph()->createRequest('GET', $url)
            ->addHeaders(array("Content-Type" => "application/json"))
            ->setReturnType(Model\FileAttachment::class)
            ->execute();

        return collect($attachments);
    }

    public static function downloadMessageAttachment($gMessageId, $attId)
    {
        if (!$gMessageId || !$attId) {
            return;
        }

        $url = '/me/messages/'.$gMessageId.'/attachments/'.$attId;

        return getGraph()->createRequest('GET', $url)
            ->addHeaders(array("Content-Type" => "application/json"))
            ->setReturnType(Model\FileAttachment::class)
            ->execute();
    }
}