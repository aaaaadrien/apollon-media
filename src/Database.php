<?php
declare(strict_types=1);

namespace Apollon;

use PDO;
use PDOException;

final class Database
{
    private static ?PDO $pdo = null;

    public static function connect(): PDO
    {
        if (self::$pdo instanceof PDO) {
            return self::$pdo;
        }

        $dir = dirname(DB_FILE);
        if (!is_dir($dir)) {
            mkdir($dir, 0775, true);
        }

        try {
            $db = new PDO('sqlite:' . DB_FILE, null, null, [PDO::ATTR_TIMEOUT => 30]);
            $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $db->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
            $db->exec("PRAGMA journal_mode=WAL;");
            $db->exec("PRAGMA foreign_keys = ON;");
            self::$pdo = $db;
            return $db;
        } catch (PDOException $e) {
            error_log('Database connection failed: ' . $e->getMessage());
            http_response_code(500);
            die('Database connection failed.');
        }
    }

    public static function initSchema(PDO $db): void
    {
        $db->exec("CREATE TABLE IF NOT EXISTS users (
            id INTEGER PRIMARY KEY,
            username TEXT UNIQUE,
            password_hash TEXT,
            verified TEXT DEFAULT 'yes',
            theme TEXT DEFAULT 'dark'
        )");

        $db->exec("
            CREATE TABLE IF NOT EXISTS music (
              id INTEGER PRIMARY KEY,
              user_id INTEGER,
              file TEXT UNIQUE,
              title TEXT,
              artist TEXT,
              album TEXT,
              year INTEGER,
              duration INTEGER,
              image BLOB,
              last_modified INTEGER,
              bitrate INTEGER,
              FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
            );
        ");

        $db->exec("
            CREATE TABLE IF NOT EXISTS videos (
              id INTEGER PRIMARY KEY,
              file TEXT UNIQUE,
              title TEXT,
              type TEXT DEFAULT 'movie',
              series TEXT,
              season INTEGER DEFAULT 0,
              episode INTEGER DEFAULT 0,
              year INTEGER,
              duration INTEGER,
              image BLOB,
              last_modified INTEGER
            );
        ");

        $db->exec("
            CREATE TABLE IF NOT EXISTS favorites (
              user_id INTEGER NOT NULL,
              song_id INTEGER NOT NULL,
              sort_order INTEGER,
              PRIMARY KEY (user_id, song_id),
              FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
              FOREIGN KEY (song_id) REFERENCES music(id) ON DELETE CASCADE
            );
        ");

        $db->exec("
            CREATE TABLE IF NOT EXISTS video_favorites (
              user_id INTEGER NOT NULL,
              video_id INTEGER NOT NULL,
              sort_order INTEGER,
              PRIMARY KEY (user_id, video_id),
              FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
              FOREIGN KEY (video_id) REFERENCES videos(id) ON DELETE CASCADE
            );
        ");

        $db->exec("
            CREATE TABLE IF NOT EXISTS video_watches (
              user_id INTEGER NOT NULL,
              video_id INTEGER NOT NULL,
              watched INTEGER NOT NULL DEFAULT 1,
              position_seconds INTEGER,
              last_watched_at INTEGER,
              PRIMARY KEY (user_id, video_id),
              FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
              FOREIGN KEY (video_id) REFERENCES videos(id) ON DELETE CASCADE
            );
        ");

        $db->exec("
            CREATE TABLE IF NOT EXISTS playlists (
              id INTEGER PRIMARY KEY,
              user_id INTEGER NOT NULL,
              name TEXT NOT NULL,
              public_id TEXT UNIQUE NOT NULL,
              created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
              FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
            );
        ");

        $db->exec("
            CREATE TABLE IF NOT EXISTS playlist_songs (
              playlist_id INTEGER NOT NULL,
              song_id INTEGER NOT NULL,
              sort_order INTEGER,
              added_at DATETIME DEFAULT CURRENT_TIMESTAMP,
              PRIMARY KEY (playlist_id, song_id),
              FOREIGN KEY (playlist_id) REFERENCES playlists(id) ON DELETE CASCADE,
              FOREIGN KEY (song_id) REFERENCES music(id) ON DELETE CASCADE
            );
        ");

        $db->exec("
            CREATE TABLE IF NOT EXISTS webradios (
              id INTEGER PRIMARY KEY,
              name TEXT NOT NULL,
              url TEXT NOT NULL,
              created_at DATETIME DEFAULT CURRENT_TIMESTAMP
            );
        ");

        $db->exec("
            CREATE TABLE IF NOT EXISTS login_attempts (
              id INTEGER PRIMARY KEY,
              username TEXT,
              ip TEXT,
              attempted_at INTEGER
            );
        ");

        $db->exec("CREATE INDEX IF NOT EXISTS music_artist_idx ON music(artist);");
        $db->exec("CREATE INDEX IF NOT EXISTS music_album_idx ON music(album);");
        $db->exec("CREATE INDEX IF NOT EXISTS videos_series_idx ON videos(series);");
        $db->exec("CREATE INDEX IF NOT EXISTS videos_type_idx ON videos(type);");
        $db->exec("CREATE INDEX IF NOT EXISTS video_watches_video_id_idx ON video_watches(video_id);");
        $db->exec("CREATE INDEX IF NOT EXISTS fav_user_id_idx ON favorites(user_id);");
        $db->exec("CREATE INDEX IF NOT EXISTS video_favorites_user_id_idx ON video_favorites(user_id);");
        $db->exec("CREATE INDEX IF NOT EXISTS playlists_user_id_idx ON playlists(user_id);");
        $db->exec("CREATE INDEX IF NOT EXISTS playlists_public_id_idx ON playlists(public_id);");
        $db->exec("CREATE INDEX IF NOT EXISTS playlist_songs_playlist_id_idx ON playlist_songs(playlist_id);");

        // Migration: make webradios a global list (drop the per-user user_id column) if it still exists.
        $webradioCols = $db->query("PRAGMA table_info(webradios)")->fetchAll();
        $hasUserId = false;
        foreach ($webradioCols as $col) {
            if (($col['name'] ?? '') === 'user_id') {
                $hasUserId = true;
                break;
            }
        }
        if ($hasUserId) {
            $db->exec("BEGIN");
            $db->exec("
                CREATE TABLE webradios_new (
                  id INTEGER PRIMARY KEY,
                  name TEXT NOT NULL,
                  url TEXT NOT NULL,
                  created_at DATETIME DEFAULT CURRENT_TIMESTAMP
                );
            ");
            $db->exec("INSERT INTO webradios_new (id, name, url, created_at) SELECT id, name, url, created_at FROM webradios;");
            $db->exec("DROP TABLE webradios;");
            $db->exec("ALTER TABLE webradios_new RENAME TO webradios;");
            $db->exec("COMMIT");
        }
    }
}
