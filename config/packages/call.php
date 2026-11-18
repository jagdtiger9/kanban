<?php

use Symfony\Component\DependencyInjection\Loader\Configurator\App;

use function Symfony\Component\DependencyInjection\Loader\Configurator\env;

// Calls between project members over WebRTC (src/Call). Own coturn for
// STUN/TURN, signaling over the personal Centrifugo namespace "call".
return App::config([
    'parameters' => [
        // mesh call: every participant connects to every other one
        'call.max_participants' => 4,
        // seconds without a heartbeat before a participant is dropped (client beats every 20 s)
        'call.participant_ttl' => 60,
        // comma-separated stun:/turn: URLs of our coturn; empty = direct connections only
        'call.ice_urls' => env('CALL_ICE_URLS'),
        // coturn static-auth-secret (use-auth-secret); TURN entries are skipped when empty
        'call.turn_secret' => env('CALL_TURN_SECRET'),
        'call.turn_credential_ttl' => 43200,
    ],
]);
