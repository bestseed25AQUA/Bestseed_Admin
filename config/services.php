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

    'resend' => [
        'key' => env('RESEND_KEY'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    'google' => [
        'maps_api_key' => env('GOOGLE_MAPS_API_KEY'),
    ],

    'sms' => [
        'app_hash' => env('SMS_APP_HASH', 'FA+9qCX9VSu'),
    ],


    /*
     * ffmpeg, used to re-encode uploaded demo videos.
     *
     * Optional. Without it the original file is kept as uploaded. Set
     * FFMPEG_PATH when the binary is not on the system PATH — on shared
     * hosting that usually means a static build in the account's home
     * directory, e.g. /home/<user>/bin/ffmpeg
     */
    'ffmpeg' => [
        'path' => env('FFMPEG_PATH', ''),
    ],

];
