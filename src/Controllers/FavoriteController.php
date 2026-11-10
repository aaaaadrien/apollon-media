<?php
declare(strict_types=1);

namespace Apollon\Controllers;

use Apollon\Request;
use Apollon\Language;
use Apollon\Response;
use Apollon\Security;
use Apollon\Session;
use PDO;

final class FavoriteController
{
    public function __construct(private PDO $db)
    {
    }

    public function getFavorites(): void
    {
        $userId = Session::userId();
        if (!$userId) {
            Response::json([]);
        }
        $sortKey = Request::query('sort', 'manual_order');
        $sortMap = [
            'manual_order' => 'ORDER BY f.sort_order ASC',
            'artist_asc' => 'ORDER BY m.artist COLLATE NOCASE ASC, m.title COLLATE NOCASE ASC',
            'title_asc' => 'ORDER BY m.title COLLATE NOCASE ASC',
            'album_asc' => 'ORDER BY m.album COLLATE NOCASE ASC, m.title COLLATE NOCASE ASC',
        ];
        $orderBy = $sortMap[$sortKey] ?? $sortMap['manual_order'];
        $stmt = $this->db->prepare("
            SELECT m.id, m.title, m.artist, m.album, m.year, m.duration, m.user_id, 1 as is_favorite
            FROM music m
            JOIN favorites f ON m.id = f.song_id
            WHERE f.user_id = ? {$orderBy} " . Request::limitClause()
        );
        $stmt->execute([$userId]);
        Response::json($stmt->fetchAll());
    }

    /**
     * Return the current user's favorite videos, split into movies and
     * episodes (each favorite is a single video row).
     */
    public function getFavoriteVideos(): void
    {
        $userId = Session::userId();
        if (!$userId) {
            Response::json(['movies' => [], 'episodes' => []]);
        }
        $stmt = $this->db->prepare("
            SELECT v.id, v.title, v.type, v.series, v.season, v.episode, v.year, v.duration,
                   COALESCE(w.watched, 0) AS is_watched
            FROM videos v
            JOIN video_favorites f ON f.video_id = v.id
            LEFT JOIN video_watches w ON w.video_id = v.id AND w.user_id = ?
            WHERE f.user_id = ?
            ORDER BY v.series COLLATE NOCASE ASC, v.season ASC, v.episode ASC, v.title COLLATE NOCASE ASC
        ");
        $stmt->execute([$userId, $userId]);
        $movies = [];
        $episodes = [];
        foreach ($stmt->fetchAll() as $row) {
            if ($row['type'] === 'movie') {
                $movies[] = $row;
            } else {
                $episodes[] = $row;
            }
        }
        Response::json(['movies' => $movies, 'episodes' => $episodes]);
    }

    public function toggleVideoFavorite(): void
    {
        Security::verifyCsrf();
        $userId = Session::userId();
        if (!$userId) {
            Response::json(['status' => 'error', 'message' => Language::t('api.unauthorized')], 403);
        }
        $data = Request::jsonBody();
        $videoId = (int)($data['id'] ?? 0);
        if ($videoId <= 0) {
            Response::json(['status' => 'error', 'message' => Language::t('api.invalid_params')], 400);
        }
        $stmt = $this->db->prepare("SELECT id FROM videos WHERE id = ?");
        $stmt->execute([$videoId]);
        if (!$stmt->fetch()) {
            Response::json(['status' => 'error', 'message' => Language::t('api.invalid_params')], 404);
        }
        $stmt = $this->db->prepare("SELECT video_id FROM video_favorites WHERE user_id = ? AND video_id = ?");
        $stmt->execute([$userId, $videoId]);
        if ($stmt->fetch()) {
            $this->db->prepare("DELETE FROM video_favorites WHERE user_id = ? AND video_id = ?")->execute([$userId, $videoId]);
            Response::json(['status' => 'removed', 'is_favorite' => false]);
        } else {
            $this->db->prepare("INSERT INTO video_favorites (user_id, video_id) VALUES (?, ?)")->execute([$userId, $videoId]);
            Response::json(['status' => 'added', 'is_favorite' => true]);
        }
    }

    public function toggleFavorite(): void
    {
        Security::verifyCsrf();
        $userId = Session::userId();
        if (!$userId) {
            Response::json(['status' => 'error', 'message' => Language::t('api.unauthorized')], 403);
        }
        $data = Request::jsonBody();
        $songId = (int)($data['id'] ?? 0);
        $stmt = $this->db->prepare("SELECT song_id FROM favorites WHERE user_id = ? AND song_id = ?");
        $stmt->execute([$userId, $songId]);
        if ($stmt->fetch()) {
            $this->db->prepare("DELETE FROM favorites WHERE user_id = ? AND song_id = ?")->execute([$userId, $songId]);
            Response::json(['status' => 'removed', 'is_favorite' => false]);
        } else {
            $stmtOrder = $this->db->prepare("SELECT COALESCE(MAX(sort_order), 0) FROM favorites WHERE user_id = ?");
            $stmtOrder->execute([$userId]);
            $maxOrder = (int)$stmtOrder->fetchColumn();
            $this->db->prepare("INSERT INTO favorites (user_id, song_id, sort_order) VALUES (?, ?, ?)")
                ->execute([$userId, $songId, $maxOrder + 1]);
            Response::json(['status' => 'added', 'is_favorite' => true]);
        }
    }

    public function updateFavoriteOrder(): void
    {
        Security::verifyCsrf();
        $userId = Session::userId();
        if (!$userId) {
            Response::json(['status' => 'error', 'message' => Language::t('api.unauthorized')], 403);
        }
        $data = Request::jsonBody();
        $orderedIds = $data['ids'] ?? [];
        $this->db->beginTransaction();
        try {
            $stmt = $this->db->prepare("UPDATE favorites SET sort_order = ? WHERE user_id = ? AND song_id = ?");
            foreach ($orderedIds as $index => $songId) {
                $stmt->execute([$index, (int)$userId, (int)$songId]);
            }
            $this->db->commit();
            Response::json(['status' => 'success']);
        } catch (\Exception $e) {
            $this->db->rollBack();
            Response::json(['status' => 'error', 'message' => Language::t('api.order_failed')], 500);
        }
    }
}
