<?php
// Apollon Media - application bootstrap.
declare(strict_types=1);

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/autoload.php';

use Apollon\Language;
use Apollon\Security;
use Apollon\Session;

// Detect the interface language from the browser (Accept-Language header).
// Unknown languages fall back to English (see src/Language.php).
Language::init();

set_time_limit(0);

Session::start();
Security::sendHeaders();

// getID3 library (extracted into ./getid3 next to the script) for ID3 tag parsing.
if (file_exists(__DIR__ . '/../getid3/getid3.php')) {
  require_once __DIR__ . '/../getid3/getid3.php';
}
