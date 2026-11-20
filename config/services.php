<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'postmark' => [
        'token' => env('POSTMARK_TOKEN'),
    ],

    // Claude-Assistent (Schluessel bevorzugt unter Admin > System, sonst hier aus .env)
    'anthropic' => [
        'key' => env('ANTHROPIC_API_KEY'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'resend' => [
        'key' => env('RESEND_KEY'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    'webpush' => [
        'subject' => env('VAPID_SUBJECT', 'mailto:ok@example.ch'),
        // Robust gegen eingefuegte ganze Zeilen ("VAPID_PUBLIC_KEY=..."), Anfuehrungszeichen und Leerzeichen
        'public_key' => preg_replace('/^\s*VAPID_PUBLIC_KEY\s*=\s*|[\s\'"]+/', '', (string) env('VAPID_PUBLIC_KEY')) ?: null,
        'private_key' => preg_replace('/^\s*VAPID_PRIVATE_KEY\s*=\s*|[\s\'"]+/', '', (string) env('VAPID_PRIVATE_KEY')) ?: null,
    ],

];
