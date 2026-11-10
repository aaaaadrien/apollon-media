<?php
declare(strict_types=1);

namespace Apollon\Services;

/**
 * Transcodes videos to browser-friendly MP4 (H.264/AAC) using ffmpeg.
 *
 * The transcoded file is cached in VIDEO_CACHE_DIR and reused until the
 * source file changes (cache key = path + mtime + size). Transcoding runs
 * as a detached background process; progress is tracked in a status file
 * and polled by the client.
 */
final class VideoTranscoder
{
    private const FRIENDLY_CONTAINERS = ['mp4', 'webm', 'm4v'];
    private const FRIENDLY_VCODECS = ['h264', 'vp8', 'vp9', 'av1'];
    private const FRIENDLY_ACODECS = ['aac', 'mp3', 'opus', 'vorbis'];

    public static function isAvailable(): bool
    {
        return self::binary('ffmpeg') !== null && self::binary('ffprobe') !== null;
    }

    private static function binary(string $name): ?string
    {
        static $cache = [];
        if (array_key_exists($name, $cache)) {
            return $cache[$name];
        }
        $found = null;
        if (function_exists('exec')) {
            exec('command -v ' . escapeshellarg($name) . ' 2>/dev/null', $out, $rc);
            if ($rc === 0 && !empty($out[0])) {
                $found = trim($out[0]);
            }
        }
        $cache[$name] = $found;
        return $found;
    }

    /**
     * True when the file's container/codecs are not natively playable by
     * browsers and should be transcoded.
     */
    public static function needsTranscode(string $filePath): bool
    {
        if (!is_file($filePath)) {
            return false;
        }
        $probe = self::probe($filePath);
        if ($probe === null) {
            // ffprobe failed: fall back to the extension.
            $ext = strtolower(pathinfo($filePath, PATHINFO_EXTENSION));
            return !in_array($ext, self::FRIENDLY_CONTAINERS, true);
        }

        $container = strtolower($probe['format'] ?? '');
        // ffprobe reports several possible containers ("mov,mp4,m4a,...").
        $containers = array_map('trim', explode(',', $container));
        $vcodec = strtolower($probe['vcodec'] ?? '');
        $acodec = strtolower($probe['acodec'] ?? '');

        if (!array_intersect($containers, self::FRIENDLY_CONTAINERS)) {
            return true;
        }
        if ($vcodec !== '' && !in_array($vcodec, self::FRIENDLY_VCODECS, true)) {
            return true;
        }
        if ($acodec !== '' && !in_array($acodec, self::FRIENDLY_ACODECS, true)) {
            return true;
        }
        return false;
    }

    /**
     * ffprobe summary: format name, video codec, audio codec, duration.
     *
     * @return array<string, mixed>|null
     */
    public static function probe(string $filePath): ?array
    {
        $ffprobe = self::binary('ffprobe');
        if ($ffprobe === null) {
            return null;
        }
        $cmd = $ffprobe . ' -v error -print_format json -show_format -show_streams '
            . escapeshellarg($filePath) . ' 2>/dev/null';
        exec($cmd, $out, $rc);
        if ($rc !== 0) {
            return null;
        }
        $data = json_decode(implode("\n", $out), true);
        if (!is_array($data)) {
            return null;
        }

        $result = [
            'format' => $data['format']['format_name'] ?? '',
            'duration' => (float)($data['format']['duration'] ?? 0),
            'vcodec' => '',
            'acodec' => '',
        ];
        foreach ($data['streams'] ?? [] as $stream) {
            $type = $stream['codec_type'] ?? '';
            $codec = $stream['codec_name'] ?? '';
            if ($type === 'video' && $result['vcodec'] === '') {
                $result['vcodec'] = $codec;
            } elseif ($type === 'audio' && $result['acodec'] === '') {
                $result['acodec'] = $codec;
            }
        }
        return $result;
    }

    private static function hashFor(string $filePath): string
    {
        $stat = @stat($filePath);
        return md5($filePath . '|' . ($stat['mtime'] ?? 0) . '|' . ($stat['size'] ?? 0));
    }

    private static function ensureCacheDir(): void
    {
        if (!is_dir(VIDEO_CACHE_DIR)) {
            @mkdir(VIDEO_CACHE_DIR, 0775, true);
        }
    }

    public static function cachePath(string $filePath): string
    {
        return VIDEO_CACHE_DIR . DIRECTORY_SEPARATOR . self::hashFor($filePath) . '.mp4';
    }

    private static function statusFile(string $filePath): string
    {
        return VIDEO_CACHE_DIR . DIRECTORY_SEPARATOR . self::hashFor($filePath) . '.status';
    }

    private static function pidFile(string $filePath): string
    {
        return VIDEO_CACHE_DIR . DIRECTORY_SEPARATOR . self::hashFor($filePath) . '.pid';
    }

    private static function logFile(string $filePath): string
    {
        return VIDEO_CACHE_DIR . DIRECTORY_SEPARATOR . self::hashFor($filePath) . '.log';
    }

    /**
     * Current transcode state for a file.
     *
     * @return array{state: string, progress: float} state: none|transcoding|ready|error
     */
    public static function status(string $filePath): array
    {
        self::ensureCacheDir();
        $statusPath = self::statusFile($filePath);
        $cache = self::cachePath($filePath);

        if (!is_file($statusPath)) {
            return ['state' => 'none', 'progress' => 0.0];
        }
        $raw = trim((string)@file_get_contents($statusPath));
        if ($raw === 'ready') {
            return is_file($cache) ? ['state' => 'ready', 'progress' => 1.0] : ['state' => 'none', 'progress' => 0.0];
        }
        if ($raw === 'error') {
            return ['state' => 'error', 'progress' => 0.0];
        }
        if (str_starts_with($raw, 'transcoding')) {
            $parts = preg_split('/\s+/', $raw);
            $progress = (float)($parts[1] ?? 0);

            // Reap a finished process.
            $pidPath = self::pidFile($filePath);
            $pid = is_file($pidPath) ? (int)trim((string)@file_get_contents($pidPath)) : 0;
            $alive = $pid > 0 && self::pidAlive($pid);
            if (!$alive) {
                if (is_file($cache) && filesize($cache) > 0) {
                    file_put_contents($statusPath, 'ready');
                    @unlink($pidPath);
                    return ['state' => 'ready', 'progress' => 1.0];
                }
                file_put_contents($statusPath, 'error');
                @unlink($pidPath);
                @unlink($cache);
                return ['state' => 'error', 'progress' => 0.0];
            }

            // Refresh progress from the ffmpeg log.
            $progress = self::readProgress($filePath, $progress);
            return ['state' => 'transcoding', 'progress' => $progress];
        }
        return ['state' => 'none', 'progress' => 0.0];
    }

    private static function pidAlive(int $pid): bool
    {
        if (function_exists('posix_kill')) {
            return @posix_kill($pid, 0);
        }
        // Fallback: /proc (Linux).
        return is_dir('/proc/' . $pid);
    }

    private static function readProgress(string $filePath, float $fallback): float
    {
        $log = self::logFile($filePath);
        if (!is_file($log)) {
            return $fallback;
        }
        $handle = fopen($log, 'rb');
        if ($handle === false) {
            return $fallback;
        }
        // Read the last 8 KB of the log (ffmpeg appends progress lines).
        fseek($handle, -8192, SEEK_END);
        $tail = (string)fread($handle, 8192);
        fclose($handle);

        if (!preg_match_all('/time=(\d+):(\d+):(\d+(?:\.\d+)?)/', $tail, $matches, PREG_SET_ORDER)) {
            return $fallback;
        }
        $last = end($matches);
        $elapsed = ((int)$last[1] * 3600) + ((int)$last[2] * 60) + (float)$last[3];
        $probe = self::probe($filePath);
        $total = $probe['duration'] ?? 0.0;
        if ($total <= 0) {
            return $fallback;
        }
        return min(0.99, max(0.0, $elapsed / $total));
    }

    /**
     * Start a background transcode if one is not already running.
     *
     * @return bool True if a transcode was started by this call.
     */
    public static function startTranscode(string $filePath): bool
    {
        if (!self::isAvailable() || !is_file($filePath)) {
            return false;
        }
        self::ensureCacheDir();

        $statusPath = self::statusFile($filePath);
        $lock = @fopen($statusPath, 'c+');
        if ($lock === false) {
            return false;
        }
        try {
            if (!flock($lock, LOCK_EX)) {
                return false;
            }

            $state = self::status($filePath)['state'];
            if ($state === 'ready' || $state === 'transcoding') {
                return false;
            }

            // Remove stale artifacts from a previous failed run.
            @unlink(self::cachePath($filePath));
            @unlink(self::pidFile($filePath));

            $probe = self::probe($filePath);
            $total = $probe['duration'] ?? 0.0;
            if ($total <= 0) {
                file_put_contents($statusPath, 'error');
                return false;
            }

            $dst = self::cachePath($filePath);
            $log = self::logFile($filePath);
            $ffmpeg = self::binary('ffmpeg');
            $args = [
                $ffmpeg, '-nostdin', '-hide_banner', '-stats',
                '-i', $filePath,
                '-map', '0:v:0', '-map', '0:a:0?',
                '-c:v', 'libx264', '-preset', 'veryfast', '-crf', '23',
                '-c:a', 'aac', '-b:a', '192k',
                '-movflags', '+faststart',
                '-y', $dst,
            ];
            $cmdLine = implode(' ', array_map('escapeshellarg', $args));
            // Run in its own session (setsid) so the child survives the
            // parent (PHP request) exiting. The shell backgrounds it and
            // returns immediately; the child pid is written to the pid file.
            $setsid = trim((string)@shell_exec('command -v setsid 2>/dev/null'));
            $prefix = $setsid !== '' ? $setsid . ' ' : '';
            $cmd = $prefix . $cmdLine
                . ' > ' . escapeshellarg($log) . ' 2>&1 < /dev/null & echo $! > ' . escapeshellarg(self::pidFile($filePath));
            exec($cmd . ' 2>/dev/null', $out, $rc);
            $pid = (int)@file_get_contents(self::pidFile($filePath));
            if ($rc !== 0 || $pid <= 0) {
                file_put_contents($statusPath, 'error');
                return false;
            }
            file_put_contents($statusPath, 'transcoding 0');
            return true;
        } finally {
            flock($lock, LOCK_UN);
            fclose($lock);
        }
    }
}
