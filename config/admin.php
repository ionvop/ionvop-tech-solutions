<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Admin Email
    |--------------------------------------------------------------------------
    |
    | The single Google account allowed to sign in to the admin dashboard.
    | This is intentionally server-side only: it is never exposed to the
    | browser, and an empty value denies access to everyone.
    |
    */

    'email' => env('ADMIN_EMAIL'),

];
