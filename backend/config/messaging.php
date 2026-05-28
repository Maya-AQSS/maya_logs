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

    /*
    |--------------------------------------------------------------------------
    | Nombres de colas AMQP
    |--------------------------------------------------------------------------
    |
    | Centraliza los nombres de cola para evitar strings hardcodeados.
    | Sobrescribibles por variable de entorno para distintos entornos.
    |
    */
    'queues' => [
        'logs_ingest' => env('MAYA_QUEUE_LOGS_INGEST', 'logs.ingest'),
    ],

];
