<?php
declare(strict_types=1);

namespace Apollon\Controllers;

use Apollon\Request;
use Apollon\Language;
use Apollon\Response;
use Apollon\Security;
use Apollon\Session;
use PDO;

final class WebradioController
{
    public function __construct(private PDO $db)
    {
    }

    public function getWebradios(): void
    {
        $userId = Session::userId();
        if (!$userId) {
            Response::json([]);
        }
        $stmt = $this->db->prepare("SELECT id, name, url, created_at FROM webradios ORDER BY name COLLATE NOCASE ASC");
        $stmt->execute();
        Response::json($stmt->fetchAll());
    }

    public function createWebradio(): void
    {
        Security::verifyCsrf();
        $userId = Session::userId();
        if (!$userId) {
            Response::json(['status' => 'error', 'message' => Language::t('api.unauthorized')], 403);
        }
        $data = Request::jsonBody();
        $name = trim((string)($data['name'] ?? ''));
        $url = trim((string)($data['url'] ?? ''));
        if (empty($name)) {
            Response::json(['status' => 'error', 'message' => Language::t('api.webradio_name_empty')], 400);
        }
        if (empty($url) || !str_contains($url, '://')) {
            Response::json(['status' => 'error', 'message' => Language::t('api.webradio_url_invalid')], 400);
        }
        $stmt = $this->db->prepare("INSERT INTO webradios (name, url) VALUES (?, ?)");
        $stmt->execute([$name, $url]);
        Response::json(['status' => 'success', 'message' => Language::t('api.webradio_created')]);
    }

    public function editWebradio(): void
    {
        Security::verifyCsrf();
        $userId = Session::userId();
        if (!$userId) {
            Response::json(['status' => 'error', 'message' => Language::t('api.unauthorized')], 403);
        }
        $data = Request::jsonBody();
        $id = (int)($data['id'] ?? 0);
        $name = trim((string)($data['name'] ?? ''));
        $url = trim((string)($data['url'] ?? ''));
        if (empty($name)) {
            Response::json(['status' => 'error', 'message' => Language::t('api.webradio_name_empty')], 400);
        }
        if (empty($url) || !str_contains($url, '://')) {
            Response::json(['status' => 'error', 'message' => Language::t('api.webradio_url_invalid')], 400);
        }
        $stmt = $this->db->prepare("UPDATE webradios SET name = ?, url = ? WHERE id = ?");
        $stmt->execute([$name, $url, $id]);
        Response::json(['status' => 'success', 'message' => Language::t('api.webradio_updated')]);
    }

    public function deleteWebradio(): void
    {
        Security::verifyCsrf();
        $userId = Session::userId();
        if (!$userId) {
            Response::json(['status' => 'error', 'message' => Language::t('api.unauthorized')], 403);
        }
        $data = Request::jsonBody();
        $id = (int)($data['id'] ?? 0);
        $stmt = $this->db->prepare("DELETE FROM webradios WHERE id = ?");
        $stmt->execute([$id]);
        Response::json(['status' => 'success', 'message' => Language::t('api.webradio_deleted')]);
    }

    /**
     * Proxy a webradio stream to the browser.
     *
     * Many radio streams sit behind a short-lived tokenized redirect that sets a
     * cookie (e.g. revma.com). Browsers treat the media request as cross-origin
     * and often block that third-party cookie, so the token validation fails and
     * no media is returned. Proxying through the server lets cURL follow the
     * redirect and send the cookie reliably, then streams the audio back.
     */
    public function streamWebradio(): void
    {
        $userId = Session::userId();
        if (!$userId) {
            http_response_code(403);
            exit;
        }
        $id = (int)($_GET['id'] ?? 0);
        $stmt = $this->db->prepare("SELECT url FROM webradios WHERE id = ?");
        $stmt->execute([$id]);
        $url = (string)$stmt->fetchColumn();
        if ($url === '' || !str_contains($url, '://')) {
            http_response_code(404);
            exit;
        }
        if (!$this->isSafeStreamUrl($url)) {
            http_response_code(400);
            exit;
        }

        $contentType = 'application/octet-stream';
        $headersSent = false;
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS => 5,
            CURLOPT_RETURNTRANSFER => false,
            CURLOPT_HEADER => false,
            CURLOPT_CONNECTTIMEOUT => 15,
            CURLOPT_TIMEOUT => 0,
            CURLOPT_BUFFERSIZE => 16384,
            CURLOPT_HTTPHEADER => ['User-Agent: Mozilla/5.0 (compatible; ApollonMedia/1.0)'],
            CURLOPT_HEADERFUNCTION => function ($ch, $header) use (&$contentType) {
                $len = strlen($header);
                if (stripos($header, 'Content-Type:') === 0) {
                    $ct = trim(substr($header, 13));
                    if ($ct !== '') {
                        $contentType = $ct;
                    }
                }
                return $len;
            },
            CURLOPT_WRITEFUNCTION => function ($ch, $data) use (&$headersSent, &$contentType) {
                if (connection_aborted()) {
                    return 0;
                }
                if (!$headersSent) {
                    header('Content-Type: ' . $contentType);
                    header('Cache-Control: no-store, no-cache, must-revalidate');
                    header('X-Accel-Buffering: no');
                    $headersSent = true;
                }
                echo $data;
                if (ob_get_level() > 0) {
                    ob_flush();
                }
                flush();
                return strlen($data);
            },
        ]);

        $result = curl_exec($ch);
        $httpCode = (int)curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        curl_close($ch);

        if ($result === false || $httpCode >= 400) {
            if (!$headersSent) {
                http_response_code(502);
                header('Content-Type: application/json; charset=utf-8');
                echo json_encode(['status' => 'error', 'message' => 'Stream unavailable']);
            }
            exit;
        }
    }

    private function isSafeStreamUrl(string $url): bool
    {
        $host = parse_url($url, PHP_URL_HOST);
        if (!$host) {
            return false;
        }
        $ips = gethostbynamel($host);
        if ($ips === false) {
            $ip = gethostbyname($host);
            if ($ip === $host) {
                return false;
            }
            $ips = [$ip];
        }
        foreach ($ips as $ip) {
            if ($this->isPrivateIp($ip)) {
                return false;
            }
        }
        return true;
    }

    private function isPrivateIp(string $ip): bool
    {
        $long = ip2long($ip);
        if ($long === false) {
            return true;
        }
        $ranges = [
            [ip2long('0.0.0.0'), ip2long('0.255.255.255')],
            [ip2long('10.0.0.0'), ip2long('10.255.255.255')],
            [ip2long('100.64.0.0'), ip2long('100.127.255.255')],
            [ip2long('127.0.0.0'), ip2long('127.255.255.255')],
            [ip2long('169.254.0.0'), ip2long('169.254.255.255')],
            [ip2long('172.16.0.0'), ip2long('172.31.255.255')],
            [ip2long('192.0.0.0'), ip2long('192.0.0.255')],
            [ip2long('192.0.2.0'), ip2long('192.0.2.255')],
            [ip2long('192.168.0.0'), ip2long('192.168.255.255')],
            [ip2long('198.18.0.0'), ip2long('198.19.255.255')],
        ];
        foreach ($ranges as [$start, $end]) {
            if ($long >= $start && $long <= $end) {
                return true;
            }
        }
        return false;
    }
}
