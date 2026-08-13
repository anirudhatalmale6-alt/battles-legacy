<?php
/** Music on a banner. One track per "slot" (home, aahistory, …), each with its
 *  own upload box so William can change a song without me.
 *
 *  Browsers will not make sound before a visitor has touched the page, so the
 *  player also arms itself on the first click, key or tap, and it remembers
 *  anyone who switches it off. */
require_once __DIR__ . '/db.php';

function music_migrate() {
    static $done = false;
    if ($done) return; $done = true;
    $driver = db_driver();
    $ENG = $driver === 'sqlite' ? '' : ' ENGINE=InnoDB DEFAULT CHARSET=utf8mb4';
    try {
        db()->exec("CREATE TABLE IF NOT EXISTS music_tracks (
          slot VARCHAR(24) NOT NULL PRIMARY KEY, file VARCHAR(255) DEFAULT '',
          title VARCHAR(160) DEFAULT '', auto INT NOT NULL DEFAULT 1,
          updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )$ENG");
    } catch (\Throwable $e) { /* a missing player must never take a page down */ }
}

/** The track for a slot, or [] when there isn't one (or the file has gone). */
function music_get($slot) {
    music_migrate();
    try { $r = one("SELECT * FROM music_tracks WHERE slot=?", [(string)$slot]); }
    catch (\Throwable $e) { return []; }
    if (!$r) return [];
    $file = trim((string)$r['file']);
    if ($file === '' || !is_file(dirname(__DIR__) . '/public/' . $file)) return [];
    return ['file' => $file, 'title' => (string)$r['title'], 'auto' => (int)$r['auto'] === 1];
}

function music_set($slot, $file, $title, $auto) {
    music_migrate();
    try {
        if (one("SELECT slot FROM music_tracks WHERE slot=?", [$slot]))
            q("UPDATE music_tracks SET file=?, title=?, auto=? WHERE slot=?", [$file, $title, $auto ? 1 : 0, $slot]);
        else
            q("INSERT INTO music_tracks (slot,file,title,auto) VALUES (?,?,?,?)", [$slot, $file, $title, $auto ? 1 : 0]);
    } catch (\Throwable $e) {}
}

/** Delete the track's file as well as the row, so nothing is left orphaned. */
function music_remove($slot) {
    $cur = music_get($slot);
    if ($cur && strpos($cur['file'], 'assets/music/') === 0) {
        $abs = dirname(__DIR__) . '/public/' . $cur['file'];
        if (is_file($abs)) @unlink($abs);
    }
    try { q("DELETE FROM music_tracks WHERE slot=?", [(string)$slot]); } catch (\Throwable $e) {}
}

/** Save an uploaded track -> assets/music/. Returns [relPath, error]. */
function music_store_file($field = 'track') {
    $rel = 'assets/music';
    if (empty($_FILES[$field]) || $_FILES[$field]['error'] === UPLOAD_ERR_NO_FILE) return ['', ''];
    if (in_array($_FILES[$field]['error'], [UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE], true))
        return ['', 'That file is bigger than the server allows — please use a track under 20 MB.'];
    if ($_FILES[$field]['error'] !== UPLOAD_ERR_OK) return ['', 'The music could not be uploaded — please try again.'];
    if ($_FILES[$field]['size'] > 20 * 1024 * 1024) return ['', 'That track is larger than 20 MB — please use a shorter or smaller file.'];

    $ext = strtolower(pathinfo($_FILES[$field]['name'], PATHINFO_EXTENSION));
    if (!in_array($ext, ['mp3', 'm4a', 'mp4', 'ogg', 'wav'], true))
        return ['', 'Please upload an MP3 (or M4A, OGG, WAV). Other kinds of file will not play in a browser.'];
    $head = @file_get_contents($_FILES[$field]['tmp_name'], false, null, 0, 12);
    if ($head === false || strlen($head) < 4) return ['', 'That file looks empty.'];
    $looksAudio = (substr($head, 0, 3) === 'ID3')
        || (ord($head[0]) === 0xFF && (ord($head[1]) & 0xE0) === 0xE0)
        || (substr($head, 4, 4) === 'ftyp')
        || (substr($head, 0, 4) === 'OggS')
        || (substr($head, 0, 4) === 'RIFF');
    if (!$looksAudio) return ['', 'That does not look like an audio file — please upload the track itself, not a link or a document.'];

    $fname  = date('Ymd_His') . '_' . substr(md5(uniqid('', true)), 0, 6) . '.' . $ext;
    $absDir = dirname(__DIR__) . '/public/' . $rel;
    @mkdir($absDir, 0775, true);
    if (!move_uploaded_file($_FILES[$field]['tmp_name'], $absDir . '/' . $fname)) return ['', 'Sorry — the music could not be saved.'];
    return [$rel . '/' . $fname, ''];
}

/** Handle the admin form for a slot. Redirects and exits when it acted. */
function music_handle_post($slot, $redirect) {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') return;
    if (($_POST['act'] ?? '') !== 'music' || ($_POST['slot'] ?? '') !== $slot) return;
    if (!role_at_least('admin')) return;
    csrf_check();
    if (!empty($_POST['remove'])) {
        music_remove($slot);
        flash('The music has been removed.');
    } else {
        list($rel, $err) = music_store_file('track');
        if ($err) {
            flash($err);
        } else {
            $cur = music_get($slot);
            if ($rel !== '') music_remove($slot);           // drop the old file first
            music_set($slot, $rel !== '' ? $rel : ($cur['file'] ?? ''),
                      trim($_POST['music_title'] ?? ''), !empty($_POST['music_auto']));
            flash($rel !== '' ? 'The music is on the page now.' : 'Music settings saved.');
        }
    }
    header('Location: ' . $redirect); exit;
}

/** The button + the audio element. Nothing is printed when there is no track. */
function music_player($slot, $opts = []) {
    $m = music_get($slot);
    if (!$m) return;
    $cls  = trim('mus ' . ($opts['class'] ?? ''));
    $lead = $opts['lead'] ?? '';
    ?>
    <div class="<?= e($cls) ?>" data-slot="<?= e($slot) ?>" data-auto="<?= $m['auto'] ? '1' : '0' ?>">
      <audio class="mus-audio" loop preload="none" src="<?= e($m['file']) ?>"></audio>
      <?php if ($lead): ?><span class="mus-lead"><?= e($lead) ?></span><?php endif; ?>
      <button type="button" class="mus-btn" aria-pressed="false">
        <span class="mus-eq" aria-hidden="true"><i></i><i></i><i></i><i></i></span>
        <span class="mus-lab">Play the music</span>
      </button>
      <?php if (trim($m['title']) !== ''): ?><span class="mus-title"><?= e($m['title']) ?></span><?php endif; ?>
    </div>
    <?php
}

/** The admin upload box. Prints nothing for anyone who isn't an editor. */
function music_admin_box($slot, $heading = 'Banner music') {
    if (!role_at_least('admin')) return;
    $m = music_get($slot);
    ?>
    <section class="mus-admin">
      <details<?= $m ? '' : ' open' ?>>
        <summary>
          <span class="mus-abt"><?= e($heading) ?></span>
          <span class="mus-abs"><?= $m ? 'Playing: ' . e($m['title'] ?: basename($m['file'])) : 'No track uploaded yet' ?></span>
        </summary>
        <form method="post" enctype="multipart/form-data" class="mus-form">
          <?= csrf_field() ?>
          <input type="hidden" name="act" value="music">
          <input type="hidden" name="slot" value="<?= e($slot) ?>">
          <label class="mus-f">Music file (MP3)<input type="file" name="track" accept="audio/*"></label>
          <label class="mus-f">Name of the track (shown beside the button)
            <input type="text" name="music_title" maxlength="160" value="<?= e($m['title'] ?? '') ?>" placeholder="Keepers of the Story"></label>
          <label class="mus-chk"><input type="checkbox" name="music_auto" value="1"<?= (!$m || $m['auto']) ? ' checked' : '' ?>>
            Start playing as soon as someone opens the page</label>
          <p class="mus-note">A visitor can always stop it with the button, and their choice is remembered.
            Browsers and phones will not make sound until the visitor taps the page once — the button flashes gold when that happens.</p>
          <div class="mus-act">
            <button class="btn2 solid" type="submit">Save</button>
            <?php if ($m): ?><button class="mus-del" type="submit" name="remove" value="1"
              onclick="return confirm('Remove this music?')">Remove the music</button><?php endif; ?>
          </div>
        </form>
      </details>
    </section>
    <?php
}

/** The behaviour, printed once per page however many players are on it. */
function music_script() {
    static $done = false;
    if ($done) return; $done = true;
    ?>
<script>
(function(){
  var boxes = document.querySelectorAll('.mus');
  if (!boxes.length) return;
  Array.prototype.forEach.call(boxes, function(box){
    var a   = box.querySelector('.mus-audio'),
        btn = box.querySelector('.mus-btn'),
        lab = box.querySelector('.mus-lab');
    if (!a || !btn) return;

    var KEY = 'music_' + (box.getAttribute('data-slot') || 'x');
    var pref = null;
    try { pref = localStorage.getItem(KEY); } catch (e) {}
    var wantAuto = box.getAttribute('data-auto') === '1' && pref !== 'off';
    var armed = false;

    function paint(playing){
      btn.classList.toggle('on', playing);
      btn.setAttribute('aria-pressed', playing ? 'true' : 'false');
      if (lab) lab.textContent = playing ? 'Pause the music' : (armed ? 'Tap to play the music' : 'Play the music');
    }
    /* ease the volume up so it never startles anyone */
    function fadeIn(){
      a.volume = 0;
      var v = 0, t = setInterval(function(){
        v += 0.06; if (v >= 0.55) { v = 0.55; clearInterval(t); }
        try { a.volume = v; } catch (e) { clearInterval(t); }
      }, 70);
    }
    function play(remember){
      var p = a.play();
      if (p && p.catch) {
        p.then(function(){ armed = false; box.classList.remove('waiting'); fadeIn(); paint(true);
                           if (remember) { try { localStorage.setItem(KEY,'on'); } catch(e){} } })
         .catch(function(){ try { a.pause(); } catch(e){}   // stop the download it just started
                            arm(); });
      } else { fadeIn(); paint(true); }
    }
    /* the browser said no until the visitor interacts — wait for that instead */
    function arm(){
      if (armed) return;
      armed = true;
      box.classList.add('waiting');
      paint(false);
      var go = function(ev){
        if (ev && btn.contains(ev.target)) return;   // the button handles itself
        off(); play(false);
      };
      var off = function(){
        document.removeEventListener('click', go, true);
        document.removeEventListener('keydown', go, true);
        document.removeEventListener('touchend', go, true);
      };
      document.addEventListener('click', go, true);
      document.addEventListener('keydown', go, true);
      document.addEventListener('touchend', go, true);
    }

    btn.addEventListener('click', function(){
      if (a.paused) { armed = false; box.classList.remove('waiting'); play(true); }
      else { a.pause(); paint(false); try { localStorage.setItem(KEY,'off'); } catch(e){} }
    });
    a.addEventListener('pause', function(){ paint(false); });
    a.addEventListener('play',  function(){ paint(true); });

    paint(false);
    /* Don't even ask when the browser is certain to refuse — calling play()
       starts fetching the track, and an anthem is a big file to pull down for
       a visitor who is about to be told "no" anyway. */
    if (wantAuto) {
      var ua = navigator.userActivation;
      if (ua && ua.hasBeenActive === false) arm(); else play(false);
    }
  });
})();
</script>
<?php
}
