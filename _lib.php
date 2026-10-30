<?php
/**
 * _lib.php — shared helpers. Do not edit; edit config.php instead.
 */

function cfg(): array {
    static $c;
    if (!$c) {
        $c = require __DIR__ . '/config.php';
    }
    return $c;
}

function pdo(): PDO {
    static $pdo;
    if ($pdo) return $pdo;
    $c = cfg()['db'];
    $dsn = "mysql:host={$c['host']};dbname={$c['name']};charset={$c['charset']}";
    $pdo = new PDO($dsn, $c['user'], $c['pass'], [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);
    return $pdo;
}

/** Find an agent by slug. Returns row or null. */
function agent_by_slug(string $slug): ?array {
    $stmt = pdo()->prepare("SELECT * FROM agent_agents WHERE slug = ? LIMIT 1");
    $stmt->execute([$slug]);
    $row = $stmt->fetch();
    return $row ?: null;
}

function agent_by_id(int $id): ?array {
    $stmt = pdo()->prepare("SELECT * FROM agent_agents WHERE id = ? LIMIT 1");
    $stmt->execute([$id]);
    $row = $stmt->fetch();
    return $row ?: null;
}

function agents_all(): array {
    return pdo()->query("SELECT * FROM agent_agents ORDER BY id ASC")->fetchAll();
}

function sources_for_agent(int $agentId, bool $enabledOnly = false): array {
    $where = $enabledOnly ? "AND enabled = 1" : "";
    $stmt = pdo()->prepare("SELECT * FROM agent_sources WHERE agent_id = ? $where ORDER BY priority DESC, id ASC");
    $stmt->execute([$agentId]);
    return $stmt->fetchAll();
}

/** Build the system prompt for an agent from its enabled sources, ordered by priority desc. */
function build_system_prompt(array $agent): string {
    $sources = sources_for_agent((int)$agent['id'], true);
    $preamble = trim((string)($agent['system_preamble'] ?? ''));
    if ($preamble === '') {
        $preamble = "You are a helpful assistant. Answer questions based ONLY on the reference documents below. If something isn't covered, say so plainly. Be concise. Never fabricate.";
    }
    $out = $preamble . "\n\n";
    if (!empty($sources)) {
        $out .= "REFERENCE DOCUMENTS (ordered from highest priority to lowest — when they conflict, prefer the higher-priority source):\n\n";
        foreach ($sources as $i => $s) {
            $n = $i + 1;
            $out .= "=== SOURCE #{$n}: {$s['label']} (priority {$s['priority']}) ===\n";
            $out .= rtrim($s['content']) . "\n\n";
        }
    } else {
        $out .= "(No reference documents have been added yet.)\n";
    }
    return $out;
}

/** Strip HTML to plain text, preserving rough structure. */
function html_to_text(string $html): string {
    // Remove script/style blocks entirely
    $html = preg_replace('#<(script|style)\b[^>]*>.*?</\1>#is', '', $html);
    // Convert breaks + closing block tags to newlines
    $html = preg_replace('#<(br|/p|/div|/li|/h[1-6]|/tr)>#i', "\n", $html);
    $html = preg_replace('#<(li|tr|h[1-6])\b[^>]*>#i', "\n- ", $html);
    $text = strip_tags($html);
    $text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    $text = preg_replace("/[ \t]+/", " ", $text);
    $text = preg_replace("/\n{3,}/", "\n\n", $text);
    return trim($text);
}

/** Extract text from an uploaded file. Returns [text, error]. */
function extract_uploaded_text(array $file): array {
    if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
        return ['', 'Upload error code ' . ($file['error'] ?? '?')];
    }
    $name = $file['name'];
    $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));
    $raw = file_get_contents($file['tmp_name']);
    if ($raw === false) return ['', 'Could not read file'];
    switch ($ext) {
        case 'txt':
        case 'md':
        case 'json':
        case 'csv':
            return [$raw, null];
        case 'html':
        case 'htm':
            return [html_to_text($raw), null];
        case 'pdf':
            return ['', 'PDF not supported in v1. Convert to .txt or .md first, or paste the text directly.'];
        default:
            // Try as plain text
            if (mb_check_encoding($raw, 'UTF-8')) return [$raw, null];
            return ['', "Unsupported file type: .{$ext}"];
    }
}

/** Admin auth via PIN. Sets session on success. */
function admin_require(): void {
    if (session_status() === PHP_SESSION_NONE) session_start();
    if (!empty($_SESSION['agent_admin'])) return;
    if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' && isset($_POST['__pin'])) {
        $pin = (string)$_POST['__pin'];
        if (hash_equals((string)cfg()['admin_pin'], $pin)) {
            $_SESSION['agent_admin'] = true;
            $url = strtok($_SERVER['REQUEST_URI'], '?');
            $qs = $_SERVER['QUERY_STRING'] ?? '';
            header('Location: ' . $url . ($qs ? "?$qs" : ''));
            exit;
        }
        $err = 'Wrong PIN.';
    }
    admin_render_login($err ?? null);
    exit;
}

function admin_logout(): void {
    if (session_status() === PHP_SESSION_NONE) session_start();
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $p = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $p['path'], $p['domain'], $p['secure'], $p['httponly']);
    }
    session_destroy();
    header('Location: ' . strtok($_SERVER['REQUEST_URI'], '?'));
    exit;
}

function admin_render_login(?string $err): void {
    ?><!DOCTYPE html>
<html lang="en"><head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Agent Admin — Sign in</title>
<style>
  body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif; background: #080808; color: #eeebe5; display: grid; place-items: center; min-height: 100vh; margin: 0; }
  form { background: #101010; border: 1px solid #222; padding: 32px; border-radius: 6px; min-width: 320px; }
  h1 { margin: 0 0 18px; font-size: 18px; letter-spacing: 0.5px; }
  input { width: 100%; padding: 12px 14px; background: #161616; border: 1px solid #2e2e2e; color: #eeebe5; border-radius: 4px; font-size: 16px; }
  input:focus { outline: none; border-color: #e8a020; }
  button { margin-top: 12px; width: 100%; padding: 12px; background: #e8a020; color: #080808; border: none; border-radius: 4px; cursor: pointer; font-size: 14px; font-weight: 600; letter-spacing: 1px; text-transform: uppercase; }
  .err { background: rgba(244,63,94,0.1); border: 1px solid rgba(244,63,94,0.3); color: #f43f5e; padding: 10px 12px; border-radius: 4px; font-size: 13px; margin-bottom: 14px; }
  label { display: block; font-size: 11px; letter-spacing: 1.5px; text-transform: uppercase; color: #9a948a; margin-bottom: 8px; }
</style></head>
<body>
  <form method="post">
    <h1>Agent Admin</h1>
    <?php if ($err): ?><div class="err"><?= htmlspecialchars($err) ?></div><?php endif; ?>
    <label for="__pin">PIN</label>
    <input id="__pin" name="__pin" type="password" autofocus />
    <button type="submit">Sign in</button>
  </form>
</body></html>
<?php }

function h($s): string { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
