<?php

namespace Condoedge\Messaging\Services\GmailApi;

class GmailHelper
{
    public function __construct()
    {
    }

    /* READ MESSAGE */
    public static function requestMessage($gThreadId)
    {
        if (!$gThreadId) {
            return;
        }

        $thread = getGClient()->users_threads->get('me', $gThreadId);

        $messages = $thread->getMessages();

        $processedMessages = [];
        $thread->gAttachmentsCount = 0;

        foreach ($messages as $key => $message) {

            $parsedMessage = static::parseMessage($message);

            $thread->gSubject = $parsedMessage->gSubject;
            $thread->gAttachmentsCount += $parsedMessage->gAttachmentsCount;

            $processedMessages[] = $parsedMessage;
        }

        $thread->messages = $processedMessages;

        return $thread;
    }

    public static function parseMessage($message)
    {
        $payload = $message->getPayload();

        foreach ($payload->getHeaders() as $key => $header) {
            if ($header->getName() == 'Subject') {
                $message->gSubject = $header->getValue();
            } else if ($header->getName() === 'From') {
                $message->gFrom = $header->getValue();
            } else if ($header->getName() === 'Date') {
                $messageDate = $header->getValue();
                //dd($message, $messageDate, strtotime(substr($messageDate,0,25)), substr($messageDate,0,25));
                $messageDate = date('M jS Y h:i A', strtotime(substr($messageDate,0,25)));
                $message->gDate = $messageDate;
            }
        }

        $message->gAttachmentsCount = 0;
        $message->gAttachments = [];

        foreach ($payload->getParts() as $key => $part) {
            $filename = $part->getFilename();
            $body = $part->getBody();
            $mimeType = $part->getMimeType();
            if ($filename && $body->getAttachmentId()) { // It's an attachment
                $message->gAttachmentsCount ++;
                $message->gAttachments[] = [
                    'id' => $body->getAttachmentId(),
                    'filename' => $filename,
                    'mimeType' => $mimeType,
                    'size' => $body->getSize(),
                ];
            }
        }

        $message->gBody = static::getHtmlBody($payload);

        return $message;
    }

    /* CREATE MESSAGE */

    /* UPDATE MESSAGE */

    /*** MAILBOXES ***/


    /*** CUSTOM PROPS ***/

    /*** MOVING MESSAGES (Archive / Delete) ***/

    /*** ATTACHMENTS ***/
    public static function downloadMessageAttachment($messageId, $attId)
    {
        $attachment = getGClient()->users_messages_attachments->get('me', $messageId, $attId);

        return $attachment;
    }

    /** UTILITIES **/
    protected static function getHtmlBody($payload) 
    {
        $parts = $payload->getParts();

        // If there are no parts, the body might be directly in the payload
        if (!$parts) {
            if ($payload->getMimeType() === 'text/html') {
                return static::decodeBody($payload->getBody()->getData());
            }
            return '';
        }

        // Traverse parts recursively to find text/html
        foreach ($parts as $part) {
            if ($part->getMimeType() === 'text/html') {
                return static::decodeBody($part->getBody()->getData());
            }

            // Recursively search nested parts
            if ($part->getParts()) {
                $result = static::getHtmlBody($part);
                if (!empty($result)) {
                    return $result;
                }
            }
        }

        return '';
    }

    protected static function decodeBody($data) 
    {
        $data = str_replace(['-', '_'], ['+', '/'], $data); // base64url decode
        return base64_decode($data);
    }
}