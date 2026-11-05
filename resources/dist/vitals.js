(function () {
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

  document.addEventListener('click', function (event) {
    var target = event.target.closest('[data-run],[data-filter],[data-refresh]');
    if (!target) return;
    if (target.dataset.run) run(target);
    else if (target.dataset.filter) { filter = target.dataset.filter; applyFilter(); }
    else refresh();
  });

  document.addEventListener('input', function (event) {
    if (event.target.matches('[data-search-input]')) { query = event.target.value.toLowerCase(); applyFilter(); }
  });

  setInterval(refresh, (parseInt(main.dataset.refresh, 10) || 30) * 1000);
})();
