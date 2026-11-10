// Apollon Media - video player (movies & series episodes).
'use strict';

Apollon.videoPlayer = {
  video: null,
  modal: null,
  modalEl: null,
  isShown: false,
  queue: [],
  queueIndex: 0,
  current: null,
  previousVolume: 1,
  lastPositionSent: -10,
  resumePosition: 0,

  init() {
    this.video = document.getElementById('video-element');
    this.modal = new bootstrap.Modal(document.getElementById('video-modal'));

    this.video.addEventListener('timeupdate', () => {
      if (!isFinite(this.video.duration)) return;
      const progress = (this.video.currentTime / this.video.duration) * 100;
      document.getElementById('video-progress-bar').style.width = `${progress}%`;
      document.getElementById('video-current-time').textContent = Apollon.utils.formatTime(this.video.currentTime);
      this.trackWatch();
    });

    this.video.addEventListener('loadedmetadata', () => {
      document.getElementById('video-duration').textContent = Apollon.utils.formatTime(this.video.duration);
      if (this.resumePosition > 0 && isFinite(this.video.duration) && this.resumePosition < this.video.duration) {
        this.video.currentTime = this.resumePosition;
        this.lastPositionSent = this.resumePosition;
        Apollon.utils.showToast(Apollon.i18n.t('video.resume_at', { time: Apollon.utils.formatTime(this.resumePosition) }), 'info');
      }
      this.resumePosition = 0;
    });

    this.video.addEventListener('ended', () => {
      if (this.current && Apollon.state.currentUser && !this.current.is_watched) {
        this.current.is_watched = 1;
        this.sendWatchState(1, Math.floor(this.video.duration || 0));
      }
      if (this.queueIndex < this.queue.length - 1) {
        this.playNext();
      }
    });

    this.video.addEventListener('play', () => this.updatePlayPauseIcon());
    this.video.addEventListener('pause', () => this.updatePlayPauseIcon());

    this.video.addEventListener('error', () => {
      const err = this.video.error;
      if (err && err.code === MediaError.MEDIA_ERR_SRC_NOT_SUPPORTED) {
        Apollon.utils.showToast(Apollon.i18n.t('video.format_error'), 'error');
      }
    });

    document.getElementById('video-play-pause-btn').addEventListener('click', () => this.togglePlayPause());
    document.getElementById('video-prev-btn').addEventListener('click', () => this.playPrev());
    document.getElementById('video-next-btn').addEventListener('click', () => this.playNext());
    document.getElementById('video-close-btn').addEventListener('click', () => this.close());

    document.getElementById('video-progress-container').addEventListener('click', e => {
      if (!this.video.duration || !isFinite(this.video.duration)) return;
      const rect = e.currentTarget.getBoundingClientRect();
      const percent = Math.max(0, Math.min(1, (e.clientX - rect.left) / rect.width));
      this.video.currentTime = percent * this.video.duration;
    });

    const volumeSlider = document.getElementById('video-volume-slider');
    volumeSlider.addEventListener('input', () => {
      this.video.volume = parseFloat(volumeSlider.value);
      this.video.muted = this.video.volume === 0;
      this.updateVolumeIcon();
    });
    document.getElementById('video-volume-btn').addEventListener('click', () => {
      if (this.video.muted || this.video.volume === 0) {
        this.video.muted = false;
        this.video.volume = this.previousVolume || 0.5;
      } else {
        this.previousVolume = this.video.volume;
        this.video.muted = true;
      }
      volumeSlider.value = this.video.muted ? 0 : this.video.volume;
      this.updateVolumeIcon();
    });

    const stage = document.getElementById('video-stage');
    document.getElementById('video-fullscreen-btn').addEventListener('click', () => {
      if (document.fullscreenElement) {
        document.exitFullscreen();
      } else if (stage.requestFullscreen) {
        stage.requestFullscreen();
      }
    });
    document.addEventListener('fullscreenchange', () => {
      const icon = document.querySelector('#video-fullscreen-btn .bi');
      if (icon) icon.className = document.fullscreenElement ? 'bi bi-fullscreen-exit' : 'bi bi-fullscreen';
    });

    // Keyboard shortcuts while the modal is open.
    document.addEventListener('keydown', e => {
      if (!this.isShown) return;
      if (e.target.tagName === 'INPUT') return;
      switch (e.key) {
        case ' ':
        case 'k':
          e.preventDefault();
          this.togglePlayPause();
          break;
        case 'ArrowLeft':
          this.video.currentTime = Math.max(0, this.video.currentTime - 10);
          break;
        case 'ArrowRight':
          this.video.currentTime = Math.min(this.video.duration || 0, this.video.currentTime + 10);
          break;
        case 'ArrowUp':
          e.preventDefault();
          this.video.volume = Math.min(1, this.video.volume + 0.1);
          document.getElementById('video-volume-slider').value = this.video.volume;
          this.updateVolumeIcon();
          break;
        case 'ArrowDown':
          e.preventDefault();
          this.video.volume = Math.max(0, this.video.volume - 0.1);
          document.getElementById('video-volume-slider').value = this.video.volume;
          this.updateVolumeIcon();
          break;
        case 'f':
          document.getElementById('video-fullscreen-btn').click();
          break;
        case 'j':
          this.playPrev();
          break;
        case 'l':
          this.playNext();
          break;
        case 'Escape':
          this.close();
          break;
      }
    });

    this.modalEl = document.getElementById('video-modal');
    this.modalEl.addEventListener('shown.bs.modal', () => { this.isShown = true; });
    this.modalEl.addEventListener('hidden.bs.modal', () => {
      this.isShown = false;
      this.savePositionNow();
      this.video.pause();
      this.video.removeAttribute('src');
      this.video.load();
    });
  },

  /**
   * Play a video by id. When a queue (array of video ids) is given,
   * next/previous navigate through it (used for series episodes).
   */
  async playVideo(videoId, queue = null) {
    if (!Apollon.state.currentUser && !Apollon.config.guestListen) {
      Apollon.utils.showToast(Apollon.i18n.t('video.login_required'), 'error');
      return;
    }
    if (queue && queue.length > 0) {
      this.queue = queue;
      this.queueIndex = queue.indexOf(videoId);
      if (this.queueIndex === -1) this.queueIndex = 0;
    } else {
      this.queue = [videoId];
      this.queueIndex = 0;
    }
    await this.loadCurrent();
    if (!this.current) return;
    this.modal.show();
  },

  async loadCurrent() {
    const id = this.queue[this.queueIndex];
    if (!id) return;
    const data = await Apollon.utils.fetchData(`?action=get_video_data&id=${id}`);
    if (!data) return;
    this.current = data;
    this.lastPositionSent = -10;
    this.resumePosition = (typeof data.position === 'number' && data.position > 0) ? data.position : 0;

    document.getElementById('video-title').textContent = data.title;
    const subtitle = [];
    if (data.type === 'episode' && data.series) {
      subtitle.push(data.series);
      subtitle.push(Apollon.i18n.t('video.episode', { s: data.season, e: data.episode }));
    } else if (data.year) {
      subtitle.push(String(data.year));
    }
    document.getElementById('video-subtitle').textContent = subtitle.join(' • ');

    if (data.transcode && data.transcode.needed && data.transcode.state !== 'ready') {
      this.startTranscodeFlow(data.id);
      return;
    }
    this.playSource(data.stream_url);
  },

  /**
   * Persist playback progress: save the position (throttled to ~10s) and
   * auto-mark the video as watched once >= 90% has been played.
   */
  trackWatch() {
    if (!this.current || !Apollon.state.currentUser) return;
    const dur = this.video.duration;
    const cur = this.video.currentTime;
    if (!isFinite(dur) || dur <= 0) return;

    if (cur - this.lastPositionSent >= 10) {
      this.lastPositionSent = cur;
      this.sendWatchState(null, Math.floor(cur));
    }

    if (!this.current.is_watched && cur / dur >= 0.9) {
      this.current.is_watched = 1;
      this.sendWatchState(1, Math.floor(cur));
    }
  },

  savePositionNow() {
    if (!this.current || !Apollon.state.currentUser) return;
    const cur = this.video.currentTime;
    if (!isFinite(cur) || cur <= 0) return;
    this.lastPositionSent = cur;
    this.sendWatchState(null, Math.floor(cur));
  },

  /**
   * Persist watch state. `watched` may be null to mean "leave the watched
   * flag unchanged" (used for position-only progress saves); `position` may
   * be null to mean "leave the stored position unchanged".
   */
  async sendWatchState(watched, position) {
    if (!this.current) return null;
    const setWatched = watched !== null && watched !== undefined;
    const body = { id: this.current.id };
    if (setWatched) body.watched = watched ? 1 : 0;
    if (position != null) body.position = position;
    const res = await Apollon.utils.fetchData('?action=set_video_watched', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify(body)
    });
    if (res && res.status === 'success' && setWatched) {
      this.current.is_watched = watched ? 1 : 0;
    }
    return res;
  },

  playSource(url) {
    this.hideTranscodeOverlay();
    this.video.src = url;
    this.video.load();
    this.video.play().catch(e => this.handlePlayError(e));
  },

  handlePlayError(e) {
    if (e && e.name === 'NotSupportedError') {
      Apollon.utils.showToast(Apollon.i18n.t('video.format_error'), 'error');
    } else {
      console.error('Video playback error:', e);
    }
  },

  /**
   * Kick off the background transcode (if not running) and poll until the
   * cached MP4 is ready, then start playback.
   */
  startTranscodeFlow(videoId) {
    this.showTranscodeOverlay(0);

    const poll = async () => {
      if (!this.isShown || !this.current || this.current.id !== videoId) return;
      const data = await Apollon.utils.fetchData(`?action=get_video_data&id=${videoId}`);
      if (!data || !data.transcode) return;
      if (data.transcode.state === 'ready') {
        this.playSource(data.stream_url);
        return;
      }
      if (data.transcode.state === 'error') {
        this.hideTranscodeOverlay();
        Apollon.utils.showToast(Apollon.i18n.t('video.transcode_failed'), 'error');
        return;
      }
      this.showTranscodeOverlay(data.transcode.progress || 0);
      setTimeout(poll, 3000);
    };

    Apollon.utils.fetchData(`?action=transcode_video&id=${videoId}`)
      .then(() => setTimeout(poll, 1500))
      .catch(() => this.hideTranscodeOverlay());
  },

  showTranscodeOverlay(progress) {
    const overlay = document.getElementById('video-transcode-overlay');
    const label = document.getElementById('video-transcode-label');
    if (!overlay || !label) return;
    const percent = Math.floor(progress * 100);
    label.textContent = Apollon.i18n.t('video.transcoding', { progress: percent });
    overlay.classList.remove('d-none');
  },

  hideTranscodeOverlay() {
    const overlay = document.getElementById('video-transcode-overlay');
    if (overlay) overlay.classList.add('d-none');
  },

  togglePlayPause() {
    if (!this.current) return;
    if (this.video.paused) {
      this.video.play().catch(e => console.error('Video playback error:', e));
    } else {
      this.video.pause();
    }
  },

  playNext() {
    if (this.queueIndex < this.queue.length - 1) {
      this.queueIndex++;
      this.loadCurrent();
    }
  },

  playPrev() {
    if (this.video.currentTime > 3) {
      this.video.currentTime = 0;
      return;
    }
    if (this.queueIndex > 0) {
      this.queueIndex--;
      this.loadCurrent();
    } else {
      this.video.currentTime = 0;
    }
  },

  close() {
    this.modal.hide();
  },

  updatePlayPauseIcon() {
    const icon = document.querySelector('#video-play-pause-btn .bi');
    if (icon) icon.className = this.video.paused ? 'bi bi-play-fill' : 'bi bi-pause-fill';
  },

  updateVolumeIcon() {
    const icon = document.querySelector('#video-volume-btn .bi');
    if (!icon) return;
    if (this.video.muted || this.video.volume === 0) {
      icon.className = 'bi bi-volume-mute-fill';
    } else if (this.video.volume < 0.5) {
      icon.className = 'bi bi-volume-down-fill';
    } else {
      icon.className = 'bi bi-volume-up-fill';
    }
  }
};
