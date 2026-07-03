<?php
// ============================================================
// SCREAMINGFORWEB — Project Workspace
// ============================================================

require_once __DIR__ . '/config.php';

$projectId = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($projectId <= 0) {
    header('Location: index.php');
    exit;
}

$db = getDB();

$stmt = $db->prepare("SELECT id, project_name, client_name FROM projects WHERE id = ?");
$stmt->execute([$projectId]);
$project = $stmt->fetch();

if (!$project) {
    header('Location: index.php');
    exit;
}

$stmt = $db->prepare("SELECT id, root_url, status, failed_reason, url_found, scanned_at FROM scan_sessions WHERE project_id = ? ORDER BY scanned_at DESC");
$stmt->execute([$projectId]);
$sessions = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex, nofollow">
    <title><?= htmlspecialchars($project['project_name']) ?> — SCREAMINGFORWEB</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="css/app.css">
</head>
<body>

<div class="max-w-5xl mx-auto p-4 sm:p-6 lg:p-8">

    <!-- Header -->
    <div class="flex items-center justify-between mb-6">
        <div>
            <a href="index.php" class="text-xs font-bold uppercase tracking-widest opacity-40 hover:opacity-100 transition">&larr; Dashboard</a>
            <h1 class="text-3xl font-extrabold uppercase tracking-tight mt-1">
                <?= htmlspecialchars($project['project_name']) ?>
            </h1>
            <p class="text-sm font-semibold opacity-60"><?= htmlspecialchars($project['client_name']) ?></p>
        </div>
    </div>

    <!-- Crawler Input -->
    <div class="brutal-card p-5 mb-8">
        <h2 class="text-lg font-extrabold uppercase mb-3">Crawl Target</h2>
        <form id="start-scan-form" data-project-id="<?= $projectId ?>">
            <div class="flex flex-wrap items-end gap-3">
                <div class="flex-1 min-w-[300px]">
                    <label class="block text-xs font-bold uppercase mb-1">Root URL</label>
                    <input type="url" name="root_url" class="brutal-input" placeholder="https://example.com" required>
                </div>
                <button type="submit" class="brutal-btn brutal-btn-success flex-shrink-0 px-6">Start Scan</button>
            </div>
        </form>

        <!-- Progress -->
        <div id="scan-progress" class="hidden mt-4">
            <div class="progress-bar">
                <div id="progress-fill" class="progress-fill" style="width:0%"></div>
            </div>
            <div class="flex justify-between mt-2 text-xs font-bold">
                <span id="progress-text">0 / 0 URLs processed</span>
                <span id="scan-status">Scanning...</span>
            </div>
        </div>
    </div>

    <!-- Scan History -->
    <div class="brutal-card">
        <div class="p-5 border-b-2 border-black">
            <h2 class="text-lg font-extrabold uppercase">Scan History</h2>
        </div>

        <?php if (empty($sessions)): ?>
            <div class="p-8 text-center">
                <p class="font-bold text-lg opacity-40">No scans yet</p>
                <p class="text-sm opacity-30 mt-1">Enter a URL above to start crawling.</p>
            </div>
        <?php else: ?>
            <div class="overflow-x-auto">
                <table class="brutal-table">
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>Target URL</th>
                            <th>URLs</th>
                            <th>Status</th>
                            <th style="text-align:center;width:180px">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($sessions as $s): ?>
                        <tr>
                            <td class="font-mono text-sm whitespace-nowrap"><?= date('Y-m-d H:i', strtotime($s['scanned_at'])) ?></td>
                            <td><span class="truncate-url font-mono text-sm"><?= htmlspecialchars($s['root_url']) ?></span></td>
                            <td class="font-mono text-sm"><?= (int)$s['url_found'] ?></td>
                            <td>
                                <span class="brutal-badge brutal-badge-<?= htmlspecialchars($s['status']) ?>">
                                    <span class="status-dot <?= htmlspecialchars($s['status']) ?>"></span>
                                    <?= htmlspecialchars($s['status']) ?>
                                </span>
                                <?php if ($s['status'] === 'failed' && !empty($s['failed_reason'])): ?>
                                    <div class="text-xs text-red-600 font-bold mt-1 max-w-[200px] leading-tight" title="<?= htmlspecialchars($s['failed_reason']) ?>">
                                        <?= htmlspecialchars(mb_substr($s['failed_reason'], 0, 60)) ?><?= mb_strlen($s['failed_reason']) > 60 ? '...' : '' ?>
                                    </div>
                                <?php endif; ?>
                            </td>
                            <td style="text-align:center">
                                <a href="scan-details.php?id=<?= (int)$s['id'] ?>" class="brutal-btn text-xs px-3 py-1.5 inline-flex">Details</a>
                                <button class="brutal-btn brutal-btn-danger text-xs px-3 py-1.5 btn-delete-session inline-flex" data-session-id="<?= (int)$s['id'] ?>">Delete</button>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>

    <!-- Footer -->
    <p class="text-center text-xs font-bold uppercase tracking-widest mt-8 opacity-30">
        SCREAMINGFORWEB — Internal Use Only
    </p>
    <p class="text-center text-xs font-bold uppercase tracking-widest mt-2 opacity-40">
        Software sviluppato con licenza open source in Lecce da Marco De Sangro
    </p>
</div>

<script src="js/app.js"></script>
</body>
</html>
