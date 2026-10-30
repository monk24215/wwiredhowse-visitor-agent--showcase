<?php
/**
 * admin.php — agent + source management. PIN-gated.
 */
require __DIR__ . '/_lib.php';

if (isset($_GET['logout'])) admin_logout();
admin_require();

$pdo = pdo();
$do = $_GET['do'] ?? 'agents';
$agentId = isset($_GET['agent']) ? (int)$_GET['agent'] : 0;
$flash = $_GET['msg'] ?? null;

// =================== POST HANDLERS ===================

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // --- Save agent (create or update) ---
    if (($_POST['action'] ?? '') === 'save_agent') {
        $id = (int)($_POST['id'] ?? 0);
        $data = [
            'slug' => preg_replace('/[^a-z0-9_-]/', '', strtolower(trim($_POST['slug'] ?? ''))),
            'name' => trim($_POST['name'] ?? ''),
            'greeting' => trim($_POST['greeting'] ?? ''),
            'suggested_chips' => json_encode(array_values(array_filter(array_map('trim', explode("\n", $_POST['chips'] ?? ''))))),
            'system_preamble' => trim($_POST['system_preamble'] ?? ''),
            'accent_color' => trim($_POST['accent_color'] ?? '#1a1a1a'),
            'model' => trim($_POST['model'] ?? '') ?: null,
            'max_tokens' => ($_POST['max_tokens'] ?? '') === '' ? null : (int)$_POST['max_tokens'],
            'enabled' => isset($_POST['enabled']) ? 1 : 0,
        ];
        if (!$data['slug'] || !$data['name']) {
            header("Location: admin.php?do=edit_agent" . ($id ? "&agent=$id" : '') . "&msg=" . urlencode("slug and name required"));
            exit;
        }
        if ($id) {
            $stmt = $pdo->prepare("UPDATE agent_agents SET slug=?, name=?, greeting=?, suggested_chips=?, system_preamble=?, accent_color=?, model=?, max_tokens=?, enabled=? WHERE id=?");
            $stmt->execute([$data['slug'], $data['name'], $data['greeting'], $data['suggested_chips'], $data['system_preamble'], $data['accent_color'], $data['model'], $data['max_tokens'], $data['enabled'], $id]);
            header("Location: admin.php?do=sources&agent=$id&msg=" . urlencode('Agent saved'));
        } else {
            $stmt = $pdo->prepare("INSERT INTO agent_agents (slug,name,greeting,suggested_chips,system_preamble,accent_color,model,max_tokens,enabled) VALUES (?,?,?,?,?,?,?,?,?)");
            $stmt->execute([$data['slug'], $data['name'], $data['greeting'], $data['suggested_chips'], $data['system_preamble'], $data['accent_color'], $data['model'], $data['max_tokens'], $data['enabled']]);
            $newId = (int)$pdo->lastInsertId();
            header("Location: admin.php?do=sources&agent=$newId&msg=" . urlencode('Agent created'));
        }
        exit;
    }

    // --- Add or update source ---
    if (($_POST['action'] ?? '') === 'save_source') {
        $sid = (int)($_POST['id'] ?? 0);
        $aid = (int)($_POST['agent_id'] ?? 0);
        $label = trim($_POST['label'] ?? '');
        $priority = (int)($_POST['priority'] ?? 50);
        $notes = trim($_POST['notes'] ?? '');
        $enabled = isset($_POST['enabled']) ? 1 : 0;
        $content = $_POST['content'] ?? '';
        $fileName = null;

        if (!empty($_FILES['file']['tmp_name'])) {
            list($extracted, $err) = extract_uploaded_text($_FILES['file']);
            if ($err) {
                header("Location: admin.php?do=sources&agent=$aid&msg=" . urlencode($err));
                exit;
            }
            $content = $extracted;
            $fileName = $_FILES['file']['name'];
            if (!$label) $label = pathinfo($fileName, PATHINFO_FILENAME);
        }

        if (!$label || $content === '') {
            header("Location: admin.php?do=sources&agent=$aid&msg=" . urlencode('Label and content (paste or file) required'));
            exit;
        }

        if ($sid) {
            $stmt = $pdo->prepare("UPDATE agent_sources SET label=?, content=?, priority=?, enabled=?, notes=?" . ($fileName ? ", file_name=?" : "") . " WHERE id=? AND agent_id=?");
            $params = [$label, $content, $priority, $enabled, $notes];
            if ($fileName) $params[] = $fileName;
            $params[] = $sid; $params[] = $aid;
            $stmt->execute($params);
            header("Location: admin.php?do=sources&agent=$aid&msg=" . urlencode('Source updated'));
        } else {
            $stmt = $pdo->prepare("INSERT INTO agent_sources (agent_id,label,content,priority,enabled,notes,file_name) VALUES (?,?,?,?,?,?,?)");
            $stmt->execute([$aid, $label, $content, $priority, $enabled, $notes, $fileName]);
            header("Location: admin.php?do=sources&agent=$aid&msg=" . urlencode('Source added'));
        }
        exit;
    }

    // --- AJAX: inline priority / toggle ---
    if (($_POST['action'] ?? '') === 'quick_update') {
        header('Content-Type: application/json');
        $sid = (int)($_POST['id'] ?? 0);
        $field = $_POST['field'] ?? '';
        $val = $_POST['value'] ?? '';
        if (!in_array($field, ['priority', 'enabled'], true) || !$sid) { echo json_encode(['ok'=>false]); exit; }
        if ($field === 'priority') $val = max(0, min(100, (int)$val));
        else $val = $val ? 1 : 0;
        $pdo->prepare("UPDATE agent_sources SET $field = ? WHERE id = ?")->execute([$val, $sid]);
        echo json_encode(['ok' => true, 'value' => $val]);
        exit;
    }
}

// --- Delete handlers via GET (with confirm in UI) ---
if ($do === 'delete_source' && isset($_GET['id'])) {
    $sid = (int)$_GET['id'];
    $row = $pdo->prepare("SELECT agent_id FROM agent_sources WHERE id=?");
    $row->execute([$sid]);
    $r = $row->fetch();
    if ($r) {
        $pdo->prepare("DELETE FROM agent_sources WHERE id=?")->execute([$sid]);
        header("Location: admin.php?do=sources&agent={$r['agent_id']}&msg=" . urlencode('Source deleted'));
    } else { header("Location: admin.php"); }
    exit;
}
if ($do === 'delete_agent' && isset($_GET['id'])) {
    $pdo->prepare("DELETE FROM agent_agents WHERE id=?")->execute([(int)$_GET['id']]);
    header("Location: admin.php?msg=" . urlencode('Agent deleted'));
    exit;
}

// =================== RENDER ===================

$cfg = cfg();
$publicUrl = rtrim($cfg['public_url'], '/');

function shell_open(string $title, ?string $flash) {
    ?><!DOCTYPE html>
<html lang="en"><head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= h($title) ?> — Agent Admin</title>
<style>
  *,*::before,*::after{box-sizing:border-box;margin:0;padding:0}
  body{font-family:-apple-system,BlinkMacSystemFont,"Segoe UI",sans-serif;background:#080808;color:#eeebe5;line-height:1.5}
  a{color:#e8a020;text-decoration:none}
  a:hover{text-decoration:underline}
  nav{background:rgba(8,8,8,.97);border-bottom:1px solid #222;padding:14px 32px;display:flex;align-items:center;justify-content:space-between;position:sticky;top:0;z-index:50;backdrop-filter:blur(10px)}
  nav .brand{font-weight:600;letter-spacing:.5px}
  nav .brand em{color:#e8a020;font-style:normal}
  nav .right{display:flex;gap:14px;font-size:13px}
  .wrap{max-width:1100px;margin:0 auto;padding:32px}
  h1{font-size:26px;font-weight:600;margin-bottom:8px;letter-spacing:-.3px}
  h2{font-size:18px;font-weight:600;margin:24px 0 12px;color:#eeebe5}
  .sub{color:#9a948a;font-size:14px;margin-bottom:24px}
  .flash{background:rgba(34,197,94,.1);border:1px solid rgba(34,197,94,.3);color:#22c55e;padding:10px 14px;border-radius:4px;margin-bottom:20px;font-size:13px}
  .err{background:rgba(244,63,94,.1);border:1px solid rgba(244,63,94,.3);color:#f43f5e;padding:10px 14px;border-radius:4px;margin-bottom:20px;font-size:13px}
  .btn{display:inline-block;padding:8px 16px;background:#e8a020;color:#080808;border:none;border-radius:4px;cursor:pointer;font-size:13px;font-weight:600;letter-spacing:.5px;text-transform:uppercase;text-decoration:none}
  .btn:hover{background:#f0b030;text-decoration:none}
  .btn.sec{background:transparent;color:#9a948a;border:1px solid #2e2e2e}
  .btn.sec:hover{color:#eeebe5;border-color:#444}
  .btn.danger{background:transparent;color:#f43f5e;border:1px solid rgba(244,63,94,.3)}
  .btn.danger:hover{background:rgba(244,63,94,.1)}
  .btn.sm{padding:5px 10px;font-size:11px;letter-spacing:.5px}
  table{width:100%;border-collapse:collapse;background:#101010;border:1px solid #222;border-radius:4px;overflow:hidden}
  th,td{padding:14px 18px;text-align:left;border-bottom:1px solid #1c1c1c;font-size:14px}
  th{background:#161616;color:#9a948a;font-size:11px;letter-spacing:1.5px;text-transform:uppercase;font-weight:600}
  tr:last-child td{border-bottom:none}
  tr:hover td{background:#0e0e0e}
  .card{background:#101010;border:1px solid #222;border-radius:4px;padding:24px;margin-bottom:20px}
  .field{margin-bottom:18px}
  .field label{display:block;font-size:11px;letter-spacing:1.5px;text-transform:uppercase;color:#9a948a;margin-bottom:6px;font-weight:600}
  .field input,.field textarea,.field select{width:100%;padding:10px 12px;background:#161616;border:1px solid #2e2e2e;color:#eeebe5;border-radius:4px;font-size:14px;font-family:inherit}
  .field input:focus,.field textarea:focus,.field select:focus{outline:none;border-color:#e8a020}
  .field textarea{resize:vertical;min-height:90px;font-family:ui-monospace,"SF Mono","Cascadia Code",monospace;font-size:13px;line-height:1.5}
  .field .hint{font-size:12px;color:#52504c;margin-top:4px}
  .grid2{display:grid;grid-template-columns:1fr 1fr;gap:18px}
  .pill{display:inline-block;padding:2px 8px;font-size:11px;border-radius:3px;letter-spacing:.5px}
  .pill.on{background:rgba(34,197,94,.12);color:#22c55e}
  .pill.off{background:rgba(82,80,76,.2);color:#9a948a}
  .priority-input{width:60px;padding:4px 6px;background:#161616;border:1px solid #2e2e2e;color:#eeebe5;border-radius:3px;font-size:13px;text-align:center}
  .toggle{position:relative;display:inline-block;width:36px;height:20px}
  .toggle input{opacity:0;width:0;height:0}
  .toggle .slider{position:absolute;cursor:pointer;inset:0;background:#2e2e2e;border-radius:20px;transition:.2s}
  .toggle .slider:before{position:absolute;content:"";height:14px;width:14px;left:3px;bottom:3px;background:#eeebe5;border-radius:50%;transition:.2s}
  .toggle input:checked + .slider{background:#22c55e}
  .toggle input:checked + .slider:before{transform:translateX(16px)}
  .drop{border:2px dashed #2e2e2e;border-radius:6px;padding:24px;text-align:center;color:#52504c;cursor:pointer;transition:.15s;background:#0c0c0c}
  .drop.over{border-color:#e8a020;color:#e8a020;background:rgba(232,160,32,.05)}
  .drop strong{display:block;color:#9a948a;margin-bottom:4px;font-size:13px}
  .drop small{font-size:12px}
  .drop input[type=file]{display:none}
  pre.embed{background:#0c0c0c;border:1px solid #222;border-radius:4px;padding:14px;font-size:12px;overflow-x:auto;color:#9a948a;line-height:1.6;white-space:pre}
  .row{display:flex;align-items:center;gap:12px}
  .actions{display:flex;gap:6px;flex-wrap:wrap}
  .charcount{font-size:11px;color:#52504c}
</style>
</head><body>
<nav>
  <div class="brand">Agent <em>·</em> Admin</div>
  <div class="right">
    <a href="admin.php">Agents</a>
    <a href="admin.php?logout=1">Sign out</a>
  </div>
</nav>
<div class="wrap">
<?php if ($flash): ?><div class="flash"><?= h($flash) ?></div><?php endif; ?>
<?php }

function shell_close() {
    ?>
</div>
<script>
function quickUpdate(id, field, value, el) {
  var fd = new FormData();
  fd.append('action','quick_update'); fd.append('id',id); fd.append('field',field); fd.append('value',value);
  fetch('admin.php',{method:'POST',body:fd}).then(r=>r.json()).then(d=>{
    if (el && d.ok) { el.style.outline='2px solid #22c55e'; setTimeout(()=>el.style.outline='',500); }
  });
}
// File drop
document.querySelectorAll('.drop').forEach(function(zone){
  var input = zone.querySelector('input[type=file]');
  zone.addEventListener('click', function(){ input.click(); });
  ['dragenter','dragover'].forEach(e=>zone.addEventListener(e,ev=>{ev.preventDefault();zone.classList.add('over');}));
  ['dragleave','drop'].forEach(e=>zone.addEventListener(e,ev=>{ev.preventDefault();zone.classList.remove('over');}));
  zone.addEventListener('drop',function(ev){
    if (ev.dataTransfer.files.length) {
      input.files = ev.dataTransfer.files;
      var nameEl = zone.querySelector('.dropname');
      if (nameEl) nameEl.textContent = ev.dataTransfer.files[0].name;
    }
  });
  input.addEventListener('change', function(){
    var nameEl = zone.querySelector('.dropname');
    if (nameEl && input.files.length) nameEl.textContent = input.files[0].name;
  });
});
</script>
</body></html>
<?php }

// =================== VIEWS ===================

if ($do === 'sources') {
    $agent = $agentId ? agent_by_id($agentId) : null;
    if (!$agent) { header('Location: admin.php'); exit; }
    $sources = sources_for_agent($agentId, false);
    $totalChars = array_sum(array_map(fn($s) => strlen($s['content']), array_filter($sources, fn($s) => $s['enabled'])));

    shell_open("Sources — {$agent['name']}", $flash);
    ?>
    <h1><?= h($agent['name']) ?></h1>
    <div class="sub">
      <code>slug:</code> <strong><?= h($agent['slug']) ?></strong>
      &nbsp;·&nbsp; <?= count($sources) ?> source<?= count($sources)===1?'':'s' ?>
      &nbsp;·&nbsp; <?= number_format($totalChars) ?> active chars in prompt
      &nbsp;·&nbsp; <a href="admin.php?do=edit_agent&agent=<?= $agentId ?>">Edit settings</a>
      &nbsp;·&nbsp; <a href="admin.php">All agents</a>
    </div>

    <h2>Embed snippet</h2>
    <div class="card">
      <pre class="embed">&lt;script&gt;
  window.AGENT = {
    host: '<?= h($publicUrl) ?>',
    slug: '<?= h($agent['slug']) ?>',
    title: '<?= h($agent['name']) ?>',
    accent: '<?= h($agent['accent_color']) ?>'<?php
    $chips = json_decode($agent['suggested_chips'] ?: '[]', true) ?: [];
    if ($chips) echo ",\n    chips: " . json_encode($chips);
    ?>

  };
&lt;/script&gt;
&lt;script src="<?= h($publicUrl) ?>/widget.js" defer&gt;&lt;/script&gt;</pre>
    </div>

    <h2>Sources <span style="font-size:13px;font-weight:400;color:#52504c">(higher priority appears first in the prompt)</span></h2>
    <?php if (empty($sources)): ?>
      <div class="card" style="text-align:center;color:#52504c">No sources yet. Add one below.</div>
    <?php else: ?>
    <table>
      <thead><tr>
        <th style="width:50px">On</th><th>Label</th><th style="width:90px">Priority</th>
        <th style="width:100px">Chars</th><th style="width:160px">Updated</th><th style="width:160px"></th>
      </tr></thead>
      <tbody>
      <?php foreach ($sources as $s): ?>
        <tr>
          <td><label class="toggle"><input type="checkbox" <?= $s['enabled']?'checked':'' ?>
              onchange="quickUpdate(<?= $s['id'] ?>,'enabled',this.checked?1:0,this)"><span class="slider"></span></label></td>
          <td>
            <div><strong><?= h($s['label']) ?></strong></div>
            <?php if ($s['notes']): ?><div style="font-size:12px;color:#52504c;margin-top:2px"><?= h($s['notes']) ?></div><?php endif; ?>
            <?php if ($s['file_name']): ?><div style="font-size:11px;color:#52504c;margin-top:2px">file: <?= h($s['file_name']) ?></div><?php endif; ?>
          </td>
          <td><input class="priority-input" type="number" min="0" max="100" value="<?= (int)$s['priority'] ?>"
              onchange="quickUpdate(<?= $s['id'] ?>,'priority',this.value,this)"></td>
          <td class="charcount"><?= number_format(strlen($s['content'])) ?></td>
          <td style="font-size:12px;color:#52504c"><?= h(date('M j, Y g:ia', strtotime($s['updated_at']))) ?></td>
          <td class="actions">
            <a href="admin.php?do=edit_source&agent=<?= $agentId ?>&id=<?= $s['id'] ?>" class="btn sm sec">Edit</a>
            <a href="admin.php?do=delete_source&id=<?= $s['id'] ?>" class="btn sm danger"
               onclick="return confirm('Delete this source?')">Delete</a>
          </td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
    <?php endif; ?>

    <h2>Add a source</h2>
    <div class="card">
      <form method="post" enctype="multipart/form-data">
        <input type="hidden" name="action" value="save_source">
        <input type="hidden" name="agent_id" value="<?= $agentId ?>">

        <label class="drop" tabindex="0">
          <input type="file" name="file" accept=".txt,.md,.html,.htm,.json,.csv">
          <strong>Drop a file here or click to upload</strong>
          <small>.txt, .md, .html, .json, .csv supported · PDF: convert first or paste below</small>
          <div class="dropname" style="color:#e8a020;margin-top:8px;font-size:13px"></div>
        </label>

        <div class="grid2" style="margin-top:18px">
          <div class="field"><label>Label</label>
            <input type="text" name="label" placeholder="e.g. Resume Snapshot, Project Inventory">
            <div class="hint">Auto-filled from filename if you upload</div>
          </div>
          <div class="field"><label>Priority (0-100)</label>
            <input type="number" name="priority" value="50" min="0" max="100">
            <div class="hint">Higher = appears first in the prompt context</div>
          </div>
        </div>

        <div class="field"><label>Content (or upload above)</label>
          <textarea name="content" rows="10" placeholder="Paste content here, or upload a file above"></textarea>
        </div>

        <div class="field"><label>Notes (optional)</label>
          <input type="text" name="notes" maxlength="500" placeholder="Internal note about this source">
        </div>

        <div class="row">
          <label class="toggle"><input type="checkbox" name="enabled" checked><span class="slider"></span></label>
          <span style="font-size:13px;color:#9a948a">Enabled</span>
        </div>

        <div style="margin-top:20px">
          <button type="submit" class="btn">Add Source</button>
        </div>
      </form>
    </div>
    <?php
    shell_close();
    exit;
}

if ($do === 'edit_source') {
    $sid = (int)($_GET['id'] ?? 0);
    $stmt = $pdo->prepare("SELECT * FROM agent_sources WHERE id=?");
    $stmt->execute([$sid]);
    $src = $stmt->fetch();
    if (!$src) { header('Location: admin.php'); exit; }
    $agent = agent_by_id((int)$src['agent_id']);

    shell_open("Edit Source", $flash);
    ?>
    <div class="sub"><a href="admin.php?do=sources&agent=<?= $src['agent_id'] ?>">← Back to <?= h($agent['name']) ?></a></div>
    <h1>Edit Source</h1>
    <div class="card">
      <form method="post" enctype="multipart/form-data">
        <input type="hidden" name="action" value="save_source">
        <input type="hidden" name="id" value="<?= $src['id'] ?>">
        <input type="hidden" name="agent_id" value="<?= $src['agent_id'] ?>">

        <label class="drop" tabindex="0">
          <input type="file" name="file" accept=".txt,.md,.html,.htm,.json,.csv">
          <strong>Drop a file to replace content</strong>
          <small>(leave empty to keep current content)</small>
          <div class="dropname" style="color:#e8a020;margin-top:8px;font-size:13px"></div>
        </label>

        <div class="grid2" style="margin-top:18px">
          <div class="field"><label>Label</label>
            <input type="text" name="label" value="<?= h($src['label']) ?>" required></div>
          <div class="field"><label>Priority (0-100)</label>
            <input type="number" name="priority" value="<?= (int)$src['priority'] ?>" min="0" max="100"></div>
        </div>

        <div class="field"><label>Content (<?= number_format(strlen($src['content'])) ?> chars)</label>
          <textarea name="content" rows="20"><?= h($src['content']) ?></textarea>
        </div>

        <div class="field"><label>Notes</label>
          <input type="text" name="notes" maxlength="500" value="<?= h($src['notes']) ?>"></div>

        <div class="row">
          <label class="toggle"><input type="checkbox" name="enabled" <?= $src['enabled']?'checked':'' ?>><span class="slider"></span></label>
          <span style="font-size:13px;color:#9a948a">Enabled</span>
        </div>

        <div style="margin-top:20px;display:flex;gap:8px">
          <button type="submit" class="btn">Save</button>
          <a href="admin.php?do=sources&agent=<?= $src['agent_id'] ?>" class="btn sec">Cancel</a>
          <a href="admin.php?do=delete_source&id=<?= $src['id'] ?>" class="btn danger" onclick="return confirm('Delete?')" style="margin-left:auto">Delete</a>
        </div>
      </form>
    </div>
    <?php
    shell_close();
    exit;
}

if ($do === 'edit_agent' || $do === 'new_agent') {
    $agent = ($do === 'edit_agent' && $agentId) ? agent_by_id($agentId) : null;
    $isNew = !$agent;
    $chips = $agent ? (json_decode($agent['suggested_chips'] ?: '[]', true) ?: []) : [];

    shell_open($isNew ? 'New Agent' : 'Edit Agent', $flash);
    ?>
    <div class="sub"><a href="admin.php">← All agents</a></div>
    <h1><?= $isNew ? 'New Agent' : 'Edit ' . h($agent['name']) ?></h1>

    <div class="card">
      <form method="post">
        <input type="hidden" name="action" value="save_agent">
        <?php if (!$isNew): ?><input type="hidden" name="id" value="<?= $agent['id'] ?>"><?php endif; ?>

        <div class="grid2">
          <div class="field"><label>Name (shown in widget)</label>
            <input type="text" name="name" value="<?= h($agent['name'] ?? '') ?>" required></div>
          <div class="field"><label>Slug (URL-safe id)</label>
            <input type="text" name="slug" value="<?= h($agent['slug'] ?? '') ?>" required pattern="[a-z0-9_-]+">
            <div class="hint">Lowercase, no spaces. Used in embed snippet.</div></div>
        </div>

        <div class="field"><label>Greeting (first message)</label>
          <textarea name="greeting" rows="2"><?= h($agent['greeting'] ?? '') ?></textarea></div>

        <div class="field"><label>Suggested chips (one per line)</label>
          <textarea name="chips" rows="4" placeholder="Experience&#10;Projects&#10;AI work"><?= h(implode("\n", $chips)) ?></textarea>
          <div class="hint">Optional buttons users can tap to ask common questions</div></div>

        <div class="field"><label>System preamble (instructions before reference docs)</label>
          <textarea name="system_preamble" rows="6"><?= h($agent['system_preamble'] ?? '') ?></textarea>
          <div class="hint">The persona, rules, and guardrails. Reference documents are appended automatically.</div></div>

        <div class="grid2">
          <div class="field"><label>Accent color</label>
            <input type="text" name="accent_color" value="<?= h($agent['accent_color'] ?? '#1a1a1a') ?>" placeholder="#e8a020"></div>
          <div class="field"><label>Model (optional override)</label>
            <input type="text" name="model" value="<?= h($agent['model'] ?? '') ?>" placeholder="<?= h($cfg['model']) ?>">
            <div class="hint">Leave blank to use config default</div></div>
        </div>

        <div class="grid2">
          <div class="field"><label>Max tokens (optional override)</label>
            <input type="number" name="max_tokens" value="<?= h($agent['max_tokens'] ?? '') ?>" placeholder="<?= (int)$cfg['max_tokens'] ?>"></div>
          <div class="field" style="display:flex;align-items:flex-end;padding-bottom:10px">
            <label class="toggle"><input type="checkbox" name="enabled" <?= ($agent['enabled'] ?? 1)?'checked':'' ?>><span class="slider"></span></label>
            <span style="font-size:13px;color:#9a948a;margin-left:10px">Enabled</span>
          </div>
        </div>

        <div style="margin-top:20px;display:flex;gap:8px">
          <button type="submit" class="btn"><?= $isNew ? 'Create Agent' : 'Save' ?></button>
          <a href="admin.php" class="btn sec">Cancel</a>
          <?php if (!$isNew): ?>
            <a href="admin.php?do=delete_agent&id=<?= $agent['id'] ?>" class="btn danger"
               onclick="return confirm('Delete agent and ALL its sources?')" style="margin-left:auto">Delete Agent</a>
          <?php endif; ?>
        </div>
      </form>
    </div>
    <?php
    shell_close();
    exit;
}

// === Default: list of agents ===
$agents = agents_all();
shell_open('Agents', $flash);
?>
<h1>Agents</h1>
<div class="sub"><?= count($agents) ?> agent<?= count($agents)===1?'':'s' ?> · <a href="admin.php?do=new_agent">+ New agent</a></div>

<?php if (empty($agents)): ?>
  <div class="card" style="text-align:center;color:#52504c">
    No agents yet. <a href="install.php">Run install.php</a> to seed the default resume agent, or <a href="admin.php?do=new_agent">create one manually</a>.
  </div>
<?php else: ?>
<table>
  <thead><tr>
    <th>Name</th><th>Slug</th><th style="width:90px">Status</th>
    <th style="width:90px">Sources</th><th style="width:200px"></th>
  </tr></thead>
  <tbody>
  <?php foreach ($agents as $a):
    $count = $pdo->prepare("SELECT COUNT(*) FROM agent_sources WHERE agent_id=? AND enabled=1");
    $count->execute([$a['id']]);
    $n = (int)$count->fetchColumn();
  ?>
    <tr>
      <td><strong><?= h($a['name']) ?></strong></td>
      <td><code style="color:#9a948a"><?= h($a['slug']) ?></code></td>
      <td><span class="pill <?= $a['enabled']?'on':'off' ?>"><?= $a['enabled']?'Enabled':'Disabled' ?></span></td>
      <td><?= $n ?> active</td>
      <td class="actions">
        <a href="admin.php?do=sources&agent=<?= $a['id'] ?>" class="btn sm">Sources</a>
        <a href="admin.php?do=edit_agent&agent=<?= $a['id'] ?>" class="btn sm sec">Settings</a>
      </td>
    </tr>
  <?php endforeach; ?>
  </tbody>
</table>
<?php endif; ?>
<?php shell_close();
