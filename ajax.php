<?php
// ============================================================
// SCREAMINGFORWEB — Centralized AJAX Handler
// ============================================================

header('Content-Type: application/json');
ob_start();

require_once __DIR__ . '/config.php';

$action = $_GET['action'] ?? '';

// --- HELPERS ---

function ob_clean_safe() {
    if (ob_get_level()) ob_clean();
}

function jsonRespond($data, $code = 200) {
    http_response_code($code);
    ob_clean_safe();
    echo json_encode($data);
    exit;
}

function getJsonInput() {
    $raw = file_get_contents('php://input');
    $data = json_decode($raw, true);
    if (!$data || !is_array($data)) {
        jsonRespond(['success' => false, 'error' => 'Invalid request body.'], 400);
    }
    return $data;
}

// ============================================================
// ACTION: create-project
// ============================================================

if ($action === 'create-project') {

    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        jsonRespond(['success' => false, 'error' => 'Method not allowed'], 405);
    }

    $input = getJsonInput();
    $name   = trim($input['project_name'] ?? '');
    $client = trim($input['client_name'] ?? '');

    if (empty($name) || empty($client)) {
        jsonRespond([
            'success' => false,
            'error'   => 'Project name and client name are required.',
            'received' => $input
        ], 400);
    }

    try {
        $db = getDB();
        $stmt = $db->prepare("INSERT INTO projects (project_name, client_name) VALUES (?, ?)");
        $stmt->execute([$name, $client]);
        jsonRespond(['success' => true, 'id' => (int)$db->lastInsertId()]);
    } catch (Exception $e) {
        jsonRespond(['success' => false, 'error' => 'Database error. Check server logs.'], 500);
    }
}

// ============================================================
// ACTION: delete-project
// ============================================================

if ($action === 'delete-project') {

    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        jsonRespond(['success' => false, 'error' => 'Method not allowed'], 405);
    }

    $input = getJsonInput();
    $id = (int)($input['project_id'] ?? 0);

    if ($id <= 0) {
        jsonRespond(['success' => false, 'error' => 'Invalid project ID.'], 400);
    }

    try {
        $db = getDB();
        $stmt = $db->prepare("DELETE FROM projects WHERE id = ?");
        $stmt->execute([$id]);
        jsonRespond(['success' => true]);
    } catch (Exception $e) {
        jsonRespond(['success' => false, 'error' => 'Database error. Check server logs.'], 500);
    }
}

// ============================================================
// ACTION: delete-session
// ============================================================

if ($action === 'delete-session') {

    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        jsonRespond(['success' => false, 'error' => 'Method not allowed'], 405);
    }

    $input = getJsonInput();
    $id = (int)($input['session_id'] ?? 0);

    if ($id <= 0) {
        jsonRespond(['success' => false, 'error' => 'Invalid session ID.'], 400);
    }

    try {
        $db = getDB();
        $stmt = $db->prepare("DELETE FROM scan_sessions WHERE id = ?");
        $stmt->execute([$id]);
        jsonRespond(['success' => true]);
    } catch (Exception $e) {
        jsonRespond(['success' => false, 'error' => 'Database error. Check server logs.'], 500);
    }
}

// ============================================================
// ACTION: start-scan
// ============================================================

if ($action === 'start-scan') {

    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        jsonRespond(['success' => false, 'error' => 'Method not allowed'], 405);
    }

    $input    = getJsonInput();
    $projectId = (int)($input['project_id'] ?? 0);
    $rootUrl   = trim($input['root_url'] ?? '');

    if ($projectId <= 0 || empty($rootUrl)) {
        jsonRespond(['success' => false, 'error' => 'Project ID and Root URL are required.'], 400);
    }

    if (!preg_match('#^https?://#i', $rootUrl)) {
        jsonRespond(['success' => false, 'error' => 'URL must start with http:// or https://'], 400);
    }

    try {
        $db = getDB();

        $stmt = $db->prepare("SELECT id FROM projects WHERE id = ?");
        $stmt->execute([$projectId]);
        if (!$stmt->fetch()) {
            jsonRespond(['success' => false, 'error' => 'Project not found.'], 404);
        }

        $db->beginTransaction();

        $stmt = $db->prepare("INSERT INTO scan_sessions (project_id, root_url, status) VALUES (?, ?, 'in_progress')");
        $stmt->execute([$projectId, $rootUrl]);
        $sessionId = (int)$db->lastInsertId();

        $stmt = $db->prepare("INSERT INTO scan_queue (session_id, url, status) VALUES (?, ?, 'pending')");
        $stmt->execute([$sessionId, $rootUrl]);

        $stmt = $db->prepare("UPDATE scan_sessions SET url_found = url_found + 1 WHERE id = ?");
        $stmt->execute([$sessionId]);

        $db->commit();

        jsonRespond(['success' => true, 'session_id' => $sessionId]);
    } catch (Exception $e) {
        if (isset($db) && $db->inTransaction()) $db->rollBack();
        jsonRespond(['success' => false, 'error' => 'Database error. Check server logs.'], 500);
    }
}

// ============================================================
// ACTION: crawl-batch
// ============================================================

if ($action === 'crawl-batch') {

    $sessionId = isset($_GET['session_id']) ? (int)$_GET['session_id'] : 0;
    if ($sessionId <= 0) {
        jsonRespond(['success' => false, 'error' => 'Invalid session ID.'], 400);
    }

    $batchSize = 2;

    try {
        $db = getDB();

        $stmt = $db->prepare("SELECT id, status, root_url, failed_reason FROM scan_sessions WHERE id = ?");
        $stmt->execute([$sessionId]);
        $session = $stmt->fetch();

        if (!$session) {
            jsonRespond(['success' => false, 'error' => 'Session not found.'], 404);
        }

        // If session already failed, return failure info
        if ($session['status'] === 'failed') {
            $stmt = $db->prepare("SELECT COUNT(*) FROM scan_results WHERE session_id = ?");
            $stmt->execute([$sessionId]);
            $total = (int)$stmt->fetchColumn();
            jsonRespond([
                'success'   => true,
                'has_more'  => false,
                'failed'    => true,
                'reason'    => $session['failed_reason'],
                'processed' => $total,
                'total'     => $total,
            ]);
        }

        $rootUrl    = $session['root_url'];
        $rootParts  = parse_url($rootUrl);
        $rootHost   = $rootParts['host'] ?? '';
        $rootScheme = $rootParts['scheme'] ?? 'https';

        // --- QUEUE EXHAUSTION CHECK ---

        $stmt = $db->prepare("SELECT COUNT(*) FROM scan_queue WHERE session_id = ? AND status != 'completed'");
        $stmt->execute([$sessionId]);
        $pendingTotal = (int)$stmt->fetchColumn();

        if ($pendingTotal === 0 && $session['status'] === 'in_progress') {
            $stmt = $db->prepare("SELECT COUNT(*) FROM scan_queue WHERE session_id = ? AND status = 'processing'");
            $stmt->execute([$sessionId]);
            $processing = (int)$stmt->fetchColumn();

            if ($processing === 0) {
                $db->prepare("UPDATE scan_sessions SET status = 'completed' WHERE id = ?")->execute([$sessionId]);
            }

            $stmt = $db->prepare("SELECT COUNT(*) FROM scan_results WHERE session_id = ?");
            $stmt->execute([$sessionId]);
            $total = (int)$stmt->fetchColumn();

            jsonRespond(['success' => true, 'has_more' => false, 'processed' => $total, 'total' => $total]);
        }

        if ($session['status'] !== 'in_progress') {
            $stmt = $db->prepare("SELECT COUNT(*) FROM scan_results WHERE session_id = ?");
            $stmt->execute([$sessionId]);
            $total = (int)$stmt->fetchColumn();

            jsonRespond(['success' => true, 'has_more' => false, 'processed' => $total, 'total' => $total]);
        }

        // --- DEQUEUE ATOMICALLY ---

        $db->beginTransaction();

        $stmt = $db->prepare("SELECT id, url FROM scan_queue WHERE session_id = ? AND status = 'pending' ORDER BY id ASC LIMIT ? FOR UPDATE");
        $stmt->execute([$sessionId, $batchSize]);
        $batch = $stmt->fetchAll();

        if (empty($batch)) {
            $db->commit();

            $stmt = $db->prepare("SELECT COUNT(*) FROM scan_results WHERE session_id = ?");
            $stmt->execute([$sessionId]);
            $total = (int)$stmt->fetchColumn();

            jsonRespond(['success' => true, 'has_more' => false, 'processed' => $total, 'total' => $total]);
        }

        $ids = array_column($batch, 'id');
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $db->prepare("UPDATE scan_queue SET status = 'processing' WHERE id IN ($placeholders)")->execute($ids);
        $db->commit();

        // --- PROCESS EACH URL ---

        $processed     = 0;
        $errorsInBatch = 0;
        $firstUrlIsRoot = false;
        $rootResult     = null;

        foreach ($batch as $idx => $item) {
            $url    = $item['url'];
            if ($idx === 0 && $url === $rootUrl) {
                $firstUrlIsRoot = true;
            }

            $result = crawlUrl($url);
            $isError = ($result['status_code'] === 0);

            // Store root URL result to avoid re-fetch in failure detection
            if ($idx === 0 && $firstUrlIsRoot) {
                $rootResult = $result;
            }

            if ($isError) {
                $errorsInBatch++;
            }

            try {
                $stmt = $db->prepare("INSERT IGNORE INTO scan_results (session_id, url, status_code, title, description) VALUES (?, ?, ?, ?, ?)");
                $stmt->execute([$sessionId, $url, $result['status_code'], $result['title'], $result['description']]);

                $code = (int)$result['status_code'];
                if ($code >= 200 && $code < 400) {
                    $db->prepare("UPDATE scan_sessions SET url_ok = url_ok + 1 WHERE id = ?")->execute([$sessionId]);
                } else {
                    $db->prepare("UPDATE scan_sessions SET url_broken = url_broken + 1 WHERE id = ?")->execute([$sessionId]);
                }

                if (!empty($result['links'])) {
                    foreach ($result['links'] as $link) {
                        $normalized = normalizeUrl($link, $rootHost, $rootScheme);
                        if ($normalized !== null) {
                            try {
                                $stmt = $db->prepare("INSERT IGNORE INTO scan_queue (session_id, url, status) VALUES (?, ?, 'pending')");
                                $stmt->execute([$sessionId, $normalized]);
                                if ($stmt->rowCount() > 0) {
                                    $db->prepare("UPDATE scan_sessions SET url_found = url_found + 1 WHERE id = ?")->execute([$sessionId]);
                                }
                            } catch (Exception $e) {
                                error_log('scan_queue insert error: ' . $e->getMessage());
                            }
                        }
                    }
                }
            } catch (Exception $e) {
                error_log('scan_results/scan_sessions update error: ' . $e->getMessage());
            }

            $db->prepare("UPDATE scan_queue SET status = 'completed' WHERE id = ?")->execute([$item['id']]);
            $processed++;
        }

        // --- FAILURE DETECTION ---
        // If the root URL failed, mark session as failed with the actual error
        if ($firstUrlIsRoot && $errorsInBatch > 0 && $rootResult !== null) {
            $errorMsg = $rootResult['error'] ?? 'Unknown error';
            markSessionFailed($db, $sessionId, $errorMsg);
            jsonRespond([
                'success'   => true,
                'has_more'  => false,
                'failed'    => true,
                'reason'    => $errorMsg,
                'processed' => $processed,
                'total'     => $processed,
            ]);
        }

        // All URLs in batch failed (excluding root which was handled above)
        $batchCount = count($batch);
        if (!$firstUrlIsRoot && $errorsInBatch === $batchCount && $batchCount > 0) {
            $reason = 'All ' . $batchCount . ' URLs failed. Possible network/SSL issue.';
            markSessionFailed($db, $sessionId, $reason);
            jsonRespond([
                'success'   => true,
                'has_more'  => false,
                'failed'    => true,
                'reason'    => $reason,
                'processed' => $processed,
                'total'     => $processed,
            ]);
        }

        // --- CHECK IF DONE ---

        $stmt = $db->prepare("SELECT COUNT(*) FROM scan_queue WHERE session_id = ? AND status IN ('pending', 'processing')");
        $stmt->execute([$sessionId]);
        $remaining = (int)$stmt->fetchColumn();
        $hasMore = $remaining > 0;

        if (!$hasMore) {
            $db->prepare("UPDATE scan_sessions SET status = 'completed' WHERE id = ?")->execute([$sessionId]);
        }

        $stmt = $db->prepare("SELECT COUNT(*) FROM scan_results WHERE session_id = ?");
        $stmt->execute([$sessionId]);
        $totalProcessed = (int)$stmt->fetchColumn();

        $stmt = $db->prepare("SELECT url_found FROM scan_sessions WHERE id = ?");
        $stmt->execute([$sessionId]);
        $totalFound = (int)$stmt->fetchColumn();

        jsonRespond([
            'success'   => true,
            'has_more'  => $hasMore,
            'processed' => $totalProcessed,
            'total'     => $totalFound > 0 ? $totalFound : $totalProcessed,
        ]);
    } catch (Exception $e) {
        if (isset($db) && $db->inTransaction()) $db->rollBack();
        // Mark session as failed on exception
        try {
            if (isset($db)) markSessionFailed($db, $sessionId, 'Internal crawl error');
        } catch (Exception $ex) {
            error_log('markSessionFailed error: ' . $ex->getMessage());
        }
        jsonRespond(['success' => false, 'error' => 'Internal crawl error. Check server logs.'], 500);
    }
}

// ============================================================
// ACTION: export-csv
// ============================================================

if ($action === 'export-csv') {

    $sessionId = isset($_GET['session_id']) ? (int)$_GET['session_id'] : 0;
    if ($sessionId <= 0) {
        while (ob_get_level()) ob_end_clean();
        header('Content-Type: text/plain; charset=utf-8');
        http_response_code(400);
        die('Invalid session ID.');
    }

    $statusCode = isset($_GET['status_code']) ? (int)$_GET['status_code'] : 0;

    try {
        $db = getDB();

        $stmt = $db->prepare("SELECT ss.id, ss.root_url, p.project_name FROM scan_sessions ss JOIN projects p ON p.id = ss.project_id WHERE ss.id = ?");
        $stmt->execute([$sessionId]);
        $session = $stmt->fetch();

        if (!$session) {
            while (ob_get_level()) ob_end_clean();
            header('Content-Type: text/plain; charset=utf-8');
            http_response_code(404);
            die('Session not found.');
        }

        $exportFilenamePrefix = 'screamingforweb-export';
        $sanitizedName = preg_replace('/[^a-zA-Z0-9_-]/', '', str_replace(' ', '-', $session['project_name']));
        $exportFilenamePrefix .= '-' . $sanitizedName . '-' . $sessionId;

        $resultsQuery = "SELECT url, status_code, title, description FROM scan_results WHERE session_id = ?";
        $resultsParams = [$sessionId];

        if ($statusCode > 0) {
            $safeCode = (int)$statusCode;
            $resultsQuery .= " AND status_code = " . $safeCode;
            $exportFilenamePrefix .= '-status' . $safeCode;
        }

        $resultsQuery .= " ORDER BY id ASC";

        $stmt = $db->prepare($resultsQuery);
        $stmt->execute($resultsParams);
        $results = $stmt->fetchAll();

        // Clean all output buffers and set CSV headers
        while (ob_get_level()) ob_end_clean();
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $exportFilenamePrefix . '.csv"');

        $output = fopen('php://output', 'w');
        fwrite($output, "\xEF\xBB\xBF");
        fputcsv($output, ['URL', 'Status Code', 'Title', 'Description'], ';');

        foreach ($results as $r) {
            fputcsv($output, [$r['url'], $r['status_code'] ?? '', $r['title'] ?? '', $r['description'] ?? ''], ';');
        }

        fclose($output);
        exit;
    } catch (Exception $e) {
        while (ob_get_level()) ob_end_clean();
        header('Content-Type: text/plain; charset=utf-8');
        http_response_code(500);
        $errMsg = 'CSV export error: ' . $e->getMessage();
        error_log($errMsg);
        die($errMsg);
    }
}

// --- UNKNOWN ACTION ---

jsonRespond(['success' => false, 'error' => 'Unknown action: ' . $action], 400);

// ============================================================
// FAILURE TRACKING HELPER
// ============================================================

function markSessionFailed($db, $sessionId, $reason) {
    $db->prepare("UPDATE scan_sessions SET status = 'failed', failed_reason = ? WHERE id = ?")
       ->execute([mb_substr($reason, 0, 1000), $sessionId]);
}

// ============================================================
// CRAWLER FUNCTIONS (used by crawl-batch)
// ============================================================

function crawlUrl($url) {
    $errorMsg = null;

    for ($attempt = 1; $attempt <= 2; $attempt++) {
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL            => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS      => 10,
            CURLOPT_TIMEOUT        => 30,
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => 0,
            CURLOPT_USERAGENT      => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36',
            CURLOPT_ENCODING       => '',
            CURLOPT_HTTPHEADER     => [
                'Accept: text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
                'Accept-Language: en-US,en;q=0.5',
            ],
        ]);

        $body        = curl_exec($ch);
        $httpCode    = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $contentType = curl_getinfo($ch, CURLINFO_CONTENT_TYPE);
        $error       = curl_error($ch);
        $errno       = curl_errno($ch);
        curl_close($ch);

        $errorMsg = null;

        // Build meaningful error message
        if ($error) {
            $errorMsg = 'cURL error ' . $errno . ': ' . $error;
        } elseif ($httpCode === 0) {
            $errorMsg = 'No response received (DNS / connection issue)';
        } elseif ($httpCode >= 400) {
            $errorMsg = 'HTTP ' . $httpCode;
        }

        // If first attempt failed, retry once
        if ($errorMsg !== null && $attempt === 1) {
            usleep(500000); // 500ms delay before retry
            continue;
        }

        // Success or final attempt
        break;
    }

    // If still failing after retry, return error info
    if ($errorMsg !== null) {
        return [
            'status_code' => ($httpCode ?? 0) > 0 ? ($httpCode ?? 0) : 0,
            'title'       => null,
            'description' => '[FETCH ERROR] ' . $errorMsg,
            'links'       => [],
            'error'       => $errorMsg,
        ];
    }

    // --- PARSE HTML ---
    $title       = null;
    $description = null;
    $links       = [];

    if ($body && $contentType && preg_match('/text\/html/i', $contentType)) {
        $doc = new DOMDocument();
        libxml_use_internal_errors(true);

        // Detect charset from meta or Content-Type
        $charset = 'utf-8';
        if (preg_match('/charset=([^\s;]+)/i', $contentType, $m)) {
            $charset = strtolower(trim($m[1]));
        }

        $html = $body;
        if (strtoupper($charset) !== 'UTF-8') {
            $html = mb_convert_encoding($body, 'HTML-ENTITIES', $charset);
        }

        $doc->loadHTML('<?xml encoding="utf-8" ?>' . $html);
        libxml_clear_errors();

        $xpath = new DOMXPath($doc);

        $titleNodes = $xpath->query('//title');
        if ($titleNodes && $titleNodes->length > 0) {
            $title = trim($titleNodes->item(0)->textContent);
        }

        $metaNodes = $xpath->query('//meta[@name="description"]/@content');
        if ($metaNodes && $metaNodes->length > 0) {
            $description = trim($metaNodes->item(0)->value);
        }

        // Also check for og:description
        if (empty($description)) {
            $metaOg = $xpath->query('//meta[@property="og:description"]/@content');
            if ($metaOg && $metaOg->length > 0) {
                $description = trim($metaOg->item(0)->value);
            }
        }

        $anchorNodes = $xpath->query('//a[@href]');
        foreach ($anchorNodes as $node) {
            $href = $node->getAttribute('href');
            if (trim($href) !== '') {
                $links[] = $href;
            }
        }
    } elseif ($httpCode > 0 && $body) {
        // Non-HTML content (PDF, image, etc.) — record but don't parse
        // No links to extract
    }

    return [
        'status_code' => $httpCode,
        'title'       => $title ? mb_substr($title, 0, 500) : null,
        'description' => $description ? mb_substr($description, 0, 1000) : ($errorMsg ?? null),
        'links'       => $links,
        'error'       => $errorMsg,
    ];
}

function normalizeUrl($href, $rootHost, $rootScheme = 'https') {
    $href = trim($href);
    if (empty($href)) return null;

    $href = preg_replace('/#.*$/', '', $href);
    if (empty($href)) return null;

    if (preg_match('#^(mailto|tel|javascript|ftp|file|data):#i', $href)) return null;
    if (preg_match('/\.(pdf|zip|rar|gz|tar|7z|doc|docx|xls|xlsx|ppt|pptx|jpg|jpeg|png|gif|bmp|svg|webp|ico|tiff|mp3|mp4|avi|mov|wmv|flv|css|js|json|xml|rss|woff|woff2|ttf|eot)$/i', $href)) return null;

    if (preg_match('#^//#', $href)) {
        $href = 'https:' . $href;
    }

    if (!preg_match('#^https?://#i', $href)) {
        $href = $rootScheme . '://' . $rootHost . '/' . ltrim($href, '/');
    }

    $parts = parse_url($href);
    if (!$parts || !isset($parts['host'])) return null;
    if (strtolower($parts['host']) !== strtolower($rootHost)) return null;

    $scheme = isset($parts['scheme']) ? strtolower($parts['scheme']) : 'https';
    $host   = $parts['host'];
    $port   = isset($parts['port']) ? ':' . $parts['port'] : '';
    $path   = isset($parts['path']) ? '/' . ltrim($parts['path'], '/') : '/';
    $query  = isset($parts['query']) ? '?' . $parts['query'] : '';

    return $scheme . '://' . $host . $port . $path . $query;
}
