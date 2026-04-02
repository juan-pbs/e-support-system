<?php

foreach ([
    'web/public.php',
    'web/profile.php',
    'web/admin.php',
    'web/tecnico.php',
    'web/gerencia.php',
] as $routeFile) {
    require __DIR__ . DIRECTORY_SEPARATOR . $routeFile;
}

require __DIR__ . '/auth.php';
