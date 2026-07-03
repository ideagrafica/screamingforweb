<?php
// ============================================================
// SCREAMINGFORWEB — Scan Analytics Dashboard
// ============================================================

require_once __DIR__ . '/config.php';

$sessionId = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($sessionId <= 0) {
    header('Location: index.php');
    exit;
}

$db = getDB();

$stmt = $db->prepare("
    SELECT ss.*, p.project_name, p.client_name
    FROM scan_sessions ss
    JOIN projects p ON p.id = ss.project_id
    WHERE ss.id = ?
");
$stmt->execute([$sessionId]);
$session = $stmt->fetch();

if (!$session) {
    header('Location: index.php');
    exit;
}

// Fetch all results
$stmt = $db->prepare("SELECT id, url, status_code, title, description FROM scan_results WHERE session_id = ? ORDER BY id ASC");
$stmt->execute([$sessionId]);
$results = $stmt->fetchAll();

// KPI counts
$total    = count($results);
$okCount  = 0;
$koCount  = 0;
foreach ($results as $r) {
    $code = (int)$r['status_code'];
    if ($code >= 200 && $code < 400) {
        $okCount++;
    } else {
        $koCount++;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex, nofollow">
    <title>Scan Details — <?= htmlspecialchars($session['project_name']) ?> — SCREAMINGFORWEB</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="css/app.css">
</head>
<body>

<div class="max-w-6xl mx-auto p-4 sm:p-6 lg:p-8">

    <!-- Header -->
    <div class="flex items-start justify-between mb-6">
        <div>
            <a href="project.php?id=<?= (int)$session['project_id'] ?>" class="text-xs font-bold uppercase tracking-widest opacity-40 hover:opacity-100 transition">&larr; Back to Project</a>
            <h1 class="text-3xl font-extrabold uppercase tracking-tight mt-1">
                Scan Details
            </h1>
            <p class="text-sm font-semibold opacity-60"><?= htmlspecialchars($session['project_name']) ?> &middot; <?= htmlspecialchars($session['client_name']) ?></p>
            <p class="text-xs font-mono opacity-40 mt-1"><?= htmlspecialchars($session['root_url']) ?> &middot; <?= date('Y-m-d H:i', strtotime($session['scanned_at'])) ?></p>
        </div>
        <button id="btn-export-csv" data-session-id="<?= $sessionId ?>" class="brutal-btn brutal-btn-success flex-shrink-0">
            Export CSV
        </button>
    </div>

    <!-- KPI Cards -->
    <div class="grid grid-cols-3 gap-4 mb-8">
        <div class="brutal-card kpi-card">
            <div class="kpi-value" style="color:var(--brutal-accent)"><?= $total ?></div>
            <div class="kpi-label">Total URLs</div>
        </div>
        <div class="brutal-card kpi-card">
            <div class="kpi-value" style="color:var(--brutal-green)"><?= $okCount ?></div>
            <div class="kpi-label">OK (2xx/3xx)</div>
        </div>
        <div class="brutal-card kpi-card">
            <div class="kpi-value" style="color:var(--brutal-red)"><?= $koCount ?></div>
            <div class="kpi-label">Broken / Non-2xx</div>
        </div>
    </div>

    <!-- Search -->
    <div class="mb-4">
        <input type="text" id="table-search" data-target="results-table" class="brutal-input" placeholder="Search in results...">
    </div>

    <!-- Export Buttons (VISIBLE ONLY WHEN FILTERS ARE APPLIED) -->
    <div id="export-buttons-container" class="mb-4 hidden">
        <div class="flex flex-wrap items-center gap-3">
            <span class="text-xs font-bold uppercase opacity-60 mr-2">Export filtered results:</span>

            <button id="btn-export-all" data-session-id="<?= $sessionId ?>" data-status-code="0" class="brutal-btn brutal-btn-success text-xs px-4 py-2">
                Export All URLs
            </button>

            <button id="btn-export-200" data-session-id="<?= $sessionId ?>" data-status-code="200" class="brutal-btn brutal-btn-success text-xs px-4 py-2">
                Export 2xx
            </button>

            <button id="btn-export-300" data-session-id="<?= $sessionId ?>" data-status-code="300" class="brutal-btn brutal-btn-warning text-xs px-4 py-2">
                Export 3xx
            </button>

            <button id="btn-export-404" data-session-id="<?= $sessionId ?>" data-status-code="404" class="brutal-btn brutal-btn-danger text-xs px-4 py-2">
                Export 4xx
            </button>

            <button id="btn-export-500" data-session-id="<?= $sessionId ?>" data-status-code="500" class="brutal-btn brutal-btn-danger text-xs px-4 py-2">
                Export 5xx
            </button>
        </div>
    </div>

<!-- Results Table -->
    <div class="brutal-card">
            <div class="p-5 border-b-2 border-black">
                <h2 class="text-lg font-extrabold uppercase">Results</h2>
                <span class="text-xs font-mono font-bold opacity-50 mt-1 block"><?= $total ?> URLs</span>
            </div>

            <!-- Filter by Status in Table Header -->
            <?php if ($total > 0): ?>
                <div class="px-5 pb-3 flex items-center gap-3">
                    <label class="text-xs font-bold uppercase opacity-60">Filter by status:</label>

                    <button class="status-filter-btn brutal-btn brutal-btn-primary text-xs px-3 py-1.5 brutal-ring-2 brutal-ring-offset-2" data-status-code="0" data-status-name="All">
                        All
                    </button>

                    <button class="status-filter-btn brutal-btn brutal-btn-success text-xs px-3 py-1.5" data-status-code="200" data-status-name="[2xx]">
                        2xx
                    </button>

                    <button class="status-filter-btn brutal-btn brutal-btn-warning text-xs px-3 py-1.5" data-status-code="300" data-status-name="[3xx]">
                        3xx
                    </button>

                    <button class="status-filter-btn brutal-btn brutal-btn-danger text-xs px-3 py-1.5" data-status-code="404" data-status-name="[4xx]">
                        4xx
                    </button>

                    <button class="status-filter-btn brutal-btn brutal-btn-danger text-xs px-3 py-1.5" data-status-code="500" data-status-name="[5xx]">
                        5xx
                    </button>
                </div>
            <?php endif; ?>

        <?php if (empty($results)): ?>
            <div class="p-8 text-center">
                <p class="font-bold text-lg opacity-40">No results</p>
                <p class="text-sm opacity-30 mt-1">Run a scan to see results here.</p>
            </div>
        <?php else: ?>
            <div class="overflow-x-auto">
                <table id="results-table" class="brutal-table">
                    <thead>
                        <tr>
                            <th data-sort="url">URL <span class="sort-icon"></span></th>
                            <th data-sort="status_code" style="width:100px">Status <span class="sort-icon"></span></th>
                            <th data-sort="title" style="width:250px">Title <span class="sort-icon"></span></th>
                            <th data-sort="description" style="width:300px">Description <span class="sort-icon"></span></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($results as $r): ?>
                        <tr>
                            <td>
                                <a href="<?= htmlspecialchars($r['url']) ?>" target="_blank" rel="noopener" class="truncate-url font-mono text-xs hover:underline" style="color:var(--brutal-accent)">
                                    <?= htmlspecialchars($r['url']) ?>
                                </a>
                            </td>
                            <td>
                                <?php $code = (int)$r['status_code']; ?>
                                <span class="brutal-badge <?= ($code >= 200 && $code < 400) ? 'brutal-badge-ok' : 'brutal-badge-ko' ?> font-mono">
                                    <?= $code > 0 ? $code : 'N/A' ?>
                                </span>
                            </td>
                            <td class="text-sm font-medium"><?= htmlspecialchars($r['title'] ?? '') ?></td>
                            <td class="text-xs opacity-70"><?= htmlspecialchars(mb_substr($r['description'] ?? '', 0, 200)) ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>

    <!-- Footer -->
        <p class="text-center text-xs font-bold uppercase tracking-widest mt-8 opacity-30" id="footer-text">
            SCREAMINGFORWEB — Internal Use Only
        </p>
        <p class="text-center text-xs font-bold uppercase tracking-widest mt-2 opacity-40">
            Software sviluppato con licenza open source in Lecce da Marco De Sangro
        </p>
    </div>

    <script src="js/app.js"></script>
</body>
</html>
