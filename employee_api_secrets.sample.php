<?php
// Copy this file to employee_api_secrets.php and set a long random token
// (for example: php -r 'echo bin2hex(random_bytes(32));').
// employee_api_secrets.php is gitignored, never commit it.
//
// The employee tool's backend sends this as "Authorization: Bearer <token>" to
// api/employee_payout.php. Keep it on that backend only, never in a browser.
define('EMPLOYEE_API_TOKEN', 'CHANGE_ME');
