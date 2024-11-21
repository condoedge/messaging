<?php 

use Google\Client;
use Google\Service;

function initGClient()
{
    $client = new Client();

    $client->setClientId(env('GMAIL_CLIENT_ID'));
    $client->setClientSecret(env('GMAIL_CLIENT_SECRET'));
    $client->setRedirectUri(route('gmail-inbox'));

    $client->addScope(Service\Gmail::MAIL_GOOGLE_COM);
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