<?php
// ============================================================
// SCREAMINGFORWEB — Migration Helper (browser-based)
// ============================================================

require_once __DIR__ . '/config.php';

$ran = false;
$error = null;

try {
    $db = getDB();

    // Check if column already exists
    $stmt = $db->query("SHOW COLUMNS FROM scan_sessions LIKE 'failed_reason'");
    if ($stmt->fetch()) {
        $msg = 'Column <strong>failed_reason</strong> already exists. Nothing to do.';
    } else {
        $db->exec("ALTER TABLE scan_sessions ADD COLUMN failed_reason TEXT NULL DEFAULT NULL AFTER status");
        $msg = 'Migration successful: column <strong>failed_reason</strong> added.';
    }
    $ran = true;
} catch (Exception $e) {
    $error = $e->getMessage();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex, nofollow">
    <title>Migration — SCREAMINGFORWEB</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="css/app.css">
</head>
<body class="min-h-screen flex items-center justify-center p-4">
<div class="w-full max-w-lg text-center">

    <h1 class="text-3xl font-extrabold uppercase tracking-tight mb-6">
        SCREAMING<span style="color:var(--brutal-accent)">FORWEB</span>
    </h1>

    <div class="brutal-card p-6">
        <?php if ($ran): ?>
            <div class="text-4xl mb-3" style="color:var(--brutal-green)">✓</div>
            <p class="font-bold text-lg"><?= $msg ?></p>
            <a href="index.php" class="brutal-btn mt-6">Go to Dashboard</a>
        <?php else: ?>
            <div class="text-4xl mb-3" style="color:var(--brutal-red)">✗</div>
            <p class="font-bold text-lg">Migration failed</p>
            <p class="text-sm mt-2 opacity-70"><?= htmlspecialchars($error) ?></p>
            <p class="text-xs mt-4 opacity-50">Try running manually via phpMyAdmin:<br>
            <code class="font-mono text-xs">ALTER TABLE scan_sessions ADD COLUMN failed_reason TEXT NULL DEFAULT NULL AFTER status;</code></p>
        <?php endif; ?>
    </div>

    <p class="text-center text-xs font-bold uppercase tracking-widest mt-6 opacity-30">
        SCREAMINGFORWEB — Internal Use Only
    </p>
    <p class="text-center text-xs font-bold uppercase tracking-widest mt-2 opacity-40">
        Software sviluppato con licenza open source in Lecce da Marco De Sangro
    </p>
</div>
</body>
</html>
