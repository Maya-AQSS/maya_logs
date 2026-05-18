<?php

declare(strict_types=1);

/*
| PHPUnit carga este fichero ANTES de leer phpunit.xml.
| Dotenv no pisa variables ya definidas en el proceso — fijamos aquí la BD de tests
| para evitar que .env (pgsql + log_mgmt_db_desarrollo_ceedcv con FDW de solo lectura)
| se imponga.
*/
$testingEnv = [
    'APP_ENV' => 'testing',
    'DB_CONNECTION' => 'sqlite',
    'DB_DATABASE' => ':memory:',
    'DB_URL' => '',
];

foreach ($testingEnv as $key => $value) {
    putenv($key.'='.$value);
    $_ENV[$key] = $value;
    $_SERVER[$key] = $value;
}

require dirname(__DIR__).'/vendor/autoload.php';
