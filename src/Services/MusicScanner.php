<?php
declare(strict_types=1);

namespace Apollon\Services;

use PDO;

/**
 * Scans MUSIC_DIR for audio files, reads their tags with getID3 and
 * synchronizes the music table (add / update / delete).
 */
final class MusicScanner
{
    public function __construct(private PDO $db)
    {
    }

    /**
     * Parse a single audio file and add/update it in the music table.
     *
     * @return bool True if the file was indexed.
     */
    public function indexFile(string $filePath): bool
    {
        if (!class_exists('getID3') || !is_file($filePath)) {
            return false;
        }
        $getID3 = new \getID3();
        $getID3->option_tag_lyrics3 = false;
        $getID3->option_tag_apetag = false;
        $getID3->option_tags_images = true;

        $fields = $this->parseFile($getID3, $filePath);
        if ($fields === null) {
            return false;
        }

        $stmt = $this->db->prepare("
            INSERT OR REPLACE INTO music (user_id, file, title, artist, album, year, duration, bitrate, image, last_modified)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([
            null, self::relPath($filePath), $fields['title'], $fields['artist'], $fields['album'],
            $fields['year'], $fields['duration'], $fields['bitrate'], $fields['image'], $fields['mtime'],
        ]);
        return true;
    }

    /**
     * Path relative to MUSIC_DIR (the format stored in the music table, so
     * the database stays valid when the project is moved).
     */
    public static function relPath(string $absolutePath): string
    {
        $root = realpath(MUSIC_DIR);
        if ($root !== false && str_starts_with($absolutePath, $root . DIRECTORY_SEPARATOR)) {
            return substr($absolutePath, strlen($root) + 1);
        }
        return $absolutePath;
    }

    /**
     * Read the tags of a file with getID3.
     *
     * @return array<string, mixed>|null Null if the file could not be parsed.
     */
    private function parseFile(\getID3 $getID3, string $filePath): ?array
    {
        try {
            $info = $getID3->analyze($filePath);
        } catch (\Exception $e) {
            return null;
        }
        \getid3_lib::CopyTagsToComments($info);

        return [
            'title' => trim($info['comments']['title'][0] ?? pathinfo($filePath, PATHINFO_FILENAME)),
            'artist' => trim($info['comments']['artist'][0] ?? 'Unknown Artist'),
            'album' => trim($info['comments']['album'][0] ?? 'Unknown Album'),
            'year' => (int)($info['comments']['year'][0] ?? 0),
            'duration' => (int)($info['playtime_seconds'] ?? 0),
            'bitrate' => (int)($info['audio']['bitrate'] ?? 0),
            'image' => ImageProcessor::toWebp($info['comments']['picture'][0]['data'] ?? null, 300, 70),
            'mtime' => (int)filemtime($filePath),
        ];
    }

    public function scan(): void
    {
        ini_set('memory_limit', '512M');
        header('Content-Type: text/plain; charset=utf-8');
        ob_implicit_flush();

        echo APP_NAME . " - Fast Scan\n=========================\n\n";

        if (!class_exists('getID3')) {
            die('FATAL: getID3 library not found in ' . __DIR__ . '/../../getid3/' . "\n");
        }

        $dbFiles = $this->db->query("SELECT file, last_modified FROM music")->fetchAll(PDO::FETCH_KEY_PAIR);

        $filesOnDisk = [];
        if (is_dir(MUSIC_DIR)) {
            $dir = new \RecursiveDirectoryIterator(MUSIC_DIR, \RecursiveDirectoryIterator::SKIP_DOTS);
            $iter = new \RecursiveIteratorIterator($dir, \RecursiveIteratorIterator::LEAVES_ONLY);

            foreach ($iter as $file) {
                if ($file->isDir()) {
                    continue;
                }
                $filePath = $file->getRealPath();
                if (preg_match('/\.(mp3|m4a|flac|ogg|wav)$/i', $filePath)) {
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
            die("Library is completely up to date.\n");
        }

        $this->db->exec("PRAGMA synchronous = OFF;");
        $this->db->beginTransaction();

        $insertStmt = $this->db->prepare("
            INSERT OR REPLACE INTO music (user_id, file, title, artist, album, year, duration, bitrate, image, last_modified)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        $deleteStmt = $this->db->prepare("DELETE FROM music WHERE file = ?");

        $getID3 = new \getID3();
        $getID3->option_tag_lyrics3 = false;
        $getID3->option_tag_apetag = false;
        $getID3->option_tags_images = true;

        $count = 0;
        foreach ($filesToProcess as $relPath => $mtime) {
            $count++;
            echo "[$count/$total] " . basename($relPath) . "\n";

            $fields = $this->parseFile($getID3, MUSIC_DIR . DIRECTORY_SEPARATOR . $relPath);
            if ($fields === null) {
                continue;
            }

            $insertStmt->execute([
                null, $relPath, $fields['title'], $fields['artist'], $fields['album'],
                $fields['year'], $fields['duration'], $fields['bitrate'], $fields['image'], $mtime,
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
        echo "\nScan complete! $total files processed.\n";
    }
}
