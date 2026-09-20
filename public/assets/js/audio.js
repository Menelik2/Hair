/**
 * Elite Cuts — Zero-dependency Web Audio API synthesizer
 * Tactile UI sounds for a premium barbershop experience.
 */
(() => {
  'use strict';

  let ctx = null;

  function getCtx() {
    if (!ctx) {
      ctx = new (window.AudioContext || window.webkitAudioContext)();
    }
    if (ctx.state === 'suspended') {
      ctx.resume();
    }
    return ctx;
  }

  function playTone(freq, duration, type = 'sine', gainValue = 0.15, attack = 0.01, decay = 0.08) {
    try {
      const ac = getCtx();
      const osc = ac.createOscillator();
      const gain = ac.createGain();

      osc.type = type;
      osc.frequency.setValueAtTime(freq, ac.currentTime);

      gain.gain.setValueAtTime(0, ac.currentTime);
      gain.gain.linearRampToValueAtTime(gainValue, ac.currentTime + attack);
      gain.gain.exponentialRampToValueAtTime(0.001, ac.currentTime + duration);

      osc.connect(gain);
      gain.connect(ac.destination);

      osc.start(ac.currentTime);
      osc.stop(ac.currentTime + duration + 0.05);
    } catch (e) {
      // Silent fail if audio is blocked
    }
  }

  // Public API
  window.EliteAudio = {
    /** Soft tactile click for buttons */
    click() {
      playTone(800, 0.06, 'sine', 0.08, 0.005, 0.04);
      setTimeout(() => playTone(1200, 0.04, 'sine', 0.05), 30);
    },

    /** Success / ticket created */
    success() {
      playTone(523.25, 0.12, 'sine', 0.12); // C5
      setTimeout(() => playTone(659.25, 0.12, 'sine', 0.12), 100); // E5
      setTimeout(() => playTone(783.99, 0.18, 'sine', 0.1), 200); // G5
    },

    /** Ticket called / turn ready — 2-tone melodic chime */
    ticketCalled() {
      playTone(587.33, 0.18, 'triangle', 0.18); // D5
      setTimeout(() => playTone(880.0, 0.28, 'triangle', 0.16), 160); // A5
    },

    /** Soft error / cancel */
    error() {
      playTone(220, 0.15, 'sawtooth', 0.08);
      setTimeout(() => playTone(180, 0.2, 'sawtooth', 0.06), 120);
    },

    /** Subtle hover / selection */
    select() {
      playTone(640, 0.05, 'sine', 0.06);
    },

    /** Ding for queue progress */
    ding() {
      playTone(1046.5, 0.1, 'sine', 0.1);
    }
  };
})();
