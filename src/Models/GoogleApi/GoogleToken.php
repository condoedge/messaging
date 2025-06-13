<?php

namespace Condoedge\Messaging\Models\GoogleApi;

use App\Models\Model;

class GoogleToken extends Model
{
    use \App\Models\Traits\BelongsToUser;
    use \Illuminate\Database\Eloquent\SoftDeletes;

    /* RELATIONS */

    /* CALCULATED FIELDS */
    public function isEmptyOrNotUsable()
    {
        return !$this->access_token || !$this->refresh_token || !$this->token_expires;
    }

    public function shouldRefresh()
    {
        return $this->isEmptyOrNotUsable() || ($this->token_expires <= time() + 300); // Token is expired (or very close to it), so let's refresh
    }

    public function getOrRefreshToken()
    {
        if ($this->isEmptyOrNotUsable()) {
            if ($this->access_token && !$this->refresh_token) { //This case should not happen but if it does we need to restart the process
                initGClient()->revokeToken($this->access_token);
                auth()->user()->setCurrentGoogleTokenToken(null);
                $this->delete();
            }
            return '';
        }
    
        if ($this->shouldRefresh()) {
            
            $client = initGClient();

            try {
                $newToken = $client->fetchAccessTokenWithRefreshToken($this->refresh_token);

                $this->updateGtTokens($newToken);

                return $newToken['access_token'];
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
    public function updateGtTokens($newToken)
    {
        $this->access_token = $newToken['access_token'] ?? null;
        $this->refresh_token = $newToken['refresh_token'] ?? null;
        $this->token_expires = $newToken['created'] ?? null;

        $this->save();
    }

    public static function storeGtTokens($accessToken, $user) 
    {
        $userEmail = $user->getEmail();

        //GET OR CREATE TOKEN
        $ot = GoogleToken::forAuthUser()->where('user_email', $userEmail)->first();

        if (!$ot) {
            $ot = new GoogleToken();
            $ot->user_id = auth()->id();
            $ot->user_email = $userEmail;
        }

        //UPDATE TOKEN
        $ot->gg_user_id = $user->getId();
        $ot->display_name = $user->getGivenName().' '.$user->getFamilyName();
        //$ot->user_timezone = $user->getMailboxSettings()->getTimeZone();

        $ot->updateGtTokens($accessToken);

        //SET TOKEN ON AUTH USER
        auth()->user()->setCurrentGoogleTokenToken($ot->id);

    }
}
