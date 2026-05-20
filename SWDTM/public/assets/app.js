(function () {
  function debounce(fn, wait) {
    var t = null;
    function wrapped() {
      var ctx = this;
      var args = arguments;
      if (t) window.clearTimeout(t);
      t = window.setTimeout(function () {
        fn.apply(ctx, args);
      }, wait);
    };

    wrapped.flush = function () {
      if (t) {
        window.clearTimeout(t);
        t = null;
      }
      fn();
    };

    return wrapped;
  }

  function initDispatcherRealtime() {
    var isDispatcher = document.body && document.body.classList && document.body.classList.contains('is-dispatcher');
    if (!isDispatcher) return;

    var soundUrl = '/mp3/formatka.mp3';
    if (window.SWDTM && typeof window.SWDTM.alertSoundUrl === 'string' && window.SWDTM.alertSoundUrl) {
      soundUrl = window.SWDTM.alertSoundUrl;
    }

    var audio = null;
    try {
      audio = new Audio(soundUrl);
      audio.preload = 'auto';
      audio.volume = 0.9;
    } catch (e) {
      audio = null;
    }

    function playAlertOnce() {
      if (!audio) return;
      try {
        audio.currentTime = 0;
        var p = audio.play();
        if (p && typeof p.catch === 'function') p.catch(function () {});
      } catch (e) {}
    }

    function findFormatkiLink() {
      var links = document.querySelectorAll('a.nav-item');
      var found = null;
      links.forEach(function (a) {
        if (found) return;
        var href = String(a.getAttribute('href') || '');
        if (href.indexOf('/dispatcher/formatki') !== -1) found = a;
      });
      return found;
    }

    function setFormatkiBadge(count) {
      var link = findFormatkiLink();
      if (!link) return;

      var badge = link.querySelector('.nav-badge');
      var c = parseInt(String(count || 0), 10);
      if (isNaN(c) || c < 0) c = 0;

      if (c <= 0) {
        link.classList.remove('is-attention');
        if (badge && badge.parentNode) badge.parentNode.removeChild(badge);
        return;
      }

      link.classList.add('is-attention');
      if (!badge) {
        badge = document.createElement('span');
        badge.className = 'nav-badge';
        link.appendChild(badge);
      }
      badge.textContent = String(c);
    }

    var lastFormatkiCount = null;

    function fetchFormatkiCount() {
      var url = '/api/dispatcher_formatki_count.php';
      fetch(url, { credentials: 'same-origin' })
        .then(function (r) {
          if (!r || r.ok !== true) return null;
          return r.json();
        })
        .then(function (data) {
          if (!data || data.ok !== true) return;
          var c = parseInt(String(data.count || 0), 10);
          if (isNaN(c) || c < 0) c = 0;

          if (lastFormatkiCount === null) {
            lastFormatkiCount = c;
            setFormatkiBadge(c);
            return;
          }

          if (c > lastFormatkiCount) {
            playAlertOnce();
          }
          lastFormatkiCount = c;
          setFormatkiBadge(c);
        })
        .catch(function () {});
    }

    fetchFormatkiCount();
    window.setInterval(fetchFormatkiCount, 3000);

    var teamBadge = document.querySelector('[data-team-events-badge]');
    if (teamBadge && window.MutationObserver) {
      var lastTeamCount = 0;
      try {
        var initTxt = String(teamBadge.textContent || '').trim();
        var initN = parseInt(initTxt, 10);
        if (!isNaN(initN) && initN > 0) lastTeamCount = initN;
      } catch (e) {}

      var obs = new MutationObserver(function () {
        try {
          var txt = String(teamBadge.textContent || '').trim();
          var n = parseInt(txt, 10);
          if (isNaN(n) || n < 0) n = 0;
          var visible = teamBadge.style && teamBadge.style.display !== 'none';
          if (visible && n > lastTeamCount) {
            playAlertOnce();
          }
          lastTeamCount = n;
        } catch (e) {}
      });
      try {
        obs.observe(teamBadge, { childList: true, characterData: true, subtree: true, attributes: true, attributeFilter: ['style'] });
      } catch (e) {}
    }
  }

  function initTimePicker() {
    function ensureOverlay() {
      var existing = document.querySelector('[data-time-picker-overlay]');
      if (existing) return existing;

      var overlay = document.createElement('div');
      overlay.className = 'modal-overlay';
      overlay.setAttribute('data-time-picker-overlay', '1');
      overlay.setAttribute('data-outside-close', '1');
      overlay.setAttribute('aria-modal', 'true');

      overlay.innerHTML = '' +
        '<div class="modal">' +
          '<div class="modal-head">' +
            '<div>' +
              '<div class="modal-title">Wybierz godzinę</div>' +
              '<div class="modal-text" data-time-picker-hint></div>' +
            '</div>' +
          '</div>' +
          '<div class="time-grid" data-time-grid></div>' +
          '<div class="modal-actions">' +
            '<button class="btn-secondary" type="button" data-time-cancel>Zamknij</button>' +
          '</div>' +
        '</div>';

      document.body.appendChild(overlay);
      return overlay;
    }

    function timeToMinutes(t) {
      var m = /^([0-2]\d):([0-5]\d)$/.exec(String(t || ''));
      if (!m) return null;
      var hh = parseInt(m[1], 10);
      var mm = parseInt(m[2], 10);
      if (hh > 23) return null;
      return hh * 60 + mm;
    }

    function minutesToTime(mins) {
      var hh = Math.floor(mins / 60);
      var mm = mins % 60;
      return String(hh).padStart(2, '0') + ':' + String(mm).padStart(2, '0');
    }

    function openPicker(input) {
      var overlay = ensureOverlay();
      var grid = overlay.querySelector('[data-time-grid]');
      var hint = overlay.querySelector('[data-time-picker-hint]');
      var btnCancel = overlay.querySelector('[data-time-cancel]');

      var minMins = 0;
      var minTime = input && input.dataset ? input.dataset.minTime : '';
      var parsedMin = timeToMinutes(minTime);
      if (parsedMin !== null) minMins = parsedMin;

      if (hint) {
        hint.textContent = parsedMin !== null ? ('Najwcześniej: ' + minTime) : '';
      }

      while (grid && grid.firstChild) grid.removeChild(grid.firstChild);

      for (var mins = 0; mins < 24 * 60; mins += 10) {
        if (mins < minMins) continue;
        var btn = document.createElement('button');
        btn.type = 'button';
        btn.className = 'btn-secondary time-btn';
        btn.textContent = minutesToTime(mins);
        btn.addEventListener('click', function () {
          input.value = this.textContent;
          try {
            input.dispatchEvent(new Event('change', { bubbles: true }));
            input.dispatchEvent(new Event('input', { bubbles: true }));
          } catch (e) {}
          close();
        });
        grid.appendChild(btn);
      }

      function onOverlayClick(e) {
        if (e.target !== overlay) return;
        if (overlay.getAttribute('data-outside-close') === '0') return;
        close();
      }

      function onKey(e) {
        if (e.key === 'Escape') close();
      }

      function close() {
        overlay.classList.remove('is-open');
        overlay.removeEventListener('click', onOverlayClick);
        if (btnCancel) btnCancel.removeEventListener('click', close);
        document.removeEventListener('keydown', onKey);
      }

      overlay.classList.add('is-open');
      overlay.addEventListener('click', onOverlayClick);
      if (btnCancel) btnCancel.addEventListener('click', close);
      document.addEventListener('keydown', onKey);
    }

    document.querySelectorAll('[data-time-picker]').forEach(function (input) {
      input.addEventListener('click', function () {
        openPicker(input);
      });
      input.addEventListener('keydown', function (e) {
        if (e.key === 'Enter' || e.key === ' ') {
          e.preventDefault();
          openPicker(input);
        }
      });
    });
  }

  function hideToast(el) {
    if (!el || el.classList.contains('is-hiding')) return;
    el.classList.add('is-hiding');
    window.setTimeout(function () {
      if (el && el.parentNode) el.parentNode.removeChild(el);
    }, 350);
  }

  function initAutoSubmitFilters() {
    var forms = document.querySelectorAll('form[data-autosubmit]');
    if (!forms.length) return;

    forms.forEach(function (form) {
      var wait = parseInt(form.getAttribute('data-debounce') || '300', 10);
      if (isNaN(wait) || wait < 0) wait = 300;

      var submitNow = function () {
        if (form.dataset.submitting === '1') return;
        form.dataset.submitting = '1';
        form.requestSubmit ? form.requestSubmit() : form.submit();
      };

      var submitDebounced = debounce(function () {
        submitNow();
      }, wait);

      form.addEventListener('input', function (e) {
        var target = e.target;
        if (!target || !target.name) return;
        if (target.tagName && target.tagName.toLowerCase() === 'select') {
          submitNow();
          return;
        }
        submitDebounced();
      });

      form.addEventListener('change', function (e) {
        var target = e.target;
        if (!target || !target.name) return;
        submitNow();
      });

      form.addEventListener('submit', function () {
        form.dataset.submitting = '1';
      });
    });
  }

  function initOrderMeta() {
    function todayStr(d) {
      var y = d.getFullYear();
      var m = String(d.getMonth() + 1).padStart(2, '0');
      var day = String(d.getDate()).padStart(2, '0');
      return y + '-' + m + '-' + day;
    }

    function timeStr(h, m) {
      return String(h).padStart(2, '0') + ':' + String(m).padStart(2, '0');
    }

    function normalizeTimeTo10(val) {
      var mm = /^([0-2]\d):([0-5]\d)$/.exec(String(val || ''));
      if (!mm) return '';
      var hh = parseInt(mm[1], 10);
      var mins = parseInt(mm[2], 10);

      var rounded = Math.ceil(mins / 10) * 10;
      if (rounded === 60) {
        hh = hh + 1;
        rounded = 0;
      }
      if (hh >= 24) hh = 23;
      return timeStr(hh, rounded);
    }

    function roundUpTo10Minutes(d) {
      var h = d.getHours();
      var m = d.getMinutes();
      var rounded = Math.ceil(m / 10) * 10;
      if (rounded === 60) {
        h = h + 1;
        rounded = 0;
      }
      return { h: h, m: rounded };
    }

    document.querySelectorAll('form').forEach(function (form) {
      var orderType = form.querySelector('[data-order-type]');
      if (!orderType) return;

      var urgency = form.querySelector('select[name="urgency"]');

      var plannedWrap = form.querySelector('[data-planned-fields]');
      var plannedDate = form.querySelector('[data-planned-date]');
      var plannedTime = form.querySelector('[data-planned-time]');

      function sync() {
        var v = String(orderType.value || '');
        var isPlanned = v === 'planowe';

        function syncPlannedMin() {
          if (!plannedDate || !plannedTime) return;
          var now = new Date();
          var today = todayStr(now);
          plannedDate.min = today;

          var chosenDate = String(plannedDate.value || '');
          if (chosenDate === today) {
            var r = roundUpTo10Minutes(now);
            plannedTime.dataset.minTime = timeStr(r.h, r.m);
          } else {
            delete plannedTime.dataset.minTime;
          }
        }

        if (urgency) {
          urgency.classList.remove('urgency-zwykle', 'urgency-pilne', 'urgency-natychmiast');
          var u = String(urgency.value || '');
          if (u === 'zwykłe') urgency.classList.add('urgency-zwykle');
          else if (u === 'pilne') urgency.classList.add('urgency-pilne');
          else if (u === 'natychmiast') urgency.classList.add('urgency-natychmiast');
        }

        if (plannedWrap) plannedWrap.style.display = isPlanned ? '' : 'none';
        if (plannedDate) {
          if (isPlanned) plannedDate.setAttribute('required', 'required');
          else plannedDate.removeAttribute('required');
        }
        if (plannedTime) {
          if (isPlanned) plannedTime.setAttribute('required', 'required');
          else plannedTime.removeAttribute('required');
        }

        if (plannedTime && isPlanned && plannedTime.value) {
          var normalized = normalizeTimeTo10(plannedTime.value);
          if (normalized && normalized !== plannedTime.value) plannedTime.value = normalized;
        }

        if (isPlanned) syncPlannedMin();
      }

      orderType.addEventListener('change', sync);
      if (urgency) urgency.addEventListener('change', sync);

      if (plannedDate) {
        plannedDate.addEventListener('change', function () {
          sync();
        });
      }

      if (plannedTime) {
        plannedTime.addEventListener('change', function () {
          sync();
        });
      }

      sync();
    });
  }

  function initConfirmLeave() {
    function ensureModal() {
      var existing = document.querySelector('[data-app-confirm-overlay]');
      if (existing) return existing;

      var overlay = document.createElement('div');
      overlay.className = 'modal-overlay';
      overlay.setAttribute('data-app-confirm-overlay', '1');
      overlay.setAttribute('data-outside-close', '1');
      overlay.setAttribute('role', 'dialog');
      overlay.setAttribute('aria-modal', 'true');

      overlay.innerHTML = '' +
        '<div class="modal">' +
          '<div class="modal-head">' +
            '<div>' +
              '<div class="modal-title" data-app-confirm-title></div>' +
              '<div class="modal-text" data-app-confirm-text></div>' +
            '</div>' +
          '</div>' +
          '<div class="modal-actions">' +
            '<button class="btn-secondary" type="button" data-app-confirm-cancel>Anuluj</button>' +
            '<button class="btn-danger" type="button" data-app-confirm-ok>Opuść</button>' +
          '</div>' +
        '</div>';

      document.body.appendChild(overlay);
      return overlay;
    }

    function openConfirm(opts) {
      var overlay = ensureModal();
      var titleEl = overlay.querySelector('[data-app-confirm-title]');
      var textEl = overlay.querySelector('[data-app-confirm-text]');
      var btnCancel = overlay.querySelector('[data-app-confirm-cancel]');
      var btnOk = overlay.querySelector('[data-app-confirm-ok]');

      if (titleEl) titleEl.textContent = String(opts && opts.title ? opts.title : 'Opuścić stronę?');
      if (textEl) textEl.textContent = String(opts && opts.text ? opts.text : 'Niezapisane dane zostaną utracone.');

      function close() {
        overlay.classList.remove('is-open');
        overlay.removeEventListener('click', onOverlay);
        if (btnCancel) btnCancel.removeEventListener('click', onCancel);
        if (btnOk) btnOk.removeEventListener('click', onOk);
        document.removeEventListener('keydown', onKey);
      }

      function onCancel() {
        close();
      }

      function onOk() {
        close();
        if (opts && typeof opts.onOk === 'function') opts.onOk();
      }

      function onOverlay(e) {
        if (e.target !== overlay) return;
        if (overlay.getAttribute('data-outside-close') === '0') return;
        close();
      }

      function onKey(e) {
        if (e.key === 'Escape') close();
      }

      overlay.classList.add('is-open');
      overlay.addEventListener('click', onOverlay);
      if (btnCancel) btnCancel.addEventListener('click', onCancel);
      if (btnOk) btnOk.addEventListener('click', onOk);
      document.addEventListener('keydown', onKey);
    }

    document.querySelectorAll('[data-confirm-leave]').forEach(function (el) {
      el.addEventListener('click', function (e) {
        var href = el.getAttribute('href');
        if (!href) return;
        e.preventDefault();

        openConfirm({
          title: 'Opuścić stronę?',
          text: 'Niezapisane dane zostaną utracone.',
          onOk: function () {
            window.location.href = href;
          }
        });
      });
    });
  }

  function initModals() {
    var overlays = document.querySelectorAll('[data-modal-overlay]');
    if (!overlays.length) return;

    function closeOverlay(overlay) {
      if (!overlay) return;
      overlay.classList.remove('is-open');
    }

    function openOverlay(overlay) {
      if (!overlay) return;
      overlay.classList.add('is-open');
    }

    function findOverlay(id) {
      return document.querySelector('[data-modal-overlay="' + id + '"]');
    }

    document.querySelectorAll('[data-modal-open]').forEach(function (btn) {
      btn.addEventListener('click', function () {
        var id = btn.getAttribute('data-modal-open');
        if (!id) return;
        var overlay = document.querySelector('[data-modal-overlay="' + id + '"]');
        openOverlay(overlay);
      });
    });

    overlays.forEach(function (overlay) {
      overlay.addEventListener('click', function (e) {
        if (e.target !== overlay) return;
        if (overlay.getAttribute('data-outside-close') === '0') return;
        closeOverlay(overlay);
      });

      overlay.querySelectorAll('[data-modal-close]').forEach(function (btn) {
        btn.addEventListener('click', function () {
          closeOverlay(overlay);
        });
      });

      overlay.querySelectorAll('[data-modal-confirm-close]').forEach(function (btn) {
        btn.addEventListener('click', function () {
          var targetId = btn.getAttribute('data-modal-confirm-close');
          if (targetId) {
            closeOverlay(findOverlay(targetId));
          }

          var resetFormId = btn.getAttribute('data-modal-reset-form');
          if (resetFormId) {
            var form = document.getElementById(resetFormId);
            if (form && typeof form.reset === 'function') form.reset();
          }

          closeOverlay(overlay);
        });
      });

      overlay.querySelectorAll('[data-modal-confirm-submit]').forEach(function (btn) {
        btn.addEventListener('click', function () {
          var formId = btn.getAttribute('data-modal-confirm-submit');
          if (!formId) return;
          var form = document.getElementById(formId);
          if (!form) return;
          form.requestSubmit ? form.requestSubmit() : form.submit();
        });
      });
    });

    document.addEventListener('keydown', function (e) {
      if (e.key !== 'Escape') return;
      document.querySelectorAll('[data-modal-overlay].is-open').forEach(function (o) {
        if (o.getAttribute('data-esc-close') === '0') return;
        closeOverlay(o);
      });
    });
  }

  function initPhotonSuggest() {
    var inputs = document.querySelectorAll('input[data-photon]');
    if (!inputs.length) return;

    function haversineKm(lat1, lon1, lat2, lon2) {
      var R = 6371;
      var dLat = (lat2 - lat1) * Math.PI / 180;
      var dLon = (lon2 - lon1) * Math.PI / 180;
      var a =
        Math.sin(dLat / 2) * Math.sin(dLat / 2) +
        Math.cos(lat1 * Math.PI / 180) * Math.cos(lat2 * Math.PI / 180) *
        Math.sin(dLon / 2) * Math.sin(dLon / 2);
      var c = 2 * Math.atan2(Math.sqrt(a), Math.sqrt(1 - a));
      return R * c;
    }

    function updateDistanceForForm(form) {
      if (!form) return;
      var out = form.querySelector('[data-distance-km]');
      if (!out) return;

      var fromLatEl = form.querySelector('input[name="from_lat"]');
      var fromLonEl = form.querySelector('input[name="from_lon"]');
      var toLatEl = form.querySelector('input[name="to_lat"]');
      var toLonEl = form.querySelector('input[name="to_lon"]');
      if (!fromLatEl || !fromLonEl || !toLatEl || !toLonEl) return;

      var fromLat = parseFloat(fromLatEl.value);
      var fromLon = parseFloat(fromLonEl.value);
      var toLat = parseFloat(toLatEl.value);
      var toLon = parseFloat(toLonEl.value);

      if (
        isNaN(fromLat) || isNaN(fromLon) ||
        isNaN(toLat) || isNaN(toLon)
      ) {
        out.value = '';
        return;
      }

      var km = haversineKm(fromLat, fromLon, toLat, toLon);
      var transport = form.querySelector('[data-transport-type]');
      var transportVal = transport ? String(transport.value || '') : '';
      if (transportVal === 'poradnia' || transportVal === 'miedzyszpitalna') {
        km = km * 2;
      }
      out.value = (Math.round(km * 10) / 10).toFixed(1);
    }

    function emitInput(el) {
      if (!el) return;
      try {
        el.dispatchEvent(new Event('input', { bubbles: true }));
        el.dispatchEvent(new Event('change', { bubbles: true }));
      } catch (e) {}
    }

    function removeList(wrapper) {
      var lists = wrapper.querySelectorAll('.suggest-list');
      lists.forEach(function (list) {
        if (list && list.parentNode) list.parentNode.removeChild(list);
      });
    }

    function showStatus(wrapper, text) {
      removeList(wrapper);
      var list = document.createElement('div');
      list.className = 'suggest-list';

      var empty = document.createElement('div');
      empty.className = 'suggest-empty';
      empty.textContent = String(text || '');
      list.appendChild(empty);

      wrapper.appendChild(list);
    }

    function showList(wrapper, items, kind, onPick) {
      removeList(wrapper);
      var list = document.createElement('div');
      list.className = 'suggest-list';

      if (!items.length) {
        var empty = document.createElement('div');
        empty.className = 'suggest-empty';
        empty.textContent = '[]';
        list.appendChild(empty);
      } else {
        var seen = {};
        items.slice(0, 6).forEach(function (it) {
          var row = document.createElement('div');
          row.className = 'suggest-item';
          var props = it.properties || {};
          var k = String(kind || '');
          var displayText = '';
          if (k === 'number') {
            displayText = props.housenumber || '';
          } else if (k === 'street') {
            displayText = props.street || props.road || props.name || '';
          } else if (k === 'city') {
            displayText = props.name || '';
          } else {
            displayText = props.name || '';
          }
          displayText = String(displayText || '').trim();
          if (!displayText) return;
          var key = displayText.toLowerCase();
          if (seen[key]) return;
          seen[key] = 1;

          row.textContent = displayText;
          row.addEventListener('click', function () {
            onPick(it);
            removeList(wrapper);
          });
          list.appendChild(row);
        });
      }

      wrapper.appendChild(list);
    }

    function fetchList(url, signal) {
      return fetch(url, { headers: { 'Accept': 'application/json' }, signal: signal })
        .then(function (r) { return r.text(); })
        .then(function (txt) {
          try {
            var data = JSON.parse(txt);
            return Array.isArray(data) ? data : [];
          } catch (e) {
            return [];
          }
        })
        .catch(function () {
          return [];
        });
    }

    function addressCity(a) {
      if (!a) return '';
      return a.city || a.town || a.village || a.municipality || a.county || '';
    }

    inputs.forEach(function (input) {
      var wrapper = input.closest('[data-photon-wrapper]') || input.closest('.suggest');
      if (!wrapper) return;

      var scope = input.closest('[data-photon-scope]') || wrapper;

      var latField = scope.querySelector('input[data-photon-lat]');
      var lonField = scope.querySelector('input[data-photon-lon]');
      var displayField = scope.querySelector('input[data-photon-display]');

      function field(name) {
        return scope.querySelector('[data-photon-field="' + name + '"]');
      }

      var currentAbort = null;
      var reqSeq = 0;
      var pendingTimer = null;

      function cancelPending() {
        if (pendingTimer) {
          window.clearTimeout(pendingTimer);
          pendingTimer = null;
        }
        if (currentAbort) {
          try { currentAbort.abort(); } catch (e) {}
        }
      }

      function run() {
        var q = String(input.value || '').trim();
        var kind = String(input.getAttribute('data-photon-kind') || '');
        var minLen = (kind === 'number') ? 1 : 3;
        if (q.length < minLen) {
          removeList(wrapper);
          return;
        }

        if (currentAbort) currentAbort.abort();
        currentAbort = new AbortController();
        var mySignal = currentAbort.signal;

        reqSeq++;
        var mySeq = reqSeq;

        var cityEl = field('city');
        var streetEl = field('street');
        var postcodeEl = field('postcode');
        var numberEl = field('number');

        var cityVal = cityEl && typeof cityEl.value === 'string' ? cityEl.value.trim() : '';
        var streetVal = streetEl && typeof streetEl.value === 'string' ? streetEl.value.trim() : '';

        if (kind === 'number' && (!cityVal || !streetVal)) {
          removeList(wrapper);
          return;
        }


        var base = (window.SWDTM && window.SWDTM.geocoderUrl) ? window.SWDTM.geocoderUrl : '/api/photon.php';
        var u = new URL(base, window.location.origin);
        u.searchParams.set('q', q);
        u.searchParams.set('type', kind);
        if (cityVal) u.searchParams.set('city', cityVal);
        if (streetVal) u.searchParams.set('street', streetVal);

        showStatus(wrapper, 'Szukam…');
        fetchList(u.toString(), mySignal)
          .then(function (data) {
            if (mySeq !== reqSeq) return;

            var items = Array.isArray(data) ? data.slice(0, 6) : [];
            showList(wrapper, items, kind, function (it) {
              if (!it || !it.properties) return;

              var props = it.properties;
              var geom = it.geometry || {};
              var coords = geom.coordinates || [];
              var lat = coords[1] || '';
              var lon = coords[0] || '';

              if (latField) latField.value = lat || '';
              if (lonField) lonField.value = lon || '';
              if (displayField) displayField.value = props.name || '';

              emitInput(latField);
              emitInput(lonField);

              var form = scope.closest('form');
              updateDistanceForForm(form);

              var city = props.city || props.town || props.village || props.municipality || props.county || '';
              var postcode = props.postcode || '';
              var road = props.street || props.road || props.pedestrian || props.footway || '';
              var house = props.housenumber || '';

              if (kind === 'city') {
                if (cityEl && city) cityEl.value = city;
                input.value = String(props.name || input.value || '');
                
                
                var streetInput = scope.querySelector('input[data-photon-kind="street"]');
                var numberInput = scope.querySelector('input[data-photon-kind="number"]');
                var flatInput = scope.querySelector('input[name*="_flat"]');
                
                if (streetInput) streetInput.disabled = false;
                if (numberInput) numberInput.disabled = false;
                if (flatInput) flatInput.disabled = false;
              } else if (kind === 'street') {
                if (streetEl && road) streetEl.value = road;
                if (cityEl && city && !cityEl.value) cityEl.value = city;
                if (postcodeEl && postcode && !String(postcodeEl.value || '').trim()) {
                  postcodeEl.value = postcode;
                }
                input.value = road || String(props.name || input.value || '');
                
                
                var numberInput = scope.querySelector('input[data-photon-kind="number"]');
                if (numberInput) numberInput.disabled = false;
              } else if (kind === 'number') {
                if (numberEl && house) numberEl.value = house;
                if (postcodeEl && postcode && !postcodeEl.value) postcodeEl.value = postcode;
                input.value = house;
              } else {
                input.value = String(props.name || input.value || '');
              }

              updateDistanceForForm(form);
            });
          })
          .catch(function () {
            if (mySeq !== reqSeq) return;
            showList(wrapper, [], kind, function () {});
          });
      }

      input.addEventListener('input', function () {
        var q = String(input.value || '').trim();
        if (q.length < 1) {
          cancelPending();
          removeList(wrapper);
          return;
        }

        if (pendingTimer) window.clearTimeout(pendingTimer);
        pendingTimer = window.setTimeout(function () {
          pendingTimer = null;
          run();
        }, 300);
      });

      input.addEventListener('blur', function () {
        window.setTimeout(function () {
          removeList(wrapper);
        }, 180);
      });

      if (String(input.getAttribute('data-photon-kind') || '') === 'number') {
        var tryResolveNumber = function () {
          var q = String(input.value || '').trim();
          if (!q) return;
          if (!latField || !lonField) return;
          if (String(latField.value || '').trim() !== '' && String(lonField.value || '').trim() !== '') {
            return;
          }
          run();
        };
        input.addEventListener('change', tryResolveNumber);
        input.addEventListener('blur', function () {
          window.setTimeout(tryResolveNumber, 0);
        });
      }

      document.addEventListener('click', function (e) {
        if (!wrapper.contains(e.target)) removeList(wrapper);
      });

      var form = scope.closest('form');
      if (form) {
        form.addEventListener('input', function (e) {
          var t = e.target;
          if (!t || !t.name) return;
          if (t.name === 'from_lat' || t.name === 'from_lon' || t.name === 'to_lat' || t.name === 'to_lon') {
            updateDistanceForForm(form);
          }
        });
        form.addEventListener('change', function (e) {
          var t = e.target;
          if (!t || !t.name) return;
          if (t.name === 'from_lat' || t.name === 'from_lon' || t.name === 'to_lat' || t.name === 'to_lon') {
            updateDistanceForForm(form);
          }
        });

        var transport = form.querySelector('[data-transport-type]');
        if (transport) {
          transport.addEventListener('change', function () {
            updateDistanceForForm(form);
          });
        }

        updateDistanceForForm(form);
      }
    });

  }

  function initIcd10Suggest() {
    var inputs = document.querySelectorAll('input[data-icd10]');
    if (!inputs.length) return;

    function removeList(wrapper) {
      var lists = wrapper.querySelectorAll('.suggest-list');
      lists.forEach(function (list) {
        if (list && list.parentNode) list.parentNode.removeChild(list);
      });
    }

    function showStatus(wrapper, text) {
      removeList(wrapper);
      var list = document.createElement('div');
      list.className = 'suggest-list';

      var empty = document.createElement('div');
      empty.className = 'suggest-empty';
      empty.textContent = String(text || '');
      list.appendChild(empty);

      wrapper.appendChild(list);
    }

    function showList(wrapper, items, onPick) {
      removeList(wrapper);
      var list = document.createElement('div');
      list.className = 'suggest-list';

      if (!items.length) {
        var empty = document.createElement('div');
        empty.className = 'suggest-empty';
        empty.textContent = 'Brak wyników';
        list.appendChild(empty);
      } else {
        items.slice(0, 8).forEach(function (it) {
          var row = document.createElement('div');
          row.className = 'suggest-item';
          var code = it && it.code ? String(it.code) : '';
          var name = it && it.name ? String(it.name) : '';
          row.textContent = code ? (code + ' — ' + name) : name;
          row.addEventListener('click', function () {
            onPick(it);
            removeList(wrapper);
          });
          list.appendChild(row);
        });
      }

      wrapper.appendChild(list);
    }

    function fetchList(q, signal) {
      var base = (window.SWDTM && window.SWDTM.icd10Url) ? window.SWDTM.icd10Url : '/api/icd10';
      var u = new URL(base, window.location.origin);
      u.searchParams.set('q', q);

      return fetch(u.toString(), { headers: { 'Accept': 'application/json' }, signal: signal })
        .then(function (r) { return r.text(); })
        .then(function (txt) {
          try {
            var data = JSON.parse(txt);
            return Array.isArray(data) ? data : [];
          } catch (e) {
            return [];
          }
        })
        .catch(function () {
          return [];
        });
    }

    inputs.forEach(function (input) {
      var wrapper = input.closest('[data-icd10-wrapper]') || input.closest('.suggest');
      if (!wrapper) return;

      var scope = input.closest('form') || document;
      var codeField = scope.querySelector('[data-icd10-code]');
      var nameField = scope.querySelector('[data-icd10-name]');
      var noneCheckbox = scope.querySelector('[data-icd10-none]');

      var currentAbort = null;
      var reqSeq = 0;
      var pendingTimer = null;

      function run() {
        var q = String(input.value || '').trim();
        if (q.length < 2) {
          removeList(wrapper);
          return;
        }

        if (currentAbort) currentAbort.abort();
        currentAbort = new AbortController();
        var mySignal = currentAbort.signal;

        reqSeq++;
        var mySeq = reqSeq;

        showStatus(wrapper, 'Szukam…');
        fetchList(q, mySignal)
          .then(function (items) {
            if (mySeq !== reqSeq) return;
            showList(wrapper, Array.isArray(items) ? items : [], function (it) {
              var code = it && it.code ? String(it.code) : '';
              var name = it && it.name ? String(it.name) : '';
              if (codeField) codeField.value = code;
              if (nameField) nameField.value = name;
              input.value = code ? (code + ' — ' + name) : name;
            });
          })
          .catch(function () {
            if (mySeq !== reqSeq) return;
            showList(wrapper, [], function () {});
          });
      }

      input.addEventListener('input', function () {
        if (codeField) codeField.value = '';
        if (nameField) nameField.value = '';

        var q = String(input.value || '').trim();
        if (q.length < 1) {
          removeList(wrapper);
          return;
        }

        if (pendingTimer) window.clearTimeout(pendingTimer);
        pendingTimer = window.setTimeout(function () {
          pendingTimer = null;
          run();
        }, 250);
      });

      input.addEventListener('blur', function () {
        window.setTimeout(function () {
          removeList(wrapper);
        }, 180);
      });

      document.addEventListener('click', function (e) {
        if (!wrapper.contains(e.target)) removeList(wrapper);
      });

      if (noneCheckbox) {
        var syncDisabled = function () {
          var isNone = noneCheckbox.checked;
          input.disabled = isNone;
          if (isNone) {
            input.value = '';
            if (codeField) codeField.value = '';
            if (nameField) nameField.value = '';
            removeList(wrapper);
          }
        };
        noneCheckbox.addEventListener('change', syncDisabled);
        syncDisabled();
      }
    });
  }

  function initToasts() {
    var container = document.querySelector('[data-toasts]');
    if (!container) return;

    var toasts = container.querySelectorAll('[data-toast]');
    toasts.forEach(function (toast) {
      var close = toast.querySelector('[data-toast-close]');
      if (close) {
        close.addEventListener('click', function () {
          hideToast(toast);
        });
      }

      var delay = parseInt(toast.getAttribute('data-toast-timeout') || '4500', 10);
      if (!isNaN(delay) && delay > 0) {
        window.setTimeout(function () {
          hideToast(toast);
        }, delay);
      }
    });
  }

  function initAll() {
    initToasts();
    initTimePicker();
    initAutoSubmitFilters();
    initOrderMeta();
    initConfirmLeave();
    initModals();
    initDispatcherRealtime();
    initPhotonSuggest();
    initIcd10Suggest();
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initAll);
  } else {
    initAll();
  }
})();