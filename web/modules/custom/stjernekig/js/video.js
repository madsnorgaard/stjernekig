/**
 * @file
 * Video days: GIF-like playback for short local clips, click-to-play embeds.
 */

(function (Drupal, once) {
  'use strict';

  const reducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)');
  const saveData = Boolean(navigator.connection && navigator.connection.saveData);

  /**
   * Native player for self-hosted clips.
   *
   * Clips shorter than data-loop-max seconds loop. Clips the server marked
   * as small enough (data-autoplay) also start silently while in view and
   * pause when scrolled away, unless the person asked for reduced motion or
   * is saving data. Unmuting hands control back to them.
   */
  function enhancePlayer(video) {
    const loopMax = Number(video.dataset.loopMax || 0);
    video.addEventListener('loadedmetadata', () => {
      if (loopMax > 0 && video.duration > 0 && video.duration <= loopMax) {
        video.loop = true;
      }
    }, { once: true });

    if (!('autoplay' in video.dataset) || reducedMotion.matches || saveData || !('IntersectionObserver' in window)) {
      return;
    }
    video.muted = true;
    video.classList.add('is-ambient');
    const observer = new IntersectionObserver((entries) => {
      entries.forEach((entry) => {
        if (entry.isIntersecting) {
          video.play().catch(() => {});
        }
        else if (!video.paused) {
          video.pause();
        }
      });
    }, { threshold: 0.5 });
    observer.observe(video);
    video.addEventListener('volumechange', () => {
      if (!video.muted) {
        observer.disconnect();
        video.classList.remove('is-ambient');
      }
    });
  }

  /**
   * Replace the facade with the provider's iframe on first click.
   */
  function enhanceFacade(wrapper) {
    const facade = wrapper.querySelector('.apod-video__facade');
    if (!facade || !wrapper.dataset.embed) {
      return;
    }
    facade.addEventListener('click', (event) => {
      // Let modified clicks open the watch page in a new tab as usual.
      if (event.metaKey || event.ctrlKey || event.shiftKey || event.button !== 0) {
        return;
      }
      event.preventDefault();
      const iframe = document.createElement('iframe');
      iframe.src = wrapper.dataset.embed;
      iframe.title = wrapper.dataset.title || '';
      iframe.className = 'apod-video__frame';
      iframe.setAttribute('allow', 'autoplay; encrypted-media; picture-in-picture; fullscreen');
      iframe.setAttribute('allowfullscreen', '');
      iframe.setAttribute('referrerpolicy', 'strict-origin-when-cross-origin');
      wrapper.replaceChildren(iframe);
      wrapper.classList.add('is-playing');
      iframe.focus();
    });
  }

  Drupal.behaviors.stjernekigVideo = {
    attach(context) {
      once('apod-video-player', '.apod-video__player', context).forEach(enhancePlayer);
      once('apod-video-facade', '.apod-video--embed', context).forEach(enhanceFacade);
    },
  };
})(Drupal, once);
