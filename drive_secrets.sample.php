<?php
// Copy this file to drive_secrets.php and fill in your values.
// drive_secrets.php is gitignored — never commit it.
//
// GOOGLE_SERVICE_ACCOUNT_KEY_PATH points to the service account's JSON key
// file. Keep that JSON file itself outside the git repo too (e.g. alongside
// this config file on the server).
define('GOOGLE_SERVICE_ACCOUNT_KEY_PATH', __DIR__ . '/google_service_account.json');
define('GOOGLE_DRIVE_FOLDER_ID', 'CHANGE_ME');
// Separate folder for guest_tracker's "Save to Drive" (Guest Guide PDFs).
define('GOOGLE_DRIVE_GUEST_GUIDE_FOLDER_ID', 'CHANGE_ME');
