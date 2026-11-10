<?php
declare(strict_types=1);

namespace Apollon\Controllers;

use Apollon\Request;
use Apollon\Language;
use Apollon\Response;
use Apollon\Session;
use PDO;

final class LibraryController
{
    public function __construct(private PDO $db)
    {
    }

    public function getSongs(): void
    {
        $userId = Session::userId();
        $sortKey = Request::query('sort', 'title_asc');
        $sortMap = [
            'id_desc' => 'ORDER BY m.id DESC',
            'artist_asc' => 'ORDER BY m.artist COLLATE NOCASE ASC, m.album COLLATE NOCASE ASC, m.title COLLATE NOCASE ASC',
            'title_asc' => 'ORDER BY m.title COLLATE NOCASE ASC',
            'album_asc' => 'ORDER BY m.album COLLATE NOCASE ASC, m.title COLLATE NOCASE ASC',
            'year_desc' => 'ORDER BY m.year DESC, m.title COLLATE NOCASE ASC',
            'year_asc' => 'ORDER BY m.year ASC, m.title COLLATE NOCASE ASC',
        ];
        $orderBy = $sortMap[$sortKey] ?? $sortMap['title_asc'];

        $whereClauses = [];
        $params = [$userId];

        $artist = Request::query('artist');
        if (!empty($artist)) {
            $whereClauses[] = 'm.artist = ?';
            $params[] = $artist;
        }
        $album = Request::query('album');
        if (!empty($album)) {
            $whereClauses[] = 'm.album = ?';
            $params[] = $album;
        }
        $whereSql = count($whereClauses) > 0 ? 'WHERE ' . implode(' AND ', $whereClauses) : '';

        $stmt = $this->db->prepare("
            SELECT m.id, m.title, m.artist, m.album, m.year, m.duration, m.user_id,
            CASE WHEN ? IS NOT NULL AND f.song_id IS NOT NULL THEN 1 ELSE 0 END AS is_favorite
            FROM music m
            LEFT JOIN favorites f ON m.id = f.song_id AND f.user_id = ?
            {$whereSql} {$orderBy} " . Request::limitClause()
        );
        $stmt->execute(array_merge([$userId], $params));
        Response::json($stmt->fetchAll());
    }

    public function getArtists(): void
    {
        $sortKey = Request::query('sort', 'name_asc');
        $sortMap = [
            'name_asc' => 'ORDER BY m.artist COLLATE NOCASE ASC',
            'name_desc' => 'ORDER BY m.artist COLLATE NOCASE DESC',
            'song_count_desc' => 'ORDER BY song_count DESC',
        ];
        $orderBy = $sortMap[$sortKey] ?? $sortMap['name_asc'];

        $stmt = $this->db->prepare("
            SELECT m.artist,
              (SELECT m2.id FROM music m2 WHERE m2.artist = m.artist AND m2.image IS NOT NULL LIMIT 1) AS image_id,
              COUNT(m.id) AS song_count
            FROM music m
            WHERE m.artist != '' AND m.artist IS NOT NULL
            GROUP BY m.artist
            {$orderBy}
            " . Request::limitClause()
        );
        $stmt->execute();
        Response::json($stmt->fetchAll());
    }

    public function getAlbums(): void
    {
        $sortKey = Request::query('sort', 'album_asc');
        $sortMap = [
            'album_asc' => 'ORDER BY m.album COLLATE NOCASE ASC',
            'album_desc' => 'ORDER BY m.album COLLATE NOCASE DESC',
            'artist_asc' => 'ORDER BY m.artist COLLATE NOCASE ASC',
            'year_desc' => 'ORDER BY MAX(m.year) DESC',
            'year_asc' => 'ORDER BY MAX(m.year) ASC',
        ];
        $orderBy = $sortMap[$sortKey] ?? $sortMap['album_asc'];

        $stmt = $this->db->prepare("
            SELECT m.album, m.artist,
              (SELECT m2.id FROM music m2 WHERE m2.album = m.album AND m2.image IS NOT NULL LIMIT 1) AS image_id,
              COUNT(m.id) AS song_count
            FROM music m
            WHERE m.album != '' AND m.album IS NOT NULL
            GROUP BY m.album
            {$orderBy}
            " . Request::limitClause()
        );
        $stmt->execute();
        Response::json($stmt->fetchAll());
    }

    public function getViewData(): void
    {
        $userId = Session::userId();
        $type = Request::query('type', '');
        $name = rawurldecode(Request::query('name', ''));
        $sort = Request::query('sort', 'title_asc');
        if (empty($type) || empty($name)) {
            Response::json(['error' => Language::t('api.invalid_params')], 400);
        }

        $details = null;
        $songs = [];

        if ($type === 'playlist') {
            $stmtDetails = $this->db->prepare("
              SELECT p.name, p.public_id, COALESCE(u.username, 'Anonymous') as creator,
              (SELECT COUNT(*) FROM playlist_songs WHERE playlist_id = p.id) as song_count,
              (SELECT SUM(m.duration) FROM music m JOIN playlist_songs ps ON m.id = ps.song_id WHERE ps.playlist_id = p.id) as total_duration,
              (SELECT ps.song_id FROM playlist_songs ps WHERE ps.playlist_id = p.id ORDER BY ps.added_at DESC LIMIT 1) as image_id
              FROM playlists p LEFT JOIN users u ON p.user_id = u.id
              WHERE p.public_id = ?
            ");
            $stmtDetails->execute([$name]);
            $details = $stmtDetails->fetch();
            if ($details) {
                $details['image_url'] = '?action=get_image&id=' . ($details['image_id'] ?? 0);
            }

            $sortMap = [
                'manual_order' => 'ORDER BY ps.sort_order ASC',
                'artist_asc' => 'ORDER BY m.artist COLLATE NOCASE ASC',
                'title_asc' => 'ORDER BY m.title COLLATE NOCASE ASC',
                'album_asc' => 'ORDER BY m.album COLLATE NOCASE ASC',
            ];
            $orderBy = $sortMap[$sort] ?? $sortMap['manual_order'];

            $stmtSongs = $this->db->prepare("
              SELECT m.id, m.title, m.artist, m.album, m.year, m.duration, m.user_id,
              CASE WHEN ? IS NOT NULL AND f.song_id IS NOT NULL THEN 1 ELSE 0 END AS is_favorite
              FROM music m
              JOIN playlist_songs ps ON m.id = ps.song_id
              JOIN playlists p ON ps.playlist_id = p.id
              LEFT JOIN favorites f ON m.id = f.song_id AND f.user_id = ?
              WHERE p.public_id = ? {$orderBy} " . Request::limitClause()
            );
            $stmtSongs->execute([$userId, $userId, $name]);
            $songs = $stmtSongs->fetchAll();
        } elseif (in_array($type, ['artist', 'album'], true)) {
            $field = $type;
            $stmtDetails = $this->db->prepare("
              SELECT COUNT(*) as song_count, SUM(duration) as total_duration,
              (SELECT id FROM music WHERE {$field} = ? AND image IS NOT NULL LIMIT 1) as image_id
              FROM music WHERE {$field} = ?
            ");
            $stmtDetails->execute([$name, $name]);
            $details = $stmtDetails->fetch();
            $details['name'] = $name;
            $details['image_url'] = '?action=get_image&id=' . ($details['image_id'] ?? 0);
            $details['public_id'] = null;

            $sortMap = [
                'artist_asc' => 'ORDER BY m.artist COLLATE NOCASE ASC',
                'title_asc' => 'ORDER BY m.title COLLATE NOCASE ASC',
                'album_asc' => 'ORDER BY m.album COLLATE NOCASE ASC',
                'year_desc' => 'ORDER BY m.year DESC',
                'year_asc' => 'ORDER BY m.year ASC',
            ];
            $orderBy = $sortMap[$sort] ?? $sortMap['title_asc'];

            $stmtSongs = $this->db->prepare("
              SELECT m.id, m.title, m.artist, m.album, m.year, m.duration, m.user_id,
              CASE WHEN ? IS NOT NULL AND f.song_id IS NOT NULL THEN 1 ELSE 0 END AS is_favorite
              FROM music m
              LEFT JOIN favorites f ON m.id = f.song_id AND f.user_id = ?
              WHERE m.{$field} = ? {$orderBy} " . Request::limitClause()
            );
            $stmtSongs->execute([$userId, $userId, $name]);
            $songs = $stmtSongs->fetchAll();
        }
        Response::json(['details' => $details, 'songs' => $songs]);
    }

    public function getViewIds(): void
    {
        $userId = Session::userId();
        $postData = Request::jsonBody();
        $viewType = $postData['view_type'] ?? '';
        $param = urldecode($postData['param'] ?? '');
        $sort = $postData['sort'] ?? '';

        $sql = "SELECT m.id FROM music m ";
        $conditions = "";
        $params = [];
        $defaultSort = 'title_asc';

        switch ($viewType) {
            case 'get_songs':
                break;
            case 'get_favorites':
                if (!$userId) {
                    Response::json([]);
                }
                $sql = "SELECT m.id FROM music m JOIN favorites f ON m.id = f.song_id ";
                $conditions = "WHERE f.user_id = ?";
                $params[] = $userId;
                $defaultSort = 'manual_order';
                break;
            case 'artist_songs':
                $conditions = "WHERE m.artist = ?";
                $params[] = $param;
                $defaultSort = 'album_asc';
                break;
            case 'album_songs':
                $conditions = "WHERE m.album = ?";
                $params[] = $param;
                $defaultSort = 'title_asc';
                break;
            case 'playlist_songs':
                $sql = "SELECT m.id FROM music m JOIN playlist_songs ps ON m.id = ps.song_id JOIN playlists p ON ps.playlist_id = p.id ";
                $conditions = "WHERE p.public_id = ?";
                $params[] = $param;
                $defaultSort = 'manual_order';
                break;
            case 'search':
                $conditions = "WHERE m.title LIKE ? OR m.artist LIKE ? OR m.album LIKE ?";
                $queryParam = '%' . $param . '%';
                $params = [$queryParam, $queryParam, $queryParam];
                break;
            default:
                Response::json([]);
        }

        $sortMap = [
            'manual_order' => 'ORDER BY ps.sort_order ASC',
            'id_desc' => 'ORDER BY m.id DESC',
            'artist_asc' => 'ORDER BY m.artist COLLATE NOCASE ASC, m.album COLLATE NOCASE ASC, m.title COLLATE NOCASE ASC',
            'title_asc' => 'ORDER BY m.title COLLATE NOCASE ASC',
            'album_asc' => 'ORDER BY m.album COLLATE NOCASE ASC, m.title COLLATE NOCASE ASC',
            'year_desc' => 'ORDER BY m.year DESC, m.title COLLATE NOCASE ASC',
            'year_asc' => 'ORDER BY m.year ASC, m.title COLLATE NOCASE ASC',
        ];
        if ($viewType === 'get_favorites') {
            $sortMap['manual_order'] = 'ORDER BY f.sort_order ASC';
        }
        $orderBy = $sortMap[$sort] ?? $sortMap[$defaultSort];

        $stmt = $this->db->prepare($sql . $conditions . " " . $orderBy);
        $stmt->execute($params);
        Response::json($stmt->fetchAll(PDO::FETCH_COLUMN));
    }

    public function search(): void
    {
        $userId = Session::userId();
        $query = '%' . (Request::query('q', '')) . '%';
        $orderBy = 'ORDER BY m.title COLLATE NOCASE ASC';
        $stmt = $this->db->prepare("
            SELECT m.id, m.title, m.artist, m.album, m.year, m.duration, m.user_id,
            CASE WHEN ? IS NOT NULL AND f.song_id IS NOT NULL THEN 1 ELSE 0 END AS is_favorite
            FROM music m
            LEFT JOIN favorites f ON m.id = f.song_id AND f.user_id = ?
            WHERE (m.title LIKE ? OR m.artist LIKE ? OR m.album LIKE ?)
            {$orderBy} " . Request::limitClause()
        );
        $stmt->execute([$userId, $userId, $query, $query, $query]);
        Response::json($stmt->fetchAll());
    }

    public function getSongData(): void
    {
        $userId = Session::userId();
        $id = (int)(Request::query('id', '0'));
        $stmt = $this->db->prepare("
            SELECT m.id, m.file, m.title, m.artist, m.album, m.year, m.duration, m.bitrate, m.user_id,
            CASE WHEN ? IS NOT NULL AND f.song_id IS NOT NULL THEN 1 ELSE 0 END AS is_favorite
            FROM music m
            LEFT JOIN favorites f ON m.id = f.song_id AND f.user_id = ?
            WHERE m.id = ?
        ");
        $stmt->execute([$userId, $userId, $id]);
        $song = $stmt->fetch();
        if ($song) {
            $song['stream_url'] = '?action=get_stream&id=' . $song['id'];
            $song['image_url'] = '?action=get_image&id=' . $song['id'];
        }
        Response::json($song);
    }
}
