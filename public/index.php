<?php

declare(strict_types=1);

ini_set('display_errors', '1');
ini_set('error_reporting', E_ALL);

require_once dirname(__DIR__) . '/app/bootstrap.php';
require_once dirname(__DIR__) . '/app/web.php';

app_dispatch_request();
