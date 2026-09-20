/**
 * Elite Cuts — Zero-dependency Web Audio API synthesizer
 */
(() => {
  'use strict';
  let ctx = null;
  function getCtx() {
    if (!ctx) ctx = new (window.AudioContext || window.webkitAudioContext)();
    if (ctx.state === 'suspended') ctx.resume();
    return ctx;
  }
  function playTone(freq, duration, type = 'sine', gainValue = 0.15, attack = 0.01) {
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
    } catch (e) {}
  }
  window.EliteAudio = {
    click() { playTone(800, 0.06, 'sine', 0.08, 0.005); setTimeout(() => playTone(1200, 0.04, 'sine', 0.05), 30); },
    success() { playTone(523.25, 0.12); setTimeout(() => playTone(659.25, 0.12), 100); setTimeout(() => playTone(783.99, 0.18), 200); },
    ticketCalled() { playTone(587.33, 0.18, 'triangle', 0.18); setTimeout(() => playTone(880.0, 0.28, 'triangle', 0.16), 160); },
    error() { playTone(220, 0.15, 'sawtooth', 0.08); setTimeout(() => playTone(180, 0.2, 'sawtooth', 0.06), 120); },
    select() { playTone(640, 0.05, 'sine', 0.06); },
    ding() { playTone(1046.5, 0.1, 'sine', 0.1); }
  };
})();
