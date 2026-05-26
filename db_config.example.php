<?php
// Copy this file to db_config.php on the server and fill in all values.
// db_config.php is in .gitignore — create it manually on each deployment server.

// MySQL
define('DB_HOST', 'localhost');
define('DB_USER', 'cvapp');
define('DB_PASS', 'CHANGE_ME');
define('DB_NAME', 'CVwebapp');

// SendGrid API (https://app.sendgrid.com → API Keys)
define('SENDGRID_API_KEY', 'CHANGE_ME');
define('SENDGRID_FROM',    'amrut@corevoice.in');
