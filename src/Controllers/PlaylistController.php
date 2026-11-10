<?php
declare(strict_types=1);

namespace Apollon\Controllers;

use Apollon\Request;
use Apollon\Language;
use Apollon\Response;
use Apollon\Security;
use Apollon\Session;
use PDO;

final class PlaylistController
{
    public function __construct(private PDO $db)
    {
    }

    public function getUserPlaylists(): void
    {
        $userId = Session::userId();
        if (!$userId) {
            Response::json([]);
        }
        $sortKey = Request::query('sort', 'name_asc');
        $sortMap = [
            'name_asc' => 'ORDER BY p.name COLLATE NOCASE ASC',
            'name_desc' => 'ORDER BY p.name COLLATE NOCASE DESC',
            'modified_desc' => 'ORDER BY p.created_at DESC',
        ];
        $orderBy = $sortMap[$sortKey] ?? $sortMap['name_asc'];
        $stmt = $this->db->prepare("
            SELECT p.id, p.name, p.public_id,
              (SELECT COUNT(*) FROM playlist_songs ps WHERE ps.playlist_id = p.id) AS song_count,
              (SELECT ps.song_id FROM playlist_songs ps WHERE ps.playlist_id = p.id ORDER BY ps.added_at DESC LIMIT 1) AS image_id
            FROM playlists p
            WHERE p.user_id = ?
            {$orderBy}
        ");
        $stmt->execute([$userId]);
        Response::json($stmt->fetchAll());
    }

    public function getPlaylistSongs(): void
    {
        $userId = Session::userId();
        $publicId = Request::query('public_id', '');
        $sortKey = Request::query('sort', 'manual_order');
        $sortMap = [
            'manual_order' => 'ORDER BY ps.sort_order ASC',
            'artist_asc' => 'ORDER BY m.artist COLLATE NOCASE ASC, m.title COLLATE NOCASE ASC',
            'title_asc' => 'ORDER BY m.title COLLATE NOCASE ASC',
            'album_asc' => 'ORDER BY m.album COLLATE NOCASE ASC, m.title COLLATE NOCASE ASC',
        ];
        $orderBy = $sortMap[$sortKey] ?? $sortMap['manual_order'];

        $stmt = $this->db->prepare("
            SELECT m.id, m.title, m.artist, m.album, m.year, m.duration, m.user_id,
            CASE WHEN ? IS NOT NULL AND f.song_id IS NOT NULL THEN 1 ELSE 0 END AS is_favorite
            FROM music m
            JOIN playlist_songs ps ON m.id = ps.song_id
            JOIN playlists p ON ps.playlist_id = p.id
            LEFT JOIN favorites f ON m.id = f.song_id AND f.user_id = ?
            WHERE p.public_id = ?
            {$orderBy} " . Request::limitClause()
        );
        $stmt->execute([$userId, $userId, $publicId]);
        Response::json($stmt->fetchAll());
    }

    public function createPlaylist(): void
    {
        Security::verifyCsrf();
        $userId = Session::userId();
        if (!$userId) {
            Response::json(['status' => 'error', 'message' => Language::t('api.unauthorized')], 403);
        }
        $data = Request::jsonBody();
        $name = trim((string)($data['name'] ?? ''));
        if (empty($name)) {
            Response::json(['status' => 'error', 'message' => Language::t('api.playlist_name_empty')], 400);
        }
        $publicId = bin2hex(random_bytes(8));
        $stmt = $this->db->prepare("INSERT INTO playlists (user_id, name, public_id) VALUES (?, ?, ?)");
        $stmt->execute([$userId, $name, $publicId]);
        Response::json(['status' => 'success', 'message' => Language::t('api.playlist_created')]);
    }

    public function editPlaylist(): void
    {
        Security::verifyCsrf();
        $userId = Session::userId();
        if (!$userId) {
            Response::json(['status' => 'error', 'message' => Language::t('api.unauthorized')], 403);
        }
        $data = Request::jsonBody();
        $publicId = $data['public_id'] ?? '';
        $newName = trim((string)($data['name'] ?? ''));
        if (empty($newName)) {
            Response::json(['status' => 'error', 'message' => Language::t('api.name_empty')], 400);
        }
        $stmt = $this->db->prepare("UPDATE playlists SET name = ? WHERE public_id = ? AND user_id = ?");
        $stmt->execute([$newName, $publicId, $userId]);
        Response::json(['status' => 'success', 'message' => Language::t('api.playlist_updated')]);
    }

    public function deletePlaylist(): void
    {
        Security::verifyCsrf();
        $userId = Session::userId();
        if (!$userId) {
            Response::json(['status' => 'error', 'message' => Language::t('api.unauthorized')], 403);
        }
        $data = Request::jsonBody();
        $publicId = $data['public_id'] ?? '';
        $stmt = $this->db->prepare("DELETE FROM playlists WHERE public_id = ? AND user_id = ?");
        $stmt->execute([$publicId, $userId]);
        Response::json(['status' => 'success', 'message' => Language::t('api.playlist_deleted')]);
    }

    public function addToPlaylist(): void
    {
        Security::verifyCsrf();
        $userId = Session::userId();
        if (!$userId) {
            Response::json(['status' => 'error', 'message' => Language::t('api.unauthorized')], 403);
        }
        $data = Request::jsonBody();
        $playlistId = (int)($data['playlist_id'] ?? 0);
        $songId = (int)($data['song_id'] ?? 0);

        $stmtOwner = $this->db->prepare("SELECT id FROM playlists WHERE id = ? AND user_id = ?");
        $stmtOwner->execute([$playlistId, $userId]);
        if (!$stmtOwner->fetch()) {
            Response::json(['status' => 'error', 'message' => Language::t('api.permission_denied')], 403);
        }

        $stmtExists = $this->db->prepare("SELECT 1 FROM playlist_songs WHERE playlist_id = ? AND song_id = ?");
        $stmtExists->execute([$playlistId, $songId]);
        if ($stmtExists->fetch()) {
            Response::json(['status' => 'exists', 'message' => Language::t('api.song_in_playlist')]);
        }

        $stmtOrder = $this->db->prepare("SELECT COALESCE(MAX(sort_order), 0) FROM playlist_songs WHERE playlist_id = ?");
        $stmtOrder->execute([$playlistId]);
        $maxOrder = (int)$stmtOrder->fetchColumn();

        $stmt = $this->db->prepare("INSERT OR IGNORE INTO playlist_songs (playlist_id, song_id, sort_order) VALUES (?, ?, ?)");
        $stmt->execute([$playlistId, $songId, $maxOrder + 1]);
        Response::json(['status' => 'success', 'message' => Language::t('api.song_added_playlist')]);
    }

    public function removeFromPlaylist(): void
    {
        Security::verifyCsrf();
        $userId = Session::userId();
        if (!$userId) {
            Response::json(['status' => 'error', 'message' => Language::t('api.unauthorized')], 403);
        }
        $data = Request::jsonBody();
        $publicId = $data['playlist_public_id'] ?? '';
        $songId = (int)($data['song_id'] ?? 0);

        $stmt = $this->db->prepare("
            DELETE FROM playlist_songs
            WHERE song_id = ? AND playlist_id = (SELECT id FROM playlists WHERE public_id = ? AND user_id = ?)
        ");
        $stmt->execute([$songId, $publicId, $userId]);
        Response::json(['status' => 'success', 'message' => Language::t('api.song_removed_playlist')]);
    }

    public function updatePlaylistOrder(): void
    {
        Security::verifyCsrf();
        $userId = Session::userId();
        if (!$userId) {
            Response::json(['status' => 'error', 'message' => Language::t('api.unauthorized')], 403);
        }
        $data = Request::jsonBody();
        $publicId = $data['playlist_public_id'] ?? '';
        $orderedIds = $data['ids'] ?? [];

        $stmt = $this->db->prepare("SELECT id FROM playlists WHERE public_id = ? AND user_id = ?");
        $stmt->execute([$publicId, $userId]);
        $playlist = $stmt->fetch();
        if (!$playlist) {
            Response::json(['status' => 'error', 'message' => Language::t('api.not_found')], 404);
        }

        $this->db->beginTransaction();
        try {
            $updateStmt = $this->db->prepare("UPDATE playlist_songs SET sort_order = ? WHERE playlist_id = ? AND song_id = ?");
            foreach ($orderedIds as $index => $songId) {
                $updateStmt->execute([$index, $playlist['id'], (int)$songId]);
            }
            $this->db->commit();
            Response::json(['status' => 'success']);
        } catch (\Exception $e) {
            $this->db->rollBack();
            Response::json(['status' => 'error', 'message' => Language::t('api.order_failed')], 500);
        }
    }

    public function exportPlaylist(): void
    {
        $publicId = Request::query('public_id', '');
        $stmt = $this->db->prepare("SELECT id, name FROM playlists WHERE public_id = ?");
        $stmt->execute([$publicId]);
        $pl = $stmt->fetch();
        if (!$pl) {
            Response::json(['error' => 'Playlist not found'], 404);
        }

        $stmtSongs = $this->db->prepare("
            SELECT m.title, m.artist, m.album, m.duration, ps.sort_order
            FROM playlist_songs ps
            JOIN music m ON ps.song_id = m.id
            WHERE ps.playlist_id = ?
            ORDER BY ps.sort_order ASC
        ");
        $stmtSongs->execute([$pl['id']]);
        $songs = $stmtSongs->fetchAll();

        $exportData = [
            'app' => APP_NAME,
            'playlist_name' => $pl['name'],
            'exported_at' => date('c'),
            'songs' => $songs,
        ];
        header('Content-Type: application/json; charset=utf-8');
        header('Content-Disposition: attachment; filename="playlist_' . preg_replace('/[^a-zA-Z0-9_-]/', '_', $pl['name']) . '.json"');
        echo json_encode($exportData, JSON_PRETTY_PRINT | JSON_INVALID_UTF8_SUBSTITUTE);
        exit;
    }

    public function importPlaylist(): void
    {
        Security::verifyCsrf();
        $userId = Session::userId();
        if (!$userId) {
            Response::json(['status' => 'error', 'message' => Language::t('api.unauthorized')], 403);
        }
        if (!isset($_FILES['file']) || $_FILES['file']['error'] !== UPLOAD_ERR_OK) {
            Response::json(['status' => 'error', 'message' => Language::t('api.no_playlist_file')], 400);
        }

        $content = file_get_contents($_FILES['file']['tmp_name']);
        $json = json_decode($content === false ? '' : $content, true);
        if (!$json || empty($json['playlist_name']) || !isset($json['songs'])) {
            Response::json(['status' => 'error', 'message' => Language::t('api.invalid_playlist_json')], 400);
        }

        $plName = trim((string)$json['playlist_name']);
        $publicId = bin2hex(random_bytes(8));

        $stmt = $this->db->prepare("INSERT INTO playlists (user_id, name, public_id) VALUES (?, ?, ?)");
        $stmt->execute([$userId, $plName, $publicId]);
        $newPlId = $this->db->lastInsertId();

        $findStmt = $this->db->prepare("SELECT id FROM music WHERE title = ? AND artist = ? LIMIT 1");
        $findFallback = $this->db->prepare("SELECT id FROM music WHERE title = ? LIMIT 1");
        $insStmt = $this->db->prepare("INSERT OR IGNORE INTO playlist_songs (playlist_id, song_id, sort_order) VALUES (?, ?, ?)");

        $added = 0;
        foreach ($json['songs'] as $idx => $s) {
            $title = $s['title'] ?? '';
            $artist = $s['artist'] ?? '';
            if (!$title) {
                continue;
            }

            $findStmt->execute([$title, $artist]);
            $sid = $findStmt->fetchColumn();
            if (!$sid) {
                $findFallback->execute([$title]);
                $sid = $findFallback->fetchColumn();
            }
            if ($sid) {
                $insStmt->execute([$newPlId, $sid, $idx]);
                $added++;
            }
        }
        Response::json(['status' => 'success', 'message' => Language::t('api.playlist_imported', ['name' => $plName, 'count' => $added])]);
    }
}
