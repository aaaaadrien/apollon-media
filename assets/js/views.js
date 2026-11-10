// Apollon Media - views: rendering, navigation, context menu, search.
'use strict';

Apollon.views = {
  showLoader(initial = true) {
    if (initial) {
      Apollon.dom.contentArea.innerHTML = '<div class="loader">' + Apollon.i18n.t('common.loading') + '</div>';
      Apollon.dom.contentTitle.classList.remove('d-none');
    } else {
      Apollon.dom.infiniteScrollLoader.classList.remove('d-none');
    }
  },

  hideLoader() {
    Apollon.dom.infiniteScrollLoader.classList.add('d-none');
  },

  updateContentTitle(text, show = true) {
    if (!show) {
      Apollon.dom.contentTitle.classList.add('d-none');
      return;
    }
    Apollon.dom.contentTitle.classList.remove('d-none');
    const decoded = decodeURIComponent(text.replace(/\+/g, ' '));
    Apollon.dom.contentTitle.textContent = decoded;
    document.title = decoded + ' - ' + Apollon.i18n.t('brand.name');
  },

  renderViewDetailsHeader(details, type) {
    let typeText = type.toUpperCase();
    let statsText = `${Apollon.i18n.t('common.songs', { count: details.song_count || 0 })} &bull; ${Apollon.utils.formatTime(details.total_duration || 0)}`;
    let exportBtn = (type === 'playlist') ? `
      <button class="btn btn-outline-theme rounded-pill me-2 px-3 export-playlist-btn" title="${Apollon.i18n.t('share.export_playlist')}" data-public-id="${Apollon.utils.escapeAttr(details.public_id)}">
        <i class="bi bi-box-arrow-up"></i> <span class="d-none d-md-inline">${Apollon.i18n.t('common.export')}</span>
      </button>` : '';
    let shareBtn = `
      <button class="btn btn-outline-theme rounded-pill share-view-btn ms-auto px-3" title="${Apollon.i18n.t('common.share')}" data-share-id="${Apollon.utils.escapeAttr(details.public_id || encodeURIComponent(details.name))}" data-share-name="${Apollon.utils.escapeAttr(encodeURIComponent(details.name))}">
        <i class="bi bi-share-fill"></i> <span class="d-none d-md-inline">${Apollon.i18n.t('common.share')}</span>
      </button>`;

    if (type === 'playlist') {
      typeText = Apollon.i18n.t('view.playlist_by', { creator: Apollon.utils.escapeHtml(details.creator) });
    }

    const headerHTML = `
      <div class="view-details-header">
        <img src="${Apollon.utils.escapeAttr(details.image_url)}" alt="${Apollon.utils.escapeAttr(details.name)}">
        <div class="view-details-header-info text-truncate">
          <div class="type text-truncate">${typeText}</div>
          <h2 class="name text-truncate">${Apollon.utils.escapeHtml(details.name)}</h2>
          <div class="stats text-truncate">${statsText}</div>
        </div>
        <div class="d-flex align-items-center ms-auto">
          ${exportBtn}
          ${shareBtn}
        </div>
      </div>`;
    Apollon.dom.contentArea.insertAdjacentHTML('afterbegin', headerHTML);
  },

  renderSongs(songs, append = false) {
    if (Apollon.state.sortable) {
      Apollon.state.sortable.destroy();
      Apollon.state.sortable = null;
    }

    if (!append) {
      if (!Apollon.dom.contentArea.querySelector('.view-details-header')) {
        Apollon.dom.contentArea.innerHTML = '';
      }
    }

    if (!songs || songs.length === 0) {
      if (!append) {
        Apollon.dom.contentArea.innerHTML += '<div class="text-center p-5 text-secondary">' + Apollon.i18n.t('view.no_songs') + '</div>';
      }
      Apollon.state.allContentloaded = true;
      this.hideLoader();
      return;
    }

    let songList = Apollon.dom.contentArea.querySelector('.song-list');
    if (!songList) {
      songList = document.createElement('div');
      songList.className = 'song-list';
      const header = `
        <div class="song-list-header d-none d-md-grid">
          <div>#</div><div class="text-truncate">${Apollon.i18n.t('common.title')}</div><div class="text-truncate">${Apollon.i18n.t('common.artist')}</div><div class="text-truncate">${Apollon.i18n.t('common.album')}</div><div>${Apollon.i18n.t('common.year')}</div><div>${Apollon.i18n.t('common.time')}</div><div></div>
        </div>`;
      Apollon.dom.contentArea.insertAdjacentHTML('beforeend', header);
      Apollon.dom.contentArea.appendChild(songList);
    }

    const songsHTML = songs.map(song => {
      const isNowPlaying = Apollon.state.currentSong && Apollon.state.currentSong.id === song.id;
      return `
        <div class="song-item py-md-3 ${isNowPlaying ? 'now-playing' : ''}"
          data-song-id="${song.id}"
          data-is-favorite="${song.is_favorite == 1 ? '1' : '0'}"
          data-song-title="${Apollon.utils.escapeAttr(song.title)}"
          data-song-artist="${Apollon.utils.escapeAttr(song.artist)}"
          data-song-album="${Apollon.utils.escapeAttr(song.album)}"
          data-song-user-id="${song.user_id}">
          <div class="song-indicator-wrapper d-flex align-items-center justify-content-center">
            <img src="?action=get_image&id=${song.id}" class="song-thumb" loading="lazy" alt="art">
            ${Apollon.ICONS.soundwave}
          </div>
          <div class="song-title-wrapper text-truncate">
            <div class="song-title text-truncate">${Apollon.utils.escapeHtml(song.title)}</div>
          </div>
          <div class="song-artist text-truncate" data-artist="${Apollon.utils.escapeAttr(encodeURIComponent(song.artist))}">${Apollon.utils.escapeHtml(song.artist)}</div>
          <div class="song-album text-truncate" data-album="${Apollon.utils.escapeAttr(encodeURIComponent(song.album))}">${Apollon.utils.escapeHtml(song.album)}</div>
          <div class="song-year d-none d-md-block">${song.year || ''}</div>
          <div class="song-duration d-none d-md-block">${Apollon.utils.formatTime(song.duration)}</div>
          <div class="song-more">
            <button class="more-btn" data-song-id="${song.id}">
              <i class="bi bi-three-dots-vertical"></i>
            </button>
          </div>
          <div class="song-artist-mobile d-md-none text-truncate">
            <span class="text-truncate me-2">${Apollon.utils.escapeHtml(song.artist)}</span>
            <span>${Apollon.utils.formatTime(song.duration)}</span>
          </div>
        </div>`;
    }).join('');

    songList.insertAdjacentHTML('beforeend', songsHTML);

    const isSortable = (Apollon.state.currentView.type === 'get_favorites' || Apollon.state.currentView.type === 'playlist_songs') && Apollon.state.currentView.sort === 'manual_order';
    if (isSortable) {
      Apollon.state.sortable = Sortable.create(songList, {
        animation: 150,
        delay: 200,
        delayOnTouchOnly: true,
        scroll: Apollon.dom.mainContent,
        onEnd: async () => {
          const ids = Array.from(songList.querySelectorAll('.song-item')).map(item => item.dataset.songId);
          const action = Apollon.state.currentView.type === 'get_favorites' ? 'update_favorite_order' : 'update_playlist_order';
          const body = {
            ids,
            ...(Apollon.state.currentView.type === 'playlist_songs' && { playlist_public_id: decodeURIComponent(Apollon.state.currentView.param) })
          };
          await Apollon.utils.fetchData(`?action=${action}`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(body)
          });
        }
      });
    }
  },

  renderGrid(items, type, append = false) {
    if (!append) Apollon.dom.contentArea.innerHTML = '';
    if (type === 'get_user_playlists' && !append) {
      Apollon.dom.contentArea.innerHTML = `
        <div class="p-3 d-flex gap-2">
          <button class="btn btn-danger rounded-pill px-3" id="create-new-playlist-btn"><i class="bi bi-plus-lg"></i> ${Apollon.i18n.t('playlist.create_title')}</button>
          <button class="btn btn-outline-theme rounded-pill px-3" id="import-playlist-btn"><i class="bi bi-box-arrow-in-down"></i> ${Apollon.i18n.t('playlist.import_btn')}</button>
        </div>`;
    }

    if (!items || items.length === 0) {
      if (!append && type !== 'get_user_playlists') {
        Apollon.dom.contentArea.innerHTML += '<div class="text-center p-5 text-secondary">' + Apollon.i18n.t('view.nothing_found') + '</div>';
      }
      Apollon.state.allContentloaded = true;
      this.hideLoader();
      return;
    }

    let grid = Apollon.dom.contentArea.querySelector('.row');
    if (!grid) {
      grid = document.createElement('div');
      grid.className = 'row row-cols-2 row-cols-sm-3 row-cols-md-4 row-cols-lg-5 row-cols-xl-6 g-4';
      Apollon.dom.contentArea.appendChild(grid);
    }

    const itemsHTML = items.map(item => {
      let name = '', subtext = '', imageId = 0, dataType = '', dataVal = '', isRound = false;

      if (type === 'get_albums') {
        name = item.album;
        subtext = item.artist;
        imageId = item.image_id || 0;
        dataType = 'album';
        dataVal = name;
      } else if (type === 'get_artists') {
        name = item.artist;
        subtext = Apollon.i18n.t('common.songs', { count: item.song_count });
        imageId = item.image_id || 0;
        dataType = 'artist';
        dataVal = name;
        isRound = true;
      } else if (type === 'get_user_playlists') {
        name = item.name;
        subtext = Apollon.i18n.t('common.songs', { count: item.song_count });
        imageId = item.image_id || 0;
        dataType = 'playlist';
        dataVal = item.public_id;
      }

      const moreBtn = (type === 'get_user_playlists') ? `
        <button class="playlist-more-btn" data-public-id="${Apollon.utils.escapeAttr(item.public_id)}" data-name="${Apollon.utils.escapeAttr(name)}">
          <i class="bi bi-three-dots-vertical"></i>
        </button>` : '';

      return `
        <div class="col">
          <div class="card h-100 bg-transparent text-theme border-0 playlist-card" data-${dataType}="${Apollon.utils.escapeAttr(encodeURIComponent(dataVal))}" style="cursor: pointer;">
            ${moreBtn}
            <div style="position: relative; display: block; border-radius: ${isRound ? '50%' : '8px'}; overflow: hidden;">
              <img src="?action=get_image&id=${imageId}" class="card-img-top" alt="${Apollon.utils.escapeAttr(name)}" style="aspect-ratio: 1/1; object-fit: cover; background-color: var(--ytm-surface-2); margin-bottom: 0 !important;" loading="lazy">
            </div>
            <div class="card-body px-0 py-2">
              <h5 class="card-title fs-6 fw-normal text-truncate">${Apollon.utils.escapeHtml(name)}</h5>
              ${subtext ? `<p class="card-text small text-secondary text-truncate">${Apollon.utils.escapeHtml(subtext)}</p>` : ''}
            </div>
          </div>
        </div>
      `;
    }).join('');

    grid.insertAdjacentHTML('beforeend', itemsHTML);
  },

  renderWebradios(items, append = false) {
    if (!append) Apollon.dom.contentArea.innerHTML = '';
    Apollon.state.webradios = items || [];
    Apollon.dom.contentArea.innerHTML = `
      <div class="p-3 d-flex gap-2">
        <button class="btn btn-danger rounded-pill px-3" id="create-new-webradio-btn"><i class="bi bi-plus-lg"></i> ${Apollon.i18n.t('webradio.create_title')}</button>
      </div>`;
    if (!items || items.length === 0) {
      Apollon.dom.contentArea.innerHTML += '<div class="text-center p-5 text-secondary">' + Apollon.i18n.t('webradio.none') + '</div>';
      Apollon.state.allContentloaded = true;
      this.hideLoader();
      return;
    }
    const list = document.createElement('div');
    list.className = 'webradio-list';
    list.innerHTML = items.map(item => {
      const isPlaying = !!(Apollon.state.currentSong && Apollon.state.currentSong.is_radio && Apollon.state.currentSong.id === item.id);
      return `
        <div class="webradio-item${isPlaying ? ' now-playing' : ''}" data-webradio-id="${item.id}">
          <button class="webradio-play-btn" data-webradio-id="${item.id}" title="${Apollon.i18n.t('player.play')}">
            <i class="bi ${isPlaying ? 'bi-pause-fill' : 'bi-play-fill'}"></i>
          </button>
          <div class="webradio-info">
            <div class="webradio-name">${Apollon.utils.escapeHtml(item.name)}</div>
            <div class="webradio-url">${Apollon.utils.escapeHtml(item.url)}</div>
          </div>
          <button class="webradio-more-btn" data-webradio-id="${item.id}" data-name="${Apollon.utils.escapeAttr(item.name)}">
            <i class="bi bi-three-dots-vertical"></i>
          </button>
        </div>`;
    }).join('');
    Apollon.dom.contentArea.appendChild(list);
    Apollon.state.allContentloaded = true;
    this.hideLoader();
  },

  appendMovieCards(movies, grid) {
    if (!grid || !movies || movies.length === 0) return;
    const html = movies.map(movie => {
      const subtext = [movie.year ? String(movie.year) : '', movie.duration ? Apollon.utils.formatTime(movie.duration) : ''].filter(Boolean).join(' • ');
      const watched = movie.is_watched ? 1 : 0;
      const fav = movie.is_favorite ? 1 : 0;
      return `
        <div class="col">
          <div class="poster-card" data-movie-id="${movie.id}" data-is-watched="${watched}" data-is-favorite="${fav}">
            <div class="poster-thumb">
              <img src="?action=get_video_image&id=${movie.id}" alt="${Apollon.utils.escapeAttr(movie.title)}" loading="lazy">
              <div class="poster-play-overlay"><i class="bi bi-play-fill"></i></div>
              <button type="button" class="video-fav-toggle movie-fav-toggle" data-video-id="${movie.id}" data-is-favorite="${fav}" title="${Apollon.i18n.t(fav ? 'fav.remove' : 'fav.add')}" aria-label="${Apollon.i18n.t(fav ? 'fav.remove' : 'fav.add')}">
                <i class="bi ${fav ? 'bi-heart-fill' : 'bi-heart'}"></i>
              </button>
              <button type="button" class="watched-toggle movie-watched-toggle" data-video-id="${movie.id}" data-is-watched="${watched}" title="${Apollon.i18n.t(watched ? 'video.mark_unwatched' : 'video.mark_watched')}" aria-label="${Apollon.i18n.t(watched ? 'video.mark_unwatched' : 'video.mark_watched')}">
                <i class="bi ${watched ? 'bi-check-circle-fill' : 'bi-check-circle'}"></i>
              </button>
            </div>
            <div class="poster-info">
              <div class="poster-title text-truncate">${Apollon.utils.escapeHtml(movie.title)}</div>
              ${subtext ? `<div class="poster-subtext text-truncate">${subtext}</div>` : ''}
            </div>
          </div>
        </div>`;
    }).join('');
    grid.insertAdjacentHTML('beforeend', html);
  },

  renderMovies(movies, append = false) {
    if (!append) Apollon.dom.contentArea.innerHTML = '';
    if (!movies || movies.length === 0) {
      if (!append) {
        Apollon.dom.contentArea.innerHTML += '<div class="text-center p-5 text-secondary">' + Apollon.i18n.t('view.no_movies') + '</div>';
      }
      Apollon.state.allContentloaded = true;
      this.hideLoader();
      return;
    }

    let grid = Apollon.dom.contentArea.querySelector('.movies-grid');
    if (!grid) {
      grid = document.createElement('div');
      grid.className = 'row row-cols-2 row-cols-sm-3 row-cols-md-4 row-cols-lg-5 row-cols-xl-6 g-4 poster-grid movies-grid';
      Apollon.dom.contentArea.appendChild(grid);
    }
    this.appendMovieCards(movies, grid);
  },

  appendSeriesCards(seriesList, grid) {
    if (!grid || !seriesList || seriesList.length === 0) return;
    const html = seriesList.map(s => `
      <div class="col">
        <div class="poster-card" data-series="${Apollon.utils.escapeAttr(encodeURIComponent(s.series))}">
          <div class="poster-thumb">
            <img src="?action=get_video_image&id=${s.image_id || 0}" alt="${Apollon.utils.escapeAttr(s.series)}" loading="lazy">
            <div class="poster-play-overlay"><i class="bi bi-play-fill"></i></div>
          </div>
          <div class="poster-info">
            <div class="poster-title text-truncate">${Apollon.utils.escapeHtml(s.series)}</div>
            <div class="poster-subtext text-truncate">${Apollon.i18n.t('common.episodes', { count: s.episode_count })}</div>
          </div>
        </div>
      </div>`).join('');
    grid.insertAdjacentHTML('beforeend', html);
  },

  renderSeries(seriesList, append = false) {
    if (!append) Apollon.dom.contentArea.innerHTML = '';
    if (!seriesList || seriesList.length === 0) {
      if (!append) {
        Apollon.dom.contentArea.innerHTML += '<div class="text-center p-5 text-secondary">' + Apollon.i18n.t('view.no_series') + '</div>';
      }
      Apollon.state.allContentloaded = true;
      this.hideLoader();
      return;
    }

    let grid = Apollon.dom.contentArea.querySelector('.series-grid');
    if (!grid) {
      grid = document.createElement('div');
      grid.className = 'row row-cols-2 row-cols-sm-3 row-cols-md-4 row-cols-lg-5 row-cols-xl-6 g-4 poster-grid series-grid';
      Apollon.dom.contentArea.appendChild(grid);
    }
    this.appendSeriesCards(seriesList, grid);
  },

  renderEpisodes(episodes, append = false) {
    if (!append) Apollon.dom.contentArea.innerHTML = '';
    if (!episodes || episodes.length === 0) {
      if (!append) {
        Apollon.dom.contentArea.innerHTML += '<div class="text-center p-5 text-secondary">' + Apollon.i18n.t('view.no_episodes') + '</div>';
      }
      Apollon.state.allContentloaded = true;
      this.hideLoader();
      return;
    }

    let list = Apollon.dom.contentArea.querySelector('.episode-list');
    if (!list) {
      list = document.createElement('div');
      list.className = 'episode-list';
      Apollon.dom.contentArea.appendChild(list);
    }

    const html = episodes.map(ep => {
      const badge = ep.season > 0
        ? `S${String(ep.season).padStart(2, '0')}E${String(ep.episode).padStart(2, '0')}`
        : `E${String(ep.episode).padStart(2, '0')}`;
      const watched = ep.is_watched ? 1 : 0;
      const fav = ep.is_favorite ? 1 : 0;
      return `
        <div class="episode-item" data-video-id="${ep.id}" data-is-watched="${watched}" data-is-favorite="${fav}">
          <div class="episode-badge">${badge}</div>
          <div class="episode-title text-truncate${watched ? ' episode-watched' : ''}">${Apollon.utils.escapeHtml(ep.title)}</div>
          <div class="episode-duration d-none d-md-block">${Apollon.utils.formatTime(ep.duration)}</div>
          <button type="button" class="watched-toggle episode-watched-toggle" data-video-id="${ep.id}" data-is-watched="${watched}" title="${Apollon.i18n.t(watched ? 'video.mark_unwatched' : 'video.mark_watched')}" aria-label="${Apollon.i18n.t(watched ? 'video.mark_unwatched' : 'video.mark_watched')}">
            <i class="bi ${watched ? 'bi-check-circle-fill' : 'bi-check-circle'}"></i>
          </button>
          <button type="button" class="video-fav-toggle episode-fav-toggle" data-video-id="${ep.id}" data-is-favorite="${fav}" title="${Apollon.i18n.t(fav ? 'fav.remove' : 'fav.add')}" aria-label="${Apollon.i18n.t(fav ? 'fav.remove' : 'fav.add')}">
            <i class="bi ${fav ? 'bi-heart-fill' : 'bi-heart'}"></i>
          </button>
          <div class="episode-play"><i class="bi bi-play-fill"></i></div>
        </div>`;
    }).join('');
    list.insertAdjacentHTML('beforeend', html);
  },

  renderFavoritesTabs(active = 'songs') {
    const t = Apollon.i18n.t;
    return `
      <div class="p-3 d-flex gap-2 flex-wrap align-items-center">
        <div class="manage-tabs btn-group" role="group">
          <button type="button" class="btn btn-sm ${active === 'songs' ? 'btn-danger' : 'btn-outline-theme'} rounded-pill fav-tab" data-fav-tab="songs"><i class="bi bi-music-note-list"></i> ${t('fav.tab_songs')}</button>
          <button type="button" class="btn btn-sm ${active === 'videos' ? 'btn-danger' : 'btn-outline-theme'} rounded-pill fav-tab" data-fav-tab="videos"><i class="bi bi-film"></i> ${t('fav.tab_videos')}</button>
        </div>
      </div>`;
  },

  setFavToggleState(btn, isFav) {
    btn.dataset.isFavorite = isFav ? '1' : '0';
    const icon = btn.querySelector('.bi');
    if (icon) icon.className = isFav ? 'bi bi-heart-fill' : 'bi bi-heart';
    const label = Apollon.i18n.t(isFav ? 'fav.remove' : 'fav.add');
    btn.title = label;
    btn.setAttribute('aria-label', label);
  },

  setupSortOptions(viewType) {
    const t = Apollon.i18n.t;
    const sortConfigs = {
      'get_songs': { 'title_asc': t('sort.title'), 'artist_asc': t('sort.artist'), 'album_asc': t('sort.album'), 'year_desc': t('sort.year_newest'), 'id_desc': t('sort.recently_added') },
      'get_albums': { 'album_asc': t('sort.title_az'), 'album_desc': t('sort.title_za'), 'artist_asc': t('sort.artist'), 'year_desc': t('sort.year') },
      'get_artists': { 'name_asc': t('sort.name_az'), 'name_desc': t('sort.name_za'), 'song_count_desc': t('sort.most_songs') },
      'get_movies': { 'title_asc': t('sort.title_az'), 'title_desc': t('sort.title_za'), 'year_desc': t('sort.year_newest'), 'year_asc': t('sort.year'), 'id_desc': t('sort.recently_added') },
      'get_series': { 'name_asc': t('sort.name_az'), 'name_desc': t('sort.name_za'), 'episode_count_desc': t('sort.most_episodes') },
      'series_episodes': { 'episode_asc': t('sort.episode_asc'), 'episode_desc': t('sort.episode_desc'), 'title_asc': t('sort.title_az') },
      'get_favorites': { 'manual_order': t('sort.my_order'), 'title_asc': t('sort.title'), 'artist_asc': t('sort.artist') },
      'playlist_songs': { 'manual_order': t('sort.my_order'), 'title_asc': t('sort.title'), 'artist_asc': t('sort.artist') },
      'artist_songs': { 'title_asc': t('sort.title'), 'album_asc': t('sort.album'), 'year_desc': t('sort.year') },
      'album_songs': { 'title_asc': t('sort.track_title') }
    };

    const options = sortConfigs[viewType];
    if (options) {
      Apollon.dom.sortSelect.innerHTML = Object.entries(options)
        .map(([val, label]) => `<option value="${val}" ${Apollon.state.currentView.sort === val ? 'selected' : ''}>${label}</option>`)
        .join('');
      Apollon.dom.sortControls.classList.remove('d-none');
    } else {
      Apollon.dom.sortControls.classList.add('d-none');
    }
  },

  async loadMoreContent() {
    if (Apollon.state.isLoadingMore || Apollon.state.allContentloaded) return;
    Apollon.state.isLoadingMore = true;
    this.showLoader(false);

    Apollon.state.currentPage++;
    let data;
    const { type, param, sort } = Apollon.state.currentView;
    const params = new URLSearchParams({ page: Apollon.state.currentPage, sort });

    if (type === 'get_songs' || type === 'get_favorites') {
      data = await Apollon.utils.fetchData(`?action=${type}&${params.toString()}`);
      this.renderSongs(data, true);
    } else if (type === 'get_albums' || type === 'get_artists' || type === 'get_user_playlists') {
      data = await Apollon.utils.fetchData(`?action=${type}&${params.toString()}`);
      this.renderGrid(data, type, true);
    } else if (type === 'get_movies' || type === 'get_series') {
      data = await Apollon.utils.fetchData(`?action=${type}&${params.toString()}`);
      if (type === 'get_movies') {
        this.renderMovies(data, true);
      } else {
        this.renderSeries(data, true);
      }
    } else if (type === 'get_videos') {
      const pp = Apollon.state.videosPages;
      const loaded = Apollon.state.videosLoaded;
      if (!loaded.movies) {
        pp.movies++;
        const movies = await Apollon.utils.fetchData(`?action=get_movies&sort=title_asc&page=${pp.movies}`);
        this.appendMovieCards(movies, Apollon.dom.contentArea.querySelector('.movies-grid'));
        if (!movies || movies.length < Apollon.PAGE_SIZE) loaded.movies = true;
      } else if (!loaded.series) {
        pp.series++;
        const series = await Apollon.utils.fetchData(`?action=get_series&sort=name_asc&page=${pp.series}`);
        this.appendSeriesCards(series, Apollon.dom.contentArea.querySelector('.series-grid'));
        if (!series || series.length < Apollon.PAGE_SIZE) loaded.series = true;
      }
      if (loaded.movies && loaded.series) Apollon.state.allContentloaded = true;
      data = null;
    } else if (type === 'series_episodes') {
      params.append('series', decodeURIComponent(param));
      data = await Apollon.utils.fetchData(`?action=get_series_episodes&${params.toString()}`);
      this.renderEpisodes(data, true);
    } else if (type === 'playlist_songs') {
      params.append('public_id', decodeURIComponent(param));
      data = await Apollon.utils.fetchData(`?action=get_playlist_songs&${params.toString()}`);
      this.renderSongs(data, true);
    } else if (type === 'artist_songs' || type === 'album_songs') {
      const filterType = type.split('_')[0];
      params.append(filterType, decodeURIComponent(param));
      data = await Apollon.utils.fetchData(`?action=get_songs&${params.toString()}`);
      this.renderSongs(data, true);
    } else if (type === 'search') {
      params.delete('sort');
      params.append('q', param);
      data = await Apollon.utils.fetchData(`?action=search&${params.toString()}`);
      this.renderSongs(data, true);
    } else {
      Apollon.state.allContentloaded = true;
    }

    if (type !== 'get_videos' && (!data || data.length < Apollon.PAGE_SIZE)) {
      Apollon.state.allContentloaded = true;
    }
    Apollon.state.isLoadingMore = false;
    this.hideLoader();
  },

  updateActiveNavLink(viewType) {
    Apollon.dom.allNavLinks.forEach(l => l.classList.remove('active'));
    let active = document.querySelector(`.nav-link[data-view="${viewType}"]`);
    if (!active) {
      if (viewType === 'artist_songs') active = document.querySelector('.nav-link[data-view="get_artists"]');
      if (viewType === 'album_songs') active = document.querySelector('.nav-link[data-view="get_albums"]');
      if (viewType === 'playlist_songs') active = document.querySelector('.nav-link[data-view="get_user_playlists"]');
      if (viewType === 'series_episodes') active = document.querySelector('.nav-link[data-view="get_series"]');
      if (viewType === 'manage_videos') active = document.querySelector('.nav-link[data-view="manage_songs"]');
      if (viewType === 'favorite_videos') active = document.querySelector('.nav-link[data-view="get_favorites"]');
    }
    if (active) active.classList.add('active');
  },

  saveViewState(viewConfig) {
    try {
      localStorage.setItem(Apollon.VIEW_STORAGE_KEY, JSON.stringify(viewConfig));
    } catch (e) {}
  },

  parseViewFromHash() {
    if (!window.location.hash || window.location.hash.length <= 1) return null;
    const hashStr = window.location.hash.substring(1);
    const params = new URLSearchParams(hashStr);
    const type = params.get('view');
    if (type) {
      return {
        type: type,
        param: decodeURIComponent(params.get('param') || ''),
        sort: decodeURIComponent(params.get('sort') || 'title_asc')
      };
    }
    return null;
  },

  async loadView(viewConfig, pushToHistory = true) {
    Apollon.dom.mainContent.scrollTop = 0;
    Apollon.state.currentPage = 1;
    Apollon.state.allContentloaded = false;
    Apollon.state.isLoadingMore = false;
    this.showLoader();

    Apollon.state.currentView = viewConfig;

    if (pushToHistory) {
      const hash = `view=${encodeURIComponent(Apollon.state.currentView.type)}&param=${encodeURIComponent(Apollon.state.currentView.param || '')}&sort=${encodeURIComponent(Apollon.state.currentView.sort || '')}`;
      window.history.pushState(Apollon.state.currentView, '', `#${hash}`);
    }
    this.saveViewState(Apollon.state.currentView);

    this.updateActiveNavLink(Apollon.state.currentView.type);
    this.setupSortOptions(Apollon.state.currentView.type);

    let data;
    const params = new URLSearchParams({ sort: Apollon.state.currentView.sort, page: 1 });

    switch (Apollon.state.currentView.type) {
      case 'get_songs':
        this.updateContentTitle(Apollon.i18n.t('view.all_songs'));
        data = await Apollon.utils.fetchData(`?action=get_songs&${params.toString()}`);
        this.renderSongs(data, false);
        break;
      case 'get_favorites':
        this.updateContentTitle(Apollon.i18n.t('view.favorites'));
        data = await Apollon.utils.fetchData(`?action=get_favorites&${params.toString()}`);
        this.renderSongs(data, false);
        Apollon.dom.contentArea.insertAdjacentHTML('afterbegin', this.renderFavoritesTabs('songs'));
        break;
      case 'favorite_videos': {
        this.updateContentTitle(Apollon.i18n.t('view.favorites'));
        Apollon.dom.contentArea.innerHTML = this.renderFavoritesTabs('videos');
        const favData = await Apollon.utils.fetchData('?action=get_favorite_videos');
        const favMovies = (favData && favData.movies) || [];
        const favEpisodes = (favData && favData.episodes) || [];
        if (favMovies.length === 0 && favEpisodes.length === 0) {
          Apollon.dom.contentArea.insertAdjacentHTML('beforeend', '<div class="text-center p-5 text-secondary">' + Apollon.i18n.t('view.no_favorite_videos') + '</div>');
        } else {
          if (favMovies.length > 0) {
            Apollon.dom.contentArea.insertAdjacentHTML('beforeend', `<h2 class="videos-section-title">${Apollon.i18n.t('view.movies')}</h2>`);
            const moviesGrid = document.createElement('div');
            moviesGrid.className = 'row row-cols-2 row-cols-sm-3 row-cols-md-4 row-cols-lg-5 row-cols-xl-6 g-4 poster-grid movies-grid';
            Apollon.dom.contentArea.appendChild(moviesGrid);
            this.appendMovieCards(favMovies, moviesGrid);
          }
          if (favEpisodes.length > 0) {
            Apollon.dom.contentArea.insertAdjacentHTML('beforeend', `<h2 class="videos-section-title">${Apollon.i18n.t('view.series')}</h2>`);
            this.renderEpisodes(favEpisodes, true);
          }
        }
        Apollon.state.allContentloaded = true;
        break;
      }
      case 'get_albums':
        this.updateContentTitle(Apollon.i18n.t('view.albums'));
        data = await Apollon.utils.fetchData(`?action=get_albums&${params.toString()}`);
        this.renderGrid(data, 'get_albums', false);
        break;
      case 'get_artists':
        this.updateContentTitle(Apollon.i18n.t('view.artists'));
        data = await Apollon.utils.fetchData(`?action=get_artists&${params.toString()}`);
        this.renderGrid(data, 'get_artists', false);
        break;
      case 'get_user_playlists':
        this.updateContentTitle(Apollon.i18n.t('view.playlists'));
        data = await Apollon.utils.fetchData(`?action=get_user_playlists&${params.toString()}`);
        this.renderGrid(data, 'get_user_playlists', false);
        break;
      case 'get_webradios':
        this.updateContentTitle(Apollon.i18n.t('view.webradios'));
        data = await Apollon.utils.fetchData(`?action=get_webradios`);
        this.renderWebradios(data, false);
        break;
      case 'artist_songs':
      case 'album_songs':
      case 'playlist_songs': {
        const type = Apollon.state.currentView.type.split('_')[0];
        this.updateContentTitle('', false);
        const viewData = await Apollon.utils.fetchData(`?action=get_view_data&type=${type}&name=${Apollon.state.currentView.param}&sort=${Apollon.state.currentView.sort}&page=1`);
        Apollon.dom.contentArea.innerHTML = '';
        if (viewData && viewData.details) {
          this.renderViewDetailsHeader(viewData.details, type);
          this.renderSongs(viewData.songs, false);
          data = viewData.songs;
        }
        break;
      }
      case 'get_videos': {
        this.updateContentTitle(Apollon.i18n.t('view.videos'));
        Apollon.dom.contentArea.innerHTML = '';
        Apollon.state.videosPages = { movies: 1, series: 1 };
        Apollon.state.videosLoaded = { movies: false, series: false };

        const moviesGrid = document.createElement('div');
        moviesGrid.className = 'row row-cols-2 row-cols-sm-3 row-cols-md-4 row-cols-lg-5 row-cols-xl-6 g-4 poster-grid movies-grid';
        const seriesGrid = document.createElement('div');
        seriesGrid.className = 'row row-cols-2 row-cols-sm-3 row-cols-md-4 row-cols-lg-5 row-cols-xl-6 g-4 poster-grid series-grid';
        Apollon.dom.contentArea.insertAdjacentHTML('beforeend', `<h2 class="videos-section-title">${Apollon.i18n.t('view.movies')}</h2>`);
        Apollon.dom.contentArea.appendChild(moviesGrid);
        Apollon.dom.contentArea.insertAdjacentHTML('beforeend', `<h2 class="videos-section-title">${Apollon.i18n.t('view.series')}</h2>`);
        Apollon.dom.contentArea.appendChild(seriesGrid);

        const [movies, series] = await Promise.all([
          Apollon.utils.fetchData('?action=get_movies&sort=title_asc&page=1'),
          Apollon.utils.fetchData('?action=get_series&sort=name_asc&page=1')
        ]);
        this.appendMovieCards(movies, moviesGrid);
        this.appendSeriesCards(series, seriesGrid);
        const noMovies = !movies || movies.length === 0;
        const noSeries = !series || series.length === 0;
        if (noMovies && noSeries) {
          Apollon.dom.contentArea.insertAdjacentHTML('beforeend', '<div class="text-center p-5 text-secondary">' + Apollon.i18n.t('view.nothing_found') + '</div>');
        }
        if (!movies || movies.length < Apollon.PAGE_SIZE) Apollon.state.videosLoaded.movies = true;
        if (!series || series.length < Apollon.PAGE_SIZE) Apollon.state.videosLoaded.series = true;
        if (Apollon.state.videosLoaded.movies && Apollon.state.videosLoaded.series) {
          Apollon.state.allContentloaded = true;
        }
        break;
      }
      case 'get_movies':
        this.updateContentTitle(Apollon.i18n.t('view.movies'));
        data = await Apollon.utils.fetchData(`?action=get_movies&${params.toString()}`);
        this.renderMovies(data, false);
        break;
      case 'get_series':
        this.updateContentTitle(Apollon.i18n.t('view.series'));
        data = await Apollon.utils.fetchData(`?action=get_series&${params.toString()}`);
        this.renderSeries(data, false);
        break;
      case 'series_episodes': {
        const seriesName = decodeURIComponent(Apollon.state.currentView.param);
        this.updateContentTitle(seriesName);
        params.append('series', seriesName);
        data = await Apollon.utils.fetchData(`?action=get_series_episodes&${params.toString()}`);
        this.renderEpisodes(data, false);
        break;
      }
      case 'search':
        this.updateContentTitle(Apollon.i18n.t('search.title', { query: Apollon.state.currentView.param }));
        params.delete('sort');
        params.append('q', Apollon.state.currentView.param);
        data = await Apollon.utils.fetchData(`?action=search&${params.toString()}`);
        this.renderSongs(data, false);
        break;
      case 'manage_songs':
      case 'manage_videos':
        this.updateContentTitle(Apollon.i18n.t(Apollon.state.currentView.type === 'manage_videos' ? 'view.manage_videos' : 'view.manage_songs'));
        await this.loadManageView(Apollon.state.currentView.param, Apollon.state.currentView.type === 'manage_videos' ? 'videos' : 'music');
        break;
    }

    if (data && data.length < Apollon.PAGE_SIZE) {
      Apollon.state.allContentloaded = true;
    }

    if (viewConfig.highlight) {
      setTimeout(() => {
        const el = Apollon.dom.contentArea.querySelector(`.song-item[data-song-id="${viewConfig.highlight}"]`);
        if (el) {
          el.scrollIntoView({ behavior: 'smooth', block: 'center' });
          el.style.backgroundColor = 'rgba(108, 92, 255, 0.2)';
          setTimeout(() => el.style.backgroundColor = '', 2000);
        }
      }, 300);
    }
    this.hideLoader();
  },

  showShareModal(type, id, name) {
    const decoded = decodeURIComponent(name);
    const shareUrl = `${window.location.origin}${window.location.pathname}?share_type=${type}&id=${id}`;
    Apollon.dom.shareModalTitle.textContent = Apollon.i18n.t('share.item_title', { name: decoded });
    Apollon.dom.shareUrlInput.value = shareUrl;
    Apollon.dom.copyShareUrlBtn.textContent = Apollon.i18n.t('common.copy');
    Apollon.dom.shareModal.show();
  },

  async toggleFavorite(songId) {
    if (!Apollon.state.currentUser) {
      Apollon.utils.showToast(Apollon.i18n.t('fav.login_required'), 'error');
      return;
    }
    const res = await Apollon.utils.fetchData('?action=toggle_favorite', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ id: songId })
    });
    if (res) {
      if (Apollon.state.currentSong && Apollon.state.currentSong.id === songId) {
        Apollon.state.currentSong.is_favorite = res.is_favorite ? 1 : 0;
      }
      const songEls = document.querySelectorAll(`.song-item[data-song-id="${songId}"]`);
      if (Apollon.state.currentView.type === 'get_favorites' && !res.is_favorite) {
        songEls.forEach(el => el.remove());
      } else {
        songEls.forEach(el => el.dataset.isFavorite = res.is_favorite ? "1" : "0");
      }
      Apollon.utils.showToast(res.status === 'added' ? Apollon.i18n.t('fav.added') : Apollon.i18n.t('fav.removed'), 'success');
    }
  },

  async toggleVideoWatched(videoId, watched, btn) {
    if (!Apollon.state.currentUser) {
      Apollon.utils.showToast(Apollon.i18n.t('video.login_required'), 'error');
      return;
    }
    const prev = btn.dataset.isWatched === '1';
    this.setWatchedToggleState(btn, watched);
    const res = await Apollon.utils.fetchData('?action=set_video_watched', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ id: videoId, watched: watched ? 1 : 0 })
    });
    if (res && res.status === 'success') {
      const parent = btn.closest('.poster-card, .episode-item');
      if (parent) {
        parent.dataset.isWatched = watched ? '1' : '0';
        const title = parent.querySelector('.episode-title');
        if (title) title.classList.toggle('episode-watched', watched);
      }
      const label = Apollon.i18n.t(watched ? 'video.mark_unwatched' : 'video.mark_watched');
      btn.title = label;
      btn.setAttribute('aria-label', label);
    } else {
      this.setWatchedToggleState(btn, prev);
    }
  },

  setWatchedToggleState(btn, watched) {
    btn.dataset.isWatched = watched ? '1' : '0';
    const icon = btn.querySelector('.bi');
    if (icon) icon.className = watched ? 'bi bi-check-circle-fill' : 'bi bi-check-circle';
  },

  async toggleVideoFavorite(videoId, btn) {
    if (!Apollon.state.currentUser) {
      Apollon.utils.showToast(Apollon.i18n.t('fav.login_required'), 'error');
      return;
    }
    const prev = btn.dataset.isFavorite === '1';
    this.setFavToggleState(btn, !prev);
    const res = await Apollon.utils.fetchData('?action=toggle_video_favorite', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ id: videoId })
    });
    if (res) {
      const isFav = res.is_favorite ? 1 : 0;
      const parent = btn.closest('.poster-card, .episode-item');
      if (parent) parent.dataset.isFavorite = isFav;
      if (Apollon.state.currentView.type === 'favorite_videos' && !res.is_favorite) {
        const col = btn.closest('.col');
        const ep = btn.closest('.episode-item');
        if (col) col.remove();
        else if (ep) ep.remove();
        const moviesGrid = Apollon.dom.contentArea.querySelector('.movies-grid');
        const episodeList = Apollon.dom.contentArea.querySelector('.episode-list');
        const hasMovies = moviesGrid && moviesGrid.querySelector('.poster-card');
        const hasEpisodes = episodeList && episodeList.querySelector('.episode-item');
        if (moviesGrid && !hasMovies) {
          const title = moviesGrid.previousElementSibling;
          moviesGrid.remove();
          if (title && title.classList.contains('videos-section-title')) title.remove();
        }
        if (episodeList && !hasEpisodes) {
          const title = episodeList.previousElementSibling;
          episodeList.remove();
          if (title && title.classList.contains('videos-section-title')) title.remove();
        }
        if (!hasMovies && !hasEpisodes) {
          Apollon.dom.contentArea.insertAdjacentHTML('beforeend', '<div class="text-center p-5 text-secondary">' + Apollon.i18n.t('view.no_favorite_videos') + '</div>');
        }
      } else {
        this.setFavToggleState(btn, isFav === 1);
      }
      Apollon.utils.showToast(res.status === 'added' ? Apollon.i18n.t('fav.added') : Apollon.i18n.t('fav.removed'), 'success');
    } else {
      this.setFavToggleState(btn, prev);
    }
  },

  positionContextMenu(buttonEl) {
    const rect = buttonEl.getBoundingClientRect();
    const menuWidth = 220;
    let x = rect.right - menuWidth;
    let y = rect.bottom + 5;
    if (x < 10) x = 10;
    if (y + 200 > window.innerHeight) y = rect.top - 200;
    Apollon.dom.contextMenu.style.left = `${x}px`;
    Apollon.dom.contextMenu.style.top = `${y}px`;
  },

  buildAndShowSongContextMenu(btn, data) {
    Apollon.state.contextMenuItemEl = btn;
    const { id, title, artist, album, is_favorite } = data;
    let items = `
      <li class="context-menu-item text-truncate" data-action="share_song" data-id="${id}" data-name="${Apollon.utils.escapeAttr(encodeURIComponent(title))}"><i class="bi bi-share-fill"></i> ${Apollon.i18n.t('ctx.share')}</li>
      <li class="context-menu-item text-truncate" data-action="go_artist" data-name="${Apollon.utils.escapeAttr(encodeURIComponent(artist))}"><i class="bi bi-person-fill"></i> ${Apollon.i18n.t('ctx.go_artist')}</li>
      <li class="context-menu-item text-truncate" data-action="go_album" data-name="${Apollon.utils.escapeAttr(encodeURIComponent(album))}"><i class="bi bi-disc-fill"></i> ${Apollon.i18n.t('ctx.go_album')}</li>
      <li class="context-menu-item text-truncate" data-action="show_metadata" data-id="${id}"><i class="bi bi-info-circle"></i> ${Apollon.i18n.t('common.info')}</li>`;

    if (Apollon.state.currentUser) {
      const favText = is_favorite ? Apollon.i18n.t('fav.remove') : Apollon.i18n.t('fav.add');
      const favIcon = is_favorite ? Apollon.ICONS.heartFill : Apollon.ICONS.heart;
      items += `
        <li class="context-menu-item text-truncate" data-action="toggle_favorite" data-id="${id}">${favIcon} ${favText}</li>
        <li class="context-menu-item text-truncate" data-action="add_to_playlist" data-id="${id}"><i class="bi bi-plus-lg"></i> ${Apollon.i18n.t('playlist.add_to')}</li>`;
      if (Apollon.state.currentView.type === 'playlist_songs') {
        items += `<li class="context-menu-item text-danger text-truncate" data-action="remove_from_playlist" data-id="${id}"><i class="bi bi-x-circle"></i> ${Apollon.i18n.t('playlist.remove_from')}</li>`;
      }
    }
    items += `<li class="context-menu-item" data-action="close_menu"><i class="bi bi-x-lg"></i> ${Apollon.i18n.t('common.close')}</li>`;
    Apollon.dom.contextMenu.innerHTML = items;
    Apollon.dom.contextMenu.style.display = 'block';
    this.positionContextMenu(btn);
  },

  buildAndShowPlaylistContextMenu(btn, data) {
    Apollon.state.contextMenuItemEl = btn;
    const { publicId, name } = data;
    Apollon.dom.contextMenu.innerHTML = `
      <li class="context-menu-item text-truncate" data-action="edit_playlist" data-public-id="${Apollon.utils.escapeAttr(publicId)}" data-name="${Apollon.utils.escapeAttr(name)}"><i class="bi bi-pencil"></i> ${Apollon.i18n.t('common.edit')}</li>
      <li class="context-menu-item text-truncate" data-action="export_playlist" data-public-id="${Apollon.utils.escapeAttr(publicId)}"><i class="bi bi-box-arrow-up"></i> ${Apollon.i18n.t('common.export')}</li>
      <li class="context-menu-item text-danger text-truncate" data-action="delete_playlist" data-public-id="${Apollon.utils.escapeAttr(publicId)}"><i class="bi bi-trash"></i> ${Apollon.i18n.t('common.delete')}</li>
      <li class="context-menu-item" data-action="close_menu"><i class="bi bi-x-lg"></i> ${Apollon.i18n.t('common.close')}</li>`;
    Apollon.dom.contextMenu.style.display = 'block';
    this.positionContextMenu(btn);
  },

  buildAndShowWebradioContextMenu(btn, data) {
    Apollon.state.contextMenuItemEl = btn;
    const { id, name } = data;
    Apollon.dom.contextMenu.innerHTML = `
      <li class="context-menu-item text-truncate" data-action="edit_webradio" data-id="${id}" data-name="${Apollon.utils.escapeAttr(name)}"><i class="bi bi-pencil"></i> ${Apollon.i18n.t('common.edit')}</li>
      <li class="context-menu-item text-danger text-truncate" data-action="delete_webradio" data-id="${id}" data-name="${Apollon.utils.escapeAttr(name)}"><i class="bi bi-trash"></i> ${Apollon.i18n.t('common.delete')}</li>
      <li class="context-menu-item" data-action="close_menu"><i class="bi bi-x-lg"></i> ${Apollon.i18n.t('common.close')}</li>`;
    Apollon.dom.contextMenu.style.display = 'block';
    this.positionContextMenu(btn);
  },

  buildAndShowManageContextMenu(btn, data) {
    Apollon.state.contextMenuItemEl = btn;
    const { kind, path, name } = data;
    let items = '';
    if (kind === 'folder') {
      items += `<li class="context-menu-item text-truncate" data-action="manage_open" data-path="${path}"><i class="bi bi-folder2-open"></i> ${Apollon.i18n.t('common.open')}</li>`;
    }
    items += `
      <li class="context-menu-item text-truncate" data-action="manage_rename" data-path="${path}" data-name="${name}"><i class="bi bi-pencil"></i> ${Apollon.i18n.t('common.rename')}</li>
      <li class="context-menu-item text-danger text-truncate" data-action="manage_delete" data-path="${path}" data-name="${name}"><i class="bi bi-trash"></i> ${Apollon.i18n.t('common.delete')}</li>
      <li class="context-menu-item" data-action="close_menu"><i class="bi bi-x-lg"></i> ${Apollon.i18n.t('common.close')}</li>`;
    Apollon.dom.contextMenu.innerHTML = items;
    Apollon.dom.contextMenu.style.display = 'block';
    this.positionContextMenu(btn);
  },

  async loadManageView(path, dir = 'music') {
    const data = await Apollon.utils.fetchData(`?action=get_manage_list&path=${encodeURIComponent(path || '')}&dir=${dir}`);
    if (!data || data.status !== 'success') {
      this.hideLoader();
      return;
    }
    this.renderManageView(data, dir);
  },

  renderManageView(data, dir = 'music') {
    Apollon.dom.contentArea.innerHTML = '';

    const crumbs = [{ name: Apollon.i18n.t('view.library'), path: '' }];
    if (data.path) {
      data.path.split('/').forEach((part, i, arr) => {
        crumbs.push({ name: part, path: arr.slice(0, i + 1).join('/') });
      });
    }
    const crumbsHtml = crumbs.map((crumb, i) =>
      `<span class="manage-crumb${i === crumbs.length - 1 ? ' active' : ''}" data-path="${Apollon.utils.escapeAttr(encodeURIComponent(crumb.path))}">${Apollon.utils.escapeHtml(crumb.name)}</span>` +
      (i < crumbs.length - 1 ? '<i class="bi bi-chevron-right manage-crumb-sep"></i>' : '')
    ).join('');

    const tabs = `
      <div class="manage-tabs btn-group" role="group">
        <button type="button" class="btn btn-sm ${dir === 'music' ? 'btn-danger' : 'btn-outline-theme'} rounded-pill manage-tab" data-dir="music"><i class="bi bi-music-note-list"></i> ${Apollon.i18n.t('manage.tab_music')}</button>
        <button type="button" class="btn btn-sm ${dir === 'videos' ? 'btn-danger' : 'btn-outline-theme'} rounded-pill manage-tab" data-dir="videos"><i class="bi bi-film"></i> ${Apollon.i18n.t('manage.tab_videos')}</button>
      </div>`;

    const toolbar = `
      <div class="p-3 d-flex gap-2 flex-wrap align-items-center">
        ${tabs}
        <button class="btn btn-danger rounded-pill px-3" id="new-folder-btn"><i class="bi bi-folder-plus"></i> ${Apollon.i18n.t('manage.new_folder')}</button>
        <button class="btn btn-outline-theme rounded-pill px-3" id="upload-files-btn"><i class="bi bi-cloud-arrow-up"></i> ${Apollon.i18n.t('manage.upload_files')}</button>
        <span class="text-secondary small ms-auto">${Apollon.i18n.t('common.folders_files', { folders: data.folders.length, files: data.files.length })}</span>
      </div>`;

    const rows = [
      ...data.folders.map(folder => this.manageRow('folder', folder)),
      ...data.files.map(file => this.manageRow('file', file)),
    ].join('');

    const listHtml = rows
      ? `<div class="manage-list">
          <div class="manage-item manage-item-header d-none d-md-grid"><div></div><div>${Apollon.i18n.t('common.name')}</div><div class="manage-size">${Apollon.i18n.t('common.size')}</div><div class="manage-modified">${Apollon.i18n.t('common.modified')}</div><div></div></div>
          ${rows}
        </div>`
      : '<div class="text-center p-5 text-secondary">' + Apollon.i18n.t('view.empty_folder') + '</div>';

    Apollon.dom.contentArea.insertAdjacentHTML('beforeend', `<div class="manage-breadcrumbs">${crumbsHtml}</div>` + toolbar + listHtml);
    this.hideLoader();
  },

  manageRow(kind, item) {
    const icon = kind === 'folder'
      ? '<i class="bi bi-folder-fill manage-icon"></i>'
      : '<i class="bi bi-file-earmark-music manage-icon"></i>';
    const size = kind === 'file' ? Apollon.utils.formatSize(item.size) : '&mdash;';
    const modified = (kind === 'file' && item.modified) ? new Date(item.modified * 1000).toLocaleDateString(Apollon.i18n.locale()) : '&mdash;';
    return `
      <div class="manage-item ${kind === 'folder' ? 'manage-folder' : ''}" data-path="${Apollon.utils.escapeAttr(encodeURIComponent(item.path))}">
        <div>${icon}</div>
        <div class="manage-name text-truncate" title="${Apollon.utils.escapeAttr(item.name)}">${Apollon.utils.escapeHtml(item.name)}</div>
        <div class="manage-size text-truncate">${size}</div>
        <div class="manage-modified text-truncate d-none d-md-block">${modified}</div>
        <div class="manage-more">
          <button class="manage-more-btn" data-kind="${kind}" data-path="${Apollon.utils.escapeAttr(encodeURIComponent(item.path))}" data-name="${Apollon.utils.escapeAttr(encodeURIComponent(item.name))}">
            <i class="bi bi-three-dots-vertical"></i>
          </button>
        </div>
      </div>`;
  },

  async createManageFolder() {
    const name = prompt(Apollon.i18n.t('manage.new_folder_prompt'));
    if (!name || !name.trim()) return;
    const parent = Apollon.state.currentView.param || '';
    const dir = Apollon.state.currentView.type === 'manage_videos' ? 'videos' : 'music';
    const res = await Apollon.utils.fetchData('?action=create_folder', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ path: parent ? parent + '/' + name.trim() : name.trim(), dir })
    });
    if (res && res.status === 'success') {
      Apollon.utils.showToast(Apollon.i18n.t('manage.folder_created'), 'success');
      Apollon.views.loadView(Apollon.state.currentView, false);
    }
  },

  async renameManageItem(path, name) {
    const newName = prompt(Apollon.i18n.t('manage.rename_prompt'), name);
    if (!newName || !newName.trim() || newName.trim() === name) return;
    const dir = Apollon.state.currentView.type === 'manage_videos' ? 'videos' : 'music';
    const res = await Apollon.utils.fetchData('?action=rename_item', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ path, new_name: newName.trim(), dir })
    });
    if (res && res.status === 'success') {
      Apollon.utils.showToast(Apollon.i18n.t('manage.renamed'), 'success');
      Apollon.views.loadView(Apollon.state.currentView, false);
    }
  },

  async deleteManageItem(path, name) {
    if (!confirm(Apollon.i18n.t('manage.delete_confirm', { name }))) return;
    const dir = Apollon.state.currentView.type === 'manage_videos' ? 'videos' : 'music';
    const res = await Apollon.utils.fetchData('?action=delete_item', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ path, dir })
    });
    if (res && res.status === 'success') {
      Apollon.utils.showToast(Apollon.i18n.t('manage.deleted'), 'success');
      Apollon.views.loadView(Apollon.state.currentView, false);
    }
  },

  /**
   * Upload a file in 1 MB chunks (start -> chunks -> finish).
   * Returns the finish response, or null on failure.
   */
  async uploadFileChunked(file, path, dir = 'music') {
    const rawId = (window.crypto && crypto.randomUUID)
      ? crypto.randomUUID()
      : 'u' + Date.now().toString(36) + Math.random().toString(36).slice(2);
    const id = rawId.replace(/[^A-Za-z0-9_-]/g, '').slice(0, 64);
    const totalChunks = Math.max(1, Math.ceil(file.size / Apollon.UPLOAD_CHUNK_SIZE));

    const start = await Apollon.utils.fetchData('?action=upload_chunk', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ op: 'start', id, name: file.name, size: file.size, path, dir })
    });
    if (!start || start.status !== 'success') return null;

    for (let i = 0; i < totalChunks; i++) {
      const chunk = file.slice(i * Apollon.UPLOAD_CHUNK_SIZE, (i + 1) * Apollon.UPLOAD_CHUNK_SIZE);
      const res = await Apollon.utils.fetchData('?action=upload_chunk', {
        method: 'POST',
        headers: { 'X-Upload-Id': id, 'X-Upload-Dir': dir },
        body: await chunk.arrayBuffer()
      });
      if (!res || res.status !== 'success') return null;
    }

    const finish = await Apollon.utils.fetchData('?action=upload_chunk', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ op: 'finish', id, name: file.name, size: file.size, path, dir })
    });
    return (finish && finish.status === 'success') ? finish : null;
  },

  hideContextMenu() {
    Apollon.dom.contextMenu.style.display = 'none';
  },

  runSearch(q) {
    if (q.trim()) this.loadView({ type: 'search', param: q.trim(), sort: 'title_asc' }, true);
  },
};
