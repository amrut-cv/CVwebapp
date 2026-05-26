<?php
// Copy this file to db_config.php on the server and fill in all values.
// db_config.php is in .gitignore — create it manually on each deployment server.

// MySQL
define('DB_HOST', 'localhost');
define('DB_USER', 'cvapp');
define('DB_PASS', 'CHANGE_ME');
define('DB_NAME', 'CVwebapp');

// SMTP (Google Workspace App Password)
define('SMTP_HOST', 'ssl://smtp.gmail.com');
define('SMTP_PORT', 465);
define('SMTP_USER', 'amrut@corevoice.in'); // sending address
define('SMTP_PASS', 'CHANGE_ME');          // 16-char Google App Password
