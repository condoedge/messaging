<?php 

namespace Condoedge\Messaging\Models\MsGraph;

trait HasOutlookTokens
{

    /* RELATIONSHIPS */
    public function currentOutlookToken()
    {
        return $this->belongsTo(OutlookToken::class, 'current_outlook_id');
    }

    public function outlookTokens()
    {
        return $this->hasMany(OutlookToken::class);
    }

    /* ACTIONS */
    public function setCurrentOutlookToken($otId)
    {
        $this->current_outlook_id = $otId;
        $this->save();
    }

}