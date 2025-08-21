<?php

namespace Condoedge\Messaging\IncomingEmail;

use App\Models\Files\File;
use App\Models\Messaging\Attachment;
use App\Models\Messaging\EmailAccount;
use App\Models\Messaging\Message;
use App\Models\Messaging\Thread;
use BeyondCode\Mailbox\Facades\Mailbox;
use Condoedge\Messaging\Models\Incoming\InboundEmail;
use Illuminate\Http\UploadedFile;
use Kompo\Core\FileHandler;

class CatchIncomingEmails
{
    public function __construct()
    {
    }
    
    public static function catchAndDistribute()
    {
        Mailbox::catchAll(function(InboundEmail $email) {

            $emailId = $email->id(); //Had to do this because for some reason, it changed during this call

            //Same email sent to multiple mailboxes should be handled once...
            if ($dupId = InboundEmail::where('message_id', $emailId)->value('id')) {
                \Log::info('Duplicate email: '.$dupId.' with m-ID: '.$emailId);
                abort(200, __('Skipped - Duplicate or Too big'));
            }

            $inboundEmail = InboundEmail::create([
                //'message' => 'Saving...',
                'message' => $email->message, //temp switch again
            ]);
            $inboundEmail->message_id = $emailId; //had to do it in 2 steps because of boot package method
            $inboundEmail->save();

            static::handleIncomingEmail($email);

            \Log::debug('Savin email '.$emailId.' LEN '.strlen($email->message));

            $inboundEmail->update(['message' => $email->message]);

            \Log::debug('Saved email '.$emailId);

            abort(200, __('Saved!'));

        });
    }

    public static function handleIncomingEmail($incomingEmail)
    {
        if (isMailbox($incomingEmail->from())) {
            return 'Skipped - Same team internal mailbox'; //Disabling processing of same team emails sent by the application
        }

        $uuid = $incomingEmail->findUuidInMessage();

        if ($message = Message::where('uuid', $uuid)->first()) {
            return static::saveReplyToMessage($incomingEmail, $message);
        }

        return static::saveEmailToDB($incomingEmail);
    }

    public static function saveEmailToDB($email)
    {
        \DB::transaction(function () use ($email) {

            $thread = new Thread();
            $thread->type = Thread::TYPE_INCOMING;
            $thread->subject = $email->subject() ?: '';
            $thread->created_at = $email->date() ?: now();
            $thread->save();

            $thread->boxes()->delete();

            static::createIncomingMessage($email, $thread);

            $thread->updateStats();
            
        });

        Thread::pusherBroadcast();
    }

    public static function saveReplyToMessage(InboundEmail $email, $message)
    {
        \DB::transaction(function () use ($email, $message) {

            $currentThread = $message->thread;

            if ($message->hasDifferentDistributions($email->getRecipientsEmails(), $email->from())) {
                $currentThread = $currentThread->createNewBranch(Thread::TYPE_INCOMING);
            }

            $currentThread->boxes()->delete();

            static::createIncomingMessage($email, $currentThread);

            $currentThread->updateStats();
            
        });

    }

    public static function createIncomingMessage($email, $thread)
    {
        $messageText = $email->getTextAndSendgridBounce();

        $message = new Message();
        $message->sender_id = EmailAccount::findOrCreateFromEmail($email->from())->id;
        $message->subject = $thread->subject;
        $message->html = $email->getFullHtml() ?: ('<p>'.nl2br($messageText).'</p>');
        $message->setSummaryFrom($messageText);
        $message->thread_id = $thread->id;
        $message->type = Message::INCOMING_TYPE;
        $message->external_id = $email->id();
        $message->save();

        $email->getRecipientsEmails()->each(fn($e) => $message->addDistributionFromEmail($e));

        static::createIncomingAttachments($email->attachments(), $message);
    }

    public static function createIncomingAttachments($attachments, $message)
    {
        collect($attachments)->each(function ($attm) use ($message) {

            if($attm instanceOf UploadedFile){
                
                $fileInfo = (new FileHandler)->fileToDB($attm, $message);

                Attachment::createAttachmentFromFile(
                    $message, 
                    $fileInfo['name'],
                    $fileInfo['mime_type'],
                    $fileInfo['path']
                );

            }else if($attm instanceOf File){

                Attachment::createAttachmentFromFile(
                    $message, 
                    $attm->name,
                    $attm->mime_type,
                    $attm->path,
                );

            }else{

                $origFilename = $attm->getFilename();
                $ext = \Str::afterLast($origFilename, '.');
                $filename = \Str::uuid()->toString().($ext ? ('.'.$ext) : '');
                $path = "mysql/attachments/path";
                \Storage::disk('local')->makeDirectory("{$path}", 0755, true);
                try {
                    $attm->saveContent(storage_path("app/{$path}/{$filename}"));                    
                } catch (\Throwable $e) {
                    \Log::info("Error saving attm in message: ".$message->id." - PATH: app/{$path}/{$filename}");                    
                }
                
                $attmModel = Attachment::createAttachmentFromFile(
                    $message, 
                    $origFilename,
                    $attm->getContentType(),
                    $path.'/'.$filename
                );

                if($attm->getContentDisposition() == 'inline'){
                    $message->html = str_replace(
                        'cid:'.$attm->getHeaderValue('Content-Id'), 
                        $attmModel->getDisplayRoute(), 
                        $message->html
                    );
                }
            }
        });

        $message->save(); //Resave message after attachments inline replacement
    }
}
