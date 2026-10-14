<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/app/bootstrap.php';

$initialAdminPassword = install_database();
echo "Database ready.\n";
if ($initialAdminPassword !== null) {
    echo "Initial username: administrator\nInitial one-time password: $initialAdminPassword\n";
}
