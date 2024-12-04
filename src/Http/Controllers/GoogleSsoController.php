<?php

namespace Condoedge\Messaging\Http\Controllers;

use Condoedge\Messaging\Models\GoogleApi\GoogleToken;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;

class GoogleSsoController extends Controller
{
    public function redirectToSso()
    {
        $client = initGClient();

        return redirect($client->createAuthUrl());
    }

    public function returnFromSso(Request $request)
    {
        $authCode = request('code');
        if (isset($authCode)) {

            try {
            
                $client = initGClient();
                $client->authenticate($authCode);
                $accessToken = $client->getAccessToken();
                $client->setAccessToken($accessToken);

                $oauth2 = new \Google\Service\Oauth2($client);
                $user = $oauth2->userinfo_v2_me->get();

                GoogleToken::storeGtTokens($accessToken, $user);

                return redirect('gmail-inbox');
            }
            catch (\League\OAuth2\Client\Provider\Exception\IdentityProviderException $e) {
                return redirect('/')
                  ->with('error', 'Error requesting access token')
                  ->with('errorDetail', json_encode($e->getResponseBody()));
            }
        }

        return redirect('/')
            ->with('error', $request->query('error'))
            ->with('errorDetail', $request->query('error_description'));
    }

    public function signout()
    {
        $ot = getCurrentGoogleToken();

        if ($ot) {
            auth()->user()->setCurrentGoogleTokenToken(null);
            $ot->delete();
        }

        return redirect('dashboard');
    }

    public function changeOutlookToken($id)
    {
        $ot = OutlookToken::findOrFail($id);

        if (auth()->id() !== $ot->user_id) {
            abort(403, __('Action not allowed!'));
        }

        auth()->user()->setCurrentOutlookToken($id);

        return redirect()->route('new-inbox');
    }

    public function resetOutlookToken()
    {        
        auth()->user()->setCurrentOutlookToken(null);

        return redirect()->route('new-inbox');
    }
}
