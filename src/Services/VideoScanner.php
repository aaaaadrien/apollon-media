<?php
declare(strict_types=1);

namespace Apollon\Services;

use PDO;

/**
 * Scans VIDEO_DIR for video files, reads their metadata with getID3 and
 * synchronizes the videos table (add / update / delete).
 *
 * Expected folder layout:
 *   <root>/<Movie Title> (Year).ext
 *   <root>/<Series Name>/Saison NN/EE - Episode Title.ext
 *   <root>/<Series Name>/EE - Episode Title.ext   (flat, season 0)
 *
 * A poster image (poster.jpg/png/webp, or one named after the file) placed
 * in a series folder or next to a movie is used as the artwork.
 */
final class VideoScanner
{
    private const POSTER_EXTENSIONS = ['jpg', 'jpeg', 'png', 'webp'];

    public function __construct(private PDO $db)
    {
    }

    /**
     * Parse a single video file and add/update it in the videos table.
     *
     * @return bool True if the file was indexed.
     */
    public function indexFile(string $filePath): bool
    {
        if (!is_file($filePath)) {
            return false;
        }
        $fields = $this->parseFile($filePath);
        if ($fields === null) {
            return false;
        }

        $stmt = $this->db->prepare("
            INSERT OR REPLACE INTO videos (file, title, type, series, season, episode, year, duration, image, last_modified)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([
            self::relPath($filePath), $fields['title'], $fields['type'], $fields['series'],
            $fields['season'], $fields['episode'], $fields['year'], $fields['duration'],
            $fields['image'], $fields['mtime'],
        ]);
        return true;
    }

    /**
     * Path relative to VIDEO_DIR (the format stored in the videos table, so
     * the database stays valid when the project is moved).
     */
    public static function relPath(string $absolutePath): string
    {
        $root = realpath(VIDEO_DIR);
        if ($root !== false && str_starts_with($absolutePath, $root . DIRECTORY_SEPARATOR)) {
            return substr($absolutePath, strlen($root) + 1);
        }
        return $absolutePath;
    }

    /**
     * Derive title/type/series/season/episode from the path, then enrich
     * with getID3 metadata (year, duration, cover art).
     *
     * @return array<string, mixed>|null Null if the file could not be read.
     */
    private function parseFile(string $filePath): ?array
    {
        $relPath = str_replace('\\', '/', self::relPath($filePath));
        $segments = explode('/', $relPath);
        $fileName = array_pop($segments);
        $baseName = pathinfo($fileName, PATHINFO_FILENAME);

        $type = 'movie';
        $series = null;
        $season = 0;
        $episode = 0;

        // Season folder: "Saison 01", "Season 2", "S01"...
        $seasonIndex = null;
        foreach ($segments as $i => $segment) {
            if (preg_match('/^(?:saison|season|s)\s*0*(\d+)$/i', $segment, $m)) {
                $seasonIndex = $i;
                $season = (int)$m[1];
                break;
            }
        }

        if ($seasonIndex !== null) {
            $type = 'episode';
            $series = $seasonIndex > 0 ? $segments[$seasonIndex - 1] : ($segments[0] ?? null);
            $episode = $this->episodeFromName($baseName);
        } elseif (count($segments) >= 1 && preg_match('/^0*(\d+)\s*[-_.]/', $baseName, $m)) {
            // Flat episode: <Series>/01 - Title.ext
            $type = 'episode';
            $series = $segments[count($segments) - 1];
            $episode = (int)$m[1];
        }

        // S01E02 pattern in the file name (works for both layouts).
        if (preg_match('/[Ss]\s*0*(\d+)\s*[Ee]\s*0*(\d+)/', $baseName, $m)) {
            $type = 'episode';
            $season = (int)$m[1];
            $episode = (int)$m[2];
            if ($series === null) {
                $series = $segments[count($segments) - 1] ?? null;
            }
        }

        // Title: strip episode prefix, SxxExx and trailing year.
        $title = $baseName;
        if ($type === 'episode') {
            $title = preg_replace('/^0*\d+\s*[-_.]\s*/', '', $title);
            $title = preg_replace('/[Ss]\s*\d+\s*[Ee]\s*\d+[-_.]?\s*/', '', $title);
            // Collapse leftover separators ("Show - - Title" -> "Show - Title").
            $title = preg_replace('/(\s*[-_.]\s*){2,}/', ' - ', $title);
        }
        $title = preg_replace('/\s*\(\d{4}\)\s*$/', '', $title);
        $title = trim(str_replace(['_', '.'], ' ', $title));
        if ($title === '') {
            $title = $baseName;
        }

        // Year from the file name ("Title (2010).ext").
        $year = 0;
        if (preg_match('/\((\d{4})\)/', $baseName, $m)) {
            $year = (int)$m[1];
        }

        // getID3 metadata (optional: the file is indexed even without it).
        $image = null;
        $duration = 0;
        if (class_exists('getID3')) {
            try {
                $getID3 = new \getID3();
                $getID3->option_tag_lyrics3 = false;
                $info = $getID3->analyze($filePath);
                \getid3_lib::CopyTagsToComments($info);
                $duration = (int)($info['playtime_seconds'] ?? 0);
                if ($year === 0) {
                    $year = (int)($info['comments']['year'][0] ?? 0);
                }
                if (isset($info['comments']['picture'][0]['data'])) {
                    $image = ImageProcessor::toWebpPoster($info['comments']['picture'][0]['data']);
                }
            } catch (\Exception $e) {
                // Keep the file indexed with path-derived data only.
            }
        }

        if ($image === null) {
            $image = $this->findPoster($segments, $seasonIndex, $baseName);
        }

        return [
            'title' => $title,
            'type' => $type,
            'series' => $series,
            'season' => $season,
            'episode' => $episode,
            'year' => $year,
            'duration' => $duration,
            'image' => $image,
            'mtime' => (int)filemtime($filePath),
        ];
    }

    private function episodeFromName(string $baseName): int
    {
        if (preg_match('/^0*(\d+)/', $baseName, $m)) {
            return (int)$m[1];
        }
        return 0;
    }

    /**
     * Look for a poster image: poster.* (or a file named after the video)
     * in the series folder, or next to a movie file.
     */
    private function findPoster(array $segments, ?int $seasonIndex, string $baseName): ?string
    {
        $root = realpath(VIDEO_DIR);
        if ($root === false) {
            return null;
        }

        // Series folder: above the season folder, or the file's own folder.
        $seriesSegments = $seasonIndex !== null ? array_slice($segments, 0, $seasonIndex) : $segments;
        $candidates = [];
        if (count($seriesSegments) > 0) {
            $candidates[] = $root . DIRECTORY_SEPARATOR . implode(DIRECTORY_SEPARATOR, $seriesSegments);
        }
        $candidates[] = $root . (count($segments) > 0 ? DIRECTORY_SEPARATOR . implode(DIRECTORY_SEPARATOR, $segments) : '');

        $wanted = strtolower($baseName);
        foreach (array_unique($candidates) as $dir) {
            if (!is_dir($dir)) {
                continue;
            }
            foreach (scandir($dir) ?: [] as $entry) {
                $ext = strtolower(pathinfo($entry, PATHINFO_EXTENSION));
                if (!in_array($ext, self::POSTER_EXTENSIONS, true)) {
                    continue;
                }
                $name = strtolower(pathinfo($entry, PATHINFO_FILENAME));
                if ($name === 'poster' || $name === $wanted) {
                    $data = @file_get_contents($dir . DIRECTORY_SEPARATOR . $entry);
                    if ($data !== false) {
                        return ImageProcessor::toWebpPoster($data);
                    }
                }
            }
        }
        return null;
    }

    public function scan(): void
    {
        ini_set('memory_limit', '512M');
        header('Content-Type: text/plain; charset=utf-8');
        ob_implicit_flush();

        echo "\n" . APP_NAME . " - Video Scan\n========================\n\n";

        if (!class_exists('getID3')) {
            die('FATAL: getID3 library not found in ' . __DIR__ . '/../../getid3/' . "\n");
        }

        $dbFiles = $this->db->query("SELECT file, last_modified FROM videos")->fetchAll(PDO::FETCH_KEY_PAIR);

        $filesOnDisk = [];
        if (is_dir(VIDEO_DIR)) {
            $dir = new \RecursiveDirectoryIterator(VIDEO_DIR, \RecursiveDirectoryIterator::SKIP_DOTS);
            $iter = new \RecursiveIteratorIterator($dir, \RecursiveIteratorIterator::LEAVES_ONLY);
            $extPattern = implode('|', array_map('preg_quote', VIDEO_EXTENSIONS));

            foreach ($iter as $file) {
                if ($file->isDir()) {
                    continue;
                }
                $filePath = $file->getRealPath();
                if (preg_match('/\.(' . $extPattern . ')$/i', $filePath)) {
                    $filesOnDisk[self::relPath($filePath)] = $file->getMTime();
                }
            }
        }

        $filesToAdd = array_diff_key($filesOnDisk, $dbFiles);
        $filesToDelete = array_diff_key($dbFiles, $filesOnDisk);
        $filesToUpdate = [];

        foreach (array_intersect_key($filesOnDisk, $dbFiles) as $filePath => $mtime) {
            if ($mtime > $dbFiles[$filePath]) {
                $filesToUpdate[$filePath] = $mtime;
            }
        }

        $filesToProcess = $filesToAdd + $filesToUpdate;
        $total = count($filesToProcess) + count($filesToDelete);
        echo 'Files: ' . count($filesOnDisk) . ' (Add: ' . count($filesToAdd) . ', Update: ' . count($filesToUpdate) . ', Delete: ' . count($filesToDelete) . ")\n\n";

        if ($total === 0) {
            echo "Video library is completely up to date.\n";
            return;
        }

        $this->db->exec("PRAGMA synchronous = OFF;");
        $this->db->beginTransaction();

        $insertStmt = $this->db->prepare("
            INSERT OR REPLACE INTO videos (file, title, type, series, season, episode, year, duration, image, last_modified)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        $deleteStmt = $this->db->prepare("DELETE FROM videos WHERE file = ?");

        $count = 0;
        foreach ($filesToProcess as $relPath => $mtime) {
            $count++;
            echo "[$count/$total] " . basename($relPath) . "\n";

            $fields = $this->parseFile(VIDEO_DIR . DIRECTORY_SEPARATOR . $relPath);
            if ($fields === null) {
                continue;
            }

            $insertStmt->execute([
                $relPath, $fields['title'], $fields['type'], $fields['series'],
                $fields['season'], $fields['episode'], $fields['year'], $fields['duration'],
                $fields['image'], $mtime,
            ]);

            if ($count % 50 === 0) {
                $this->db->commit();
                $this->db->beginTransaction();
            }
        }

        foreach ($filesToDelete as $filePath => $mtime) {
            $count++;
            echo "[$count/$total] Deleting: " . basename($filePath) . "\n";
            $deleteStmt->execute([$filePath]);
        }

        $this->db->commit();
        $this->db->exec("PRAGMA synchronous = NORMAL;");
        echo "\nVideo scan complete! $total files processed.\n";
    }
}
