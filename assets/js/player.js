// Apollon Media - audio player (queue, shuffle, repeat, media session).
'use strict';

Apollon.ICONS = {
  play: '<i class="bi bi-play-fill"></i>',
  pause: '<i class="bi bi-pause-fill"></i>',
  repeat: '<i class="bi bi-repeat"></i>',
  repeatOne: '<i class="bi bi-repeat-1"></i>',
  shuffle: '<i class="bi bi-shuffle"></i>',
  prev: '<i class="bi bi-skip-start-fill"></i>',
  next: '<i class="bi bi-skip-end-fill"></i>',
  heart: '<i class="bi bi-heart"></i>',
  heartFill: '<i class="bi bi-heart-fill"></i>',
  volumeUp: '<i class="bi bi-volume-up-fill"></i>',
  volumeDown: '<i class="bi bi-volume-down-fill"></i>',
  volumeMute: '<i class="bi bi-volume-mute-fill"></i>',
  soundwave: `<svg class="playing-icon soundwave-svg" viewBox="0 0 100 100"><rect width="100" height="100" rx="24" fill="#0d0d0d"/><rect x="13" y="38" width="7.5" height="24" rx="3.75" fill="#ffffff"/><rect x="27" y="25" width="7.5" height="50" rx="3.75" fill="#6C5CFF"/><rect x="41" y="13" width="7.5" height="74" rx="3.75" fill="#ffffff"/><rect x="55" y="25" width="7.5" height="50" rx="3.75" fill="#ffffff"/><rect x="69" y="38" width="7.5" height="24" rx="3.75" fill="#ffffff"/><rect x="83" y="25" width="7.5" height="50" rx="3.75" fill="#ffffff"/></svg>`
};

Apollon.player = {
  audio: new Audio(),

  init() {
    this.audio.preload = 'metadata';

    this.audio.addEventListener('timeupdate', () => {
      if (!isFinite(this.audio.duration)) return;
      const progress = (this.audio.currentTime / this.audio.duration) * 100;
      Apollon.dom.playerElements.progress.forEach(p => { if (p) p.style.width = `${progress}%`; });
      Apollon.dom.playerElements.currentTime.forEach(c => { if (c) c.textContent = Apollon.utils.formatTime(this.audio.currentTime); });
      Apollon.dom.playerElements.timeLeft.forEach(t => { if (t) t.textContent = '-' + Apollon.utils.formatTime(this.audio.duration - this.audio.currentTime); });
    });

    this.audio.addEventListener('ended', () => {
      if (Apollon.state.repeatMode === 'one') {
        this.audio.play();
      } else {
        this.playNext();
      }
    });

    this.audio.addEventListener('volumechange', () => {
      if (!this.audio.muted) Apollon.state.previousVolume = this.audio.volume;
      const isMuted = this.audio.muted || this.audio.volume === 0;
      Apollon.dom.playerElements.volumeBtn.innerHTML = isMuted ? Apollon.ICONS.volumeMute : (this.audio.volume < 0.5 ? Apollon.ICONS.volumeDown : Apollon.ICONS.volumeUp);
    });

    Apollon.dom.playerElements.progressContainer.forEach(container => {
      container?.addEventListener('click', e => {
        if (!this.audio.duration || !isFinite(this.audio.duration)) return;
        const rect = container.getBoundingClientRect();
        const percent = Math.max(0, Math.min(1, (e.clientX - rect.left) / rect.width));
        this.audio.currentTime = percent * this.audio.duration;
      });
    });
  },

  async playSongById(songId) {
    const sharedSong = Apollon.config.sharedSong;
    const isSharedSong = !!(sharedSong && sharedSong.id === songId);
    if (!Apollon.state.currentUser && !Apollon.config.guestListen && !isSharedSong) {
      Apollon.utils.showToast(Apollon.i18n.t('player.login_required'), 'error');
      return;
    }
    const song = await Apollon.utils.fetchData(`?action=get_song_data&id=${songId}`);
    if (!song) return;
    Apollon.state.currentSong = song;

    // For a shared song (guest, guest listening disabled) the stream URL must
    // carry the signed token so the server authorizes this single track.
    let streamUrl = song.stream_url;
    if (isSharedSong) {
      streamUrl += '&share=' + encodeURIComponent(sharedSong.token);
    }

    const cachedBlob = await Apollon.opfs.getBlob(`song_${song.id}.audio`);
    if (cachedBlob) {
      this.audio.src = URL.createObjectURL(cachedBlob);
    } else {
      this.audio.src = streamUrl;
      this.audio.addEventListener('playing', () => {
        fetch(streamUrl)
          .then(r => r.ok ? r.blob() : null)
          .then(blob => { if (blob) Apollon.opfs.saveBlob(`song_${song.id}.audio`, blob); })
          .catch(() => {});
      }, { once: true });
    }

    this.audio.load();
    this.audio.play().catch(e => console.error("Audio playback error:", e));
    Apollon.state.isPlaying = true;
    this.updateUI();

    if ('mediaSession' in navigator) {
      navigator.mediaSession.metadata = new MediaMetadata({
        title: song.title,
        artist: song.artist,
        album: song.album,
        artwork: [{ src: song.image_url, sizes: '500x500', type: 'image/webp' }]
      });
      navigator.mediaSession.setActionHandler('play', () => this.togglePlayPause());
      navigator.mediaSession.setActionHandler('pause', () => this.togglePlayPause());
      navigator.mediaSession.setActionHandler('previoustrack', () => this.playPrev());
      navigator.mediaSession.setActionHandler('nexttrack', () => this.playNext());
    }
  },

  playWebradio(webradio) {
    if (!Apollon.state.currentUser) {
      Apollon.utils.showToast(Apollon.i18n.t('player.login_required'), 'error');
      return;
    }
    Apollon.state.currentSong = {
      id: webradio.id,
      title: webradio.name,
      artist: Apollon.i18n.t('webradio.live'),
      album: '',
      is_radio: true,
      radio_url: webradio.url
    };
    Apollon.state.queue = [];
    Apollon.state.originalQueue = [];
    Apollon.state.queueIndex = -1;
    // Stream through the server proxy: the raw URL sits behind a tokenized
    // redirect + cookie that browsers block for cross-origin media requests.
    this.audio.src = `?action=stream_webradio&id=${webradio.id}`;
    this.audio.load();
    this.audio.play().catch(e => console.error('Webradio playback error:', e));
    Apollon.state.isPlaying = true;
    this.updateUI();
    if ('mediaSession' in navigator) {
      navigator.mediaSession.metadata = new MediaMetadata({
        title: webradio.name,
        artist: Apollon.i18n.t('webradio.live'),
        artwork: []
      });
      navigator.mediaSession.setActionHandler('play', () => this.togglePlayPause());
      navigator.mediaSession.setActionHandler('pause', () => this.togglePlayPause());
    }
  },

  updateUI() {
    const song = Apollon.state.currentSong;
    if (!song) return;
    if (Apollon.dom.playerBar.classList.contains('d-none')) {
      Apollon.dom.playerBar.classList.remove('d-none');
      document.body.classList.add('player-visible');
    }
    const imgUrl = song.is_radio ? '?action=get_app_icon' : `?action=get_image&id=${song.id}`;
    Apollon.dom.playerElements.art.forEach(el => { if (el) el.src = imgUrl; });
    Apollon.dom.playerElements.title.forEach(el => { if (el) el.textContent = song.title; });
    Apollon.dom.playerElements.artist.forEach(el => { if (el) el.textContent = song.artist; });
    document.title = song.is_radio ? `${song.title} • ${Apollon.i18n.t('webradio.live')}` : `${song.title} • ${song.artist}`;

    this.updatePlayPauseIcons();
    document.querySelectorAll('.song-item.now-playing').forEach(el => el.classList.remove('now-playing'));
    document.querySelectorAll(`.song-item[data-song-id="${song.id}"]`).forEach(el => el.classList.add('now-playing'));
    if (song.is_radio) {
      Apollon.dom.playerElements.currentTime.forEach(c => { if (c) c.textContent = Apollon.i18n.t('webradio.live'); });
      Apollon.dom.playerElements.timeLeft.forEach(t => { if (t) t.textContent = ''; });
      Apollon.dom.playerElements.progress.forEach(p => { if (p) p.style.width = '0%'; });
      document.querySelectorAll('.webradio-item.now-playing').forEach(el => el.classList.remove('now-playing'));
      document.querySelectorAll(`.webradio-item[data-webradio-id="${song.id}"]`).forEach(el => el.classList.add('now-playing'));
    }
  },

  updatePlayPauseIcons() {
    const icon = Apollon.state.isPlaying ? Apollon.ICONS.pause : Apollon.ICONS.play;
    Apollon.dom.playerElements.playPauseBtn.forEach(btn => {
      if (btn) {
        btn.innerHTML = icon;
        btn.title = Apollon.state.isPlaying ? Apollon.i18n.t('player.pause') : Apollon.i18n.t('player.play');
      }
    });
    if ('mediaSession' in navigator) {
      navigator.mediaSession.playbackState = Apollon.state.isPlaying ? "playing" : "paused";
    }
  },

  updateRepeatIcons() {
    let icon = Apollon.ICONS.repeat, title = Apollon.i18n.t('player.repeat_off');
    Apollon.dom.playerElements.repeatBtn.forEach(btn => btn?.classList.remove('active'));
    if (Apollon.state.repeatMode === 'one') {
      icon = Apollon.ICONS.repeatOne; title = Apollon.i18n.t('player.repeat_one');
      Apollon.dom.playerElements.repeatBtn.forEach(btn => btn?.classList.add('active'));
    } else if (Apollon.state.repeatMode === 'all') {
      title = Apollon.i18n.t('player.repeat_all');
      Apollon.dom.playerElements.repeatBtn.forEach(btn => btn?.classList.add('active'));
    }
    Apollon.dom.playerElements.repeatBtn.forEach(btn => { if (btn) { btn.innerHTML = icon; btn.title = title; } });
  },

  updateShuffleButtons() {
    Apollon.dom.playerElements.shuffleBtn.forEach(btn => {
      if (btn) {
        btn.classList.toggle('active', Apollon.state.isShuffle);
        btn.title = Apollon.state.isShuffle ? Apollon.i18n.t('player.shuffle_on') : Apollon.i18n.t('player.shuffle_off');
      }
    });
  },

  togglePlayPause() {
    if (!Apollon.state.currentSong) return;
    Apollon.state.isPlaying = !Apollon.state.isPlaying;
    Apollon.state.isPlaying ? this.audio.play() : this.audio.pause();
    this.updatePlayPauseIcons();
  },

  playNext() {
    if (Apollon.state.queue.length === 0) return;
    Apollon.state.queueIndex++;
    if (Apollon.state.queueIndex >= Apollon.state.queue.length) {
      if (Apollon.state.repeatMode === 'all') {
        Apollon.state.queueIndex = 0;
      } else {
        Apollon.state.isPlaying = false;
        this.audio.pause();
        this.updatePlayPauseIcons();
        Apollon.state.queueIndex = Apollon.state.queue.length - 1;
        return;
      }
    }
    this.playSongById(Apollon.state.queue[Apollon.state.queueIndex]);
  },

  playPrev() {
    if (Apollon.state.queue.length === 0) return;
    if (this.audio.currentTime > 3) {
      this.audio.currentTime = 0;
      return;
    }
    if (Apollon.state.queueIndex <= 0) {
      if (Apollon.state.repeatMode === 'all') {
        Apollon.state.queueIndex = Apollon.state.queue.length - 1;
      } else {
        this.audio.currentTime = 0;
        return;
      }
    } else {
      Apollon.state.queueIndex--;
    }
    this.playSongById(Apollon.state.queue[Apollon.state.queueIndex]);
  },

  toggleShuffle() {
    Apollon.state.isShuffle = !Apollon.state.isShuffle;
    if (Apollon.state.queue.length > 0 && Apollon.state.currentSong) {
      const currentId = Apollon.state.currentSong.id;
      if (Apollon.state.isShuffle) {
        Apollon.state.queue = [...Apollon.state.originalQueue];
        for (let i = Apollon.state.queue.length - 1; i > 0; i--) {
          const j = Math.floor(Math.random() * (i + 1));
          [Apollon.state.queue[i], Apollon.state.queue[j]] = [Apollon.state.queue[j], Apollon.state.queue[i]];
        }
        const idx = Apollon.state.queue.findIndex(id => id === currentId);
        if (idx > -1) {
          [Apollon.state.queue[0], Apollon.state.queue[idx]] = [Apollon.state.queue[idx], Apollon.state.queue[0]];
        }
      } else {
        Apollon.state.queue = [...Apollon.state.originalQueue];
      }
      Apollon.state.queueIndex = Apollon.state.queue.findIndex(id => id === currentId);
    }
    this.updateShuffleButtons();
  },

  async setQueueAndPlay(startId) {
    if (!Apollon.state.currentUser && !Apollon.config.guestListen) {
      Apollon.utils.showToast(Apollon.i18n.t('player.login_required'), 'error');
      return;
    }
    const allIds = await Apollon.utils.fetchData('?action=get_view_ids', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({
        view_type: Apollon.state.currentView.type,
        param: Apollon.state.currentView.param,
        sort: Apollon.state.currentView.sort
      })
    });
    if (!allIds || allIds.length === 0) return;
    Apollon.state.originalQueue = allIds;
    Apollon.state.queue = [...Apollon.state.originalQueue];
    if (Apollon.state.isShuffle) {
      Apollon.state.isShuffle = false;
      this.toggleShuffle();
    }
    Apollon.state.queueIndex = Apollon.state.queue.findIndex(id => id === startId);
    if (Apollon.state.queueIndex > -1) {
      this.playSongById(startId);
    }
  },
};
