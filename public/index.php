<?php

use Illuminate\Http\Request;

define('LARAVEL_START', microtime(true));
define('SELO_SKIP_DB_BOOTSTRAP', true);

require __DIR__ . '/../app/bootstrap.php';

$app = require_once __DIR__ . '/../bootstrap/app.php';

$app->handleRequest(Request::capture());
