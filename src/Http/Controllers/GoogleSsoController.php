<?php

namespace Condoedge\Surveys\Http\Controllers;

use Condoedge\Surveys\Models\GoogleApi\GoogleToken;
use Illuminate\Http\Request;

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
                $client->authenticate($code);
                $accessToken = $client->getAccessToken();
                $client->setAccessToken($accessToken);

                //TODO SEE OUTLOOK GET /me?$select=displayName,mail,mailboxSettings,userPrincipalName
                $user = null; /*$graph->createRequest('GET', '/me?$select=displayName,mail,mailboxSettings,userPrincipalName')
                  ->setReturnType(Model\User::class)
                  ->execute();*/

                dd($accessToken, $client, $user);

                GoogleToken::storeGtTokens($accessToken, $user);

                return redirect('new-inbox');
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
        $ot = getCurrentUserToken();

        if ($ot) {
            auth()->user()->setCurrentOutlookToken(null);
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
