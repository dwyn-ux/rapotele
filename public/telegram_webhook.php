<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/app/bootstrap.php';

if (!app_installed()) {
    http_response_code(503);
    echo 'App belum diinstall';
    exit;
}

handle_telegram_webhook();
