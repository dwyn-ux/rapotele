<?php

declare(strict_types=1);

$base = __DIR__;

require_once $base . '/Services/pdf.php';
require_once $base . '/Services/schedule.php';
require_once $base . '/Services/whatsapp.php';

require_once $base . '/Actions/master.php';
require_once $base . '/Actions/assessment.php';
require_once $base . '/Actions/settings.php';
require_once $base . '/Actions/backup.php';
require_once $base . '/Actions/dapodik.php';

require_once $base . '/Pages/render.php';
require_once $base . '/Pages/helpers.php';
require_once $base . '/Pages/auth.php';
require_once $base . '/Pages/dashboard.php';
require_once $base . '/Pages/master.php';
require_once $base . '/Pages/assessment.php';
require_once $base . '/Pages/student.php';
require_once $base . '/Pages/reports.php';
require_once $base . '/Pages/telegram.php';
require_once $base . '/Pages/rapor.php';
require_once $base . '/Pages/kokurikuler.php';
require_once $base . '/Pages/dapodik.php';
require_once $base . '/Pages/export.php';
require_once $base . '/Pages/import_bulk.php';
