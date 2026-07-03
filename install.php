<?php
// ============================================================
// SCREAMINGFORWEB — Guided Installation Wizard
// ============================================================

// If config.php already exists, redirect to dashboard
if (file_exists(__DIR__ . '/config.php')) {
    header('Location: index.php');
    exit;
}

$step = isset($_GET['step']) ? (int)$_GET['step'] : 1;
$error = '';
$success = '';

// Redirect GET access to step 3 back to step 2 (prevents refresh loops)
if ($_SERVER['REQUEST_METHOD'] === 'GET' && $step === 3) {
    header('Location: ?step=2');
    exit;
}

// --- STEP HANDLERS ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $step === 3) {
    // Test DB connection and create tables
    $host = trim($_POST['db_host'] ?? '127.0.0.1');
    $port = trim($_POST['db_port'] ?? '3306');
    $name = trim($_POST['db_name'] ?? 'screamingforweb');
    $user = trim($_POST['db_user'] ?? '');
    $pass = $_POST['db_pass'] ?? '';

    if (empty($host) || empty($name) || empty($user)) {
        $error = 'Host, Database Name, and Username are required.';
    } else {
        try {
            $dsn = "mysql:host={$host};port={$port};charset=utf8mb4";
            $pdo = new PDO($dsn, $user, $pass, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            ]);

            // Create database if not exists
            $safeName = str_replace('`', '``', $name);
            $pdo->exec("CREATE DATABASE IF NOT EXISTS `{$safeName}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
            $pdo->exec("USE `{$safeName}`");

            // Create tables
            $pdo->exec("
                CREATE TABLE IF NOT EXISTS projects (
                    id          INT AUTO_INCREMENT PRIMARY KEY,
                    project_name VARCHAR(255) NOT NULL,
                    client_name VARCHAR(255) NOT NULL,
                    created_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP
                ) ENGINE=InnoDB
            ");

            $pdo->exec("
                CREATE TABLE IF NOT EXISTS scan_sessions (
                    id          INT AUTO_INCREMENT PRIMARY KEY,
                    project_id  INT NOT NULL,
                    root_url    VARCHAR(2048) NOT NULL,
                    status      ENUM('in_progress','completed','failed') NOT NULL DEFAULT 'in_progress',
                    failed_reason TEXT NULL DEFAULT NULL,
                    url_found   INT UNSIGNED NOT NULL DEFAULT 0,
                    url_ok      INT UNSIGNED NOT NULL DEFAULT 0,
                    url_broken  INT UNSIGNED NOT NULL DEFAULT 0,
                    scanned_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    FOREIGN KEY (project_id) REFERENCES projects(id) ON DELETE CASCADE,
                    INDEX idx_sessions_project (project_id),
                    INDEX idx_sessions_status (status)
                ) ENGINE=InnoDB
            ");

            $pdo->exec("
                CREATE TABLE IF NOT EXISTS scan_queue (
                    id          INT AUTO_INCREMENT PRIMARY KEY,
                    session_id  INT NOT NULL,
                    url         TEXT NOT NULL,
                    status      ENUM('pending','processing','completed','error') NOT NULL DEFAULT 'pending',
                    created_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    FOREIGN KEY (session_id) REFERENCES scan_sessions(id) ON DELETE CASCADE,
                    INDEX idx_queue_session_status (session_id, status),
                    UNIQUE KEY uq_session_url (session_id, url(191))
                ) ENGINE=InnoDB
            ");

            $pdo->exec("
                CREATE TABLE IF NOT EXISTS scan_results (
                    id          INT AUTO_INCREMENT PRIMARY KEY,
                    session_id  INT NOT NULL,
                    url         TEXT NOT NULL,
                    status_code INT NULL,
                    title       VARCHAR(500) NULL,
                    description TEXT NULL,
                    created_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    FOREIGN KEY (session_id) REFERENCES scan_sessions(id) ON DELETE CASCADE,
                    INDEX idx_results_session (session_id),
                    UNIQUE KEY uq_result_session_url (session_id, url(191))
                ) ENGINE=InnoDB
            ");

            // Write config.php
            $esc = function($v) {
                return str_replace(["\\", "'", "\$"], ["\\\\", "\\'", "\\\$"], $v);
            };
            $config = <<<PHP
<?php
// ============================================================
// SCREAMINGFORWEB — Database Configuration
// ============================================================

define('DB_HOST', '{$esc($host)}');
define('DB_PORT', '{$esc($port)}');
define('DB_NAME', '{$esc($name)}');
define('DB_USER', '{$esc($user)}');
define('DB_PASS', '{$esc($pass)}');

function getDB() {
    static \$pdo = null;
    if (\$pdo === null) {
        \$pdo = new PDO(
            'mysql:host=' . DB_HOST . ';port=' . DB_PORT . ';dbname=' . DB_NAME . ';charset=utf8mb4',
            DB_USER,
            DB_PASS,
            [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
            ]
        );
    }
    return \$pdo;
}

PHP;

            $configPath = __DIR__ . '/config.php';
            if (file_put_contents($configPath, $config) === false) {
                throw new Exception('Cannot write config.php — check directory permissions.');
            }
            @chmod($configPath, 0640);

            $success = 'Installation completed successfully!';
            $step = 4;
        } catch (Exception $e) {
            $error = 'Error: ' . $e->getMessage();
        }
    }
}

// --- EXTENSION CHECKS ---
$required = ['pdo', 'pdo_mysql', 'curl', 'dom', 'xml', 'mbstring'];
$extStatus = [];
$allPass = true;
foreach ($required as $ext) {
    $ok = extension_loaded($ext);
    $extStatus[] = ['name' => $ext, 'ok' => $ok];
    if (!$ok) $allPass = false;
}

$writable = is_writable(__DIR__);

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex, nofollow">
    <title>Install — SCREAMINGFORWEB</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="css/app.css">
</head>
<body class="min-h-screen flex items-center justify-center p-4">

<div class="w-full max-w-2xl">
    <!-- Header -->
    <div class="text-center mb-8">
        <h1 class="text-4xl font-extrabold uppercase tracking-tight" style="color:var(--brutal-black)">
            SCREAMING<span style="color:var(--brutal-accent)">FORWEB</span>
        </h1>
        <p class="text-sm font-semibold uppercase tracking-widest mt-1 opacity-60">Guided Installation</p>
    </div>

    <!-- Progress Steps -->
    <div class="flex items-center gap-2 mb-8 text-xs font-bold uppercase">
        <?php $steps = [1 => 'Requirements', 2 => 'Database', 3 => 'Install', 4 => 'Done']; ?>
        <?php foreach ($steps as $num => $label): ?>
            <div class="flex-1 text-center px-2 py-2 border-2 border-black <?= $num === $step ? 'bg-black text-white' : ($num < $step ? 'bg-green-500 text-white border-green-500' : 'bg-white') ?>">
                <?= $label ?>
            </div>
            <?php if ($num < count($steps)): ?>
                <span class="opacity-30">→</span>
            <?php endif; ?>
        <?php endforeach; ?>
    </div>

    <!-- Step 1: Requirements -->
    <?php if ($step === 1): ?>
    <div class="brutal-card p-6">
        <h2 class="text-xl font-extrabold uppercase mb-4">System Requirements</h2>

        <?php foreach ($extStatus as $ext): ?>
        <div class="install-step <?= $ext['ok'] ? 'pass' : 'fail' ?>">
            <div class="step-number"><?= $ext['ok'] ? '✓' : '✗' ?></div>
            <div>
                <div class="font-bold font-mono"><?= htmlspecialchars($ext['name']) ?></div>
                <div class="text-xs opacity-60">PHP Extension</div>
            </div>
        </div>
        <?php endforeach; ?>

        <div class="install-step <?= $writable ? 'pass' : 'fail' ?>">
            <div class="step-number"><?= $writable ? '✓' : '✗' ?></div>
            <div>
                <div class="font-bold">Directory Writable</div>
                <div class="text-xs opacity-60"><?= __DIR__ ?></div>
            </div>
        </div>

        <?php if ($allPass && $writable): ?>
            <a href="?step=2" class="brutal-btn w-full mt-4">Continue →</a>
        <?php else: ?>
            <p class="text-red-600 font-bold mt-4">Fix the requirements above before continuing.</p>
        <?php endif; ?>
    </div>

    <!-- Step 2: Database Form -->
    <?php elseif ($step === 2): ?>
    <div class="brutal-card p-6">
        <h2 class="text-xl font-extrabold uppercase mb-4">Database Connection</h2>
        <p class="text-sm mb-6 opacity-70">Enter your MySQL/MariaDB credentials. A new database will be created automatically.</p>

        <form method="POST" action="?step=3" class="space-y-4">
            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="block text-xs font-bold uppercase mb-1">Host</label>
                    <input type="text" name="db_host" value="127.0.0.1" class="brutal-input" required>
                </div>
                <div>
                    <label class="block text-xs font-bold uppercase mb-1">Port</label>
                    <input type="text" name="db_port" value="3306" class="brutal-input" required>
                </div>
            </div>
            <div>
                <label class="block text-xs font-bold uppercase mb-1">Database Name</label>
                <input type="text" name="db_name" value="screamingforweb" class="brutal-input" required>
            </div>
            <div>
                <label class="block text-xs font-bold uppercase mb-1">Username</label>
                <input type="text" name="db_user" class="brutal-input" required autocomplete="off">
            </div>
            <div>
                <label class="block text-xs font-bold uppercase mb-1">Password</label>
                <input type="password" name="db_pass" class="brutal-input" autocomplete="off">
            </div>

            <?php if ($error): ?>
                <div class="p-3 border-2 border-red-600 bg-red-50 text-red-700 font-bold text-sm"><?= htmlspecialchars($error) ?></div>
            <?php endif; ?>

            <div class="flex gap-3 pt-2">
                <a href="?step=1" class="brutal-btn" style="background:#666;flex:1">← Back</a>
                <button type="submit" class="brutal-btn" style="flex:3">Install & Create Tables →</button>
            </div>
        </form>
    </div>

    <!-- Step 3: Installing (Processing) -->
    <?php elseif ($step === 3): ?>
    <div class="brutal-card p-6 text-center">
        <h2 class="text-xl font-extrabold uppercase mb-4">Installing...</h2>
        <?php if ($error): ?>
            <div class="p-3 border-2 border-red-600 bg-red-50 text-red-700 font-bold text-sm mb-4"><?= htmlspecialchars($error) ?></div>
            <a href="?step=2" class="brutal-btn">← Try Again</a>
        <?php else: ?>
            <p class="opacity-70">Please wait...</p>
            <meta http-equiv="refresh" content="0;url=?step=3">
        <?php endif; ?>
    </div>

    <!-- Step 4: Done -->
    <?php elseif ($step === 4): ?>
    <div class="brutal-card p-6 text-center">
        <div class="text-5xl mb-4">✓</div>
        <h2 class="text-xl font-extrabold uppercase mb-2">Installation Complete!</h2>
        <p class="text-sm opacity-70 mb-6">SCREAMINGFORWEB is ready to use. The database has been created and config.php has been written.</p>
        <a href="index.php" class="brutal-btn text-lg px-8 py-3">Go to Dashboard →</a>
    </div>
    <?php endif; ?>

    <!-- Footer -->
    <p class="text-center text-xs font-bold uppercase tracking-widest mt-6 opacity-40">
        SCREAMINGFORWEB — Internal Use Only
    </p>
    <p class="text-center text-xs font-bold uppercase tracking-widest mt-2 opacity-40">
        Software sviluppato con licenza open source in Lecce da Marco De Sangro
    </p>
</div>

</body>
</html>
