<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Slug de aplicación en mensajería (audit, logs estructurados, etc.)
    |--------------------------------------------------------------------------
    |
    | Debe coincidir con MAYA_MESSAGING_APP y con el registro en maya_auth /
    | consumidores que enrutan por application_slug.
    |
    */
    'app' => env('MAYA_MESSAGING_APP', 'maya-logs'),

    // Los nombres de cola (messaging.queues.*) los define el paquete: estándar de mensajería,
    // maya_platform/messaging. No redefinir aquí `queues`, `exchanges` ni `connection`.
];
