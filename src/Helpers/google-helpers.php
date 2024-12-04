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
    return $client;
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