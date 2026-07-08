/**
 * TakePOS Custom UI Script
 * Matches the layout and interactions from takepos_redesign_final.html
 */
(function() {
  'use strict';

  /* ============================================================
     CLOCK
     ============================================================ */
  function updateClock() {
    var now = new Date();
    var h = String(now.getHours()).padStart(2, '0');
    var m = String(now.getMinutes()).padStart(2, '0');
    var clockEl = document.getElementById('takepos-clock');
    if (clockEl) clockEl.textContent = h + ':' + m;
    var dateEl = document.getElementById('takepos-date');
    if (dateEl) {
      var d  = String(now.getDate()).padStart(2, '0');
      var mo = String(now.getMonth() + 1).padStart(2, '0');
      var y  = now.getFullYear();
      dateEl.textContent = d + '/' + mo + '/' + y;
    }
  }
  updateClock();
  setInterval(updateClock, 30000);

  /* ============================================================
     SHORTCUTS DRAWER
     ============================================================ */
  function initShortcutsDrawer() {
    var launcher = document.getElementById('shortcuts-launcher');
    var drawer   = document.getElementById('shortcuts-drawer');
    var overlay  = document.getElementById('shortcuts-overlay');
    var closeBtn = document.getElementById('shortcuts-close');
    var searchIn = document.getElementById('shortcuts-search-input');

    if (!launcher || !drawer) return;

    function openDrawer() {
      drawer.classList.add('open');
      if (overlay) overlay.classList.add('open');
    }
    function closeDrawer() {
      drawer.classList.remove('open');
      if (overlay) overlay.classList.remove('open');
    }

    launcher.addEventListener('click', openDrawer);
    if (closeBtn) closeBtn.addEventListener('click', closeDrawer);
    if (overlay)  overlay.addEventListener('click', closeDrawer);

    // Search filter
    if (searchIn) {
      searchIn.addEventListener('input', function() {
        var q = searchIn.value.trim().toLowerCase();
        document.querySelectorAll('.shortcut-section').forEach(function(section) {
          var links = section.querySelectorAll('.shortcut-link');
          var visible = 0;
          links.forEach(function(link) {
            var txt  = (link.querySelector('.text') || link).textContent.toLowerCase();
            var show = !q || txt.includes(q);
            link.style.display = show ? '' : 'none';
            if (show) visible++;
          });
          if (q) {
            section.classList.remove('collapsed');
            section.style.display = visible > 0 ? '' : 'none';
          } else {
            section.style.display = '';
          }
        });
      });
    }

    // Keyboard shortcut Ctrl+K
    document.addEventListener('keydown', function(e) {
      if ((e.ctrlKey || e.metaKey) && e.key === 'k') {
        e.preventDefault();
        drawer.classList.contains('open') ? closeDrawer() : openDrawer();
      }
      if (e.key === 'Escape') closeDrawer();
    });
  }

  /* ============================================================
     KEYPAD TOGGLE
     ============================================================ */
  function initKeypad() {
    var toggle  = document.getElementById('keypad-toggle');
    var keypad  = document.getElementById('keypad');
    var closeKp = document.getElementById('keypad-close');

    if (!toggle || !keypad) return;

    toggle.addEventListener('click', function() {
      keypad.classList.add('visible');
      toggle.style.display = 'none';
    });
    if (closeKp) {
      closeKp.addEventListener('click', function() {
        keypad.classList.remove('visible');
        toggle.style.display = '';
      });
    }
  }

  /* ============================================================
     VIEW TOGGLE (Grid / List)
     ============================================================ */
  function initViewToggle() {
    var gridBtn = document.getElementById('grid-view-btn');
    var listBtn = document.getElementById('list-view-btn');
    var grid    = document.getElementById('div_products_list') || document.getElementById('products-grid');

    if (!gridBtn || !listBtn || !grid) return;

    gridBtn.addEventListener('click', function() {
      gridBtn.classList.add('active');
      listBtn.classList.remove('active');
      grid.classList.remove('list-view');
    });
    listBtn.addEventListener('click', function() {
      listBtn.classList.add('active');
      gridBtn.classList.remove('active');
      grid.classList.add('list-view');
    });
  }

  /* ============================================================
     FULLSCREEN
     ============================================================ */
  function initFullscreen() {
    var btn = document.getElementById('fullscreen-btn');
    if (!btn) return;
    btn.addEventListener('click', function() {
      if (!document.fullscreenElement) {
        document.documentElement.requestFullscreen && document.documentElement.requestFullscreen();
      } else {
        document.exitFullscreen && document.exitFullscreen();
      }
    });
  }

  /* ============================================================
     TOAST HELPER (global)
     ============================================================ */
  var toastTimer;
  window.showTakePOSToast = function(msg, type) {
    var toast = document.getElementById('takepos-toast');
    if (!toast) {
      toast = document.createElement('div');
      toast.id = 'takepos-toast';
      toast.className = 'toast';
      toast.innerHTML = '<div class="icon-circle">✓</div><span id="takepos-toast-msg"></span>';
      document.body.appendChild(toast);
    }
    var msgEl = document.getElementById('takepos-toast-msg');
    if (msgEl) msgEl.textContent = msg;
    toast.classList.add('show');
    clearTimeout(toastTimer);
    toastTimer = setTimeout(function() { toast.classList.remove('show'); }, 2200);
  };

  /* ============================================================
     INIT
     ============================================================ */
  document.addEventListener('DOMContentLoaded', function() {
    initShortcutsDrawer();
    initKeypad();
    initViewToggle();
    initFullscreen();
  });

})();
