<?php
declare(strict_types=1);

namespace Apollon;

/**
 * Resolves ?share_type=...&id=... links into an initial view configuration
 * injected into the page (window.initialView).
 *
 * @return array{type: string, param: string, sort: string, highlight?: int}|null
 */
final class ShareResolver
{
    public static function resolve(string $shareType, string $shareId): ?array
    {
        $db = Database::connect();
        $viewConfig = null;

        switch ($shareType) {
            case 'song':
                $stmt = $db->prepare("SELECT album FROM music WHERE id = ?");
                $stmt->execute([(int)$shareId]);
                $songInfo = $stmt->fetch();
                if ($songInfo) {
                    $viewConfig = [
                        'type' => 'album_songs',
                        'param' => rawurlencode($songInfo['album']),
                        'sort' => 'title_asc',
                        'highlight' => (int)$shareId,
                    ];
                }
                break;
            case 'album':
                $viewConfig = ['type' => 'album_songs', 'param' => rawurlencode($shareId), 'sort' => 'title_asc'];
                break;
            case 'artist':
                $viewConfig = ['type' => 'artist_songs', 'param' => rawurlencode($shareId), 'sort' => 'title_asc'];
                break;
            case 'playlist':
                $stmt = $db->prepare("SELECT id FROM playlists WHERE public_id = ?");
                $stmt->execute([$shareId]);
                if ($stmt->fetch()) {
                    $viewConfig = ['type' => 'playlist_songs', 'param' => rawurlencode($shareId), 'sort' => 'manual_order'];
                }
                break;
        }

        return $viewConfig;
    }
}
