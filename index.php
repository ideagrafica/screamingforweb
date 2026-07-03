<?php
// ============================================================
// SCREAMINGFORWEB — Dashboard (Project List & Creation)
// ============================================================

require_once __DIR__ . '/config.php';

$db = getDB();
$projects = $db->query("SELECT id, project_name, client_name, created_at FROM projects ORDER BY created_at DESC")->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex, nofollow">
    <title>Dashboard — SCREAMINGFORWEB</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="css/app.css">
</head>
<body>

<div class="max-w-5xl mx-auto p-4 sm:p-6 lg:p-8">

    <!-- Header -->
    <div class="flex items-center justify-between mb-8">
        <div>
            <h1 class="text-3xl font-extrabold uppercase tracking-tight" style="color:var(--brutal-black)">
                SCREAMING<span style="color:var(--brutal-accent)">FORWEB</span>
            </h1>
            <p class="text-xs font-semibold uppercase tracking-widest opacity-50">Internal SEO Spider</p>
        </div>
    </div>

    <!-- Create Project Card -->
    <div class="brutal-card p-5 mb-8">
        <h2 class="text-lg font-extrabold uppercase mb-4">New Project</h2>
        <form id="create-project-form" class="flex flex-wrap items-end gap-3">
            <div class="flex-1 min-w-[200px]">
                <label class="block text-xs font-bold uppercase mb-1">Project Name</label>
                <input type="text" name="project_name" class="brutal-input" placeholder="e.g. Corporate Redesign" required>
            </div>
            <div class="flex-1 min-w-[200px]">
                <label class="block text-xs font-bold uppercase mb-1">Client Name</label>
                <input type="text" name="client_name" class="brutal-input" placeholder="e.g. Acme Corp" required>
            </div>
            <button type="submit" class="brutal-btn flex-shrink-0 px-6">Create Project</button>
        </form>
    </div>

    <!-- Projects Table -->
    <div class="brutal-card">
        <div class="p-5 border-b-2 border-black">
            <h2 class="text-lg font-extrabold uppercase">Projects Directory</h2>
        </div>

        <?php if (empty($projects)): ?>
            <div class="p-8 text-center">
                <p class="font-bold text-lg opacity-40">No projects yet</p>
                <p class="text-sm opacity-30 mt-1">Create your first project above.</p>
            </div>
        <?php else: ?>
            <div class="overflow-x-auto">
                <table class="brutal-table">
                    <thead>
                        <tr>
                            <th>Project Name</th>
                            <th>Client</th>
                            <th>Created</th>
                            <th style="text-align:center;width:180px">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($projects as $p): ?>
                        <tr>
                            <td class="font-bold"><?= htmlspecialchars($p['project_name']) ?></td>
                            <td><?= htmlspecialchars($p['client_name']) ?></td>
                            <td class="font-mono text-sm"><?= date('Y-m-d H:i', strtotime($p['created_at'])) ?></td>
                            <td style="text-align:center">
                                <a href="project.php?id=<?= (int)$p['id'] ?>" class="brutal-btn text-xs px-3 py-1.5 inline-flex">View</a>
                                <button class="brutal-btn brutal-btn-danger text-xs px-3 py-1.5 btn-delete-project inline-flex" data-project-id="<?= (int)$p['id'] ?>">Delete</button>
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
