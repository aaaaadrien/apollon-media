<?php
// Apollon Media - front controller.
declare(strict_types=1);

require_once __DIR__ . '/src/bootstrap.php';

use Apollon\Pwa;
use Apollon\Router;
use Apollon\ShareResolver;
use Apollon\ShareToken;
use Apollon\Session;
use Apollon\Security;

// PWA endpoints (manifest + service worker)
if (isset($_GET['pwa'])) {
  if ($_GET['pwa'] === 'manifest') {
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-cache, no-store, must-revalidate');
    echo json_encode(Pwa::manifest());
    exit;
  }
  if ($_GET['pwa'] === 'sw') {
    header('Content-Type: application/javascript; charset=utf-8');
    header('Cache-Control: no-cache, no-store, must-revalidate');
    echo Pwa::serviceWorker();
    exit;
  }
}

// API actions
if (isset($_GET['action'])) {
  Router::create()->dispatch($_GET['action']);
  exit;
}

// Shared view links (?share_type=...&id=...)
$initialViewJS = '';
$sharedSong = null;
if (isset($_GET['share_type'], $_GET['id'])) {
  $shareType = (string)$_GET['share_type'];
  $shareId = (string)$_GET['id'];

  // Single-song share for a guest (guest listening disabled): authorize
  // streaming of just this song via a signed token.
  if ($shareType === 'song' && !ALLOW_GUEST_LISTEN && !Session::userId()) {
    $songId = (int)$shareId;
    if ($songId > 0) {
      $sharedSong = ['id' => $songId, 'token' => ShareToken::create($songId)];
    }
  }

  $viewConfig = ShareResolver::resolve($shareType, $shareId);
  if ($viewConfig) {
    $initialViewJS = '<script nonce="' . htmlspecialchars(Security::nonce(), ENT_QUOTES) . '">window.initialView = ' . json_encode($viewConfig, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) . ';</script>';
  }
}

require __DIR__ . '/src/Views/page.php';
