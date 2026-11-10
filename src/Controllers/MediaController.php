<?php
declare(strict_types=1);

namespace Apollon\Controllers;

use Apollon\Request;
use Apollon\Response;
use Apollon\Session;
use Apollon\ShareToken;
use PDO;

final class MediaController
{
    private ?PDO $db;

    public function __construct(PDO $db)
    {
        $this->db = $db;
    }

    public function getAppIcon(): void
    {
        header('Content-Type: image/svg+xml');
        header('Cache-Control: public, max-age=86400');
        $size = max(16, min(512, (int)(Request::query('size', '192'))));
        echo '<svg xmlns="http://www.w3.org/2000/svg" width="' . $size . '" height="' . $size . '" viewBox="0 0 100 100">'
            . '<rect width="100" height="100" rx="24" fill="#0d0d0d"/>'
            . '<rect x="13" y="38" width="7.5" height="24" rx="3.75" fill="#ffffff"/>'
            . '<rect x="27" y="25" width="7.5" height="50" rx="3.75" fill="#6C5CFF"/>'
            . '<rect x="41" y="13" width="7.5" height="74" rx="3.75" fill="#ffffff"/>'
            . '<rect x="55" y="25" width="7.5" height="50" rx="3.75" fill="#ffffff"/>'
            . '<rect x="69" y="38" width="7.5" height="24" rx="3.75" fill="#ffffff"/>'
            . '<rect x="83" y="25" width="7.5" height="50" rx="3.75" fill="#ffffff"/>'
            . '</svg>';
        exit;
    }

    public function getStream(): void
    {
        $id = (int)(Request::query('id', '0'));
        $shareToken = (string)Request::query('share', '');
        if (!ALLOW_GUEST_LISTEN && !Session::userId() && !ShareToken::verify($id, $shareToken)) {
            http_response_code(403);
            exit('Access denied');
        }
        $stmt = $this->db->prepare("SELECT file FROM music WHERE id = ?");
        $stmt->execute([$id]);
        $file = $stmt->fetchColumn();

        $stmt = null;
        $this->db = null;
        session_write_close();

        // Paths are stored relative to MUSIC_DIR (legacy absolute paths are
        // still accepted and checked below).
        $filePath = ($file !== false && $file !== '')
            ? (str_starts_with($file, '/') ? $file : MUSIC_DIR . DIRECTORY_SEPARATOR . $file)
            : '';

        if (!$filePath || !file_exists($filePath)) {
            http_response_code(404);
            exit('File not found');
        }

        $realMusicDir = rtrim(realpath(MUSIC_DIR), DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
        $realFilePath = realpath($filePath);
        if (!$realFilePath || strpos($realFilePath, $realMusicDir) !== 0) {
            http_response_code(403);
            exit('Access denied');
        }

        $filesize = filesize($realFilePath);
        $ext = strtolower(pathinfo($realFilePath, PATHINFO_EXTENSION));
        if (!isset(AUDIO_MIME_TYPES[$ext])) {
            http_response_code(403);
            exit('Invalid audio type');
        }
        $mimeType = AUDIO_MIME_TYPES[$ext];

        if (function_exists('apache_setenv')) {
            @apache_setenv('no-gzip', 1);
        }
        @ini_set('zlib.output_compression', 'Off');
        while (ob_get_level() > 0) {
            @ob_end_clean();
        }

        header('Content-Type: ' . $mimeType);
        header('Accept-Ranges: bytes');
        header('Cache-Control: no-cache, no-store, must-revalidate');
        header('Pragma: no-cache');
        header('Expires: 0');

        $start = 0;
        $end = $filesize - 1;

        if (isset($_SERVER['HTTP_RANGE'])) {
            $range = str_replace('bytes=', '', $_SERVER['HTTP_RANGE']);
            list($rStart, $rEnd) = explode('-', $range, 2);
            $start = (int)$rStart;
            if ($rEnd !== '') {
                $end = (int)$rEnd;
            }
            if ($start > $end || $start >= $filesize) {
                header('HTTP/1.1 416 Range Not Satisfiable');
                header("Content-Range: bytes */$filesize");
                exit;
            }
            $length = $end - $start + 1;
            header('HTTP/1.1 206 Partial Content');
            header('Content-Length: ' . $length);
            header("Content-Range: bytes $start-$end/$filesize");
        } else {
            header('Content-Length: ' . $filesize);
        }

        $fp = fopen($realFilePath, 'rb');
        fseek($fp, $start);
        $buffer = 32768;
        while (!feof($fp) && ($pos = ftell($fp)) <= $end && !connection_aborted()) {
            if ($pos + $buffer > $end) {
                $buffer = $end - $pos + 1;
            }
            echo fread($fp, $buffer);
            flush();
        }
        fclose($fp);
        exit;
    }

    public function getVideoStream(): void
    {
        if (!ALLOW_GUEST_LISTEN && !Session::userId()) {
            http_response_code(403);
            exit('Access denied');
        }

        $id = (int)(Request::query('id', '0'));
        $stmt = $this->db->prepare("SELECT file FROM videos WHERE id = ?");
        $stmt->execute([$id]);
        $file = $stmt->fetchColumn();

        $stmt = null;
        $this->db = null;
        session_write_close();

        $filePath = ($file !== false && $file !== '')
            ? (str_starts_with($file, '/') ? $file : VIDEO_DIR . DIRECTORY_SEPARATOR . $file)
            : '';

        if (!$filePath || !file_exists($filePath)) {
            http_response_code(404);
            exit('File not found');
        }

        $realVideoDir = rtrim(realpath(VIDEO_DIR), DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
        $realFilePath = realpath($filePath);
        if (!$realFilePath || strpos($realFilePath, $realVideoDir) !== 0) {
            http_response_code(403);
            exit('Access denied');
        }

        // Serve a cached MP4 transcode when the source format is not
        // natively playable by browsers.
        $servePath = $realFilePath;
        if (VIDEO_TRANSCODE && \Apollon\Services\VideoTranscoder::isAvailable()
            && \Apollon\Services\VideoTranscoder::needsTranscode($realFilePath)) {
            $state = \Apollon\Services\VideoTranscoder::status($realFilePath);
            $cache = \Apollon\Services\VideoTranscoder::cachePath($realFilePath);
            if ($state['state'] === 'ready' && is_file($cache)) {
                $servePath = $cache;
            } else {
                header('Content-Type: application/json; charset=utf-8');
                http_response_code(409);
                exit(json_encode(['status' => $state['state'], 'progress' => $state['progress']]));
            }
        }

        $filesize = filesize($servePath);
        $ext = strtolower(pathinfo($servePath, PATHINFO_EXTENSION));
        if (!isset(VIDEO_MIME_TYPES[$ext])) {
            http_response_code(403);
            exit('Invalid video type');
        }
        $mimeType = VIDEO_MIME_TYPES[$ext];

        if (function_exists('apache_setenv')) {
            @apache_setenv('no-gzip', (string) 1);
        }
        @ini_set('zlib.output_compression', 'Off');
        while (ob_get_level() > 0) {
            @ob_end_clean();
        }

        header('Content-Type: ' . $mimeType);
        header('Accept-Ranges: bytes');
        header('Cache-Control: no-cache, no-store, must-revalidate');
        header('Pragma: no-cache');
        header('Expires: 0');

        $start = 0;
        $end = $filesize - 1;

        if (isset($_SERVER['HTTP_RANGE'])) {
            $range = str_replace('bytes=', '', $_SERVER['HTTP_RANGE']);
            list($rStart, $rEnd) = explode('-', $range, 2);
            $start = (int)$rStart;
            if ($rEnd !== '') {
                $end = (int)$rEnd;
            }
            if ($start > $end || $start >= $filesize) {
                header('HTTP/1.1 416 Range Not Satisfiable');
                header("Content-Range: bytes */$filesize");
                exit;
            }
            $length = $end - $start + 1;
            header('HTTP/1.1 206 Partial Content');
            header('Content-Length: ' . $length);
            header("Content-Range: bytes $start-$end/$filesize");
        } else {
            header('Content-Length: ' . $filesize);
        }

        $fp = fopen($servePath, 'rb');
        fseek($fp, $start);
        $buffer = 65536;
        while (!feof($fp) && ($pos = ftell($fp)) <= $end && !connection_aborted()) {
            if ($pos + $buffer > $end) {
                $buffer = $end - $pos + 1;
            }
            echo fread($fp, $buffer);
            flush();
        }
        fclose($fp);
        exit;
    }

    public function getVideoImage(): void
    {
        $id = (int)(Request::query('id', '0'));
        $stmt = $this->db->prepare("SELECT image FROM videos WHERE id = ?");
        $stmt->execute([$id]);
        $imageData = $stmt->fetchColumn();
        $stmt = null;
        $this->db = null;
        session_write_close();

        if ($imageData) {
            header('Content-Type: image/webp');
            header('Cache-Control: public, max-age=604800');
            echo $imageData;
        } else {
            header('Content-Type: image/svg+xml');
            header('Cache-Control: public, max-age=604800');
            echo '<svg xmlns="http://www.w3.org/2000/svg" width="100" height="150" viewBox="0 0 24 36" fill="#303030"><rect width="24" height="36" rx="4" fill="#181818"/><path d="M9.5 10.5v15l13-7.5-13-7.5z" fill="#404040"/></svg>';
        }
        exit;
    }

    public function getImage(): void
    {
        $id = (int)(Request::query('id', '0'));
        $stmt = $this->db->prepare("SELECT image FROM music WHERE id = ?");
        $stmt->execute([$id]);
        $imageData = $stmt->fetchColumn();
        $stmt = null;
        $this->db = null;
        session_write_close();

        if ($imageData) {
            header('Content-Type: image/webp');
            header('Cache-Control: public, max-age=604800');
            echo $imageData;
        } else {
            header('Content-Type: image/svg+xml');
            header('Cache-Control: public, max-age=604800');
            echo '<svg xmlns="http://www.w3.org/2000/svg" width="100" height="100" viewBox="0 0 24 24" fill="#303030"><rect width="24" height="24" rx="4" fill="#181818"/><path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm0 14.5c-2.49 0-4.5-2.01-4.5-4.5S9.51 7.5 12 7.5s4.5 2.01 4.5 4.5-2.01 4.5-4.5 4.5zm0-5.5c-.55 0-1 .45-1 1s.45 1 1 1 1-.45 1-1-.45-1-1-1z" fill="#404040"/></svg>';
        }
        exit;
    }

}
