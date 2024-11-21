<?php 

use Google;
use App\Models\Team;

function initGClient()
{
    $client = new Google\Client();

    $client->setClientId(env('GMAIL_CLIENT_ID'));
    $client->setClientSecret(env('GMAIL_CLIENT_SECRET'));
    $client->setRedirectUri(route('gmail-inbox'));

    $client->addScope(Google\Service\Gmail::MAIL_GOOGLE_COM);
    $client->setAccessType('offline');

    return $client;
}

function getGClient()
{
    $graph = new Graph();
    $graph->setAccessToken(getCurrentUserAccessToken());
    return $graph;
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