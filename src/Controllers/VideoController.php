<?php
declare(strict_types=1);

namespace Apollon\Controllers;

use Apollon\Request;
use Apollon\Response;
use Apollon\Language;
use Apollon\Security;
use Apollon\Session;
use Apollon\Services\VideoTranscoder;
use PDO;

final class VideoController
{
    public function __construct(private PDO $db)
    {
    }

    /**
     * Build the LEFT JOIN + SELECT fragment exposing the current user's
     * watched state for a `videos v` query. Returns [join, select, params].
     * Guests get a constant `is_watched = 0` (no join).
     */
    private function watchJoin(bool $withPosition = false): array
    {
        $userId = Session::userId();
        if (!$userId) {
            return ['', ', 0 AS is_watched', []];
        }
        $pos = $withPosition ? ', w.position_seconds AS position' : '';
        return [
            'LEFT JOIN video_watches w ON w.video_id = v.id AND w.user_id = ?',
            ", COALESCE(w.watched, 0) AS is_watched{$pos}",
            [$userId],
        ];
    }

    /**
     * Build the LEFT JOIN + SELECT fragment exposing the current user's
     * favorite state for a `videos v` query. Returns [join, select, params].
     * Guests get a constant `is_favorite = 0` (no join).
     */
    private function favoriteJoin(): array
    {
        $userId = Session::userId();
        if (!$userId) {
            return ['', ', 0 AS is_favorite', []];
        }
        return [
            'LEFT JOIN video_favorites vf ON vf.video_id = v.id AND vf.user_id = ?',
            ', (vf.video_id IS NOT NULL) AS is_favorite',
            [$userId],
        ];
    }

    public function getMovies(): void
    {
        $sortKey = Request::query('sort', 'title_asc');
        $sortMap = [
            'title_asc' => 'ORDER BY v.title COLLATE NOCASE ASC',
            'title_desc' => 'ORDER BY v.title COLLATE NOCASE DESC',
            'year_desc' => 'ORDER BY v.year DESC, v.title COLLATE NOCASE ASC',
            'year_asc' => 'ORDER BY v.year ASC, v.title COLLATE NOCASE ASC',
            'id_desc' => 'ORDER BY v.id DESC',
        ];
        $orderBy = $sortMap[$sortKey] ?? $sortMap['title_asc'];
        [$wJoin, $wSel, $wParams] = $this->watchJoin();
        [$fJoin, $fSel, $fParams] = $this->favoriteJoin();

        $stmt = $this->db->prepare("
            SELECT v.id, v.title, v.year, v.duration {$wSel}{$fSel}
            FROM videos v
            {$wJoin}
            {$fJoin}
            WHERE v.type = 'movie'
            {$orderBy} " . Request::limitClause()
        );
        $stmt->execute([...$wParams, ...$fParams]);
        Response::json($stmt->fetchAll());
    }

    public function getSeries(): void
    {
        $sortKey = Request::query('sort', 'name_asc');
        $sortMap = [
            'name_asc' => 'ORDER BY v.series COLLATE NOCASE ASC',
            'name_desc' => 'ORDER BY v.series COLLATE NOCASE DESC',
            'episode_count_desc' => 'ORDER BY episode_count DESC',
        ];
        $orderBy = $sortMap[$sortKey] ?? $sortMap['name_asc'];

        $stmt = $this->db->prepare("
            SELECT v.series,
              (SELECT v2.id FROM videos v2 WHERE v2.series = v.series AND v2.image IS NOT NULL LIMIT 1) AS image_id,
              COUNT(v.id) AS episode_count,
              MAX(v.year) AS year
            FROM videos v
            WHERE v.type = 'episode' AND v.series IS NOT NULL AND v.series != ''
            GROUP BY v.series
            {$orderBy}
            " . Request::limitClause()
        );
        $stmt->execute();
        Response::json($stmt->fetchAll());
    }

    public function getSeriesEpisodes(): void
    {
        $series = Request::query('series');
        if (empty($series)) {
            Response::json([], 400);
        }

        $sortKey = Request::query('sort', 'episode_asc');
        $sortMap = [
            'episode_asc' => 'ORDER BY v.season ASC, v.episode ASC, v.title COLLATE NOCASE ASC',
            'episode_desc' => 'ORDER BY v.season DESC, v.episode DESC, v.title COLLATE NOCASE DESC',
            'title_asc' => 'ORDER BY v.title COLLATE NOCASE ASC',
        ];
        $orderBy = $sortMap[$sortKey] ?? $sortMap['episode_asc'];
        [$wJoin, $wSel, $wParams] = $this->watchJoin();
        [$fJoin, $fSel, $fParams] = $this->favoriteJoin();

        $stmt = $this->db->prepare("
            SELECT v.id, v.title, v.series, v.season, v.episode, v.year, v.duration {$wSel}{$fSel}
            FROM videos v
            {$wJoin}
            {$fJoin}
            WHERE v.type = 'episode' AND v.series = ?
            {$orderBy} " . Request::limitClause()
        );
        $stmt->execute([...$wParams, ...$fParams, $series]);
        Response::json($stmt->fetchAll());
    }

    public function getVideoData(): void
    {
        $id = (int)(Request::query('id', '0'));
        [$wJoin, $wSel, $wParams] = $this->watchJoin(true);
        [$fJoin, $fSel, $fParams] = $this->favoriteJoin();
        $stmt = $this->db->prepare("
            SELECT v.id, v.file, v.title, v.type, v.series, v.season, v.episode, v.year, v.duration {$wSel}{$fSel}
            FROM videos v
            {$wJoin}
            {$fJoin}
            WHERE v.id = ?
        ");
        $stmt->execute([...$wParams, ...$fParams, $id]);
        $video = $stmt->fetch();
        if ($video) {
            $video['stream_url'] = '?action=get_video_stream&id=' . $video['id'];
            $video['image_url'] = '?action=get_video_image&id=' . $video['id'];
            $video['transcode'] = ['needed' => false, 'state' => 'ready', 'progress' => 0.0];
            $absolute = VIDEO_DIR . DIRECTORY_SEPARATOR . $video['file'];
            if (VIDEO_TRANSCODE && VideoTranscoder::isAvailable()
                && is_file($absolute) && VideoTranscoder::needsTranscode($absolute)) {
                $state = VideoTranscoder::status($absolute);
                $video['transcode'] = [
                    'needed' => true,
                    'state' => $state['state'],
                    'progress' => $state['progress'],
                ];
            }
        }
        Response::json($video);
    }

    /**
     * Set (or clear) the watched flag for a video, optionally updating the
     * saved playback position. A manual "unwatch" keeps the stored position.
     */
    public function setVideoWatched(): void
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

        // `watched` and `position` are each optional: a field that is absent
        // from the payload is left untouched. This lets the player persist
        // progress (position-only) without ever changing the watched flag.
        $hasWatched = array_key_exists('watched', $data);
        $hasPosition = array_key_exists('position', $data) && $data['position'] !== null;
        if (!$hasWatched && !$hasPosition) {
            Response::json(['status' => 'error', 'message' => Language::t('api.invalid_params')], 400);
        }

        $stmt = $this->db->prepare("SELECT watched, position_seconds FROM video_watches WHERE user_id = ? AND video_id = ?");
        $stmt->execute([$userId, $videoId]);
        $existing = $stmt->fetch();
        $curWatched = $existing ? (int)$existing['watched'] : 0;
        $curPosition = $existing ? $existing['position_seconds'] : null;

        $newWatched = $hasWatched ? ((int)$data['watched'] ? 1 : 0) : $curWatched;
        $newPosition = $hasPosition ? max(0, (int)$data['position']) : $curPosition;

        if ($existing) {
            $upd = $this->db->prepare("UPDATE video_watches SET watched = ?, position_seconds = ?, last_watched_at = ? WHERE user_id = ? AND video_id = ?");
            $upd->execute([$newWatched, $newPosition, time(), $userId, $videoId]);
        } else {
            $ins = $this->db->prepare("INSERT INTO video_watches (user_id, video_id, watched, position_seconds, last_watched_at) VALUES (?, ?, ?, ?, ?)");
            $ins->execute([$userId, $videoId, $newWatched, $newPosition, time()]);
        }

        Response::json(['status' => 'success', 'is_watched' => (bool)$newWatched]);
    }

    /**
     * Start (or resume) the background transcode of a video. Idempotent:
     * a second call while a transcode is running does nothing.
     */
    public function transcodeVideo(): void
    {
        Security::verifyCsrf();
        if (!Session::userId()) {
            Response::json(['status' => 'error', 'message' => Language::t('api.unauthorized')], 403);
        }

        $id = (int)(Request::query('id', '0'));
        $stmt = $this->db->prepare("SELECT file FROM videos WHERE id = ?");
        $stmt->execute([$id]);
        $file = $stmt->fetchColumn();
        if ($file === false) {
            Response::json(['status' => 'error', 'message' => Language::t('api.invalid_params')], 404);
        }

        if (!VIDEO_TRANSCODE || !VideoTranscoder::isAvailable()) {
            Response::json(['status' => 'error', 'message' => Language::t('video.no_ffmpeg')], 501);
        }

        $absolute = VIDEO_DIR . DIRECTORY_SEPARATOR . $file;
        if (!is_file($absolute)) {
            Response::json(['status' => 'error', 'message' => Language::t('api.path_not_found')], 404);
        }

        $started = VideoTranscoder::startTranscode($absolute);
        $state = VideoTranscoder::status($absolute);
        Response::json([
            'status' => 'success',
            'started' => $started,
            'state' => $state['state'],
            'progress' => $state['progress'],
        ]);
    }
}
