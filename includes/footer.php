      </div><!-- /.vp-stack -->

      <!-- Print-only footer (hidden on screen, shown on paper). -->
      <div class="vp-print-foot" aria-hidden="true">
        Confidential — contains private medical information.
        <?= e(defined('CLINIC_NAME') ? CLINIC_NAME : 'Paw Prints Veterinary Clinic') ?>.
      </div>
    </div><!-- /.vp-content -->

    <!-- Back-to-top: .vp-content is its own scroll container (the page
         itself doesn't scroll), so this listens to and scrolls that
         element rather than the window. -->
    <button type="button" class="vp-scroll-top" id="vpScrollTop" aria-label="Back to top" hidden>
      <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 19V5M5 12l7-7 7 7"/></svg>
    </button>
  </main>
</div><!-- /.vp-root -->

<!-- Sidebar hamburger. Desktop: collapse to the icon rail and back (remembered
     per browser). Phones / tablets (the sidebar is already the icon rail):
     open the full menu over the page; tap outside or press Esc to close. -->
<script>
(function () {
  var btn = document.getElementById('vpMenuBtn'), root = document.documentElement;
  if (!btn) return;
  var small = window.matchMedia('(max-width: 840px)');
  function sync() {
    var expanded = small.matches ? root.classList.contains('vp-side-open') : !root.classList.contains('vp-side-collapsed');
    btn.setAttribute('aria-expanded', expanded ? 'true' : 'false');
    var label = small.matches ? (expanded ? 'Close menu' : 'Open menu') : (expanded ? 'Collapse menu' : 'Expand menu');
    btn.setAttribute('aria-label', label); btn.title = label;
  }
  btn.addEventListener('click', function (e) {
    e.stopPropagation();
    if (small.matches) {
      root.classList.toggle('vp-side-open');
    } else {
      var c = root.classList.toggle('vp-side-collapsed');
      try { localStorage.setItem('pp-side', c ? 'collapsed' : 'open'); } catch (err) {}
    }
    sync();
  });
  document.addEventListener('click', function (e) {
    if (root.classList.contains('vp-side-open') && !e.target.closest('#vpSide')) { root.classList.remove('vp-side-open'); sync(); }
  });
  document.addEventListener('keydown', function (e) {
    if (e.key === 'Escape' && root.classList.contains('vp-side-open')) { root.classList.remove('vp-side-open'); sync(); btn.focus(); }
  });
  (small.addEventListener ? small.addEventListener('change', function () { root.classList.remove('vp-side-open'); sync(); }) : small.addListener(sync));
  sync();
})();
</script>
<!-- Color theme switcher. -->
<script>
(function () {
  var switches = document.querySelectorAll('.vp-theme-switch');
  if (!switches.length) return;
  var current = document.documentElement.getAttribute('data-theme') || 'red';

  function apply(theme) {
    if (theme === 'red') document.documentElement.removeAttribute('data-theme');
    else document.documentElement.setAttribute('data-theme', theme);
    try { localStorage.setItem('pp-theme', theme); } catch (e) {}
    switches.forEach(function (group) {
      group.querySelectorAll('.vp-theme-dot').forEach(function (dot) {
        dot.setAttribute('aria-pressed', dot.dataset.themeChoice === theme ? 'true' : 'false');
      });
    });
  }

  switches.forEach(function (group) {
    var trigger = group.querySelector('.vp-theme-trigger');
    var panel = group.querySelector('.vp-theme-panel');

    group.querySelectorAll('.vp-theme-dot').forEach(function (dot) {
      dot.setAttribute('aria-pressed', dot.dataset.themeChoice === current ? 'true' : 'false');
      dot.addEventListener('click', function () {
        apply(dot.dataset.themeChoice);
        if (panel) panel.hidden = true;
        if (trigger) { trigger.setAttribute('aria-expanded', 'false'); trigger.focus(); }
      });
    });

    if (!trigger || !panel) return;
    function isOpen() { return !panel.hidden; }
    function open() { panel.hidden = false; trigger.setAttribute('aria-expanded', 'true'); }
    function close() { panel.hidden = true; trigger.setAttribute('aria-expanded', 'false'); }

    trigger.addEventListener('click', function (ev) {
      ev.stopPropagation();
      isOpen() ? close() : open();
    });
    document.addEventListener('click', function (ev) {
      if (isOpen() && !panel.contains(ev.target) && !trigger.contains(ev.target)) close();
    });
    document.addEventListener('keydown', function (ev) {
      if (ev.key === 'Escape' && isOpen()) { close(); trigger.focus(); }
    });
  });
})();
</script>

<!-- Back-to-top button behavior. -->
<script>
(function () {
  var content = document.querySelector('.vp-content');
  var btn = document.getElementById('vpScrollTop');
  if (!content || !btn) return;

  function onScroll() {
    var show = content.scrollTop > 400;
    if (show) btn.hidden = false;
    // Let the fade/slide transition finish before actually removing it
    // from layout when scrolling back up past the threshold.
    requestAnimationFrame(function () { btn.classList.toggle('show', show); });
    if (!show) {
      clearTimeout(btn._hideTimer);
      btn._hideTimer = setTimeout(function () { if (!btn.classList.contains('show')) btn.hidden = true; }, 200);
    }
  }

  content.addEventListener('scroll', onScroll, { passive: true });
  onScroll();

  btn.addEventListener('click', function () {
    content.scrollTo({ top: 0, behavior: 'smooth' });
  });
})();
</script>

<!-- Stamp the exact print time into the letterhead when printing. -->
<script>
(function () {
  var el = document.getElementById('vpPrintDate');
  if (!el) return;
  function stamp() {
    try {
      el.textContent = new Date().toLocaleString(undefined, {
        month: 'short', day: 'numeric', year: 'numeric',
        hour: 'numeric', minute: '2-digit'
      });
    } catch (e) { /* keep the server-rendered fallback */ }
  }
  window.addEventListener('beforeprint', stamp);
  if (window.matchMedia) {
    var mq = window.matchMedia('print');
    (mq.addEventListener ? mq.addEventListener('change', function (e) { if (e.matches) stamp(); })
                        : mq.addListener(function (e) { if (e.matches) stamp(); }));
  }
})();
</script>

<?php /* A form-bound message renders inside its form, not as a toast. */ ?>
<?php if ($flash && !$flashTarget): ?>
<div class="vp-toast" id="vp-toast" role="status">
  <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 11.1V12a10 10 0 1 1-5.9-9.1"/><path d="M22 4 12 14.01l-3-3"/></svg>
  <?= e($flash) ?>
</div>
<script>
  // Auto-dismiss the toast after a few seconds.
  setTimeout(function () {
    var t = document.getElementById('vp-toast');
    if (t) { t.style.transition = 'opacity .3s'; t.style.opacity = '0'; setTimeout(function(){ t.remove(); }, 320); }
  }, 2600);
</script>
<?php endif; ?>

<!-- In-app confirmation dialog. Replaces window.confirm(), which the
     browser labels "localhost says" and styles itself. -->
<div class="vp-confirm-overlay" id="vpConfirm" hidden>
  <div class="vp-confirm-box" role="alertdialog" aria-modal="true"
       aria-labelledby="vpConfirmTitle" aria-describedby="vpConfirmMsg">
    <div class="vp-confirm-icon" id="vpConfirmIcon">
      <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 9v4M12 17h.01M10.3 3.9 1.8 18a2 2 0 0 0 1.7 3h17a2 2 0 0 0 1.7-3L13.7 3.9a2 2 0 0 0-3.4 0z"/></svg>
    </div>
    <h3 id="vpConfirmTitle">Please confirm</h3>
    <p id="vpConfirmMsg"></p>
    <div class="vp-confirm-actions">
      <button type="button" class="vp-btn-ghost" id="vpConfirmNo">Cancel</button>
      <button type="button" class="vp-btn-danger" id="vpConfirmYes">Confirm</button>
    </div>
  </div>
</div>

<script>
  // Confirm before any destructive action, using the in-app dialog
  // above rather than the browser's own confirm() box.
  (function () {
    var overlay = document.getElementById('vpConfirm');
    var msgEl   = document.getElementById('vpConfirmMsg');
    var yesBtn  = document.getElementById('vpConfirmYes');
    var noBtn   = document.getElementById('vpConfirmNo');
    var pending = null;          // the form waiting on an answer
    var lastFocus = null;

    function close() {
      overlay.hidden = true;
      pending = null;
      if (lastFocus) { try { lastFocus.focus(); } catch (e) {} }
    }

    var titleEl = document.getElementById('vpConfirmTitle');
    var iconEl  = document.getElementById('vpConfirmIcon');
    var WARN_ICON = iconEl.innerHTML;
    var OK_ICON = '<svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M20 6 9 17l-5-5"/></svg>';

    function open(form, message) {
      pending = form;
      lastFocus = document.activeElement;
      msgEl.textContent = message;
      // Forms can opt out of the default "destructive" look with
      // data-confirm-tone="ok" (a green check + primary button), and
      // customise the heading / button text.
      var ok = form.getAttribute('data-confirm-tone') === 'ok';
      titleEl.textContent = form.getAttribute('data-confirm-title') || 'Please confirm';
      yesBtn.textContent  = form.getAttribute('data-confirm-yes') || 'Confirm';
      yesBtn.className    = ok ? 'vp-btn-primary' : 'vp-btn-danger';
      iconEl.classList.toggle('ok', ok);
      iconEl.innerHTML    = ok ? OK_ICON : WARN_ICON;
      overlay.hidden = false;
      yesBtn.focus();
    }

    yesBtn.addEventListener('click', function () {
      var form = pending;
      close();
      if (form && form.tagName === 'A') { window.location.href = form.href; return; }   // a link (e.g. Sign out)
      if (form) {
        // Mark it so the submit handler lets this one through.
        form.dataset.confirmed = '1';
        if (typeof form.requestSubmit === 'function') form.requestSubmit();
        else form.submit();
      }
    });
    noBtn.addEventListener('click', close);

    overlay.addEventListener('mousedown', function (ev) {
      if (ev.target === overlay) close();
    });
    document.addEventListener('keydown', function (ev) {
      if (overlay.hidden) return;
      if (ev.key === 'Escape') { ev.preventDefault(); close(); }
      // Keep focus inside the dialog while it's open.
      if (ev.key === 'Tab') {
        ev.preventDefault();
        (document.activeElement === yesBtn ? noBtn : yesBtn).focus();
      }
    });

    // Links can ask first too: <a href="…" data-confirm="…">.
    document.querySelectorAll('a[data-confirm]').forEach(function (a) {
      a.addEventListener('click', function (ev) {
        if (ev.ctrlKey || ev.metaKey || ev.shiftKey || ev.button === 1) return;   // open-in-new-tab etc.
        ev.preventDefault();
        open(a, a.getAttribute('data-confirm'));
      });
    });

    document.querySelectorAll('form[data-confirm]').forEach(function (f) {
      f.addEventListener('submit', function (ev) {
        if (f.dataset.confirmed === '1') { delete f.dataset.confirmed; return; }
        ev.preventDefault();
        open(f, f.getAttribute('data-confirm'));
      });
    });
  })();

  // Tell every POST action which page (filters + #tab included) it was
  // submitted from, so it can send the person back there afterwards
  // instead of to the page's default view. See return_to_target() in
  // includes/functions.php. Stamped at load and refreshed on submit,
  // since tabs/filters can change the URL after the page loads.
  (function () {
    function here() {
      var file = location.pathname.split('/').pop() || 'dashboard.php';
      return file + location.search + location.hash;
    }
    function stamp(form) {
      if ((form.getAttribute('method') || '').toLowerCase() !== 'post') return;
      var f = form.querySelector('input[name="return_to"]');
      if (!f) {
        f = document.createElement('input');
        f.type = 'hidden';
        f.name = 'return_to';
        form.appendChild(f);
      }
      f.value = here();
    }
    document.querySelectorAll('form').forEach(stamp);
    document.addEventListener('submit', function (ev) { stamp(ev.target); }, true);
  })();

  // Lightweight modal open/close (used by the "New patient", "Log visit", etc. buttons).
  document.querySelectorAll('[data-open-modal]').forEach(function (btn) {
    btn.addEventListener('click', function () {
      var m = document.getElementById(btn.getAttribute('data-open-modal'));
      if (m) m.classList.add('open');
    });
  });
  document.querySelectorAll('[data-close-modal]').forEach(function (btn) {
    btn.addEventListener('click', function () {
      var m = btn.closest('.vp-modal-overlay');
      if (m) m.classList.remove('open');
    });
  });
  document.querySelectorAll('.vp-modal-overlay').forEach(function (ov) {
    ov.addEventListener('mousedown', function (ev) {
      if (ev.target === ov) ov.classList.remove('open');
    });
  });
  document.addEventListener('keydown', function (ev) {
    if (ev.key === 'Escape') {
      document.querySelectorAll('.vp-modal-overlay.open').forEach(function (m) { m.classList.remove('open'); });
    }
  });
</script>

<!-- Password show/hide toggles -->
<script>
(function () {
  var SHOW = '<svg class="vp-pw-show" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"><path d="M2 12s3.5-7 10-7 10 7 10 7-3.5 7-10 7-10-7-10-7z"/><circle cx="12" cy="12" r="3"/></svg>';
  var HIDE = '<svg class="vp-pw-hide" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"><path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-10-8-10-8a18.45 18.45 0 0 1 5.06-5.94"/><path d="M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 10 8 10 8a18.5 18.5 0 0 1-2.16 3.19"/><path d="M14.12 14.12a3 3 0 1 1-4.24-4.24"/><line x1="2" y1="2" x2="22" y2="22"/></svg>';

  function attach(input) {
    if (!input || input.dataset.pwReady === '1') return;
    input.dataset.pwReady = '1';

    var wrap = document.createElement('span');
    wrap.className = 'vp-pw-wrap';
    input.parentNode.insertBefore(wrap, input);
    wrap.appendChild(input);

    var btn = document.createElement('button');
    btn.type = 'button';            // never submits the form
    btn.className = 'vp-pw-toggle';
    btn.innerHTML = SHOW + HIDE;
    btn.setAttribute('aria-label', 'Show password');
    btn.setAttribute('title', 'Show password');
    // aria-pressed tells screen readers this is a two-state control.
    btn.setAttribute('aria-pressed', 'false');

    function setState(visible) {
      input.type = visible ? 'text' : 'password';
      btn.classList.toggle('is-on', visible);
      var label = visible ? 'Hide password' : 'Show password';
      btn.setAttribute('aria-label', label);
      btn.setAttribute('title', label);
      btn.setAttribute('aria-pressed', visible ? 'true' : 'false');
    }

    function toggle(refocusInput) {
      var visible = input.type !== 'text';
      setState(visible);
      if (refocusInput) {
        input.focus();
        var end = input.value.length;
        // Changing `type` resets the caret; some browsers only apply a
        // new selection on the next tick, so the restore is deferred.
        setTimeout(function () {
          try { input.setSelectionRange(end, end); } catch (e) {}
        }, 0);
      }
    }

    // Pointer/touch: don't let the press steal focus from the input,
    // which on mobile would dismiss the on-screen keyboard.
    btn.addEventListener('mousedown', function (ev) { ev.preventDefault(); });

    // Calling preventDefault() on touchstart (needed above, to keep the
    // input focused) also suppresses the synthetic "click" event most
    // mobile browsers would otherwise fire afterward — so the toggle has
    // to happen on touchend directly, or a real tap does nothing at all.
    // touchHandled guards against the rare browser that still fires a
    // click on top of this.
    var touchHandled = false;
    btn.addEventListener('touchstart', function (ev) { ev.preventDefault(); }, { passive: false });
    btn.addEventListener('touchend', function (ev) {
      ev.preventDefault();
      touchHandled = true;
      toggle(true);
      setTimeout(function () { touchHandled = false; }, 400);
    }, { passive: false });

    // Click covers mouse and Enter/Space on a real <button>.
    btn.addEventListener('click', function (ev) {
      if (touchHandled) return;
      ev.preventDefault();
      // If activated by keyboard, keep focus on the button so the user
      // can toggle again; otherwise return the caret to the input.
      var viaKeyboard = ev.detail === 0;
      toggle(!viaKeyboard);
      if (viaKeyboard) btn.focus();
    });

    // Explicit Space handling: browsers scroll the page on Space
    // unless we intercept it here.
    btn.addEventListener('keydown', function (ev) {
      if (ev.key === ' ' || ev.key === 'Spacebar' || ev.key === 'Enter') {
        ev.preventDefault();
        toggle(false);
        btn.focus();
      }
    });

    // Re-mask before submit so a visible password is never left on
    // screen after navigation, and never cached as type=text.
    var form = input.form;
    if (form && !form.dataset.pwSubmitBound) {
      form.dataset.pwSubmitBound = '1';
      form.addEventListener('submit', function () {
        form.querySelectorAll('input[data-pw-ready="1"]').forEach(function (i) {
          if (i.type === 'text') i.type = 'password';
        });
        form.querySelectorAll('.vp-pw-toggle').forEach(function (b) {
          b.classList.remove('is-on');
          b.setAttribute('aria-pressed', 'false');
          b.setAttribute('aria-label', 'Show password');
          b.setAttribute('title', 'Show password');
        });
      });
    }

    wrap.appendChild(btn);
  }

  function scan(root) {
    (root || document).querySelectorAll('input[type="password"]').forEach(attach);
  }

  scan();

  // Re-scan when a modal opens, since those inputs may have been
  // hidden (or added) after the first pass.
  document.querySelectorAll('[data-open-modal]').forEach(function (b) {
    b.addEventListener('click', function () { setTimeout(scan, 0); });
  });

  // Safety net: if any password field is injected later, catch it.
  if (window.MutationObserver) {
    new MutationObserver(function (muts) {
      for (var i = 0; i < muts.length; i++) {
        if (muts[i].addedNodes.length) { scan(); return; }
      }
    }).observe(document.body, { childList: true, subtree: true });
  }
})();
</script>


<!-- Notification bell dropdown + live clock -->
<script>
(function () {
  var btn   = document.getElementById('vpBellBtn');
  var panel = document.getElementById('vpBellPanel');

  if (btn && panel) {
    // Position the fixed panel directly under the bell, right-aligned to
    // it, and clamped so it never runs off the screen edge. On phones it
    // docks to a full-width strip near the top.
    function place() {
      var r  = btn.getBoundingClientRect();
      var vw = window.innerWidth;
      if (vw <= 520) {
        panel.style.top   = (r.bottom + 8) + 'px';
        panel.style.left  = '12px';
        panel.style.right = '12px';
        panel.style.width = 'auto';
      } else {
        panel.style.top   = (r.bottom + 10) + 'px';
        panel.style.left  = 'auto';
        panel.style.right = Math.max(12, vw - r.right) + 'px';
        panel.style.width = '340px';
      }
    }
    function open() {
      // Re-parent to <body> so the fixed panel escapes the stacking context
      // created by the animated .vp-top ancestor (which otherwise traps it
      // behind the page content regardless of z-index).
      if (panel.parentNode !== document.body) document.body.appendChild(panel);
      place();
      panel.hidden = false;
      btn.setAttribute('aria-expanded', 'true');
      markSeen();
    }

    // Opening the bell counts as reading the new activity: record it
    // server-side and drop those from the badge. The highlighted rows
    // stay highlighted for this view so you can still tell what was new.
    // "Needs attention" items are standing states, so they stay counted.
    var seenSent = false;
    function markSeen() {
      if (seenSent || !(parseInt(btn.dataset.unread, 10) > 0) || !window.fetch) return;
      seenSent = true;
      var body = new FormData();
      body.append('csrf', btn.dataset.csrf || '');
      fetch('actions/notifications_seen.php', { method: 'POST', body: body, credentials: 'same-origin' })
        .catch(function () {});
      var left = parseInt(btn.dataset.standing, 10) || 0;
      var dot = btn.querySelector('.vp-bell-dot');
      var count = panel.querySelector('.vp-bell-count');
      if (left > 0) {
        if (dot) dot.textContent = left > 9 ? '9+' : left;
        if (count) count.textContent = left;
      } else {
        if (dot) dot.remove();
        if (count) count.remove();
      }
      btn.setAttribute('aria-label', 'Notifications' + (left ? ' (' + left + ' new)' : ''));
    }
    function close() {
      panel.hidden = true;
      btn.setAttribute('aria-expanded', 'false');
    }
    function isOpen() { return !panel.hidden; }

    // Keep it anchored to the bell if the viewport changes while open.
    window.addEventListener('resize', function () { if (isOpen()) place(); });

    btn.addEventListener('click', function (ev) {
      ev.stopPropagation();
      isOpen() ? close() : open();
    });

    // Click anywhere outside closes the panel.
    document.addEventListener('click', function (ev) {
      if (isOpen() && !panel.contains(ev.target) && !btn.contains(ev.target)) close();
    });

    // Escape closes and returns focus to the bell.
    document.addEventListener('keydown', function (ev) {
      if (ev.key === 'Escape' && isOpen()) { close(); btn.focus(); }
    });

    // Arrow-down from the bell moves into the first notification.
    btn.addEventListener('keydown', function (ev) {
      if (ev.key === 'ArrowDown') {
        ev.preventDefault();
        if (!isOpen()) open();
        var first = panel.querySelector('.vp-bell-item, .vp-bell-foot');
        if (first) first.focus();
      }
    });
  }

  // Live clock — 12-hour format, matching the PHP-rendered value.
  var clock = document.getElementById('vpClock');
  if (clock) {
    var MONTHS = ['Jan','Feb','Mar','Apr','May','Jun',
                  'Jul','Aug','Sep','Oct','Nov','Dec'];

    function tick() {
      var d = new Date();
      var h = d.getHours();
      var ampm = h >= 12 ? 'PM' : 'AM';
      h = h % 12;
      if (h === 0) h = 12;                       // midnight/noon read as 12
      var m = d.getMinutes();
      if (m < 10) m = '0' + m;
      clock.textContent = MONTHS[d.getMonth()] + ' ' + d.getDate() + ', ' +
                          d.getFullYear() + ' · ' + h + ':' + m + ' ' + ampm;
    }

    tick();
    setInterval(tick, 1000);

    // Browsers throttle timers in background tabs, so the clock can drift
    // or stall while you're elsewhere. Redraw as soon as the tab is
    // visible again rather than waiting for the next interval.
    document.addEventListener('visibilitychange', function () {
      if (!document.hidden) tick();
    });
    window.addEventListener('focus', tick);
  }
})();
</script>


<!-- If a validation message landed inside a modal, reopen that modal so
     the person can actually see it and fix the field. -->
<script>
(function () {
  var alertEl = document.querySelector('.vp-modal-overlay .vp-form-alert');
  if (!alertEl) return;
  var overlay = alertEl.closest('.vp-modal-overlay');
  if (overlay) {
    overlay.classList.add('open');
    // Focus the first field so typing corrects it straight away.
    var first = overlay.querySelector('input:not([type=hidden]):not([disabled]), select, textarea');
    if (first) setTimeout(function () { first.focus(); }, 60);
  }
})();
</script>


<!-- The Address field is stored on a pet owner's client record, so it is
     only required while the Role is "Pet owner". -->
<script>
(function () {
  document.querySelectorAll('#vp-addr-input').forEach(function (input) {
    var form = input.form;
    if (!form) return;
    var role  = form.querySelector('select[name="role"]');
    var field = input.closest('.vp-field');
    if (!role) return;

    function sync() {
      var isOwner = role.value === 'owner';
      input.required = isOwner;
      if (field) field.style.display = isOwner ? '' : 'none';
      if (!isOwner) input.value = '';
    }
    role.addEventListener('change', sync);
    sync();
  });
})();
</script>


<?php if (is_logged_in()): ?>
<!-- Idle sign-out warning. The server is the real authority (see
     enforce_idle_timeout in auth.php); this just gives the person a
     heads-up and a way to stay signed in before their work is lost. -->
<div class="vp-idle-overlay" id="vpIdle" hidden>
  <div class="vp-idle-box" role="alertdialog" aria-labelledby="vpIdleTitle" aria-describedby="vpIdleMsg">
    <svg width="26" height="26" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/></svg>
    <h3 id="vpIdleTitle">Still there?</h3>
    <p id="vpIdleMsg">You'll be signed out in <strong id="vpIdleCount">2:00</strong> because of inactivity.</p>
    <div class="vp-idle-actions">
      <a class="vp-btn-ghost" href="logout.php">Sign out now</a>
      <button type="button" class="vp-btn-primary" id="vpIdleStay">Stay signed in</button>
    </div>
  </div>
</div>
<script>
(function () {
  var LIMIT = <?= IDLE_TIMEOUT ?>;          // seconds of inactivity allowed
  var WARN  = <?= IDLE_TIMEOUT - IDLE_WARN_AFTER ?>; // show the dialog this long before the end
  var left  = <?= idle_seconds_left() ?>;

  var box   = document.getElementById('vpIdle');
  var count = document.getElementById('vpIdleCount');
  var stay  = document.getElementById('vpIdleStay');
  if (!box) return;

  function reset() {
    left = LIMIT;
    if (!box.hidden) box.hidden = true;
  }

  // Any real interaction counts as activity.
  ['mousedown', 'keydown', 'touchstart', 'scroll'].forEach(function (ev) {
    document.addEventListener(ev, reset, { passive: true });
  });

  setInterval(function () {
    left--;

    if (left <= 0) {
      // The server has already expired it; reload to land on the
      // login page with the "signed out" message.
      window.location.href = 'index.php';
      return;
    }

    if (left <= WARN) {
      if (box.hidden) box.hidden = false;
      var m = Math.floor(left / 60);
      var s = left % 60;
      count.textContent = m + ':' + (s < 10 ? '0' + s : s);
    }
  }, 1000);

  // "Stay signed in" pings the server, which refreshes last_activity.
  stay.addEventListener('click', function () {
    fetch('ping.php', { cache: 'no-store' })
      .then(function () { reset(); })
      .catch(function () { window.location.href = 'index.php'; });
  });
})();
</script>
<?php endif; ?>


<!-- Live password-strength checklist. Mirrors password_problem() in
     functions.php — the server still validates on submit. -->
<script>
(function () {
  var pw    = document.getElementById('vpNewPw');
  var rules = document.getElementById('vpPwRules');
  if (!pw || !rules) return;

  var tests = {
    'At least 8 characters':            function (v) { return v.length >= 8; },
    'An uppercase letter (A\u2013Z)':    function (v) { return /[A-Z]/.test(v); },
    'A lowercase letter (a\u2013z)':     function (v) { return /[a-z]/.test(v); },
    'A number (0\u20139)':               function (v) { return /[0-9]/.test(v); },
    'A special character (! @ # $ \u2026)': function (v) { return /[^A-Za-z0-9]/.test(v); }
  };

  function check() {
    var v = pw.value;
    rules.querySelectorAll('li').forEach(function (li) {
      var fn = tests[li.getAttribute('data-rule')];
      li.classList.toggle('ok', fn ? fn(v) : false);
    });
  }
  pw.addEventListener('input', check);
  check();

  // Flag a mismatch early instead of after a round trip.
  var confirmPw = document.getElementById('vpConfirmPw');
  if (confirmPw) {
    function match() {
      confirmPw.setCustomValidity(
        confirmPw.value && confirmPw.value !== pw.value ? 'Passwords do not match.' : ''
      );
    }
    confirmPw.addEventListener('input', match);
    pw.addEventListener('input', match);
  }
})();
</script>


<!-- Pet owner accounts have no admin features to grant, so the
     checkbox row (not just the input) is hidden whenever Role is
     "Pet owner" — same pattern as the address field above. Clearing
     the checked state when it hides keeps a hidden box from silently
     submitting can_manage_users=1.
     Runs per-modal: every modal on the page (the "new" one plus one
     per listed user) renders its own checkbox, so this loops over all
     of them instead of grabbing a single id — with one edit modal per
     user, an id lookup would only ever affect the first modal in the
     page, leaving the checkbox visible when editing everyone else. -->
<script>
(function () {
  document.querySelectorAll('.vp-canmanage').forEach(function (box) {
    var form = box.form;
    if (!form) return;
    var role = form.querySelector('select[name="role"]');
    var row  = box.closest('.vp-check');
    if (!role || !row) return;

    function sync() {
      var isOwner = role.value === 'owner';
      row.style.display = isOwner ? 'none' : '';
      if (isOwner) box.checked = false;
    }
    role.addEventListener('change', sync);
    sync();
  });
})();
</script>


<!-- Phone number mask: inserts the dashes as you type, e.g.
     0917-555-0142. Digits are the only thing stored; the dashes are
     added for readability. -->
<script>
(function () {
  function digits(v) { return (v || '').replace(/\D/g, '').slice(0, 11); }

  function format(v) {
    var d = digits(v);
    if (d.length <= 4)  return d;
    if (d.length <= 7)  return d.slice(0, 4) + '-' + d.slice(4);
    return d.slice(0, 4) + '-' + d.slice(4, 7) + '-' + d.slice(7);
  }

  document.querySelectorAll('input[data-phone-mask]').forEach(function (input) {
    // Tidy whatever is already in the field (e.g. when editing).
    if (input.value) input.value = format(input.value);

    input.addEventListener('input', function () {
      // Count digits before the caret so it can be restored in the
      // right place after the dashes shift everything along.
      var caret = input.selectionStart;
      var before = digits(input.value.slice(0, caret)).length;

      input.value = format(input.value);

      // Walk forward until that many digits have been passed.
      var pos = 0, seen = 0;
      while (pos < input.value.length && seen < before) {
        if (/\d/.test(input.value[pos])) seen++;
        pos++;
      }
      // Don't leave the caret sitting just before a dash.
      if (input.value[pos] === '-') pos++;
      try { input.setSelectionRange(pos, pos); } catch (e) {}
    });

    // Backspacing onto a dash should remove the digit before it,
    // otherwise the dash reappears and nothing seems to happen.
    input.addEventListener('keydown', function (ev) {
      if (ev.key !== 'Backspace') return;
      var pos = input.selectionStart;
      if (pos === input.selectionEnd && pos > 0 && input.value[pos - 1] === '-') {
        ev.preventDefault();
        var v = input.value;
        input.value = format(v.slice(0, pos - 2) + v.slice(pos));
        var np = Math.max(0, pos - 2);
        try { input.setSelectionRange(np, np); } catch (e) {}
      }
    });
  });
})();
</script>

<!-- Searchable dropdown: progressively upgrades any <select data-searchable>.
     The native <select> stays in the DOM (hidden) as the real form value,
     so this needs no backend changes and still works if JS is unavailable.
     Honours <optgroup> — the group label (e.g. an owner) shows once, with
     its options listed beneath it. -->
<script>
(function () {
  var idSeq = 0;

  function build(select) {
    if (select.dataset.ssReady) return;
    select.dataset.ssReady = '1';

    var wrap = document.createElement('div');
    wrap.className = 'vp-ss';

    var trigger = document.createElement('button');
    trigger.type = 'button';
    trigger.className = 'vp-ss-trigger';
    trigger.setAttribute('aria-haspopup', 'listbox');
    trigger.setAttribute('aria-expanded', 'false');

    var label = document.createElement('span');
    label.className = 'vp-ss-label';
    trigger.appendChild(label);

    var caret = document.createElementNS('http://www.w3.org/2000/svg', 'svg');
    caret.setAttribute('class', 'vp-ss-caret');
    caret.setAttribute('viewBox', '0 0 24 24');
    caret.setAttribute('fill', 'none');
    caret.setAttribute('stroke', 'currentColor');
    caret.setAttribute('stroke-width', '2');
    caret.setAttribute('stroke-linecap', 'round');
    caret.setAttribute('stroke-linejoin', 'round');
    caret.innerHTML = '<path d="m6 9 6 6 6-6"/>';
    trigger.appendChild(caret);

    var panel = document.createElement('div');
    panel.className = 'vp-ss-panel';
    panel.hidden = true;
    var panelId = 'vp-ss-panel-' + (++idSeq);
    panel.id = panelId;
    trigger.setAttribute('aria-controls', panelId);

    var searchWrap = document.createElement('div');
    searchWrap.className = 'vp-ss-search';
    searchWrap.innerHTML =
      '<svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="8"/><path d="m21 21-4.3-4.3"/></svg>';
    var search = document.createElement('input');
    search.type = 'text';
    search.placeholder = select.getAttribute('data-search-placeholder') || 'Search…';
    search.autocomplete = 'off';
    searchWrap.appendChild(search);

    var list = document.createElement('div');
    list.className = 'vp-ss-list';
    list.setAttribute('role', 'listbox');

    var empty = document.createElement('div');
    empty.className = 'vp-ss-empty';
    empty.textContent = 'No matches';
    empty.hidden = true;

    panel.appendChild(searchWrap);
    panel.appendChild(list);
    panel.appendChild(empty);

    // Insert the wrapper right after the (now hidden) native select.
    select.parentNode.insertBefore(wrap, select);
    wrap.appendChild(select);
    wrap.appendChild(trigger);
    wrap.appendChild(panel);
    select.classList.add('vp-ss-native');

    // A label built as "Title · Subtitle" (e.g. a pet name + species, or an
    // owner's name + email) renders as two stacked lines instead of one
    // run-on line — the " · " is just the server-side convention for
    // "there's a second, lesser detail here," not literal text to show.
    function renderTitleSub(el, text) {
      var i = text.indexOf(' · ');
      if (i === -1) { el.textContent = text; return; }
      var title = document.createElement('span');
      title.className = 'vp-ss-title';
      title.textContent = text.slice(0, i);
      var sub = document.createElement('span');
      sub.className = 'vp-ss-subtitle';
      sub.textContent = text.slice(i + 3);
      el.appendChild(title);
      el.appendChild(sub);
    }

    // Mirror every real <option> as a clickable row, keeping <optgroup>
    // headings so a shared owner is shown only once.
    var rows = [];

    function addOption(opt) {
      var row = document.createElement('div');
      row.className = 'vp-ss-opt';
      row.setAttribute('role', 'option');
      renderTitleSub(row, opt.textContent);
      row.dataset.value = opt.value;
      // Search over the option text plus its group label (owner name).
      var grp = opt.parentNode && opt.parentNode.tagName === 'OPTGROUP'
        ? opt.parentNode.label : '';
      row.dataset.search = (grp + ' ' + opt.textContent).toLowerCase();
      row.addEventListener('click', function () { choose(opt.value); });
      list.appendChild(row);
      rows.push(row);
    }

    Array.prototype.forEach.call(select.children, function (child) {
      if (child.tagName === 'OPTGROUP') {
        var head = document.createElement('div');
        head.className = 'vp-ss-group';
        renderTitleSub(head, child.label);
        list.appendChild(head);
        Array.prototype.forEach.call(child.children, addOption);
      } else if (child.tagName === 'OPTION') {
        addOption(child);
      }
    });

    function syncLabel() {
      var opt = select.options[select.selectedIndex];
      if (opt && opt.value !== '') {
        label.textContent = opt.textContent;
        label.classList.remove('vp-ss-placeholder');
      } else {
        label.textContent = select.getAttribute('data-placeholder') || 'Select…';
        label.classList.add('vp-ss-placeholder');
      }
      rows.forEach(function (r) {
        r.classList.toggle('selected', r.dataset.value === select.value);
      });
    }

    function choose(value) {
      select.value = value;
      select.dispatchEvent(new Event('change', { bubbles: true }));
      syncLabel();
      close();
    }

    function filter() {
      var q = search.value.trim().toLowerCase();
      var anyGroup = false, shown = 0;
      // Track whether the current group has any visible rows so empty
      // group headings can be hidden while searching.
      var groupHeads = list.querySelectorAll('.vp-ss-group');
      rows.forEach(function (r) {
        var match = !q || r.dataset.search.indexOf(q) !== -1;
        r.hidden = !match;
        if (match) shown++;
      });
      // Hide a group heading when none of the rows under it are visible.
      Array.prototype.forEach.call(groupHeads, function (head) {
        var vis = false, n = head.nextElementSibling;
        while (n && !n.classList.contains('vp-ss-group')) {
          if (n.classList.contains('vp-ss-opt') && !n.hidden) { vis = true; break; }
          n = n.nextElementSibling;
        }
        head.hidden = !vis;
      });
      empty.hidden = shown > 0;
    }

    function open() {
      panel.hidden = false;
      trigger.setAttribute('aria-expanded', 'true');
      wrap.classList.add('open');
      search.value = '';
      filter();

      // Flip the panel above the field when there isn't room below it
      // (e.g. the Owner field sits near the bottom of the modal).
      wrap.classList.remove('vp-ss-up');
      var tr = trigger.getBoundingClientRect();
      var spaceBelow = window.innerHeight - tr.bottom;
      var needed = panel.offsetHeight + 12;
      if (spaceBelow < needed && tr.top > spaceBelow) {
        wrap.classList.add('vp-ss-up');
      }

      setTimeout(function () { search.focus(); }, 0);
      // Bring the selected row into view.
      var sel = list.querySelector('.vp-ss-opt.selected');
      if (sel) sel.scrollIntoView({ block: 'nearest' });
    }

    function close() {
      panel.hidden = true;
      trigger.setAttribute('aria-expanded', 'false');
      wrap.classList.remove('open');
    }

    function toggle() { panel.hidden ? open() : close(); }

    trigger.addEventListener('click', toggle);
    search.addEventListener('input', filter);

    // Keyboard: arrows move through visible rows, Enter picks, Esc closes.
    search.addEventListener('keydown', function (ev) {
      var vis = rows.filter(function (r) { return !r.hidden; });
      var cur = list.querySelector('.vp-ss-opt.active');
      var i = vis.indexOf(cur);
      if (ev.key === 'ArrowDown') {
        ev.preventDefault();
        var nd = vis[Math.min(i + 1, vis.length - 1)] || vis[0];
        setActive(nd, vis);
      } else if (ev.key === 'ArrowUp') {
        ev.preventDefault();
        var nu = vis[Math.max(i - 1, 0)] || vis[0];
        setActive(nu, vis);
      } else if (ev.key === 'Enter') {
        ev.preventDefault();
        if (cur) choose(cur.dataset.value);
        else if (vis.length === 1) choose(vis[0].dataset.value);
      } else if (ev.key === 'Escape') {
        ev.preventDefault();
        close();
        trigger.focus();
      }
    });

    function setActive(row, vis) {
      (vis || rows).forEach(function (r) { r.classList.remove('active'); });
      if (row) { row.classList.add('active'); row.scrollIntoView({ block: 'nearest' }); }
    }

    // Click-away closes the panel.
    document.addEventListener('mousedown', function (ev) {
      if (!wrap.contains(ev.target)) close();
    });

    syncLabel();
  }

  function initAll(root) {
    (root || document).querySelectorAll('select[data-searchable]').forEach(build);
  }

  initAll(document);

  // Modals are already in the DOM at load, so a single pass is enough;
  // this stays exposed in case markup is added later.
  window.vpInitSearchableSelects = initAll;
})();
</script>

<script>
// Click an appointment code to copy it (just the code, e.g. APT-20260723-001).
(function () {
  function legacyCopy(t) {                              // works where the clipboard API isn't allowed
    return new Promise(function (ok, fail) {
      var ta = document.createElement('textarea');
      ta.value = t; ta.setAttribute('readonly', ''); ta.style.cssText = 'position:fixed;left:-9999px;top:0;opacity:0';
      document.body.appendChild(ta); ta.select();
      var done = false;
      try { done = document.execCommand('copy'); } catch (e) {}
      document.body.removeChild(ta);
      done ? ok() : fail();
    });
  }
  function copyText(t) {
    if (navigator.clipboard && window.isSecureContext) {
      return navigator.clipboard.writeText(t).catch(function () { return legacyCopy(t); });
    }
    return legacyCopy(t);
  }
  document.addEventListener('click', function (e) {
    var b = e.target.closest('[data-copy-code]');
    if (!b) return;
    e.preventDefault(); e.stopPropagation();           // never follow a surrounding link
    var code = b.getAttribute('data-copy-code'), label = b.getAttribute('data-label') || b.textContent;
    b.setAttribute('data-label', label);
    copyText(code).then(function () { b.classList.add('is-copied'); b.textContent = 'Copied!'; })
                  .catch(function () { b.classList.add('is-copied'); b.textContent = 'Press Ctrl+C'; });
    clearTimeout(b._t);
    b._t = setTimeout(function () { b.classList.remove('is-copied'); b.textContent = label; }, 1400);
  });
})();</script>

<!-- Appointment-code search boxes (input[data-appt-code]): typing "apt…" gives
     capitals and dashes (APT-YYYYMMDD-001), and pasting keeps just the code —
     "# APT-20260723-001 Scheduled" becomes "APT-20260723-001". Other text is
     left exactly as typed or pasted. -->
<script>
(function () {
  function isCode(v) { return /^[\s#]*apt[\s\-]*[\d\s\-]*$/i.test(v); }
  function format(raw) {
    var m = raw.match(/apt([\s\-]*[\d\s\-]*)/i), rest = m ? m[1] : '';
    var d = rest.replace(/\D/g, '').slice(0, 11), tail = /[\s\-]$/.test(rest);
    if (!d.length) return tail ? 'APT-' : 'APT';
    if (d.length < 8) return 'APT-' + d;
    if (d.length === 8) return 'APT-' + d + (tail ? '-' : '');
    return 'APT-' + d.slice(0, 8) + '-' + d.slice(8);
  }
  document.querySelectorAll('input[data-appt-code]').forEach(function (input) {
    function setVal(f) { input.value = f; input.setSelectionRange(f.length, f.length); }
    input.addEventListener('input', function () {
      if (isCode(input.value)) { var f = format(input.value); if (f !== input.value) setVal(f); }
    });
    input.addEventListener('paste', function (e) {
      var text = (e.clipboardData || window.clipboardData).getData('text') || '';
      if (!/apt[\s\-]*\d/i.test(text)) return;          // not a code — paste normally
      e.preventDefault();
      setVal(format(text.match(/apt[\s\-]*\d[\d\s\-]*/i)[0]));
      input.dispatchEvent(new Event('input', { bubbles: true }));
    });
  });
})();
</script>
<!-- Reusable debounced search: any <form data-search-form> with a text
     input[data-search-input] auto-submits shortly after typing stops, keeps
     the caret where it was across the reload, and lets filter chips/links
     cancel a pending submit. Each form needs a unique data-search-key so
     pages don't clobber each other's saved caret position. -->
<script>
(function () {
  document.querySelectorAll('form[data-search-form]').forEach(function (form) {
    var input = form.querySelector('input[data-search-input]');
    if (!input) return;

    var key = 'pp_caret_' + (form.getAttribute('data-search-key') || form.id || 'search');
    var DELAY = 450;
    var timer = null;

    // Restore focus + caret after the reload the previous submit caused.
    try {
      var saved = sessionStorage.getItem(key);
      if (saved !== null) {
        sessionStorage.removeItem(key);
        input.focus();
        var at = Math.min(parseInt(saved, 10) || 0, input.value.length);
        input.setSelectionRange(at, at);
      }
    } catch (e) {}

    function remember() {
      try { sessionStorage.setItem(key, input.selectionStart); } catch (e) {}
    }

    function submitSoon() {
      clearTimeout(timer);
      timer = setTimeout(function () { remember(); form.submit(); }, DELAY);
    }

    input.addEventListener('input', submitSoon);

    // Enter searches at once instead of waiting out the delay.
    input.addEventListener('keydown', function (ev) {
      if (ev.key === 'Enter') {
        ev.preventDefault();
        clearTimeout(timer);
        remember();
        form.submit();
      }
    });

    // A chip/link click should win over a pending auto-submit.
    form.querySelectorAll('.vp-chip').forEach(function (chip) {
      chip.addEventListener('click', function () { clearTimeout(timer); });
    });
  });
})();
</script>

<!-- Phone layout for data tables: each row shows as a card, with the
     column name next to each value. This copies the header text onto every
     cell as data-label (the CSS shows it only on small screens). -->
<script>
document.querySelectorAll('.vp-table').forEach(function (t) {
  var heads = Array.prototype.map.call(t.querySelectorAll('thead th'), function (th) { return th.textContent.replace(/\s+/g, ' ').trim(); });
  t.querySelectorAll('tbody tr').forEach(function (tr) {
    Array.prototype.forEach.call(tr.children, function (td, i) { if (heads[i] && !td.hasAttribute('data-label')) td.setAttribute('data-label', heads[i]); });
  });
});
</script>

<!-- Filter chips → dropdown. Every row of filter chips (species, status,
     category, role, archive tab) is shown as one dropdown instead. The chips
     stay in the page, hidden; choosing an option clicks the matching chip, so
     the links and form submits behave exactly as before. -->
<script>
(function () {
  var CARET = '<svg class="vp-ss-caret" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m6 9 6 6 6-6"/></svg>';
  function esc(t) { return String(t).replace(/[&<>"']/g, function (c) { return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]; }); }
  function clean(t) { return t.replace(/\s+/g, ' ').trim(); }

  document.querySelectorAll('.vp-filter-chips').forEach(function (row) {
    var chips = Array.prototype.slice.call(row.querySelectorAll('.vp-chip'));
    if (chips.length < 2) return;
    var label = row.getAttribute('data-label') || '';

    var wrap = document.createElement('div');
    wrap.className = 'vp-ss vp-filter-dd';
    var trig = document.createElement('button');
    trig.type = 'button';
    trig.className = 'vp-ss-trigger';
    trig.setAttribute('aria-haspopup', 'listbox');
    trig.setAttribute('aria-expanded', 'false');
    var panel = document.createElement('div');
    panel.className = 'vp-ss-panel vp-fdd-panel';
    panel.hidden = true;
    var list = document.createElement('div');
    list.className = 'vp-ss-list';
    list.setAttribute('role', 'listbox');
    panel.appendChild(list);
    wrap.appendChild(trig);
    // The list lives on <body>: page sections are animated (transformed), and
    // a fixed element inside one is positioned relative to it, not the window.
    document.body.appendChild(panel);

    var active = chips.filter(function (c) { return c.classList.contains('active'); })[0] || chips[0];
    trig.innerHTML = (label ? '<span class="vp-fdd-label">' + esc(label) + '</span>' : '')
      + '<span class="vp-ss-label">' + esc(clean(active.textContent)) + '</span>' + CARET;

    chips.forEach(function (chip) {
      var b = document.createElement('button');
      b.type = 'button';
      b.className = 'vp-ss-opt' + (chip === active ? ' selected' : '');
      b.setAttribute('role', 'option');
      b.textContent = clean(chip.textContent);
      b.addEventListener('click', function () {
        setOpen(false);
        if (chip === active) return;
        chip.click();            // same link / submit button as before
      });
      list.appendChild(b);
    });

    function place() {
      var box = trig.getBoundingClientRect(), h = Math.min(list.scrollHeight + 14, 280);
      var below = window.innerHeight - box.bottom - 12, up = below < h && box.top > below;
      var w = Math.min(Math.max(box.width, 200), window.innerWidth - 24);
      panel.style.left = Math.max(12, Math.min(box.left, window.innerWidth - w - 12)) + 'px';
      panel.style.width = w + 'px';
      panel.style.top = up ? '' : (box.bottom + 6) + 'px';
      panel.style.bottom = up ? (window.innerHeight - box.top + 6) + 'px' : '';
      list.style.maxHeight = Math.max(140, (up ? box.top : below) - 18) + 'px';
    }
    function setOpen(open) {
      panel.hidden = !open;
      wrap.classList.toggle('open', open);
      trig.setAttribute('aria-expanded', open ? 'true' : 'false');
      if (open) place();
    }
    trig.addEventListener('click', function () { setOpen(panel.hidden); });
    document.addEventListener('click', function (e) { if (!wrap.contains(e.target) && !panel.contains(e.target)) setOpen(false); });
    document.addEventListener('keydown', function (e) { if (e.key === 'Escape' && !panel.hidden) { setOpen(false); trig.focus(); } });
    window.addEventListener('resize', function () { setOpen(false); });
    document.addEventListener('scroll', function (e) { if (!panel.hidden && !panel.contains(e.target)) setOpen(false); }, true);

    row.parentNode.insertBefore(wrap, row);
    row.classList.add('vp-chips-hidden');
  });
})();
</script>
</body>
</html>
