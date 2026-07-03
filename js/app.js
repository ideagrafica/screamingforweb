// ============================================================
// SCREAMINGFORWEB — Frontend Application
// ============================================================

(function () {
    'use strict';

    var BASE = (window.location.pathname.substring(0, window.location.pathname.lastIndexOf('/') + 1) || '/');
    var AJAX = BASE + 'ajax.php';

    function $(sel, ctx) { return (ctx || document).querySelector(sel); }
    function $$(sel, ctx) { return Array.from((ctx || document).querySelectorAll(sel)); }
    function qs(id) { return document.getElementById(id); }

    function api(action, opts, timeoutMs, extraParams) {
        var url = AJAX + '?action=' + encodeURIComponent(action);
        if (extraParams) {
            for (var k in extraParams) {
                if (Object.prototype.hasOwnProperty.call(extraParams, k)) {
                    url += '&' + encodeURIComponent(k) + '=' + encodeURIComponent(extraParams[k]);
                }
            }
        }
        timeoutMs = timeoutMs || 0;

        var controller = timeoutMs > 0 ? new AbortController() : null;
        var fetchOpts = {
            headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
            ...opts
        };
        if (controller) fetchOpts.signal = controller.signal;

        var fetchPromise = fetch(url, fetchOpts).then(function (r) {
            if (!r.ok) throw new Error('HTTP ' + r.status + ' - ' + r.statusText);
            return r.text().then(function (text) {
                try { return JSON.parse(text); }
                catch (e) { throw new Error('Invalid JSON: ' + text.substring(0, 200)); }
            });
        });

        if (controller) {
            var tid = setTimeout(function () { controller.abort(); }, timeoutMs);
            return fetchPromise.finally(function () { clearTimeout(tid); });
        }

        return fetchPromise;
    }

    function getFormValues(form) {
        var data = {};
        var fields = form.querySelectorAll('[name]');
        for (var i = 0; i < fields.length; i++) {
            var f = fields[i];
            if (f.name) data[f.name] = f.value;
        }
        return data;
    }

    // --- CREATE PROJECT ---
    var createForm = qs('create-project-form');
    if (createForm) {
        createForm.addEventListener('submit', function (e) {
            e.preventDefault();
            var btn = createForm.querySelector('button[type="submit"]');
            btn.disabled = true;
            btn.textContent = 'Creating...';

            api('create-project', {
                method: 'POST',
                body: JSON.stringify(getFormValues(createForm))
            }).then(function (res) {
                if (res.success) {
                    window.location.reload();
                } else {
                    alert(res.error || 'Error creating project');
                    btn.disabled = false;
                    btn.textContent = 'Create Project';
                }
            }).catch(function (err) {
                alert('ERROR: ' + err.message + '\nCheck browser console (F12) for details.');
                console.error('Create project failed:', err);
                btn.disabled = false;
                btn.textContent = 'Create Project';
            });
        });
    }

    // --- DELETE PROJECT ---
    $$('.btn-delete-project').forEach(function (btn) {
        btn.addEventListener('click', function () {
            if (!confirm('Delete this project and all its scans? This cannot be undone.')) return;
            var projectId = btn.dataset.projectId;
            btn.disabled = true;
            btn.textContent = '...';

            api('delete-project', {
                method: 'POST',
                body: JSON.stringify({ project_id: projectId })
            }).then(function (res) {
                if (res.success) {
                    window.location.reload();
                } else {
                    alert(res.error || 'Error deleting project');
                    btn.disabled = false;
                    btn.textContent = 'Delete';
                }
            }).catch(function (err) {
                alert('Connection error: ' + err.message);
                btn.disabled = false;
                btn.textContent = 'Delete';
            });
        });
    });

    // --- DELETE SESSION ---
    $$('.btn-delete-session').forEach(function (btn) {
        btn.addEventListener('click', function () {
            if (!confirm('Delete this scan session? Results will be permanently removed.')) return;
            var sessionId = btn.dataset.sessionId;
            btn.disabled = true;
            btn.textContent = '...';

            api('delete-session', {
                method: 'POST',
                body: JSON.stringify({ session_id: sessionId })
            }).then(function (res) {
                if (res.success) {
                    window.location.reload();
                } else {
                    alert(res.error || 'Error deleting session');
                    btn.disabled = false;
                    btn.textContent = 'Delete';
                }
            }).catch(function (err) {
                alert('Connection error: ' + err.message);
                btn.disabled = false;
                btn.textContent = 'Delete';
            });
        });
    });

    // --- START SCAN ---
    var scanForm = qs('start-scan-form');
    var progressContainer = qs('scan-progress');
    var progressFill = qs('progress-fill');
    var progressText = qs('progress-text');
    var scanStatus = qs('scan-status');

    if (scanForm) {
        scanForm.addEventListener('submit', function (e) {
            e.preventDefault();
            var btn = scanForm.querySelector('button[type="submit"]');
            var input = scanForm.querySelector('input[name="root_url"]');
            var url = input.value.trim();

            if (!url) { alert('Please enter a URL'); return; }
            if (!/^https?:\/\//i.test(url)) {
                alert('URL must start with http:// or https://');
                return;
            }

            btn.disabled = true;
            btn.textContent = 'Starting...';
            if (progressContainer) progressContainer.classList.remove('hidden');

            api('start-scan', {
                method: 'POST',
                body: JSON.stringify({
                    project_id: scanForm.dataset.projectId,
                    root_url: url
                })
            }).then(function (res) {
                if (res.success) {
                    btn.textContent = 'Scanning...';
                    pollQueue(res.session_id, btn, url);
                } else {
                    alert(res.error || 'Error starting scan');
                    btn.disabled = false;
                    btn.textContent = 'Start Scan';
                    if (progressContainer) progressContainer.classList.add('hidden');
                }
            }).catch(function (err) {
                alert('Connection error: ' + err.message);
                btn.disabled = false;
                btn.textContent = 'Start Scan';
                if (progressContainer) progressContainer.classList.add('hidden');
            });
        });
    }

    function pollQueue(sessionId, btn, rootUrl) {
        var retries = 0;
        var maxRetries = 5;
        var startTime = Date.now();
        var workingTimer = null;

        function updateWorkingMsg() {
            var elapsed = Math.floor((Date.now() - startTime) / 1000);
            var mins = Math.floor(elapsed / 60);
            var secs = elapsed % 60;
            if (progressText) {
                progressText.textContent = 'Working... (' + mins + 'm ' + secs + 's)';
            }
        }

        function tick() {
            // Show working indicator while waiting for response
            if (progressFill) progressFill.classList.add('animate-pulse');
            if (progressText) progressText.textContent = 'Contacting server...';
            workingTimer = setInterval(updateWorkingMsg, 3000);

            api('crawl-batch', {}, 120000, { session_id: sessionId })
                .then(function (data) {
                    clearInterval(workingTimer);
                    if (progressFill) progressFill.classList.remove('animate-pulse');
                    retries = 0;

                    // Handle session failure
                    if (data.failed) {
                        if (progressText) progressText.textContent = 'Failed: ' + (data.reason || 'Unknown error');
                        if (scanStatus) { scanStatus.textContent = 'Failed'; scanStatus.style.color = '#ef4444'; }
                        if (progressFill) progressFill.style.width = '100%';
                        if (btn) { btn.disabled = false; btn.textContent = 'Retry'; }
                        console.warn('Scan failed, reloading...');
                        setTimeout(function () { window.location.reload(); }, 2000);
                        return;
                    }

                    var processed = data.processed || 0;
                    var total = data.total || 0;
                    if (progressFill && total > 0) {
                        var pct = Math.min(100, Math.round((processed / total) * 100));
                        progressFill.style.width = pct + '%';
                    }
                    if (progressText) {
                        progressText.textContent = processed + ' / ' + total + ' URLs processed';
                    }
                    if (scanStatus) {
                        scanStatus.textContent = data.has_more ? 'Scanning...' : 'Complete!';
                        if (!data.has_more) scanStatus.style.color = '#10b981';
                    }

                    if (data.has_more) {
                        setTimeout(tick, 300);
                    } else {
                        if (btn) { btn.disabled = false; btn.textContent = 'Start Scan'; }
                        if (progressFill) progressFill.style.width = '100%';
                        if (progressText) progressText.textContent = 'Scan complete — ' + processed + ' URLs found';
                        console.log('Scan complete, reloading...');
                        setTimeout(function () { window.location.reload(); }, 800);
                    }
                })
                .catch(function (err) {
                    clearInterval(workingTimer);
                    if (progressFill) progressFill.classList.remove('animate-pulse');
                    retries++;
                    if (retries <= maxRetries) {
                        if (progressText) progressText.textContent = 'Retrying (' + retries + '/' + maxRetries + ')... ' + err.message;
                        setTimeout(tick, 2000);
                    } else {
                        if (progressText) progressText.textContent = 'Network error - reloading page...';
                        if (progressFill) progressFill.style.width = '100%';
                        if (scanStatus) { scanStatus.textContent = 'Failed'; scanStatus.style.color = '#ef4444'; }
                        if (btn) { btn.disabled = false; btn.textContent = 'Retry'; }
                        console.error('Crawl batch failed after retries, reloading...');
                        setTimeout(function () { window.location.reload(); }, 2000);
                    }
                });
        }

        tick();
    }

    // --- TABLE SEARCH / FILTER ---
    var searchInput = qs('table-search');
    if (searchInput) {
        var table = document.getElementById(searchInput.dataset.target);
        if (table) {
            searchInput.addEventListener('input', function () {
                var q = this.value.toLowerCase();
                $$('tbody tr', table).forEach(function (row) {
                    row.style.display = row.textContent.toLowerCase().indexOf(q) > -1 ? '' : 'none';
                });
            });
        }
    }

    // --- TABLE COLUMN SORTING ---
    $$('th[data-sort]').forEach(function (th) {
        th.addEventListener('click', function () {
            var table = th.closest('table');
            var tbody = table.querySelector('tbody');
            var colIdx = $$('th', table).indexOf(th);
            var key = th.dataset.sort;
            var rows = $$('tr', tbody);

            var isAsc = th.classList.contains('sort-asc');
            $$('th', table).forEach(function (h) { h.classList.remove('sort-asc', 'sort-desc'); });
            th.classList.add(isAsc ? 'sort-desc' : 'sort-asc');

            rows.sort(function (a, b) {
                var va = a.cells[colIdx] ? a.cells[colIdx].textContent.trim() : '';
                var vb = b.cells[colIdx] ? b.cells[colIdx].textContent.trim() : '';
                if (key === 'status_code') return (parseInt(va, 10) || 0) - (parseInt(vb, 10) || 0);
                return va.localeCompare(vb, undefined, { numeric: true });
            });

            if (th.classList.contains('sort-desc')) rows.reverse();
            rows.forEach(function (r) { tbody.appendChild(r); });
        });
    });

    // --- EXPORT CSV ---
    var exportBtn = qs('btn-export-csv');
    if (exportBtn) {
        exportBtn.addEventListener('click', function () {
            var sessionId = exportBtn.dataset.sessionId;
            if (sessionId) {
                window.location.href = AJAX + '?action=export-csv&session_id=' + encodeURIComponent(sessionId);
            }
        });
    }

    // --- STATUS CODE EXPORT BUTTONS ---
    var exportButtons = $$('button[data-status-code]');
    var exportButtonsContainer = qs('export-buttons-container');
    var footerText = qs('footer-text');
    if (exportButtons && exportButtonsContainer && footerText) {
        exportButtons.forEach(function (btn) {
            btn.addEventListener('click', function () {
                var sessionId = this.dataset.sessionId;
                var statusCode = this.dataset.statusCode;
                if (sessionId) {
                    if (statusCode) {
                        window.location.href = AJAX + '?action=export-csv&session_id=' + encodeURIComponent(sessionId) + '&status_code=' + encodeURIComponent(statusCode);
                    } else {
                        window.location.href = AJAX + '?action=export-csv&session_id=' + encodeURIComponent(sessionId);
                    }
                }
            });
        });
    }

    // --- STATUS FILTER BUTTONS ---
    var filterBtns = $$('.status-filter-btn');
    var activeFilterBtns = [];
    var currentFilterValue = 0;
    var resultsTable = qs('results-table');
    var tableBody = resultsTable ? resultsTable.querySelector('tbody') : null;

    function updateFilterUI(statusCode) {
        currentFilterValue = statusCode;
        activeFilterBtns.forEach(function (btn) {
            btn.classList.remove('brutal-btn-primary', 'brutal-ring-2', 'brutal-ring-offset-2');
        });
        activeFilterBtns = [];
        $$('.status-filter-btn').forEach(function (btn) {
            if (parseInt(btn.dataset.statusCode) === statusCode) {
                btn.classList.add('brutal-btn-primary', 'brutal-ring-2', 'brutal-ring-offset-2');
                activeFilterBtns.push(btn);
            }
        });
    }

    function applyStatusFilter(statusCode) {
        updateFilterUI(statusCode);

        if (tableBody) {
            var visibleRows = 0;
            var rows = $$('tr', tableBody);
            rows.forEach(function (row) {
                var statusCodeCell = row.cells[1] ? row.cells[1].textContent : '';
                var code = parseInt(statusCodeCell) || 0;

                if (statusCode === 0 || code === statusCode) {
                    row.style.display = '';
                    visibleRows++;
                } else {
                    row.style.display = 'none';
                }
            });

            if (footerText) {
                footerText.textContent = 'SCREAMINGFORWEB — Internal Use Only — ' + visibleRows + ' URLs visible';
            }
        }
    }

    function showExportButtons() {
        if (exportButtonsContainer) {
            exportButtonsContainer.classList.remove('hidden');
        }
        if (footerText) {
            var visibleCount = footerText.textContent.replace(/.*\s—\s/, '').split(' ')[0];
            footerText.textContent = 'SCREAMINGFORWEB — Internal Use Only — Filter applied — ' + visibleCount + ' URLs visible';
        }
    }

    function hideExportButtons() {
        if (exportButtonsContainer) {
            exportButtonsContainer.classList.add('hidden');
        }
        if (footerText) {
            footerText.textContent = 'SCREAMINGFORWEB — Internal Use Only';
        }
    }

    // Initialize filter state to "All"
    var allFilterBtn = qs('.status-filter-btn[data-status-code="0"]');
    if (allFilterBtn && tableBody) {
        applyStatusFilter(0);
        hideExportButtons();
    }

    // Handle filter clicks
    if (filterBtns) {
        filterBtns.forEach(function (btn) {
            btn.addEventListener('click', function () {
                var statusCode = parseInt(this.dataset.statusCode);
                applyStatusFilter(statusCode);
                
                if (statusCode > 0) {
                    showExportButtons();
                } else {
                    hideExportButtons();
                }
            });
        });
    }

    // --- INITIAL STATUS COUNT (maintain current footer) ---
    if (tableBody) {
        $$('tr', tableBody).forEach(function (row) {
            var statusCodeCell = row.cells[1] ? row.cells[1].textContent : '';
            var code = parseInt(statusCodeCell) || 0;

            if (code >= 200 && code < 400) {
                var okBadge = row.querySelector('.brutal-badge-ok');
                if (okBadge) okBadge.style.display = 'inline-block';
            } else {
                var koBadge = row.querySelector('.brutal-badge-ko');
                if (koBadge) koBadge.style.display = 'inline-block';
            }
        });
    }

})();
