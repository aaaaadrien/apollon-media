<?php
declare(strict_types=1);

namespace Apollon\Controllers;

use Apollon\Request;
use Apollon\Language;
use Apollon\Response;
use Apollon\Security;
use Apollon\Session;
use Apollon\Services\MusicScanner;
use Apollon\Services\VideoScanner;
use PDO;

/**
 * File manager: browse, create, rename, delete and upload files inside
 * MUSIC_DIR or VIDEO_DIR (selected with the "dir" parameter). Requires a
 * logged-in user.
 */
final class FileController
{
    public function __construct(private PDO $db)
    {
    }

    private function requireUser(): void
    {
        if (!Session::userId()) {
            Response::json(['status' => 'error', 'message' => Language::t('api.unauthorized')], 403);
        }
    }

    private function rootDir(?string $dir): string
    {
        return $dir === 'videos' ? VIDEO_DIR : MUSIC_DIR;
    }

    /**
     * Resolve a path relative to $root. Exits with a JSON error when the
     * path is invalid, escapes the root directory, or (when $mustExist)
     * does not exist on disk.
     */
    private function safePath(string $relPath, bool $mustExist = true, string $root = MUSIC_DIR): string
    {
        $relPath = trim(str_replace('\\', '/', $relPath));
        if ($relPath !== '' && (str_starts_with($relPath, '/') || str_contains($relPath, '..') || str_contains($relPath, "\0"))) {
            Response::json(['status' => 'error', 'message' => Language::t('api.invalid_path')], 400);
        }

        $realRoot = realpath($root);
        if ($realRoot === false) {
            Response::json(['status' => 'error', 'message' => Language::t('api.music_dir_not_found')], 500);
        }

        $absolute = $relPath === '' ? $realRoot : $realRoot . DIRECTORY_SEPARATOR . $relPath;

        if ($mustExist) {
            $real = realpath($absolute);
            if ($real === false) {
                Response::json(['status' => 'error', 'message' => Language::t('api.path_not_found')], 404);
            }
            if ($real !== $realRoot && strpos($real, $realRoot . DIRECTORY_SEPARATOR) !== 0) {
                Response::json(['status' => 'error', 'message' => Language::t('api.access_denied')], 403);
            }
            return $real;
        }

        return $absolute;
    }

    /**
     * Path relative to $root (the format stored in the matching library
     * table, so the database stays valid when the project is moved).
     */
    private function relPath(string $absolutePath, string $root = MUSIC_DIR): string
    {
        $realRoot = realpath($root);
        if ($realRoot !== false && str_starts_with($absolutePath, $realRoot . DIRECTORY_SEPARATOR)) {
            return substr($absolutePath, strlen($realRoot) + 1);
        }
        return $absolutePath;
    }

    /**
     * List the folders and files of a directory (relative to MUSIC_DIR).
     */
    public function listFiles(): void
    {
        $this->requireUser();
        $root = $this->rootDir(Request::query('dir'));
        $relPath = (string)Request::query('path', '');
        $dir = $this->safePath($relPath, true, $root);
        if (!is_dir($dir)) {
            Response::json(['status' => 'error', 'message' => Language::t('api.not_a_folder')], 400);
        }

        $folders = [];
        $files = [];
        foreach (scandir($dir) as $entry) {
            if ($entry === '.' || $entry === '..') {
                continue;
            }
            $full = $dir . DIRECTORY_SEPARATOR . $entry;
            $rel = $relPath === '' ? $entry : $relPath . '/' . $entry;
            if (is_dir($full)) {
                $folders[] = ['name' => $entry, 'path' => $rel];
            } else {
                $files[] = [
                    'name' => $entry,
                    'path' => $rel,
                    'size' => (int)filesize($full),
                    'modified' => (int)filemtime($full),
                ];
            }
        }
        usort($folders, fn($a, $b) => strcasecmp($a['name'], $b['name']));
        usort($files, fn($a, $b) => strcasecmp($a['name'], $b['name']));

        Response::json(['status' => 'success', 'path' => $relPath, 'folders' => $folders, 'files' => $files]);
    }

    /**
     * Create a folder. Body: { path: "parent/folder-name" }
     */
    public function createFolder(): void
    {
        Security::verifyCsrf();
        $this->requireUser();
        $data = Request::jsonBody();
        $root = $this->rootDir($data['dir'] ?? null);
        $relPath = trim(str_replace('\\', '/', (string)($data['path'] ?? '')));

        if ($relPath === '' || str_starts_with($relPath, '/') || str_contains($relPath, '..') || str_contains($relPath, "\0")) {
            Response::json(['status' => 'error', 'message' => Language::t('api.invalid_path')], 400);
        }
        $parts = explode('/', $relPath);
        $newName = array_pop($parts);
        if ($newName === '' || $newName === '.' || $newName === '..') {
            Response::json(['status' => 'error', 'message' => Language::t('api.invalid_folder_name')], 400);
        }

        $parent = $this->safePath(implode('/', $parts), true, $root);
        $target = $parent . DIRECTORY_SEPARATOR . $newName;
        if (file_exists($target)) {
            Response::json(['status' => 'error', 'message' => Language::t('api.folder_exists')], 409);
        }
        if (!mkdir($target, 0775, true)) {
            Response::json(['status' => 'error', 'message' => Language::t('api.folder_create_failed')], 500);
        }
        Response::json(['status' => 'success', 'message' => Language::t('api.folder_created')]);
    }

    /**
     * Rename a folder or file. Body: { path, new_name }
     */
    public function renameItem(): void
    {
        Security::verifyCsrf();
        $this->requireUser();
        $data = Request::jsonBody();
        $root = $this->rootDir($data['dir'] ?? null);
        $relPath = (string)($data['path'] ?? '');
        $newName = trim((string)($data['new_name'] ?? ''));

        if ($newName === '' || $newName === '.' || $newName === '..'
            || str_contains($newName, '/') || str_contains($newName, '\\') || str_contains($newName, "\0")) {
            Response::json(['status' => 'error', 'message' => Language::t('api.invalid_name')], 400);
        }

        $absolute = $this->safePath($relPath, true, $root);
        $target = dirname($absolute) . DIRECTORY_SEPARATOR . $newName;
        if (file_exists($target)) {
            Response::json(['status' => 'error', 'message' => Language::t('api.name_exists')], 409);
        }
        if (!rename($absolute, $target)) {
            Response::json(['status' => 'error', 'message' => Language::t('api.rename_failed')], 500);
        }

        // Keep the matching library in sync with the new location (relative
        // paths are stored against the directory the file lives in).
        $table = $root === VIDEO_DIR ? 'videos' : 'music';
        if (is_dir($target)) {
            $oldPrefix = $this->relPath($absolute, $root) . '/';
            $stmt = $this->db->prepare("UPDATE {$table} SET file = ? || substr(file, ?) WHERE substr(file, 1, ?) = ?");
            $stmt->execute([$this->relPath($target, $root) . '/', strlen($oldPrefix) + 1, strlen($oldPrefix), $oldPrefix]);
        } else {
            $this->db->prepare("UPDATE {$table} SET file = ? WHERE file = ?")->execute([$this->relPath($target, $root), $this->relPath($absolute, $root)]);
        }
        Response::json(['status' => 'success', 'message' => Language::t('api.renamed')]);
    }

    /**
     * Delete a folder (recursively) or a file. Body: { path }
     */
    public function deleteItem(): void
    {
        Security::verifyCsrf();
        $this->requireUser();
        $data = Request::jsonBody();
        $root = $this->rootDir($data['dir'] ?? null);
        $relPath = (string)($data['path'] ?? '');
        if ($relPath === '') {
            Response::json(['status' => 'error', 'message' => Language::t('api.invalid_path')], 400);
        }

        $absolute = $this->safePath($relPath, true, $root);
        if ($absolute === realpath($root)) {
            Response::json(['status' => 'error', 'message' => Language::t('api.cannot_delete_root')], 400);
        }

        $table = $root === VIDEO_DIR ? 'videos' : 'music';
        if (is_dir($absolute)) {
            $this->deleteDirectory($absolute);
            $prefix = $this->relPath($absolute, $root) . '/';
            $this->db->prepare("DELETE FROM {$table} WHERE substr(file, 1, ?) = ?")->execute([strlen($prefix), $prefix]);
        } else {
            if (!@unlink($absolute)) {
                Response::json(['status' => 'error', 'message' => Language::t('api.delete_failed')], 500);
            }
            $this->db->prepare("DELETE FROM {$table} WHERE file = ?")->execute([$this->relPath($absolute, $root)]);
        }
        Response::json(['status' => 'success', 'message' => Language::t('api.deleted')]);
    }

    /**
     * Chunked upload endpoint (bypasses post_max_size, no php.ini changes).
     *
     * JSON body: { op: "start" | "finish", id, name, size, path }
     * Raw body:  binary chunk, with the upload id in the X-Upload-Id header.
     * Uploaded files are indexed in the library immediately on "finish".
     */
    public function uploadChunk(): void
    {
        Security::verifyCsrf();
        $this->requireUser();

        $raw = file_get_contents('php://input');
        $data = json_decode($raw === false ? '' : $raw, true);
        $data = is_array($data) ? $data : [];
        $op = (string)($data['op'] ?? '');
        $id = (string)($data['id'] ?? ($_SERVER['HTTP_X_UPLOAD_ID'] ?? ''));
        $dir = (string)($data['dir'] ?? ($_SERVER['HTTP_X_UPLOAD_DIR'] ?? 'music'));
        $root = $this->rootDir($dir);

        if (!preg_match('/^[A-Za-z0-9_-]{8,64}$/', $id)) {
            Response::json(['status' => 'error', 'message' => Language::t('api.invalid_upload_id')], 400);
        }
        $tmpFile = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'apollon_upload_' . $id;

        if ($op === 'start') {
            $name = basename((string)($data['name'] ?? ''));
            $size = (int)($data['size'] ?? -1);
            if ($name === '' || $size < 0) {
                Response::json(['status' => 'error', 'message' => Language::t('api.invalid_file')], 400);
            }
            $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));
            $allowed = $root === VIDEO_DIR ? VIDEO_EXTENSIONS : AUDIO_EXTENSIONS;
            if (!in_array($ext, $allowed, true)) {
                Response::json(['status' => 'error', 'message' => Language::t('api.unsupported_type', ['name' => $name])], 400);
            }
            $dirPath = $this->safePath((string)($data['path'] ?? ''), true, $root);
            if (!is_dir($dirPath)) {
                Response::json(['status' => 'error', 'message' => Language::t('api.target_folder_not_found')], 404);
            }

            $this->cleanupStaleUploads();
            $handle = fopen($tmpFile, 'x+b');
            if ($handle === false) {
                // Same id reused (interrupted upload): restart from scratch.
                $handle = fopen($tmpFile, 'w+b');
            }
            if ($handle === false) {
                Response::json(['status' => 'error', 'message' => Language::t('api.upload_start_failed')], 500);
            }
            fclose($handle);
            Response::json(['status' => 'success']);
        }

        if ($op === 'finish') {
            $name = basename((string)($data['name'] ?? ''));
            $size = (int)($data['size'] ?? -1);
            if (!is_file($tmpFile) || filesize($tmpFile) !== $size) {
                @unlink($tmpFile);
                Response::json(['status' => 'error', 'message' => Language::t('api.upload_incomplete')], 400);
            }
            $dirPath = $this->safePath((string)($data['path'] ?? ''), true, $root);
            if (!is_dir($dirPath)) {
                @unlink($tmpFile);
                Response::json(['status' => 'error', 'message' => Language::t('api.target_folder_not_found')], 404);
            }

            $target = $this->uniquePath($dirPath, $name);
            $moved = @rename($tmpFile, $target);
            if (!$moved) {
                // Cross-filesystem fallback.
                $moved = @copy($tmpFile, $target) && @unlink($tmpFile);
            }
            if (!$moved) {
                @unlink($tmpFile);
                Response::json(['status' => 'error', 'message' => Language::t('api.upload_save_failed')], 500);
            }
            if ($root === VIDEO_DIR) {
                (new VideoScanner($this->db))->indexFile($target);
            } else {
                (new MusicScanner($this->db))->indexFile($target);
            }
            Response::json(['status' => 'success', 'message' => Language::t('api.uploaded'), 'file' => $name]);
        }

        // Raw binary chunk (no JSON body).
        if ($raw === false || $raw === '') {
            if (is_file($tmpFile)) {
                Response::json(['status' => 'success', 'received' => 0]);
            }
            Response::json(['status' => 'error', 'message' => Language::t('api.upload_not_started')], 400);
        }
        $written = file_put_contents($tmpFile, $raw, FILE_APPEND | LOCK_EX);
        if ($written === false) {
            Response::json(['status' => 'error', 'message' => Language::t('api.upload_chunk_failed')], 500);
        }
        if (filesize($tmpFile) > MAX_UPLOAD_SIZE) {
            @unlink($tmpFile);
            Response::json(['status' => 'error', 'message' => Language::t('api.upload_too_large')], 413);
        }
        Response::json(['status' => 'success', 'received' => $written]);
    }

    /**
     * Remove leftover temp files from interrupted uploads (older than 1 hour).
     */
    private function cleanupStaleUploads(): void
    {
        foreach (glob(sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'apollon_upload_*') ?: [] as $stale) {
            if (is_file($stale) && time() - filemtime($stale) > 3600) {
                @unlink($stale);
            }
        }
    }

    /**
     * Return a non-existing path in $dir for $name ("name (1).ext", ...).
     */
    private function uniquePath(string $dir, string $name): string
    {
        $target = $dir . DIRECTORY_SEPARATOR . $name;
        if (!file_exists($target)) {
            return $target;
        }
        $base = pathinfo($name, PATHINFO_FILENAME);
        $suffix = pathinfo($name, PATHINFO_EXTENSION) !== '' ? '.' . pathinfo($name, PATHINFO_EXTENSION) : '';
        $counter = 1;
        while (file_exists($target)) {
            $target = $dir . DIRECTORY_SEPARATOR . $base . ' (' . $counter . ')' . $suffix;
            $counter++;
        }
        return $target;
    }

    private function deleteDirectory(string $dir): void
    {
        $items = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($dir, \RecursiveDirectoryIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST
        );
        foreach ($items as $item) {
            $item->isDir() ? rmdir($item->getPathname()) : unlink($item->getPathname());
        }
        rmdir($dir);
    }
}
