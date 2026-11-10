<?php
// Apollon Media - main page view.
// Expects: $initialViewJS (string)
use Apollon\Database;
use Apollon\Language;
use Apollon\Security;
use Apollon\Session;

$i18n = json_encode(
    ['lang' => Language::current(), 'strings' => Language::catalog()],
    JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP
);
$t = fn(string $key, array $params = []): string => htmlspecialchars(Language::t($key, $params), ENT_QUOTES, 'UTF-8');

// Theme: dark by default, per-user preference (light) when logged in.
$theme = 'dark';
$pageDb = Database::connect();
Database::initSchema($pageDb);
$pageUserId = Session::userId();
$locked = !ALLOW_GUEST_LISTEN && !$pageUserId;
// Shared single-song mode (guest, guest listening disabled, song share link).
$sharedSong = isset($sharedSong) ? $sharedSong : null;
$sharedSongData = null;
if ($sharedSong) {
    $stmt = $pageDb->prepare("SELECT id, title, artist, album, year, duration FROM music WHERE id = ?");
    $stmt->execute([(int)$sharedSong['id']]);
    $sharedSongData = $stmt->fetch();
}
if ($pageUserId) {
    $stmt = $pageDb->prepare("SELECT theme FROM users WHERE id = ?");
    $stmt->execute([$pageUserId]);
    $userTheme = (string)$stmt->fetchColumn();
    if ($userTheme === 'dark' || $userTheme === 'light') {
        $theme = $userTheme;
    }
}
?>
<!DOCTYPE html>
<html lang="<?= htmlspecialchars(Language::current(), ENT_QUOTES, 'UTF-8') ?>" data-theme="<?= $theme ?>">
  <head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= APP_NAME ?></title>
    <link rel="icon" type="image/svg+xml" href="?action=get_app_icon&v=<?= APP_VERSION ?>" />
    <meta name="theme-color" content="<?= $theme === 'dark' ? '#121212' : '#f5f5f5' ?>"/>
    <meta name="csrf-token" content="<?= htmlspecialchars(Session::csrfToken(), ENT_QUOTES, 'UTF-8') ?>">
    <link rel="manifest" href="?pwa=manifest">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@400;500;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/apollon.css?v=<?= APP_VERSION ?>">
    <?= $initialViewJS ?>
    <script nonce="<?= htmlspecialchars(Security::nonce(), ENT_QUOTES) ?>">window.apollonConfig = { guestListen: <?= ALLOW_GUEST_LISTEN ? 'true' : 'false' ?>, sharedSong: <?= $sharedSong ? json_encode(['id' => (int)$sharedSong['id'], 'token' => (string)$sharedSong['token']], JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) : 'null' ?> };</script>
    <script nonce="<?= htmlspecialchars(Security::nonce(), ENT_QUOTES) ?>">window.apollonI18n = <?= $i18n ?>;</script>
  </head>
  <body class="logged-out<?= $locked ? ' body-locked' : '' ?><?= $sharedSong ? ' body-shared' : '' ?>">
    <div class="app-container">
      <nav class="sidebar offcanvas-md offcanvas-start" tabindex="-1" id="main-nav-offcanvas">
        <div class="offcanvas-header">
          <div class="logo">
            <svg width="24" height="24" viewBox="0 0 100 100"><rect width="100" height="100" rx="24" fill="#0d0d0d"/><rect x="13" y="38" width="7.5" height="24" rx="3.75" fill="#ffffff"/><rect x="27" y="25" width="7.5" height="50" rx="3.75" fill="#6C5CFF"/><rect x="41" y="13" width="7.5" height="74" rx="3.75" fill="#ffffff"/><rect x="55" y="25" width="7.5" height="50" rx="3.75" fill="#ffffff"/><rect x="69" y="38" width="7.5" height="24" rx="3.75" fill="#ffffff"/><rect x="83" y="25" width="7.5" height="50" rx="3.75" fill="#ffffff"/></svg>
            Apollon <span>Media</span>
          </div>
          <button type="button" class="btn-close btn-close-white" data-bs-dismiss="offcanvas" data-bs-target="#main-nav-offcanvas" aria-label="<?= $t('common.close') ?>"></button>
        </div>
        <div class="offcanvas-body d-flex flex-column">
          <div class="logo d-none d-md-flex">
            <svg width="28" height="28" viewBox="0 0 100 100"><rect width="100" height="100" rx="24" fill="#0d0d0d"/><rect x="13" y="38" width="7.5" height="24" rx="3.75" fill="#ffffff"/><rect x="27" y="25" width="7.5" height="50" rx="3.75" fill="#6C5CFF"/><rect x="41" y="13" width="7.5" height="74" rx="3.75" fill="#ffffff"/><rect x="55" y="25" width="7.5" height="50" rx="3.75" fill="#ffffff"/><rect x="69" y="38" width="7.5" height="24" rx="3.75" fill="#ffffff"/><rect x="83" y="25" width="7.5" height="50" rx="3.75" fill="#ffffff"/></svg>
            Apollon <small class="text-danger fs-6">Media</small>
          </div>
          <a href="#" class="nav-link active" data-view="get_songs">
            <i class="bi bi-music-note-list"></i>
            <span class="text-truncate"><?= $t('nav.all_songs') ?></span>
          </a>
          <a href="#" class="nav-link" data-view="get_albums">
            <i class="bi bi-disc-fill"></i>
            <span class="text-truncate"><?= $t('nav.albums') ?></span>
          </a>
          <a href="#" class="nav-link" data-view="get_artists">
            <i class="bi bi-people-fill"></i>
            <span class="text-truncate"><?= $t('nav.artists') ?></span>
          </a>
          <a href="#" class="nav-link logged-in-only" data-view="get_user_playlists">
            <i class="bi bi-music-note-beamed"></i>
            <span class="text-truncate"><?= $t('nav.playlists') ?></span>
          </a>
          <a href="#" class="nav-link logged-in-only" data-view="get_webradios">
            <i class="bi bi-broadcast"></i>
            <span class="text-truncate"><?= $t('nav.webradios') ?></span>
          </a>
          <a href="#" class="nav-link" data-view="get_videos">
            <i class="bi bi-collection-play-fill"></i>
            <span class="text-truncate"><?= $t('nav.videos') ?></span>
          </a>
          <div class="logged-in-only">
            <a href="#" class="nav-link" data-view="get_favorites">
              <i class="bi bi-heart-fill"></i>
              <span class="text-truncate"><?= $t('nav.favorites') ?></span>
            </a>
            <a href="#" class="nav-link" data-view="manage_songs">
              <i class="bi bi-cloud-arrow-down-fill"></i>
              <span class="text-truncate"><?= $t('nav.manage_songs') ?></span>
            </a>
            <a href="#" class="nav-link" data-bs-toggle="modal" data-bs-target="#settings-modal">
              <i class="bi bi-sliders"></i>
              <span class="text-truncate"><?= $t('nav.settings') ?></span>
            </a>
            <a href="#" class="nav-link" id="sidebar-logout-btn">
              <i class="bi bi-box-arrow-left"></i>
              <span class="text-truncate"><?= $t('nav.logout') ?></span>
            </a>
          </div>
          <div class="logged-out-only">
            <a href="#" class="nav-link" data-bs-toggle="modal" data-bs-target="#login-modal">
              <i class="bi bi-box-arrow-in-right"></i>
              <span class="text-truncate"><?= $t('nav.login') ?></span>
            </a>
            <?php if (ALLOW_REGISTRATION): ?>
            <a href="#" class="nav-link" data-bs-toggle="modal" data-bs-target="#register-modal">
              <i class="bi bi-person-plus-fill"></i>
              <span class="text-truncate"><?= $t('nav.register') ?></span>
            </a>
            <?php endif; ?>
          </div>
          <div class="mt-auto">
            <a href="#" class="nav-link logged-in-only" data-bs-toggle="modal" data-bs-target="#full-scan-modal">
              <i class="bi bi-hdd-stack-fill"></i>
              <span class="text-truncate"><?= $t('nav.scan_all') ?></span>
            </a>
          </div>
        </div>
      </nav>
      <main class="main-content" id="main-content">
        <div id="landing" class="landing">
          <div class="landing-shell">
            <div class="landing-logo">
              <svg width="40" height="40" viewBox="0 0 100 100"><rect width="100" height="100" rx="24" fill="#0d0d0d"/><rect x="13" y="38" width="7.5" height="24" rx="3.75" fill="#ffffff"/><rect x="27" y="25" width="7.5" height="50" rx="3.75" fill="#6C5CFF"/><rect x="41" y="13" width="7.5" height="74" rx="3.75" fill="#ffffff"/><rect x="55" y="25" width="7.5" height="50" rx="3.75" fill="#ffffff"/><rect x="69" y="38" width="7.5" height="24" rx="3.75" fill="#ffffff"/><rect x="83" y="25" width="7.5" height="50" rx="3.75" fill="#ffffff"/></svg>
              Apollon <small class="text-danger">Media</small>
            </div>
            <h1 class="landing-title"><?= $t('landing.welcome', ['app' => APP_NAME]) ?></h1>
            <p class="landing-subtitle"><?= $t('landing.subtitle') ?></p>
            <div class="card landing-card">
              <div class="card-body">
                <h5 class="landing-card-title"><?= $t('login.title') ?></h5>
                <form id="landing-login-form">
                  <div class="mb-3">
                    <label for="landing-username" class="form-label text-secondary small"><?= $t('login.username') ?></label>
                    <input type="text" class="form-control" id="landing-username" autocomplete="username" required>
                  </div>
                  <div class="mb-3">
                    <label for="landing-password" class="form-label text-secondary small"><?= $t('login.password') ?></label>
                    <input type="password" class="form-control" id="landing-password" autocomplete="current-password" required>
                  </div>
                  <button type="submit" class="btn btn-danger w-100 rounded-pill mt-2"><?= $t('login.submit') ?></button>
                </form>
                <?php if (ALLOW_REGISTRATION): ?>
                <div class="landing-switch">
                  <span><?= $t('landing.no_account') ?></span>
                  <a href="#" id="landing-register-link"><?= $t('landing.register_cta') ?></a>
                </div>
                <?php endif; ?>
              </div>
            </div>
          </div>
        </div>
        <div id="shared-song" class="shared-song">
          <div class="shared-song-shell">
            <?php if ($sharedSongData): ?>
              <div class="shared-song-cover">
                <img src="?action=get_image&id=<?= (int)$sharedSongData['id'] ?>" alt="">
              </div>
              <h1 class="shared-song-title"><?= $t('shared.title', ['app' => APP_NAME]) ?></h1>
              <p class="shared-song-name"><?= htmlspecialchars((string)$sharedSongData['title'], ENT_QUOTES, 'UTF-8') ?></p>
              <p class="shared-song-artist"><?= htmlspecialchars((string)$sharedSongData['artist'], ENT_QUOTES, 'UTF-8') ?><?= !empty($sharedSongData['album']) ? ' &middot; ' . htmlspecialchars((string)$sharedSongData['album'], ENT_QUOTES, 'UTF-8') : '' ?></p>
              <button type="button" class="btn btn-danger rounded-pill shared-song-play" id="shared-song-play-btn">
                <i class="bi bi-play-fill"></i> <?= $t('shared.play') ?>
              </button>
            <?php else: ?>
              <h1 class="shared-song-title"><?= $t('shared.not_found') ?></h1>
            <?php endif; ?>
          </div>
        </div>
        <div class="mobile-header d-md-none">
          <button class="header-btn" type="button" data-bs-toggle="offcanvas" data-bs-target="#main-nav-offcanvas">
            <i class="bi bi-list"></i>
          </button>
          <div class="input-group search-bar flex-grow-1 ms-2">
            <input type="text" class="form-control" id="search-input-mobile" placeholder="<?= $t('search.placeholder') ?>" aria-label="<?= $t('search.placeholder') ?>">
            <button class="btn" type="button" id="search-btn-mobile"><i class="bi bi-search"></i></button>
          </div>
        </div>
        <div class="page-header">
          <h1 id="content-title" class="content-title text-truncate"><?= $t('view.all_songs') ?></h1>
          <div class="header-controls">
            <div id="sort-controls" class="d-none">
              <label for="sort-select" class="text-secondary small"><?= $t('common.sort_by') ?></label>
              <select id="sort-select" class="form-select form-select-sm" style="width: auto;"></select>
            </div>
            <div class="input-group search-bar d-none d-md-flex">
              <input type="text" class="form-control" id="search-input-desktop" placeholder="<?= $t('search.placeholder') ?>" aria-label="<?= $t('search.placeholder') ?>">
              <button class="btn" type="button" id="search-btn-desktop"><i class="bi bi-search"></i></button>
            </div>
          </div>
        </div>
        <div id="content-area" class="content-area-wrapper"></div>
        <div id="infinite-scroll-loader" class="loader d-none"><?= $t('common.loading_more') ?></div>
      </main>
    </div>

    <!-- Player Bar -->
    <div class="player-bar d-none" id="player-bar">
      <div class="track-info d-none d-md-flex">
        <img src="" alt="<?= $t('player.album_art') ?>" class="track-info-art" id="player-art-desktop">
        <div class="track-info-text text-truncate">
          <div class="title text-truncate" id="player-title-desktop"><?= $t('player.song_title') ?></div>
          <div class="artist text-truncate" id="player-artist-desktop"><?= $t('player.artist_name') ?></div>
        </div>
      </div>
      <div class="player-controls">
        <div class="track-info d-md-none">
          <img src="" alt="<?= $t('player.album_art') ?>" class="track-info-art" id="player-art-mobile">
          <div class="track-info-text text-truncate">
            <div class="title text-truncate" id="player-title-mobile"><?= $t('player.song_title') ?></div>
            <div class="artist text-truncate" id="player-artist-mobile"><?= $t('player.artist_name') ?></div>
          </div>
          <button class="player-btn" id="player-more-btn-mobile" title="<?= $t('player.more') ?>"><i class="bi bi-three-dots-vertical"></i></button>
        </div>
        <div class="playback-bar">
          <span class="time" id="current-time">0:00</span>
          <div class="progress-bar-container" id="progress-container">
            <div class="progress-bar-bg"></div>
            <div class="progress-bar-fg" id="progress-bar"></div>
          </div>
          <span class="time" id="time-left">0:00</span>
        </div>
        <div class="player-buttons d-none d-md-flex mt-md-2">
          <button class="player-btn" id="shuffle-btn-desktop" title="<?= $t('player.shuffle') ?>"></button>
          <button class="player-btn" id="prev-btn-desktop" title="<?= $t('player.previous') ?>"></button>
          <button class="player-btn play-btn" id="play-pause-btn-desktop" title="<?= $t('player.play') ?>"></button>
          <button class="player-btn" id="next-btn-desktop" title="<?= $t('player.next') ?>"></button>
          <button class="player-btn" id="repeat-btn-desktop" title="<?= $t('player.repeat') ?>"></button>
        </div>
        <div class="player-buttons-mobile d-md-none">
          <button class="player-btn" id="shuffle-btn-mobile" title="<?= $t('player.shuffle') ?>"></button>
          <button class="player-btn" id="prev-btn-mobile" title="<?= $t('player.previous') ?>"></button>
          <button class="player-btn play-btn" id="play-pause-btn-mobile" title="<?= $t('player.play') ?>"></button>
          <button class="player-btn" id="next-btn-mobile" title="<?= $t('player.next') ?>"></button>
          <button class="player-btn" id="repeat-btn-mobile" title="<?= $t('player.repeat') ?>"></button>
        </div>
      </div>
      <div class="extra-controls d-none d-md-flex">
        <div class="volume-control d-flex align-items-center">
          <button class="player-btn" id="volume-btn" title="<?= $t('player.mute') ?>">
            <i class="bi bi-volume-up-fill"></i>
          </button>
          <div class="volume-slider-container">
            <input type="range" class="form-range" id="volume-slider" min="0" max="1" step="0.01" value="1">
          </div>
        </div>
        <button class="player-btn" id="player-more-btn-desktop" title="<?= $t('player.more') ?>"><i class="bi bi-three-dots-vertical"></i></button>
      </div>
    </div>
    <ul class="context-menu" id="context-menu"></ul>

    <!-- Fullscreen Player Modal for Mobile -->
    <div class="modal fade" id="player-modal" tabindex="-1" aria-hidden="true">
      <div class="modal-dialog modal-fullscreen">
        <div class="modal-content player-modal-content">
          <div class="modal-header player-modal-header">
            <button type="button" class="btn player-btn" data-bs-dismiss="modal" aria-label="<?= $t('common.close') ?>">
              <i class="bi bi-chevron-down"></i>
            </button>
            <button type="button" class="btn player-btn" id="player-modal-more-btn" title="<?= $t('player.more') ?>">
              <i class="bi bi-three-dots-vertical"></i>
            </button>
          </div>
          <div class="modal-body player-modal-body">
            <div class="player-modal-art-wrapper">
              <img src="" id="player-modal-art" alt="<?= $t('player.album_art') ?>">
            </div>
            <div class="player-modal-track-info text-truncate">
              <h3 id="player-modal-title" class="title text-truncate"><?= $t('player.song_title') ?></h3>
              <p id="player-modal-artist" class="artist text-truncate"><?= $t('player.artist_name') ?></p>
            </div>
            <div class="player-modal-progress">
              <div class="progress-bar-container" id="player-modal-progress-container">
                <div class="progress-bar-bg"></div>
                <div class="progress-bar-fg" id="player-modal-progress-bar"></div>
              </div>
              <div class="time-stamps">
                <span id="player-modal-current-time">0:00</span>
                <span id="player-modal-time-left">0:00</span>
              </div>
            </div>
            <div class="player-modal-controls">
              <button class="player-btn" id="player-modal-shuffle-btn" title="<?= $t('player.shuffle') ?>"></button>
              <button class="player-btn" id="player-modal-prev-btn" title="<?= $t('player.previous') ?>"></button>
              <button class="player-btn play-btn" id="player-modal-play-pause-btn" title="<?= $t('player.play') ?>"></button>
              <button class="player-btn" id="player-modal-next-btn" title="<?= $t('player.next') ?>"></button>
              <button class="player-btn" id="player-modal-repeat-btn" title="<?= $t('player.repeat') ?>"></button>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- Video Player Modal -->
    <div class="modal fade" id="video-modal" tabindex="-1" aria-hidden="true">
      <div class="modal-dialog modal-fullscreen">
        <div class="modal-content video-player-content">
          <div class="video-player-topbar">
            <button type="button" class="btn player-btn" id="video-close-btn" aria-label="<?= $t('video.close') ?>">
              <i class="bi bi-x-lg"></i>
            </button>
            <div class="video-player-title text-truncate">
              <div class="title text-truncate" id="video-title"></div>
              <div class="subtitle text-truncate" id="video-subtitle"></div>
            </div>
          </div>
          <div class="video-player-stage" id="video-stage">
            <video id="video-element" playsinline></video>
            <div class="video-transcode-overlay d-none" id="video-transcode-overlay">
              <div class="spinner-border text-light" role="status"></div>
              <div class="transcode-label" id="video-transcode-label"></div>
            </div>
          </div>
          <div class="video-player-controls">
            <div class="video-seek-row">
              <span class="time" id="video-current-time">0:00</span>
              <div class="progress-bar-container" id="video-progress-container">
                <div class="progress-bar-bg"></div>
                <div class="progress-bar-fg" id="video-progress-bar"></div>
              </div>
              <span class="time" id="video-duration">0:00</span>
            </div>
            <div class="video-buttons">
              <button class="player-btn" id="video-prev-btn" title="<?= $t('video.prev_episode') ?>"><i class="bi bi-skip-start-fill"></i></button>
              <button class="player-btn play-btn" id="video-play-pause-btn" title="<?= $t('player.play') ?>"><i class="bi bi-play-fill"></i></button>
              <button class="player-btn" id="video-next-btn" title="<?= $t('video.next_episode') ?>"><i class="bi bi-skip-end-fill"></i></button>
              <div class="video-volume d-flex align-items-center">
                <button class="player-btn" id="video-volume-btn" title="<?= $t('player.mute') ?>"><i class="bi bi-volume-up-fill"></i></button>
                <input type="range" class="form-range video-volume-slider" id="video-volume-slider" min="0" max="1" step="0.01" value="1">
              </div>
              <button class="player-btn ms-auto" id="video-fullscreen-btn" title="<?= $t('video.fullscreen') ?>"><i class="bi bi-fullscreen"></i></button>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- Modals -->
    <div class="modal fade" id="login-modal" tabindex="-1">
      <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content p-2">
          <div class="modal-header">
            <h5 class="modal-title"><?= $t('login.title') ?></h5>
            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
          </div>
          <div class="modal-body">
            <form id="login-form">
              <div class="mb-3">
                <label for="login-username" class="form-label text-secondary small"><?= $t('login.username') ?></label>
                <input type="text" class="form-control" id="login-username" autocomplete="username" required>
              </div>
              <div class="mb-3">
                <label for="login-password" class="form-label text-secondary small"><?= $t('login.password') ?></label>
                <input type="password" class="form-control" id="login-password" required>
              </div>
              <button type="submit" class="btn btn-danger w-100 rounded-pill mt-2"><?= $t('login.submit') ?></button>
            </form>
          </div>
        </div>
      </div>
    </div>

    <?php if (ALLOW_REGISTRATION): ?>
    <div class="modal fade" id="register-modal" tabindex="-1">
      <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content p-2">
          <div class="modal-header">
            <h5 class="modal-title"><?= $t('register.title') ?></h5>
            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
          </div>
          <div class="modal-body">
            <form id="register-form">
              <div class="mb-3">
                <label for="register-username" class="form-label text-secondary small"><?= $t('register.username') ?></label>
                <input type="text" class="form-control" id="register-username" autocomplete="username" maxlength="30" required>
              </div>
              <div class="mb-3">
                <label for="register-password" class="form-label text-secondary small"><?= $t('register.password') ?></label>
                <input type="password" class="form-control" id="register-password" required minlength="6">
              </div>
              <button type="submit" class="btn btn-danger w-100 rounded-pill mt-2"><?= $t('register.submit') ?></button>
            </form>
          </div>
        </div>
      </div>
    </div>
    <?php endif; ?>

    <!-- Settings Modal -->
    <div class="modal fade" id="settings-modal" tabindex="-1">
      <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content p-2">
          <div class="modal-header">
            <h5 class="modal-title"><i class="bi bi-sliders me-2"></i><?= $t('settings.title') ?></h5>
            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
          </div>
          <div class="modal-body">
            <h6 class="small text-secondary mb-2"><?= $t('settings.theme') ?></h6>
            <div class="mb-4">
              <select id="theme-select" class="form-select">
                <option value="light" <?= $theme === 'light' ? 'selected' : '' ?>><?= $t('settings.theme_light') ?></option>
                <option value="dark" <?= $theme === 'dark' ? 'selected' : '' ?>><?= $t('settings.theme_dark') ?></option>
              </select>
            </div>

            <h6 class="small text-secondary mb-2"><?= $t('settings.username') ?></h6>
            <form id="change-username-form" class="mb-4">
              <div class="mb-3">
                <input type="text" class="form-control" id="username-input" autocomplete="username" maxlength="30" required placeholder="<?= $t('settings.username_placeholder') ?>">
              </div>
              <button type="submit" class="btn btn-danger w-100 rounded-pill"><?= $t('settings.update_username') ?></button>
            </form>

            <h6 class="small text-secondary mb-2"><?= $t('settings.change_password') ?></h6>
            <form id="change-password-form" class="mb-4">
              <div class="mb-3">
                <input type="password" class="form-control" id="new-password" required minlength="6" placeholder="<?= $t('settings.new_password_placeholder') ?>">
              </div>
              <button type="submit" class="btn btn-danger w-100 rounded-pill"><?= $t('settings.save_password') ?></button>
            </form>

            <div class="mt-4 pt-3">
              <h6 class="text-danger small mb-1"><?= $t('settings.delete_account') ?></h6>
              <p class="text-secondary small mb-3"><?= $t('settings.delete_account_hint') ?></p>
              <button type="button" class="btn btn-outline-danger w-100 rounded-pill" id="delete-account-btn"><?= $t('settings.delete_account_btn') ?></button>
            </div>
          </div>
        </div>
      </div>
    </div>

    <div class="modal fade" id="create-playlist-modal" tabindex="-1">
      <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content p-2">
          <div class="modal-header">
            <h5 class="modal-title"><?= $t('playlist.create_title') ?></h5>
            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
          </div>
          <div class="modal-body">
            <form id="create-playlist-form">
              <div class="mb-3">
                <label for="playlist-name-input" class="form-label text-secondary small"><?= $t('playlist.name_label') ?></label>
                <input type="text" class="form-control" id="playlist-name-input" required>
              </div>
              <button type="submit" class="btn btn-danger w-100 rounded-pill mt-2"><?= $t('playlist.create_btn') ?></button>
            </form>
          </div>
        </div>
      </div>
    </div>

    <div class="modal fade" id="edit-playlist-modal" tabindex="-1">
      <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content p-2">
          <div class="modal-header">
            <h5 class="modal-title"><?= $t('playlist.edit_title') ?></h5>
            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
          </div>
          <div class="modal-body">
            <form id="edit-playlist-form">
              <input type="hidden" id="edit-playlist-id-input">
              <div class="mb-3">
                <label for="edit-playlist-name-input" class="form-label text-secondary small"><?= $t('playlist.name_label') ?></label>
                <input type="text" class="form-control" id="edit-playlist-name-input" required>
              </div>
              <button type="submit" class="btn btn-danger w-100 rounded-pill mt-2"><?= $t('playlist.save_btn') ?></button>
            </form>
          </div>
        </div>
      </div>
    </div>

    <div class="modal fade" id="create-webradio-modal" tabindex="-1">
      <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content p-2">
          <div class="modal-header">
            <h5 class="modal-title"><?= $t('webradio.create_title') ?></h5>
            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
          </div>
          <div class="modal-body">
            <form id="create-webradio-form">
              <div class="mb-3">
                <label for="webradio-name-input" class="form-label text-secondary small"><?= $t('webradio.name_label') ?></label>
                <input type="text" class="form-control" id="webradio-name-input" required>
              </div>
              <div class="mb-3">
                <label for="webradio-url-input" class="form-label text-secondary small"><?= $t('webradio.url_label') ?></label>
                <input type="url" class="form-control" id="webradio-url-input" placeholder="https://..." required>
              </div>
              <button type="submit" class="btn btn-danger w-100 rounded-pill mt-2"><?= $t('webradio.create_btn') ?></button>
            </form>
          </div>
        </div>
      </div>
    </div>

    <div class="modal fade" id="edit-webradio-modal" tabindex="-1">
      <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content p-2">
          <div class="modal-header">
            <h5 class="modal-title"><?= $t('webradio.edit_title') ?></h5>
            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
          </div>
          <div class="modal-body">
            <form id="edit-webradio-form">
              <input type="hidden" id="edit-webradio-id-input">
              <div class="mb-3">
                <label for="edit-webradio-name-input" class="form-label text-secondary small"><?= $t('webradio.name_label') ?></label>
                <input type="text" class="form-control" id="edit-webradio-name-input" required>
              </div>
              <div class="mb-3">
                <label for="edit-webradio-url-input" class="form-label text-secondary small"><?= $t('webradio.url_label') ?></label>
                <input type="url" class="form-control" id="edit-webradio-url-input" placeholder="https://..." required>
              </div>
              <button type="submit" class="btn btn-danger w-100 rounded-pill mt-2"><?= $t('webradio.save_btn') ?></button>
            </form>
          </div>
        </div>
      </div>
    </div>

    <div class="modal fade" id="add-to-playlist-modal" tabindex="-1">
      <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content p-2">
          <div class="modal-header">
            <h5 class="modal-title"><?= $t('playlist.add_to_title') ?></h5>
            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
          </div>
          <div class="modal-body" id="add-to-playlist-modal-body"></div>
        </div>
      </div>
    </div>

    <div class="modal fade" id="metadata-modal" tabindex="-1">
      <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content p-2">
          <div class="modal-header">
            <h5 class="modal-title"><?= $t('metadata.title') ?></h5>
            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
          </div>
          <div class="modal-body" id="metadata-modal-body"></div>
        </div>
      </div>
    </div>

    <div class="modal fade" id="share-modal" tabindex="-1">
      <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content p-2">
          <div class="modal-header">
            <h5 class="modal-title text-truncate" id="share-modal-title"><?= $t('common.share') ?></h5>
            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
          </div>
          <div class="modal-body">
            <p class="text-secondary text-center mb-4" id="share-modal-text"><?= $t('share.text') ?></p>
            <div class="input-group search-bar">
              <input type="text" class="form-control" id="share-url-input" readonly>
              <button class="btn btn-danger" type="button" id="copy-share-url-btn"><?= $t('common.copy') ?></button>
            </div>
          </div>
        </div>
      </div>
    </div>

    <div class="modal fade" id="full-scan-modal" tabindex="-1">
      <div class="modal-dialog modal-dialog-centered modal-lg modal-dialog-scrollable">
        <div class="modal-content p-2">
          <div class="modal-header">
            <h5 class="modal-title"><?= $t('scan.modal_title') ?></h5>
            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
          </div>
          <div class="modal-body p-0">
            <iframe id="full-scan-iframe" src="about:blank" style="width: 100%; height: 50dvh; border: none; background-color: var(--ytm-bg); border-radius: 12px;"></iframe>
          </div>
        </div>
      </div>
    </div>

    <input type="file" id="playlist-import-input" accept=".json,application/json" class="d-none">
    <input type="file" id="manage-upload-input" accept=".mp3,.m4a,.flac,.ogg,.wav,.mp4,.m4v,.mkv,.webm,.avi,.mov,.ts,.wmv" multiple class="d-none">

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sortablejs@1.15.2/Sortable.min.js"></script>
    <script src="assets/js/utils.js?v=<?= APP_VERSION ?>"></script>
    <script src="assets/js/player.js?v=<?= APP_VERSION ?>"></script>
    <script src="assets/js/video-player.js?v=<?= APP_VERSION ?>"></script>
    <script src="assets/js/views.js?v=<?= APP_VERSION ?>"></script>
    <script src="assets/js/app.js?v=<?= APP_VERSION ?>"></script>
  </body>
</html>
