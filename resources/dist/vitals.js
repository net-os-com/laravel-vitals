(function () {
  var root = document.documentElement, modes = ['system', 'light', 'dark'];
  var labels = { system: 'System', light: 'Light', dark: 'Dark' };
  function theme() { return root.dataset.theme || 'system'; }
  function showTheme() { var label = document.querySelector('[data-theme-label]'); if (label) label.textContent = labels[theme()]; }
  showTheme();
  document.addEventListener('click', function (event) {
    if (!event.target.closest('[data-theme-toggle]')) return;
    var next = modes[(modes.indexOf(theme()) + 1) % modes.length];
    if (next === 'system') delete root.dataset.theme; else root.dataset.theme = next;
    try { if (next === 'system') localStorage.removeItem('vitals-theme'); else localStorage.setItem('vitals-theme', next); } catch (e) {}
    showTheme();
  });

  var main = document.getElementById('vitals-main');
  if (!main) return;
  var token = document.querySelector('meta[name=csrf-token]').content;
  var filter = 'all', query = '', poll = null;

  function applyFilter() {
    main.querySelectorAll('[data-tenant-row]').forEach(function (row) {
      var problem = row.dataset.problem === '1';
      row.hidden = row.dataset.search.indexOf(query) < 0 || (filter === 'problems' && !problem) || (filter === 'healthy' && problem);
    });
    var empty = main.querySelector('[data-empty]');
    if (empty) empty.hidden = !!main.querySelector('[data-tenant-row]:not([hidden])');
    main.querySelectorAll('[data-filter]').forEach(function (chip) { chip.classList.toggle('on', chip.dataset.filter === filter); });
    var search = main.querySelector('[data-search-input]');
    if (search && search.value !== query) search.value = query;
  }

  function refresh() {
    if (document.activeElement && document.activeElement.matches('[data-search-input]')) return Promise.resolve();
    return fetch(location.href, { headers: { Accept: 'text/html' } }).then(function (response) {
      return response.ok ? response.text() : null;
    }).then(function (html) {
      if (!html) return;
      var next = new DOMParser().parseFromString(html, 'text/html').getElementById('vitals-main');
      if (next) { main.innerHTML = next.innerHTML; applyFilter(); }
    });
  }

  function lastRuns() {
    return Array.prototype.map.call(main.querySelectorAll('[data-last-run]'), function (el) { return el.dataset.lastRun; }).join();
  }

  function run(button) {
    button.disabled = true;
    button.textContent = 'Queued';
    var before = lastRuns(), tries = 0;
    fetch(button.dataset.run, { method: 'POST', headers: { 'X-CSRF-TOKEN': token, Accept: 'application/json' } }).then(function () {
      clearInterval(poll);
      poll = setInterval(function () {
        tries++;
        refresh().then(function () { if (lastRuns() !== before || tries > 30) clearInterval(poll); });
      }, 2000);
    });
  }

  var dialog = document.getElementById('vitals-mute');
  var form = dialog && dialog.querySelector('[data-mute-form]');
  var muteError = dialog && dialog.querySelector('[data-mute-error]');

  function openMute(target) {
    var data = JSON.parse(target.dataset.mute);
    form.reset();
    form.elements.scope.value = data.scope;
    form.elements.check.value = data.check || '';
    form.elements.tenant.value = data.tenant || '';
    dialog.querySelector('[data-mute-title]').textContent = data.title;
    dialog.querySelector('[data-mute-all-row]').hidden = !data.allTenants;
    muteError.textContent = '';
    dialog.showModal();
  }

  function send(url, method, body) {
    return fetch(url, { method: method, body: body, headers: { 'X-CSRF-TOKEN': token, Accept: 'application/json' } });
  }

  if (form) {
    form.addEventListener('submit', function (event) {
      event.preventDefault();
      var body = new FormData(form);
      if (body.get('all_tenants')) body.delete('tenant');
      send(form.getAttribute('action'), 'POST', body).then(function (response) {
        if (response.ok) { dialog.close(); return refresh(); }
        return response.json().then(function (json) {
          var errors = json.errors || {};
          var first = Object.keys(errors)[0];
          muteError.textContent = first ? errors[first][0] : (json.message || 'Could not mute this check.');
        });
      });
    });
  }

  document.addEventListener('click', function (event) {
    var target = event.target.closest('[data-run],[data-filter],[data-refresh],[data-mute],[data-unmute],[data-mute-cancel]');
    if (!target) return;
    if (target.dataset.run) run(target);
    else if (target.dataset.filter) { filter = target.dataset.filter; applyFilter(); }
    else if (target.dataset.mute) openMute(target);
    else if (target.dataset.unmute) { target.disabled = true; send(target.dataset.unmute, 'DELETE').then(refresh); }
    else if (target.hasAttribute('data-mute-cancel')) dialog.close();
    else refresh();
  });

  document.addEventListener('input', function (event) {
    if (event.target.matches('[data-search-input]')) { query = event.target.value.toLowerCase(); applyFilter(); }
  });

  setInterval(refresh, (parseInt(main.dataset.refresh, 10) || 30) * 1000);
})();
