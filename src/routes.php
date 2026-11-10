<?php
// Apollon Media - action route registration.
declare(strict_types=1);

use Apollon\Controllers\AuthController;
use Apollon\Controllers\FavoriteController;
use Apollon\Controllers\FileController;
use Apollon\Controllers\LibraryController;
use Apollon\Controllers\MediaController;
use Apollon\Controllers\PlaylistController;
use Apollon\Controllers\ScanController;
use Apollon\Controllers\VideoController;
use Apollon\Controllers\WebradioController;
use Apollon\Database;
use Apollon\Router;

return function (Router $router): void {
    $db = Database::connect();
    Database::initSchema($db);

    header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
    header('Pragma: no-cache');
    header('Expires: 0');

    $auth = new AuthController($db);
    $library = new LibraryController($db);
    $favorites = new FavoriteController($db);
    $playlists = new PlaylistController($db);
    $media = new MediaController($db);
    $videos = new VideoController($db);
    $webradios = new WebradioController($db);
    $scan = new ScanController($db);
    $files = new FileController($db);

    // Session & authentication
    $router->map('get_session', fn() => $auth->getSession());
    $router->map('register', fn() => $auth->register());
    $router->map('login', fn() => $auth->login());
    $router->map('logout', fn() => $auth->logout());
    $router->map('change_username', fn() => $auth->changeUsername());
    $router->map('change_password', fn() => $auth->changePassword());
    $router->map('set_theme', fn() => $auth->setTheme());
    $router->map('delete_account', fn() => $auth->deleteAccount());

    // Library browsing
    $router->map('get_songs', fn() => $library->getSongs());
    $router->map('get_favorites', fn() => $favorites->getFavorites());
    $router->map('get_artists', fn() => $library->getArtists());
    $router->map('get_albums', fn() => $library->getAlbums());
    $router->map('get_view_data', fn() => $library->getViewData());
    $router->map('get_view_ids', fn() => $library->getViewIds());
    $router->map('search', fn() => $library->search());
    $router->map('get_song_data', fn() => $library->getSongData());

    // Favorites
    $router->map('toggle_favorite', fn() => $favorites->toggleFavorite());
    $router->map('update_favorite_order', fn() => $favorites->updateFavoriteOrder());
    $router->map('get_favorite_videos', fn() => $favorites->getFavoriteVideos());
    $router->map('toggle_video_favorite', fn() => $favorites->toggleVideoFavorite());

    // Playlists
    $router->map('get_user_playlists', fn() => $playlists->getUserPlaylists());
    $router->map('get_playlist_songs', fn() => $playlists->getPlaylistSongs());
    $router->map('create_playlist', fn() => $playlists->createPlaylist());
    $router->map('edit_playlist', fn() => $playlists->editPlaylist());
    $router->map('delete_playlist', fn() => $playlists->deletePlaylist());
    $router->map('add_to_playlist', fn() => $playlists->addToPlaylist());
    $router->map('remove_from_playlist', fn() => $playlists->removeFromPlaylist());
    $router->map('update_playlist_order', fn() => $playlists->updatePlaylistOrder());
    $router->map('export_playlist', fn() => $playlists->exportPlaylist());
    $router->map('import_playlist', fn() => $playlists->importPlaylist());

    // Webradios (HTTP radio streams)
    $router->map('get_webradios', fn() => $webradios->getWebradios());
    $router->map('create_webradio', fn() => $webradios->createWebradio());
    $router->map('edit_webradio', fn() => $webradios->editWebradio());
    $router->map('delete_webradio', fn() => $webradios->deleteWebradio());
    $router->map('stream_webradio', fn() => $webradios->streamWebradio());

    // Media
    $router->map('get_app_icon', fn() => $media->getAppIcon());
    $router->map('get_stream', fn() => $media->getStream());
    $router->map('get_image', fn() => $media->getImage());
    $router->map('get_video_stream', fn() => $media->getVideoStream());
    $router->map('get_video_image', fn() => $media->getVideoImage());

    // Videos (movies & series)
    $router->map('get_movies', fn() => $videos->getMovies());
    $router->map('get_series', fn() => $videos->getSeries());
    $router->map('get_series_episodes', fn() => $videos->getSeriesEpisodes());
    $router->map('get_video_data', fn() => $videos->getVideoData());
    $router->map('set_video_watched', fn() => $videos->setVideoWatched());
    $router->map('transcode_video', fn() => $videos->transcodeVideo());

    // Manage Songs (file manager)
    $router->map('get_manage_list', fn() => $files->listFiles());
    $router->map('create_folder', fn() => $files->createFolder());
    $router->map('rename_item', fn() => $files->renameItem());
    $router->map('delete_item', fn() => $files->deleteItem());
    $router->map('upload_chunk', fn() => $files->uploadChunk());

    // Library scan
    $router->map('full_scan', fn() => $scan->fullScan());
};
