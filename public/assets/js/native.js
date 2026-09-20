(function () {
  window.nativeHaptic = function (style) {
    try {
      if (navigator.vibrate) {
        if (style === 'light') navigator.vibrate(8);
        else if (style === 'medium') navigator.vibrate(15);
        else if (style === 'success') navigator.vibrate([10, 30, 10]);
        else navigator.vibrate(10);
      }
    } catch (_) {}
  };

  document.addEventListener('DOMContentLoaded', function () {
    var path = location.pathname.replace(/\/$/, '') || '/queue.php';
    document.querySelectorAll('[data-tab]').forEach(function (el) {
      var href = el.getAttribute('href') || '';
      if (path.indexOf(href.replace('.php', '')) !== -1 || path === href) {
        el.classList.add('tab-active');
      }
    });

    document.querySelectorAll('button, [role="button"], a.ios-btn').forEach(function (el) {
      el.addEventListener('click', function () {
        window.nativeHaptic('light');
      }, { passive: true });
    });
  });
})();
