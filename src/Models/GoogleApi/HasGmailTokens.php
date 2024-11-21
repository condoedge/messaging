<?php 

namespace App\Models\GoogleApi;

trait HasGoogleTokenTokens
{

    /* RELATIONSHIPS */
    public function currentGoogleToken()
    {
        return $this->belongsTo(GoogleToken::class, 'current_google_id');
    }

    public function googleTokens()
    {
        return $this->hasMany(GoogleToken::class);
    }

    /* ACTIONS */
    public function setCurrentGoogleTokenToken($gtId)
    {
        $this->current_google_id = $gtId;
        $this->save();
    }

}