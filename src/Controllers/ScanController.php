<?php
declare(strict_types=1);

namespace Apollon\Controllers;

use Apollon\Services\MusicScanner;
use Apollon\Services\VideoScanner;
use Apollon\Session;
use Apollon\Language;
use PDO;

final class ScanController
{
    public function __construct(private PDO $db)
    {
    }

    public function fullScan(): void
    {
        if (!Session::userId()) {
            http_response_code(403);
            die(Language::t('api.scan_login_required'));
        }
        (new MusicScanner($this->db))->scan();
        (new VideoScanner($this->db))->scan();
        exit;
    }
}
