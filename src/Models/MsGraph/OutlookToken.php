<?php

namespace Condoedge\Messaging\Models\MsGraph;

use App\Models\Model;
use Condoedge\Messaging\Services\MicrosoftGraph\GraphHelper;

class OutlookToken extends Model
{
    use \App\Models\Traits\BelongsToUser;
    use \Illuminate\Database\Eloquent\SoftDeletes;

    /* RELATIONS */

    /* CALCULATED FIELDS */
    public function isEmptyOrNotUsable()
    {
        return !$this->access_token || !$this->refresh_token || !$this->token_expires;
    }

    public function getOrRefreshToken()
    {
        if ($this->isEmptyOrNotUsable()) {
            return '';
        }
    
        if ($this->token_expires <= time() + 300) { // Token is expired (or very close to it), so let's refresh
            
            $oauthClient = new \League\OAuth2\Client\Provider\GenericProvider([
                'clientId'                => config('azure.appId'),
                'clientSecret'            => config('azure.appSecret'),
                'redirectUri'             => config('azure.redirectUri'),
                'urlAuthorize'            => config('azure.authority').config('azure.authorizeEndpoint'),
                'urlAccessToken'          => config('azure.authority').config('azure.tokenEndpoint'),
                'urlResourceOwnerDetails' => '',
                'scopes'                  => config('azure.scopes')
            ]);

            try {
                $newToken = $oauthClient->getAccessToken('refresh_token', [
                  'refresh_token' => $this->refresh_token
                ]);

                $this->updateMsTokens($newToken);

                return $newToken->getToken();
            } catch (\League\OAuth2\Client\Provider\Exception\IdentityProviderException $e) {
                return '';
            }
        }
    
        return $this->access_token; // Token is still valid, just return it
    }

    public function isArchived($gMessage)
    {
        return $this->archived_mailbox_id == $gMessage?->getParentFolderId();
    }

    public function isTrashed($gMessage)
    {
        return $this->deleted_mailbox_id == $gMessage?->getParentFolderId();
    }

    /* ACTIONS */
    public function updateMsTokens($newToken)
    {
        $this->access_token = $newToken->getToken();
        $this->refresh_token = $newToken->getRefreshToken();
        $this->token_expires = $newToken->getExpires();

        $this->save();
    }

    public static function storeMsTokens($accessToken, $user) 
    {
        $userEmail = null !== $user->getMail() ? $user->getMail() : $user->getUserPrincipalName();

        //GET OR CREATE TOKEN
        $ot = OutlookToken::forAuthUser()->where('user_email', $userEmail)->first();

        if (!$ot) {
            $ot = new OutlookToken();
            $ot->user_id = auth()->id();
            $ot->user_email = $userEmail;
        }

        //UPDATE TOKEN
        $ot->ms_user_id = $user->getId();
        $ot->display_name = $user->getDisplayName();
        $ot->user_timezone = $user->getMailboxSettings()->getTimeZone();

        $ot->updateMsTokens($accessToken);

        //SET TOKEN ON AUTH USER
        auth()->user()->setCurrentOutlookToken($ot->id);

        $mailboxes = GraphHelper::getMailboxes()->mapWithKeys(fn($mailbox) => [
            $mailbox->getDisplayName() => $mailbox->getId(),
        ]);

        $ot->inbox_mailbox_id = $mailboxes['Inbox'] ?? null;
        $ot->sent_mailbox_id = $mailboxes['Sent Items'] ?? null;
        $ot->archived_mailbox_id = $mailboxes['Archive'] ?? null;
        $ot->deleted_mailbox_id = $mailboxes['Deleted Items'] ?? null;
        $ot->draft_mailbox_id = $mailboxes['Drafts'] ?? null;
        $ot->save();
    }
}
