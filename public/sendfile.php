<?php
/** Send Anirudha a file — videos above all.
 *
 *  Why this exists: the Freelancer message system will not let me download a
 *  video attachment, at all. William can attach one perfectly well; it simply
 *  never reaches me. He makes Facebook videos regularly now, so rather than
 *  asking him to learn Google Drive, this puts a "send" button on the site he
 *  already signs into from his phone every day.
 *
 *  Three decisions worth knowing about:
 *
 *  1. Files land in legacy/inbox/, which is OUTSIDE the web root (legacy/public).
 *     Nothing sent here can be fetched by URL, guessed, or executed. I collect
 *     them over FTP. An .htaccess denying everything is put there as well, in
 *     case the web root is ever moved.
 *
 *  2. The upload goes in pieces of 1.5 MB. The host's upload limit is unknown
 *     from here (cPanel's default is 2 MB, which no video fits in), and a phone
 *     on mobile data drops connections. Small pieces get under any sane limit,
 *     and a piece that fails is retried on its own instead of starting the
 *     whole video again.
 *
 *  3. "Sent" is only shown once the server has the whole file and its size
 *     matches what the phone said it was sending. A progress bar that reaches
 *     100% is not the same thing as a file that arrived.
 */
require __DIR__ . '/../src/bootstrap.php';
require_role('admin');

$INBOX = dirname(__DIR__) . '/inbox';
$PARTS = $INBOX . '/.parts';
$CHUNK = 1536 * 1024;                  // 1.5 MB, under cPanel's 2 MB default
$MAX   = 1024 * 1024 * 1024;           // 1 GB is far beyond any phone video
$ALLOW = ['mp4','mov','m4v','webm','avi','mp3','m4a','wav','aac',
          'jpg','jpeg','png','heic','gif','pdf','doc','docx','txt','zip'];

function sf_ready($INBOX, $PARTS) {
    if (!is_dir($PARTS)) @mkdir($PARTS, 0755, true);
    $ht = $INBOX . '/.htaccess';
    if (!is_file($ht)) @file_put_contents($ht, "Require all denied\nDeny from all\n");
    /* An upload abandoned halfway - the phone locked, the signal went - leaves
       its half-file here forever otherwise. Two days is long enough that no
       upload still in progress is ever swept away. */
    foreach ((array)glob($PARTS . '/*') as $p) {
        if (!preg_match('/\.(part|json)$/', $p)) continue;
        if (is_file($p) && filemtime($p) < time() - 2 * 86400) @unlink($p);
    }
    return is_dir($PARTS) && is_writable($PARTS);
}

function sf_json($a, $code = 200) {
    http_response_code($code);
    header('Content-Type: application/json');
    echo json_encode($a);
    exit;
}

function sf_clean_name($n) {
    $n = basename(str_replace('\\', '/', (string)$n));
    $n = preg_replace('/[^A-Za-z0-9._ -]+/', '_', $n);
    $n = trim(preg_replace('/\s+/', ' ', $n), ' .');
    return $n === '' ? 'file' : substr($n, 0, 120);
}

/* ------------------------------------------------------------ receiving */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    if (!sf_ready($INBOX, $PARTS)) sf_json(['ok' => false, 'error' => 'The inbox folder could not be created.'], 500);

    $id = (string)($_POST['id'] ?? '');
    if (!preg_match('/^[a-f0-9]{24}$/', $id)) sf_json(['ok' => false, 'error' => 'Bad upload id.'], 400);
    $part = $PARTS . '/' . $id . '.part';
    $meta = $PARTS . '/' . $id . '.json';
    $act  = (string)($_POST['action'] ?? '');

    if ($act === 'chunk') {
        $i     = (int)($_POST['index'] ?? -1);
        $size  = (int)($_POST['size'] ?? 0);
        $name  = sf_clean_name($_POST['name'] ?? '');
        $ext   = strtolower(pathinfo($name, PATHINFO_EXTENSION));

        if (!in_array($ext, $ALLOW, true)) sf_json(['ok' => false, 'error' => 'That kind of file cannot be sent here.'], 400);
        if ($size <= 0 || $size > $MAX)    sf_json(['ok' => false, 'error' => 'That file is too large.'], 400);
        $f = $_FILES['blob'] ?? null;
        if (!$f || $f['error'] !== UPLOAD_ERR_OK || !is_uploaded_file($f['tmp_name'])) {
            sf_json(['ok' => false, 'error' => 'A piece did not arrive (code ' . (int)($f['error'] ?? -1) . ').'], 400);
        }

        $m = is_file($meta) ? json_decode((string)file_get_contents($meta), true) : null;
        if ($i === 0) {
            $m = ['name' => $name, 'size' => $size, 'next' => 0, 'by' => (string)(current_user()['name'] ?? '')];
            @unlink($part);
        }
        if (!$m || $m['name'] !== $name || (int)$m['size'] !== $size) {
            sf_json(['ok' => false, 'error' => 'This upload was not started properly. Please try again.'], 409);
        }
        /* A retry of the piece we already have - the phone lost the reply, not
           the upload. Say yes again rather than appending it twice. */
        if ($i === (int)$m['next'] - 1) sf_json(['ok' => true, 'have' => filesize($part)]);
        if ($i !== (int)$m['next'])      sf_json(['ok' => false, 'error' => 'Pieces arrived out of order.'], 409);

        $in  = fopen($f['tmp_name'], 'rb');
        $out = fopen($part, 'ab');
        if (!$in || !$out) sf_json(['ok' => false, 'error' => 'Could not save that piece.'], 500);
        stream_copy_to_stream($in, $out);
        fclose($in); fclose($out);

        $m['next'] = $i + 1;
        file_put_contents($meta, json_encode($m));
        clearstatcache(true, $part);
        if (filesize($part) > $size) sf_json(['ok' => false, 'error' => 'More arrived than was sent.'], 409);
        sf_json(['ok' => true, 'have' => filesize($part)]);
    }

    if ($act === 'finish') {
        $m = is_file($meta) ? json_decode((string)file_get_contents($meta), true) : null;
        clearstatcache(true, $part);
        $have = is_file($part) ? filesize($part) : -1;
        if (!$m || $have !== (int)$m['size']) {
            sf_json(['ok' => false, 'error' => 'The file is incomplete (' . max(0, $have) . ' of '
                . (int)($m['size'] ?? 0) . ' bytes). Please send it again.'], 409);
        }
        $final = $INBOX . '/' . date('Y-m-d_His') . '_' . substr($id, 0, 6) . '_' . $m['name'];
        if (!@rename($part, $final)) sf_json(['ok' => false, 'error' => 'Could not file it away.'], 500);
        @unlink($meta);
        @file_put_contents($final . '.txt',
            "sent by: " . $m['by'] . "\nwhen:    " . date('Y-m-d H:i:s T') . "\nbytes:   " . $m['size'] . "\n");
        sf_json(['ok' => true, 'name' => $m['name'], 'bytes' => (int)$m['size']]);
    }

    sf_json(['ok' => false, 'error' => 'Unknown action.'], 400);
}

/* ------------------------------------------------------------- the page */
sf_ready($INBOX, $PARTS);
$sent = [];
foreach ((array)glob($INBOX . '/*') as $p) {
    if (!is_file($p) || substr($p, -4) === '.txt' || basename($p)[0] === '.') continue;
    $sent[] = ['name' => preg_replace('/^\d{4}-\d{2}-\d{2}_\d{6}_[a-f0-9]{6}_/', '', basename($p)),
               'when' => filemtime($p), 'bytes' => filesize($p)];
}
usort($sent, function ($a, $b) { return $b['when'] - $a['when']; });
$sent = array_slice($sent, 0, 8);

page_head('Send Anirudha a file');
?>
<style>
.sf{max-width:640px;margin:0 auto}
.sf .panel{margin-top:18px}
.sf-pick{display:block;width:100%;padding:22px 16px;border:2px dashed rgba(201,162,75,.6);border-radius:14px;
  text-align:center;cursor:pointer;color:var(--gold2);font-size:19px;background:rgba(0,0,0,.18)}
.sf-pick input{display:none}
.sf-file{margin:14px 0 0;font-size:16px;word-break:break-word}
.sf-bar{height:14px;border-radius:7px;background:rgba(255,255,255,.12);overflow:hidden;margin:16px 0 8px;display:none}
.sf-bar i{display:block;height:100%;width:0;background:var(--gold);transition:width .25s}
.sf-msg{min-height:1.4em;font-size:16px}
.sf-msg.ok{color:#9fe0a8}.sf-msg.bad{color:#ffb4a2}
.sf-go{width:100%;margin-top:14px;font-size:18px;padding:14px}
.sf-go[disabled]{opacity:.45;cursor:default}
.sf-list{list-style:none;padding:0;margin:8px 0 0}
.sf-list li{padding:8px 0;border-top:1px solid rgba(201,162,75,.18);font-size:15px}
.sf-list small{opacity:.7}
</style>
<div class="wrap sf">
  <div class="panel">
    <h1 style="margin-top:0">Send Anirudha a file</h1>
    <p class="muted" style="margin:0">For videos, music or anything too big for the Freelancer messages.
      It comes straight to me and is not visible to anyone else on the site.</p>

    <label class="sf-pick" id="sf-pick">&#128249; Choose the file
      <input type="file" id="sf-in" accept="video/*,audio/*,image/*,.pdf,.doc,.docx,.zip,.txt">
    </label>
    <p class="sf-file" id="sf-file"></p>
    <div class="sf-bar" id="sf-bar"><i id="sf-fill"></i></div>
    <p class="sf-msg" id="sf-msg"></p>
    <button type="button" class="btn gold sf-go" id="sf-go" disabled>Send it</button>
    <p class="muted" style="font-size:14px;margin:12px 0 0">Keep this page open until it says <b>Sent</b>.
      A one-minute video takes a minute or two on a phone.</p>
  </div>

  <?php if ($sent): ?>
  <div class="panel">
    <h2 style="margin-top:0">Already sent</h2>
    <ul class="sf-list">
      <?php foreach ($sent as $s): ?>
        <li><?= e($s['name']) ?><br><small><?= e(date('j M Y, g:ia', $s['when'])) ?> &middot;
          <?= $s['bytes'] >= 1048576 ? round($s['bytes'] / 1048576, 1) . ' MB' : max(1, round($s['bytes'] / 1024)) . ' KB' ?></small></li>
      <?php endforeach; ?>
    </ul>
  </div>
  <?php endif; ?>
</div>

<script>
(function () {
  var CHUNK = <?= (int)$CHUNK ?>, MAX = <?= (int)$MAX ?>, CSRF = <?= json_encode(csrf_token()) ?>;
  var inp = document.getElementById('sf-in'), go = document.getElementById('sf-go'),
      fileP = document.getElementById('sf-file'), bar = document.getElementById('sf-bar'),
      fill = document.getElementById('sf-fill'), msg = document.getElementById('sf-msg');
  var file = null, busy = false;

  function say(t, cls) { msg.textContent = t; msg.className = 'sf-msg' + (cls ? ' ' + cls : ''); }
  function mb(n) { return n >= 1048576 ? (n / 1048576).toFixed(1) + ' MB' : Math.max(1, Math.round(n / 1024)) + ' KB'; }
  function newId() {
    var a = new Uint8Array(12), s = '';
    (window.crypto || window.msCrypto).getRandomValues(a);
    for (var i = 0; i < a.length; i++) s += ('0' + a[i].toString(16)).slice(-2);
    return s;
  }

  inp.addEventListener('change', function () {
    file = inp.files && inp.files[0] ? inp.files[0] : null;
    if (!file) { fileP.textContent = ''; go.disabled = true; return; }
    if (file.size > MAX) { say('That file is over 1 GB — too big to send this way.', 'bad'); go.disabled = true; return; }
    fileP.textContent = file.name + ' — ' + mb(file.size);
    say(''); go.disabled = false;
  });

  function post(fd) {
    return fetch('sendfile.php', { method: 'POST', body: fd, credentials: 'same-origin' })
      .then(function (r) {
        return r.text().then(function (t) {
          var j; try { j = JSON.parse(t); } catch (e) { j = { ok: false, error: 'The site answered unexpectedly (' + r.status + ').' }; }
          return j;
        });
      });
  }

  /* Each piece gets three tries with a pause between them. On a phone the
     usual failure is the signal dropping for a few seconds, not a real fault. */
  function sendPiece(id, i, tries) {
    var start = i * CHUNK, fd = new FormData();
    fd.append('csrf', CSRF); fd.append('action', 'chunk'); fd.append('id', id);
    fd.append('index', i); fd.append('size', file.size); fd.append('name', file.name);
    fd.append('blob', file.slice(start, Math.min(file.size, start + CHUNK)), 'blob');
    return post(fd).then(function (j) {
      if (j.ok) return j;
      throw new Error(j.error || 'failed');
    }).catch(function (e) {
      if (tries > 1) {
        say('Signal wobble — trying that piece again…');
        return new Promise(function (res) { setTimeout(res, 2500); })
          .then(function () { return sendPiece(id, i, tries - 1); });
      }
      throw e;
    });
  }

  go.addEventListener('click', function () {
    if (!file || busy) return;
    busy = true; go.disabled = true; inp.disabled = true;
    bar.style.display = 'block'; fill.style.width = '0';
    var id = newId(), n = Math.max(1, Math.ceil(file.size / CHUNK)), i = 0;
    say('Sending…');

    function next() {
      if (i >= n) {
        var fd = new FormData();
        fd.append('csrf', CSRF); fd.append('action', 'finish'); fd.append('id', id);
        return post(fd).then(function (j) {
          if (!j.ok) throw new Error(j.error || 'The last step failed.');
          fill.style.width = '100%';
          say('Sent — ' + j.name + ', ' + mb(j.bytes) + '. Anirudha has it.', 'ok');
          setTimeout(function () { location.reload(); }, 3500);
        });
      }
      return sendPiece(id, i, 3).then(function () {
        i++;
        fill.style.width = Math.floor(i / n * 99) + '%';
        say('Sending… ' + Math.floor(i / n * 100) + '%');
        return next();
      });
    }
    next().catch(function (e) {
      say('It did not go through: ' + e.message + ' Please press Send to try again.', 'bad');
      busy = false; go.disabled = false; inp.disabled = false;
    });
  });
})();
</script>
<?php legacy_footer();
page_foot();
