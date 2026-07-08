<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/app/bootstrap.php';

install_database();
echo "Database ready.\n";
echo "Initial login: administrator / administrator\n";
