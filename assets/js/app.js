// Apollon Media - application bootstrap and event wiring.
'use strict';

document.addEventListener('DOMContentLoaded', () => {
  'use strict';

  Apollon.state.csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';

  // DOM references
  Apollon.dom = {
    mainContent: document.getElementById('main-content'),
    contentArea: document.getElementById('content-area'),
    contentTitle: document.getElementById('content-title'),
    searchInputDesktop: document.getElementById('search-input-desktop'),
    searchInputMobile: document.getElementById('search-input-mobile'),
    searchBtnDesktop: document.getElementById('search-btn-desktop'),
    searchBtnMobile: document.getElementById('search-btn-mobile'),
    sortControls: document.getElementById('sort-controls'),
    sortSelect: document.getElementById('sort-select'),
    allNavLinks: document.querySelectorAll('.sidebar .nav-link'),
    contextMenu: document.getElementById('context-menu'),
    playerBar: document.getElementById('player-bar'),
    infiniteScrollLoader: document.getElementById('infinite-scroll-loader'),
    fullScanModalEl: document.getElementById('full-scan-modal'),
    fullScanIframe: document.getElementById('full-scan-iframe'),
    createPlaylistModal: new bootstrap.Modal(document.getElementById('create-playlist-modal')),
    editPlaylistModal: new bootstrap.Modal(document.getElementById('edit-playlist-modal')),
    createWebradioModal: new bootstrap.Modal(document.getElementById('create-webradio-modal')),
    editWebradioModal: new bootstrap.Modal(document.getElementById('edit-webradio-modal')),
    addToPlaylistModal: new bootstrap.Modal(document.getElementById('add-to-playlist-modal')),
    addToPlaylistModalBody: document.getElementById('add-to-playlist-modal-body'),
    metadataModal: new bootstrap.Modal(document.getElementById('metadata-modal')),
    metadataModalBody: document.getElementById('metadata-modal-body'),
    shareModal: new bootstrap.Modal(document.getElementById('share-modal')),
    shareModalTitle: document.getElementById('share-modal-title'),
    shareUrlInput: document.getElementById('share-url-input'),
    copyShareUrlBtn: document.getElementById('copy-share-url-btn'),
    playerTrackInfoMobile: document.querySelector('.player-bar .track-info.d-md-none'),
    playerModalEl: document.getElementById('player-modal'),
    playerModal: document.getElementById('player-modal') ? new bootstrap.Modal(document.getElementById('player-modal')) : null,
    playlistImportInput: document.getElementById('playlist-import-input'),
    manageUploadInput: document.getElementById('manage-upload-input'),
    playerElements: {
      art: [document.getElementById('player-art-desktop'), document.getElementById('player-art-mobile'), document.getElementById('player-modal-art')],
      title: [document.getElementById('player-title-desktop'), document.getElementById('player-title-mobile'), document.getElementById('player-modal-title')],
      artist: [document.getElementById('player-artist-desktop'), document.getElementById('player-artist-mobile'), document.getElementById('player-modal-artist')],
      currentTime: [document.getElementById('current-time'), document.getElementById('player-modal-current-time')],
      timeLeft: [document.getElementById('time-left'), document.getElementById('player-modal-time-left')],
      progress: [document.getElementById('progress-bar'), document.getElementById('player-modal-progress-bar')],
      progressContainer: [document.getElementById('progress-container'), document.getElementById('player-modal-progress-container')],
      playPauseBtn: [document.getElementById('play-pause-btn-desktop'), document.getElementById('play-pause-btn-mobile'), document.getElementById('player-modal-play-pause-btn')],
      prevBtn: [document.getElementById('prev-btn-desktop'), document.getElementById('prev-btn-mobile'), document.getElementById('player-modal-prev-btn')],
      nextBtn: [document.getElementById('next-btn-desktop'), document.getElementById('next-btn-mobile'), document.getElementById('player-modal-next-btn')],
      shuffleBtn: [document.getElementById('shuffle-btn-desktop'), document.getElementById('shuffle-btn-mobile'), document.getElementById('player-modal-shuffle-btn')],
      repeatBtn: [document.getElementById('repeat-btn-desktop'), document.getElementById('repeat-btn-mobile'), document.getElementById('player-modal-repeat-btn')],
      moreBtn: [document.getElementById('player-more-btn-desktop'), document.getElementById('player-more-btn-mobile'), document.getElementById('player-modal-more-btn')],
      volumeBtn: document.getElementById('volume-btn'),
      volumeSlider: document.getElementById('volume-slider'),
    },
  };

  Apollon.player.init();

  // Navigation
  Apollon.dom.allNavLinks.forEach(link => {
    if (link.getAttribute('data-bs-toggle') === 'modal' || link.id === 'sidebar-logout-btn') return;
    link.addEventListener('click', e => {
      e.preventDefault();
      const viewType = e.currentTarget.dataset.view;
      let sort = 'title_asc';
      if (viewType === 'get_favorites' || viewType === 'get_user_playlists') sort = 'manual_order';
      if (viewType === 'get_albums') sort = 'album_asc';
      if (viewType === 'get_artists') sort = 'name_asc';
      Apollon.views.loadView({ type: viewType, param: '', sort }, true);

      const offcanvasEl = document.getElementById('main-nav-offcanvas');
      if (window.innerWidth < 768 && offcanvasEl) {
        const offcanvas = bootstrap.Offcanvas.getInstance(offcanvasEl);
        if (offcanvas) offcanvas.hide();
      }
    });
  });

  // Search
  Apollon.dom.searchInputDesktop.addEventListener('keyup', e => { if (e.key === 'Enter') Apollon.views.runSearch(e.target.value); });
  Apollon.dom.searchInputMobile.addEventListener('keyup', e => { if (e.key === 'Enter') Apollon.views.runSearch(e.target.value); });
  Apollon.dom.searchBtnDesktop.addEventListener('click', () => Apollon.views.runSearch(Apollon.dom.searchInputDesktop.value));
  Apollon.dom.searchBtnMobile.addEventListener('click', () => Apollon.views.runSearch(Apollon.dom.searchInputMobile.value));

  // Sorting
  Apollon.dom.sortSelect.addEventListener('change', e => {
    Apollon.views.loadView({ ...Apollon.state.currentView, sort: e.target.value }, true);
  });

  // Player controls
  Apollon.dom.playerElements.playPauseBtn.forEach(btn => btn?.addEventListener('click', () => Apollon.player.togglePlayPause()));
  Apollon.dom.playerElements.prevBtn.forEach(btn => btn?.addEventListener('click', () => Apollon.player.playPrev()));
  Apollon.dom.playerElements.nextBtn.forEach(btn => btn?.addEventListener('click', () => Apollon.player.playNext()));
  Apollon.dom.playerElements.shuffleBtn.forEach(btn => btn?.addEventListener('click', () => Apollon.player.toggleShuffle()));
  Apollon.dom.playerElements.repeatBtn.forEach(btn => btn?.addEventListener('click', () => {
    Apollon.state.repeatMode = (Apollon.state.repeatMode === 'none') ? 'all' : (Apollon.state.repeatMode === 'all') ? 'one' : 'none';
    Apollon.player.updateRepeatIcons();
  }));
  Apollon.dom.playerElements.moreBtn.forEach(btn => btn?.addEventListener('click', e => {
    e.stopPropagation();
    if (Apollon.state.currentSong) Apollon.views.buildAndShowSongContextMenu(btn, Apollon.state.currentSong);
  }));

  if (Apollon.dom.playerTrackInfoMobile) {
    Apollon.dom.playerTrackInfoMobile.addEventListener('click', e => {
      if (!e.target.closest('button') && Apollon.dom.playerModal) {
        Apollon.dom.playerModal.show();
      }
    });
  }

  if (Apollon.dom.playerElements.volumeSlider) {
    Apollon.dom.playerElements.volumeSlider.addEventListener('input', e => {
      Apollon.player.audio.volume = e.target.value;
      Apollon.player.audio.muted = false;
    });
  }
  if (Apollon.dom.playerElements.volumeBtn) {
    Apollon.dom.playerElements.volumeBtn.addEventListener('click', () => {
      Apollon.player.audio.muted = !Apollon.player.audio.muted;
      Apollon.dom.playerElements.volumeSlider.value = Apollon.player.audio.muted ? 0 : (Apollon.player.audio.volume > 0 ? Apollon.player.audio.volume : Apollon.state.previousVolume);
    });
  }

  // Content area interactions
  Apollon.dom.contentArea.addEventListener('click', e => {
    const target = e.target;
    const manageCrumb = target.closest('.manage-crumb');
    if (manageCrumb && !manageCrumb.classList.contains('active')) {
      e.stopPropagation();
      const manageType = Apollon.state.currentView.type === 'manage_videos' ? 'manage_videos' : 'manage_songs';
      Apollon.views.loadView({ type: manageType, param: decodeURIComponent(manageCrumb.dataset.path), sort: 'name_asc' }, true);
      return;
    }

    const manageMoreBtn = target.closest('.manage-more-btn');
    if (manageMoreBtn) {
      e.stopPropagation();
      Apollon.views.buildAndShowManageContextMenu(manageMoreBtn, {
        kind: manageMoreBtn.dataset.kind,
        path: manageMoreBtn.dataset.path,
        name: manageMoreBtn.dataset.name
      });
      return;
    }

    const manageFolder = target.closest('.manage-folder');
    if (manageFolder) {
      e.stopPropagation();
      const manageType = Apollon.state.currentView.type === 'manage_videos' ? 'manage_videos' : 'manage_songs';
      Apollon.views.loadView({ type: manageType, param: decodeURIComponent(manageFolder.dataset.path), sort: 'name_asc' }, true);
      return;
    }

    const manageTab = target.closest('.manage-tab');
    if (manageTab) {
      e.stopPropagation();
      const type = manageTab.dataset.dir === 'videos' ? 'manage_videos' : 'manage_songs';
      if (Apollon.state.currentView.type !== type) {
        Apollon.views.loadView({ type, param: '', sort: 'name_asc' }, true);
      }
      return;
    }

    const favTab = target.closest('.fav-tab');
    if (favTab) {
      e.stopPropagation();
      const type = favTab.dataset.favTab === 'videos' ? 'favorite_videos' : 'get_favorites';
      const sort = type === 'get_favorites' ? 'manual_order' : '';
      if (Apollon.state.currentView.type !== type) {
        Apollon.views.loadView({ type, param: '', sort }, true);
      }
      return;
    }

    const newFolderBtn = target.closest('#new-folder-btn');
    if (newFolderBtn) {
      Apollon.views.createManageFolder();
      return;
    }

    const uploadFilesBtn = target.closest('#upload-files-btn');
    if (uploadFilesBtn) {
      Apollon.dom.manageUploadInput.click();
      return;
    }

    const moreBtn = target.closest('.more-btn');
    if (moreBtn) {
      e.stopPropagation();
      const songItem = moreBtn.closest('.song-item');
      Apollon.views.buildAndShowSongContextMenu(moreBtn, {
        id: parseInt(songItem.dataset.songId),
        is_favorite: songItem.dataset.isFavorite === '1',
        title: songItem.dataset.songTitle,
        artist: songItem.dataset.songArtist,
        album: songItem.dataset.songAlbum
      });
      return;
    }

    const plMoreBtn = target.closest('.playlist-more-btn');
    if (plMoreBtn) {
      e.stopPropagation();
      Apollon.views.buildAndShowPlaylistContextMenu(plMoreBtn, {
        publicId: plMoreBtn.dataset.publicId,
        name: plMoreBtn.dataset.name
      });
      return;
    }

    const webradioPlayBtn = target.closest('.webradio-play-btn');
    if (webradioPlayBtn) {
      e.stopPropagation();
      const id = parseInt(webradioPlayBtn.dataset.webradioId);
      const radio = (Apollon.state.webradios || []).find(w => w.id === id);
      if (!radio) return;
      const isCurrent = !!(Apollon.state.currentSong && Apollon.state.currentSong.is_radio && Apollon.state.currentSong.id === id);
      if (isCurrent) {
        Apollon.player.togglePlayPause();
      } else {
        Apollon.player.playWebradio(radio);
      }
      document.querySelectorAll('.webradio-item').forEach(el => {
        const playing = !!(Apollon.state.currentSong && Apollon.state.currentSong.is_radio && parseInt(el.dataset.webradioId) === Apollon.state.currentSong.id && Apollon.state.isPlaying);
        el.classList.toggle('now-playing', playing);
        const icon = el.querySelector('.webradio-play-btn .bi');
        if (icon) icon.className = playing ? 'bi bi-pause-fill' : 'bi bi-play-fill';
      });
      return;
    }

    const webradioMoreBtn = target.closest('.webradio-more-btn');
    if (webradioMoreBtn) {
      e.stopPropagation();
      Apollon.views.buildAndShowWebradioContextMenu(webradioMoreBtn, {
        id: parseInt(webradioMoreBtn.dataset.webradioId),
        name: webradioMoreBtn.dataset.name
      });
      return;
    }

    const createWebradioBtn = target.closest('#create-new-webradio-btn');
    if (createWebradioBtn) {
      Apollon.dom.createWebradioModal.show();
      return;
    }

    const shareBtn = target.closest('.share-view-btn');
    if (shareBtn) {
      e.stopPropagation();
      const type = Apollon.state.currentView.type.split('_')[0];
      Apollon.views.showShareModal(type, shareBtn.dataset.shareId, shareBtn.dataset.shareName);
      return;
    }

    const exportPlBtn = target.closest('.export-playlist-btn');
    if (exportPlBtn) {
      e.stopPropagation();
      window.location.href = `?action=export_playlist&public_id=${exportPlBtn.dataset.publicId}`;
      return;
    }

    const createPlBtn = target.closest('#create-new-playlist-btn');
    if (createPlBtn) {
      Apollon.dom.createPlaylistModal.show();
      return;
    }

    const importPlBtn = target.closest('#import-playlist-btn');
    if (importPlBtn) {
      Apollon.dom.playlistImportInput.click();
      return;
    }

    const artistClick = target.closest('.song-artist');
    if (artistClick) {
      e.stopPropagation();
      Apollon.views.loadView({ type: 'artist_songs', param: artistClick.dataset.artist, sort: 'title_asc' }, true);
      return;
    }

    const albumClick = target.closest('.song-album');
    if (albumClick) {
      e.stopPropagation();
      Apollon.views.loadView({ type: 'album_songs', param: albumClick.dataset.album, sort: 'title_asc' }, true);
      return;
    }

    const watchedToggle = target.closest('.watched-toggle');
    if (watchedToggle) {
      e.stopPropagation();
      const videoId = parseInt(watchedToggle.dataset.videoId);
      const isWatched = watchedToggle.dataset.isWatched === '1';
      Apollon.views.toggleVideoWatched(videoId, !isWatched, watchedToggle);
      return;
    }

    const videoFavToggle = target.closest('.video-fav-toggle');
    if (videoFavToggle) {
      e.stopPropagation();
      const videoId = parseInt(videoFavToggle.dataset.videoId);
      Apollon.views.toggleVideoFavorite(videoId, videoFavToggle);
      return;
    }

    const movieCard = target.closest('.poster-card[data-movie-id]');
    if (movieCard) {
      Apollon.videoPlayer.playVideo(parseInt(movieCard.dataset.movieId));
      return;
    }

    const seriesCard = target.closest('.poster-card[data-series]');
    if (seriesCard) {
      Apollon.views.loadView({ type: 'series_episodes', param: seriesCard.dataset.series, sort: 'episode_asc' }, true);
      return;
    }

    const episodeItem = target.closest('.episode-item');
    if (episodeItem) {
      const queue = Array.from(document.querySelectorAll('.episode-item')).map(el => parseInt(el.dataset.videoId));
      Apollon.videoPlayer.playVideo(parseInt(episodeItem.dataset.videoId), queue);
      return;
    }

    const card = target.closest('.card');
    if (card && !target.closest('.playlist-more-btn')) {
      if (card.dataset.artist) {
        Apollon.views.loadView({ type: 'artist_songs', param: card.dataset.artist, sort: 'title_asc' }, true);
      } else if (card.dataset.album) {
        Apollon.views.loadView({ type: 'album_songs', param: card.dataset.album, sort: 'title_asc' }, true);
      } else if (card.dataset.playlist) {
        Apollon.views.loadView({ type: 'playlist_songs', param: card.dataset.playlist, sort: 'manual_order' }, true);
      }
      return;
    }

    const songItem = target.closest('.song-item');
    if (songItem) {
      Apollon.player.setQueueAndPlay(parseInt(songItem.dataset.songId));
    }
  });

  // Context menu
  document.addEventListener('click', e => {
    if (Apollon.dom.contextMenu.style.display === 'block' && !Apollon.dom.contextMenu.contains(e.target)) {
      Apollon.views.hideContextMenu();
    }
  });

  Apollon.dom.contextMenu.addEventListener('click', async e => {
    const item = e.target.closest('.context-menu-item');
    if (!item) return;
    const { action, name, id, publicId, path } = item.dataset;
    Apollon.views.hideContextMenu();

    switch (action) {
      case 'share_song':
        Apollon.views.showShareModal('song', id, name);
        break;
      case 'go_artist':
        Apollon.views.loadView({ type: 'artist_songs', param: name, sort: 'title_asc' }, true);
        break;
      case 'go_album':
        Apollon.views.loadView({ type: 'album_songs', param: name, sort: 'title_asc' }, true);
        break;
      case 'toggle_favorite':
        Apollon.views.toggleFavorite(parseInt(id));
        break;
      case 'manage_open':
        Apollon.views.loadView({ type: 'manage_songs', param: decodeURIComponent(path), sort: 'name_asc' }, true);
        break;
      case 'manage_rename':
        Apollon.views.renameManageItem(decodeURIComponent(path), decodeURIComponent(name));
        break;
      case 'manage_delete':
        Apollon.views.deleteManageItem(decodeURIComponent(path), decodeURIComponent(name));
        break;
      case 'export_playlist':
        window.location.href = `?action=export_playlist&public_id=${publicId}`;
        break;
      case 'show_metadata': {
        const meta = await Apollon.utils.fetchData(`?action=get_song_data&id=${id}`);
        if (meta) {
          Apollon.dom.metadataModalBody.innerHTML = `
            <ul class="list-group list-group-flush">
              <li class="list-group-item bg-transparent text-theme d-flex justify-content-between text-truncate"><span>${Apollon.i18n.t('common.title')}:</span> <strong class="text-truncate">${Apollon.utils.escapeHtml(meta.title)}</strong></li>
              <li class="list-group-item bg-transparent text-theme d-flex justify-content-between text-truncate"><span>${Apollon.i18n.t('common.artist')}:</span> <strong class="text-truncate">${Apollon.utils.escapeHtml(meta.artist)}</strong></li>
              <li class="list-group-item bg-transparent text-theme d-flex justify-content-between text-truncate"><span>${Apollon.i18n.t('common.album')}:</span> <strong class="text-truncate">${Apollon.utils.escapeHtml(meta.album)}</strong></li>
              <li class="list-group-item bg-transparent text-theme d-flex justify-content-between"><span>${Apollon.i18n.t('common.year')}:</span> <strong>${meta.year || Apollon.i18n.t('common.na')}</strong></li>
              <li class="list-group-item bg-transparent text-theme d-flex justify-content-between"><span>${Apollon.i18n.t('common.duration')}:</span> <strong>${Apollon.utils.formatTime(meta.duration)}</strong></li>
              <li class="list-group-item bg-transparent text-theme d-flex justify-content-between"><span>${Apollon.i18n.t('common.bitrate')}:</span> <strong>${meta.bitrate ? Math.round(meta.bitrate / 1000) + ' kbps' : Apollon.i18n.t('common.na')}</strong></li>
            </ul>`;
          Apollon.dom.metadataModal.show();
        }
        break;
      }
      case 'add_to_playlist':
        Apollon.state.songIdForPlaylist = parseInt(id);
        Apollon.dom.addToPlaylistModalBody.innerHTML = '<div class="text-center p-3 text-secondary">' + Apollon.i18n.t('playlist.loading') + '</div>';
        Apollon.dom.addToPlaylistModal.show();
        const playlists = await Apollon.utils.fetchData('?action=get_user_playlists');
        if (playlists && playlists.length > 0) {
          Apollon.dom.addToPlaylistModalBody.innerHTML = playlists.map(p =>
            `<button class="list-group-item list-group-item-action bg-transparent text-theme text-truncate my-1 p-2 rounded add-to-playlist-item" data-playlist-id="${p.id}">${Apollon.utils.escapeHtml(p.name)}</button>`
          ).join('');
        } else {
          Apollon.dom.addToPlaylistModalBody.innerHTML = '<p class="text-secondary text-center mb-0">' + Apollon.i18n.t('playlist.none') + '</p>';
        }
        break;
      case 'remove_from_playlist': {
        const removed = await Apollon.utils.fetchData('?action=remove_from_playlist', {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify({ song_id: parseInt(id), playlist_public_id: decodeURIComponent(Apollon.state.currentView.param) })
        });
        if (removed && removed.status === 'success') {
          Apollon.utils.showToast(Apollon.i18n.t('playlist.removed'), 'success');
          Apollon.views.loadView(Apollon.state.currentView, false);
        }
        break;
      }
      case 'edit_playlist':
        document.getElementById('edit-playlist-id-input').value = publicId;
        document.getElementById('edit-playlist-name-input').value = name;
        Apollon.dom.editPlaylistModal.show();
        break;
      case 'delete_playlist':
        if (confirm(Apollon.i18n.t('playlist.delete_confirm'))) {
          const del = await Apollon.utils.fetchData('?action=delete_playlist', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ public_id: publicId })
          });
          if (del && del.status === 'success') {
            Apollon.utils.showToast(Apollon.i18n.t('playlist.deleted'), 'success');
            Apollon.views.loadView(Apollon.state.currentView, false);
          }
        }
        break;
      case 'edit_webradio': {
        const radio = (Apollon.state.webradios || []).find(w => w.id === parseInt(id));
        if (radio) {
          document.getElementById('edit-webradio-id-input').value = radio.id;
          document.getElementById('edit-webradio-name-input').value = radio.name;
          document.getElementById('edit-webradio-url-input').value = radio.url;
          Apollon.dom.editWebradioModal.show();
        }
        break;
      }
      case 'delete_webradio':
        if (confirm(Apollon.i18n.t('webradio.delete_confirm'))) {
          const del = await Apollon.utils.fetchData('?action=delete_webradio', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ id: parseInt(id) })
          });
          if (del && del.status === 'success') {
            Apollon.utils.showToast(Apollon.i18n.t('webradio.deleted'), 'success');
            Apollon.views.loadView(Apollon.state.currentView, false);
          }
        }
        break;
    }
  });

  // Add to playlist
  Apollon.dom.addToPlaylistModalBody.addEventListener('click', async e => {
    const item = e.target.closest('.add-to-playlist-item');
    if (!item || !Apollon.state.songIdForPlaylist || Apollon.state.isAddingToPlaylist) return;
    Apollon.state.isAddingToPlaylist = true;
    const plId = item.dataset.playlistId;
    const sId = Apollon.state.songIdForPlaylist;
    Apollon.state.songIdForPlaylist = null;
    try {
      const res = await Apollon.utils.fetchData('?action=add_to_playlist', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ playlist_id: plId, song_id: sId })
      });
      if (res) {
        Apollon.utils.showToast(res.message, res.status === 'success' ? 'success' : 'info');
        Apollon.dom.addToPlaylistModal.hide();
      }
    } finally {
      Apollon.state.isAddingToPlaylist = false;
    }
  });

  // Playlist import
  Apollon.dom.playlistImportInput.addEventListener('change', async e => {
    const file = e.target.files[0];
    if (!file) return;
    const formData = new FormData();
    formData.append('file', file);
    const res = await Apollon.utils.fetchData('?action=import_playlist', {
      method: 'POST',
      body: formData
    });
    if (res && res.status === 'success') {
      Apollon.utils.showToast(res.message, 'success');
      Apollon.views.loadView({ type: 'get_user_playlists', param: '', sort: 'name_asc' }, true);
    }
    Apollon.dom.playlistImportInput.value = '';
  });

  // Manage Songs upload
  Apollon.dom.manageUploadInput.addEventListener('change', async e => {
    if (!e.target.files || e.target.files.length === 0) return;
    const path = Apollon.state.currentView.param || '';
    const dir = Apollon.state.currentView.type === 'manage_videos' ? 'videos' : 'music';
    const files = Array.from(e.target.files);
    const progress = Apollon.utils.showProgressToast(Apollon.i18n.t('upload.progress', { current: 0, total: files.length }));
    let uploaded = 0;
    const failed = [];
    for (let i = 0; i < files.length; i++) {
      const file = files[i];
      progress(Apollon.i18n.t('upload.progress_named', { current: i + 1, total: files.length, name: file.name }));
      const res = await Apollon.views.uploadFileChunked(file, path, dir);
      if (res) {
        uploaded++;
      } else {
        failed.push(file.name);
      }
    }
    progress.close();
    if (uploaded > 0) {
      Apollon.utils.showToast(Apollon.i18n.t('upload.done', { count: uploaded }), 'success');
      Apollon.views.loadView(Apollon.state.currentView, false);
    }
    if (failed.length > 0) {
      Apollon.utils.showToast(Apollon.i18n.t('upload.failed', { files: failed.join(', ') }), 'error');
    }
    Apollon.dom.manageUploadInput.value = '';
  });

  // Infinite scroll
  Apollon.dom.mainContent.addEventListener('scroll', () => {
    if (Apollon.dom.mainContent.scrollTop + Apollon.dom.mainContent.clientHeight >= Apollon.dom.mainContent.scrollHeight - 300) {
      Apollon.views.loadMoreContent();
    }
  });

  // Auth forms
  document.getElementById('login-form').addEventListener('submit', async e => {
    e.preventDefault();
    const username = document.getElementById('login-username').value;
    const password = document.getElementById('login-password').value;
    const res = await Apollon.utils.fetchData('?action=login', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ username, password })
    });
    if (res && res.status === 'success') {
      Apollon.state.csrfToken = res.csrf_token || Apollon.state.csrfToken;
      bootstrap.Modal.getInstance(document.getElementById('login-modal')).hide();
      e.target.reset();
      Apollon.utils.showToast(Apollon.i18n.t('login.success'), 'success');
      await checkSession();
      Apollon.views.loadView(Apollon.state.currentView, false);
    }
  });

  const registerForm = document.getElementById('register-form');
  if (registerForm) {
    registerForm.addEventListener('submit', async e => {
      e.preventDefault();
      const username = document.getElementById('register-username').value;
      const password = document.getElementById('register-password').value;
      const res = await Apollon.utils.fetchData('?action=register', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ username, password })
      });
      if (res && res.status === 'success') {
        bootstrap.Modal.getInstance(document.getElementById('register-modal')).hide();
        e.target.reset();
        Apollon.utils.showToast(res.message, 'success');
      }
    });
  }

  document.getElementById('sidebar-logout-btn').addEventListener('click', async e => {
    e.preventDefault();
    await Apollon.utils.fetchData('?action=logout', { method: 'POST' });
    Apollon.state.currentUser = null;
    Apollon.player.audio.pause();
    Apollon.opfs.clear();
    updateUIForAuthState();
    Apollon.views.loadView({ type: 'get_songs', param: '', sort: 'title_asc' }, true);
  });

  // Settings forms
  document.getElementById('change-username-form').addEventListener('submit', async e => {
    e.preventDefault();
    const username = document.getElementById('username-input').value;
    const res = await Apollon.utils.fetchData('?action=change_username', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ username })
    });
    if (res && res.status === 'success') {
      Apollon.state.currentUser.username = res.username;
      Apollon.utils.showToast(res.message, 'success');
    }
  });

  document.getElementById('change-password-form').addEventListener('submit', async e => {
    e.preventDefault();
    const new_password = document.getElementById('new-password').value;
    const res = await Apollon.utils.fetchData('?action=change_password', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ new_password })
    });
    if (res && res.status === 'success') {
      e.target.reset();
      Apollon.utils.showToast(res.message, 'success');
    }
  });

  const themeSelect = document.getElementById('theme-select');
  if (themeSelect) {
    themeSelect.addEventListener('change', async e => {
      const theme = e.target.value;
      const res = await Apollon.utils.fetchData('?action=set_theme', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ theme })
      });
      if (res && res.status === 'success') {
        Apollon.theme.apply(theme);
        Apollon.utils.showToast(res.message, 'success');
      } else {
        e.target.value = Apollon.theme.current;
      }
    });
  }

  document.getElementById('delete-account-btn').addEventListener('click', async () => {
    if (confirm(Apollon.i18n.t('settings.delete_account_confirm'))) {
      const res = await Apollon.utils.fetchData('?action=delete_account', { method: 'POST' });
      if (res && res.status === 'success') {
        bootstrap.Modal.getInstance(document.getElementById('settings-modal')).hide();
        Apollon.state.currentUser = null;
        Apollon.player.audio.pause();
        Apollon.opfs.clear();
        updateUIForAuthState();
        Apollon.utils.showToast(res.message, 'success');
        Apollon.views.loadView({ type: 'get_songs', param: '', sort: 'title_asc' }, true);
      }
    }
  });

  // Playlist forms
  document.getElementById('create-playlist-form').addEventListener('submit', async e => {
    e.preventDefault();
    const name = document.getElementById('playlist-name-input').value;
    const res = await Apollon.utils.fetchData('?action=create_playlist', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ name })
    });
    if (res && res.status === 'success') {
      Apollon.dom.createPlaylistModal.hide();
      e.target.reset();
      Apollon.utils.showToast(Apollon.i18n.t('playlist.created'), 'success');
      if (Apollon.state.currentView.type === 'get_user_playlists') Apollon.views.loadView(Apollon.state.currentView, false);
    }
  });

  document.getElementById('edit-playlist-form').addEventListener('submit', async e => {
    e.preventDefault();
    const public_id = document.getElementById('edit-playlist-id-input').value;
    const name = document.getElementById('edit-playlist-name-input').value;
    const res = await Apollon.utils.fetchData('?action=edit_playlist', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ public_id, name })
    });
    if (res && res.status === 'success') {
      Apollon.dom.editPlaylistModal.hide();
      Apollon.utils.showToast(Apollon.i18n.t('playlist.updated'), 'success');
      if (Apollon.state.currentView.type === 'get_user_playlists') Apollon.views.loadView(Apollon.state.currentView, false);
    }
  });

  // Webradio forms
  document.getElementById('create-webradio-form').addEventListener('submit', async e => {
    e.preventDefault();
    const name = document.getElementById('webradio-name-input').value;
    const url = document.getElementById('webradio-url-input').value;
    const res = await Apollon.utils.fetchData('?action=create_webradio', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ name, url })
    });
    if (res && res.status === 'success') {
      Apollon.dom.createWebradioModal.hide();
      e.target.reset();
      Apollon.utils.showToast(Apollon.i18n.t('webradio.created'), 'success');
      if (Apollon.state.currentView.type === 'get_webradios') Apollon.views.loadView(Apollon.state.currentView, false);
    }
  });

  document.getElementById('edit-webradio-form').addEventListener('submit', async e => {
    e.preventDefault();
    const id = document.getElementById('edit-webradio-id-input').value;
    const name = document.getElementById('edit-webradio-name-input').value;
    const url = document.getElementById('edit-webradio-url-input').value;
    const res = await Apollon.utils.fetchData('?action=edit_webradio', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ id, name, url })
    });
    if (res && res.status === 'success') {
      Apollon.dom.editWebradioModal.hide();
      Apollon.utils.showToast(Apollon.i18n.t('webradio.updated'), 'success');
      if (Apollon.state.currentView.type === 'get_webradios') Apollon.views.loadView(Apollon.state.currentView, false);
    }
  });

  // Share modal
  Apollon.dom.copyShareUrlBtn.addEventListener('click', () => {
    navigator.clipboard.writeText(Apollon.dom.shareUrlInput.value).then(() => {
      Apollon.dom.copyShareUrlBtn.textContent = Apollon.i18n.t('common.copied');
      setTimeout(() => Apollon.dom.copyShareUrlBtn.textContent = Apollon.i18n.t('common.copy'), 2000);
    });
  });

  // Full scan modal
  if (Apollon.dom.fullScanModalEl && Apollon.dom.fullScanIframe) {
    Apollon.dom.fullScanModalEl.addEventListener('show.bs.modal', () => Apollon.dom.fullScanIframe.src = '?action=full_scan');
    Apollon.dom.fullScanModalEl.addEventListener('hidden.bs.modal', () => {
      Apollon.dom.fullScanIframe.src = 'about:blank';
      Apollon.views.loadView(Apollon.state.currentView, false);
    });
  }

  // Browser Back & Forth Button Support
  window.addEventListener('popstate', event => {
    if (event.state && event.state.type) {
      Apollon.views.loadView(event.state, false);
    } else {
      const parsed = Apollon.views.parseViewFromHash();
      if (parsed) {
        Apollon.views.loadView(parsed, false);
      } else {
        Apollon.views.loadView({ type: 'get_songs', param: '', sort: 'title_asc' }, false);
      }
    }
  });

  function updateUIForAuthState() {
    const loggedIn = !!Apollon.state.currentUser;
    document.body.classList.toggle('logged-in', loggedIn);
    document.body.classList.toggle('logged-out', !loggedIn);
    document.body.classList.toggle('body-locked', !Apollon.config.guestListen && !loggedIn);
    if (Apollon.state.currentUser) {
      const nameInput = document.getElementById('username-input');
      if (nameInput) nameInput.value = Apollon.state.currentUser.username || '';
      Apollon.theme.apply(Apollon.state.currentUser.theme);
    } else {
      Apollon.theme.apply('dark');
    }
    const themeSelect = document.getElementById('theme-select');
    if (themeSelect) themeSelect.value = Apollon.theme.current;
  }

  async function checkSession() {
    const res = await Apollon.utils.fetchData('?action=get_session');
    Apollon.state.currentUser = (res && res.status === 'loggedin') ? res.user : null;
    if (res && res.csrf_token) {
      Apollon.state.csrfToken = res.csrf_token;
    }
    updateUIForAuthState();
  }

  function wireLanding() {
    const form = document.getElementById('landing-login-form');
    if (form) {
      form.addEventListener('submit', async e => {
        e.preventDefault();
        const username = document.getElementById('landing-username').value;
        const password = document.getElementById('landing-password').value;
        const res = await Apollon.utils.fetchData('?action=login', {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify({ username, password })
        });
        if (res && res.status === 'success') {
          Apollon.state.csrfToken = res.csrf_token || Apollon.state.csrfToken;
          e.target.reset();
          await checkSession();
          window.location.reload();
        }
      });
    }
    const regLink = document.getElementById('landing-register-link');
    if (regLink) {
      regLink.addEventListener('click', e => {
        e.preventDefault();
        const modalEl = document.getElementById('register-modal');
        if (modalEl) bootstrap.Modal.getOrCreateInstance(modalEl).show();
      });
    }
  }

  function wireSharedSong() {
    const btn = document.getElementById('shared-song-play-btn');
    if (btn) {
      btn.addEventListener('click', () => {
        Apollon.player.playSongById(Apollon.config.sharedSong.id);
      });
    }
  }

  const init = async () => {
    if ('serviceWorker' in navigator) {
      navigator.serviceWorker.register('?pwa=sw').then(reg => {
        reg.update();
      }).catch(() => {});
    }
    Apollon.dom.playerElements.prevBtn.forEach(b => { if (b) b.innerHTML = Apollon.ICONS.prev; });
    Apollon.dom.playerElements.nextBtn.forEach(b => { if (b) b.innerHTML = Apollon.ICONS.next; });
    Apollon.dom.playerElements.shuffleBtn.forEach(b => { if (b) b.innerHTML = Apollon.ICONS.shuffle; });
    Apollon.player.updatePlayPauseIcons();
    Apollon.player.updateRepeatIcons();
    Apollon.player.updateShuffleButtons();
    Apollon.videoPlayer.init();

    await checkSession();

    // Shared single-song mode: a guest opened a song share link while guest
    // listening is disabled -> show only that song (playable via signed token).
    if (Apollon.config.sharedSong && !Apollon.state.currentUser) {
      wireSharedSong();
      return;
    }

    // Locked mode: guest listening disabled and not logged in -> show the
    // landing/login page (no sidebar, no library view).
    if (!Apollon.config.guestListen && !Apollon.state.currentUser) {
      wireLanding();
      return;
    }

    // Persistent Page Recovery (Hash -> LocalStorage -> Default)
    let initialToLoad = null;
    if (window.initialView) {
      initialToLoad = window.initialView;
    } else {
      const hashView = Apollon.views.parseViewFromHash();
      if (hashView) {
        initialToLoad = hashView;
      } else {
        try {
          const saved = localStorage.getItem(Apollon.VIEW_STORAGE_KEY);
          if (saved) {
            const parsed = JSON.parse(saved);
            if (parsed && parsed.type) {
              initialToLoad = parsed;
            }
          }
        } catch (e) {}
      }
    }

    if (!initialToLoad) {
      initialToLoad = { type: 'get_songs', param: '', sort: 'title_asc' };
    }

    const initialHash = `view=${encodeURIComponent(initialToLoad.type)}&param=${encodeURIComponent(initialToLoad.param || '')}&sort=${encodeURIComponent(initialToLoad.sort || '')}`;
    window.history.replaceState(initialToLoad, '', `#${initialHash}`);
    Apollon.views.loadView(initialToLoad, false);
  };

  init();
});
