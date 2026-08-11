<?php
/** Family News — announcements/news posts + upcoming events.
 *  Idempotent migration; seeds a few sample entries once so the page looks
 *  complete, which William then edits from the manage screen. */
require_once __DIR__ . '/db.php';

function news_migrate() {
    $driver = db_driver();
    $AI  = $driver === 'sqlite' ? 'INTEGER PRIMARY KEY AUTOINCREMENT' : 'INT AUTO_INCREMENT PRIMARY KEY';
    $ENG = $driver === 'sqlite' ? '' : ' ENGINE=InnoDB DEFAULT CHARSET=utf8mb4';
    db()->exec("CREATE TABLE IF NOT EXISTS news_posts (
      id $AI, category VARCHAR(20) NOT NULL DEFAULT 'news', date_label VARCHAR(60) DEFAULT '',
      title VARCHAR(200) NOT NULL, body TEXT, photo VARCHAR(255) DEFAULT '',
      likes INT NOT NULL DEFAULT 0, comments INT NOT NULL DEFAULT 0,
      sample INT NOT NULL DEFAULT 0, sort INT NOT NULL DEFAULT 0, status VARCHAR(20) NOT NULL DEFAULT 'published',
      created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )$ENG");
    db()->exec("CREATE TABLE IF NOT EXISTS news_events (
      id $AI, mon VARCHAR(4) DEFAULT '', day VARCHAR(4) DEFAULT '', title VARCHAR(200) NOT NULL,
      place VARCHAR(200) DEFAULT '', time_label VARCHAR(100) DEFAULT '',
      sample INT NOT NULL DEFAULT 0, sort INT NOT NULL DEFAULT 0, status VARCHAR(20) NOT NULL DEFAULT 'published',
      created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )$ENG");
    // 'cover' crops the photo to fill the card; 'whole' shows all of it (portrait
    // posters and memorial cards would otherwise be cropped to an unreadable band)
    db_add_column('news_posts', 'photo_fit', "VARCHAR(10) DEFAULT 'cover'");
    news_seed();
}

function news_seed() {
    if (!one("SELECT id FROM news_posts LIMIT 1")) {
        $posts = [
          ['graduation','May 15, 2024','Congratulations to Sydney Battles!','Sydney graduated Summa Cum Laude from Howard University with a degree in Psychology.','',32,8],
          ['birth','May 12, 2024','Welcome Baby Aaliyah Grace!','Proud parents Jasmine & Michael Battles welcomed their beautiful baby girl on May 10, 2024.','',45,12],
          ['marriage','April 27, 2024','Mr. & Mrs. Jordan Battles','Jordan Battles and Brianna Smith were united in marriage on April 26, 2024 in Dallas, TX.','',57,15],
          ['memory','April 20, 2024','Remembering Evelyn Mosley','A beautiful soul who touched so many lives. Forever in our hearts.','',68,21],
        ];
        $i = 0; foreach ($posts as $p) {
            q("INSERT INTO news_posts (category,date_label,title,body,photo,likes,comments,sample,sort) VALUES (?,?,?,?,?,?,?,1,?)",
              [$p[0],$p[1],$p[2],$p[3],$p[4],$p[5],$p[6],$i++]);
        }
    }
    if (!one("SELECT id FROM news_events LIMIT 1")) {
        $events = [
          ['JUN','21','Family Reunion 2024','Tyler Rose Garden Center, Tyler, Texas','10:00 AM – 4:00 PM'],
          ['JUL','04','Independence Day Family Picnic','Bob Woodruff Park, Plano, TX','11:00 AM – 3:00 PM'],
          ['AUG','10','Youth Leadership Workshop','Online Event','10:00 AM – 12:00 PM'],
        ];
        $i = 0; foreach ($events as $e) {
            q("INSERT INTO news_events (mon,day,title,place,time_label,sample,sort) VALUES (?,?,?,?,?,1,?)",
              [$e[0],$e[1],$e[2],$e[3],$e[4],$i++]);
        }
    }
}

/* ---- read helpers (published unless $all) ----
 * Newest first within the same "Order" number, so a new announcement lands at
 * the top on its own — William never has to renumber anything. */
function news_posts($all = false, $cat = '', $limit = 0) {
    $w = [];
    $args = [];
    if (!$all) $w[] = "status='published'";
    if ($cat !== '') { $w[] = "category=?"; $args[] = $cat; }
    $sql = "SELECT * FROM news_posts" . ($w ? " WHERE " . implode(' AND ', $w) : '') . " ORDER BY sort, id DESC";
    if ($limit > 0) $sql .= " LIMIT " . (int)$limit;
    return all($sql, $args);
}
function news_count($cat = '') {
    $r = $cat === ''
        ? one("SELECT COUNT(*) c FROM news_posts WHERE status='published'")
        : one("SELECT COUNT(*) c FROM news_posts WHERE status='published' AND category=?", [$cat]);
    return (int)($r['c'] ?? 0);
}
function news_post($id) { return one("SELECT * FROM news_posts WHERE id=?", [(int)$id]); }
function news_events($all = false) {
    $w = $all ? '' : "WHERE status='published'";
    return all("SELECT * FROM news_events $w ORDER BY sort, id");
}

/** category key => [label, icon, css-class] */
function news_cats() {
    return [
      'birth'      => ['Birth',       'baby',  'c-birth'],
      'graduation' => ['Graduation',  'cap',   'c-grad'],
      'marriage'   => ['Marriage',    'rings', 'c-marr'],
      'reunion'    => ['Reunion',     'people','c-reun'],
      'memory'     => ['In Memory',   'dove',  'c-mem'],
      'news'       => ['News',        'news',  'c-news'],
      'prayer'     => ['Prayer',      'hands', 'c-pray'],
      'anniversary'=> ['Anniversary', 'heart', 'c-anniv'],
      'military'   => ['Service',     'star',  'c-mil'],
    ];
}
function news_cat($key) { $c = news_cats(); return $c[$key] ?? $c['news']; }
function news_cat_ok($key) { return array_key_exists((string)$key, news_cats()) ? (string)$key : 'news'; }

/** line-icon set shared by the news pages */
function news_icon($k) {
  $p = [
    'baby'    => '<circle cx="12" cy="8" r="3.4"/><path d="M6 21c0-3.3 2.7-6 6-6s6 2.7 6 6M9 8h.01M15 8h.01"/>',
    'cap'     => '<path d="M12 5L2 9l10 4 10-4-10-4zM6 11v4c0 1.6 2.7 3 6 3s6-1.4 6-3v-4M20 9.5v4.5"/>',
    'rings'   => '<circle cx="9" cy="14" r="5"/><circle cx="15" cy="14" r="5"/><path d="M9 9l1.5-4h3L15 9"/>',
    'people'  => '<path d="M16 20v-1a4 4 0 0 0-4-4H7a4 4 0 0 0-4 4v1"/><circle cx="9.5" cy="8" r="3.2"/><path d="M17 4.6a3 3 0 0 1 0 5.8M21.5 20v-1a4 4 0 0 0-3-3.8"/>',
    'dove'    => '<path d="M3 13c4 .5 7-1.5 9-5 0 4 2 6 5 6 2 0 4-1.4 4-1.4-1 4-4.4 6.4-8 6.4-4.6 0-8-2.6-10-6z"/><path d="M12 8V4"/>',
    'hands'   => '<path d="M12 21c4-2.5 7-5.6 7-9.3A3.3 3.3 0 0 0 12 9a3.3 3.3 0 0 0-7 2.7C5 15.4 8 18.5 12 21z"/>',
    'news'    => '<rect x="3.5" y="5" width="17" height="14" rx="2"/><path d="M7 9h7M7 12h10M7 15h6"/>',
    'lily'    => '<path d="M12 21c0-5-3-8-8-8 0-3 3-5 8-2 5-3 8-1 8 2-5 0-8 3-8 8z"/><path d="M12 21V9"/>',
    'star'    => '<path d="M12 3.5l2.6 5.3 5.9.9-4.3 4.1 1 5.8-5.2-2.7-5.2 2.7 1-5.8L3.5 9.7l5.9-.9z"/>',
    'calendar'=> '<rect x="3.5" y="5" width="17" height="16" rx="2"/><path d="M3.5 9.5h17M8 3v4M16 3v4"/>',
    'heart'   => '<path d="M12 20.5C7.2 16.9 4 13.7 4 10.2A3.7 3.7 0 0 1 10 7.4a3.7 3.7 0 0 1 2 1.3 3.7 3.7 0 0 1 2-1.3 3.7 3.7 0 0 1 6 2.8c0 3.5-3.2 6.7-8 10.3z"/>',
    'chat'    => '<path d="M4 5h16v11H8l-4 3z"/>',
    'question'=> '<circle cx="12" cy="12" r="9"/><path d="M9.5 9a2.5 2.5 0 0 1 4.5 1.5c0 1.5-2 2-2 3.5M12 17h.01"/>',
    'recipe'  => '<path d="M8 3v7M6 3v4a2 2 0 0 0 4 0V3M8 10v11M16 3c-1.5 0-2.5 2-2.5 5s1 4 2.5 4v9"/>',
    'plus'    => '<circle cx="12" cy="12" r="9"/><path d="M12 8v8M8 12h8"/>',
    'back'    => '<path d="M15 5l-7 7 7 7"/>',
  ];
  return '<svg viewBox="0 0 24 24" aria-hidden="true">' . ($p[$k] ?? '<circle cx="12" cy="12" r="8"/>') . '</svg>';
}

function news_mono($title) {
    $clean = trim(preg_replace('/\s+/', ' ', strip_tags($title)));
    $parts = array_values(array_filter(explode(' ', $clean)));
    if (!$parts) return '&#10086;';
    return e(strtoupper(substr($parts[0],0,1)));
}

/** Save an uploaded news photo -> assets/news/uploads/. Returns [relPath, error, fit].
 *  $fit comes back 'whole' for tall/portrait images (memorial posters, phone
 *  portraits) because cropping those to a wide card shows a meaningless band. */
function news_store_photo($field = 'photo', $existing = '') {
    $rel = 'assets/news/uploads';
    if (empty($_FILES[$field]) || $_FILES[$field]['error'] === UPLOAD_ERR_NO_FILE) return [$existing, '', ''];
    if ($_FILES[$field]['error'] !== UPLOAD_ERR_OK) {
        $why = ($_FILES[$field]['error'] === UPLOAD_ERR_INI_SIZE || $_FILES[$field]['error'] === UPLOAD_ERR_FORM_SIZE)
             ? 'That photo is bigger than this server accepts (' . ini_get('upload_max_filesize') . '). Please pick a smaller one.'
             : 'The photo could not be uploaded — please try again.';
        return [$existing, $why, ''];
    }
    $tmp  = $_FILES[$field]['tmp_name'];
    $info = @getimagesize($tmp);
    $allowed = ['image/jpeg'=>'jpg','image/png'=>'png','image/gif'=>'gif','image/webp'=>'webp'];
    if (!$info || !isset($allowed[$info['mime']])) return [$existing, 'That file is not a photo (JPG, PNG, GIF or WEBP only).', ''];
    if ($_FILES[$field]['size'] > 12 * 1024 * 1024) return [$existing, 'That image is larger than 12 MB — please pick a smaller one.', ''];
    $ext   = $allowed[$info['mime']];
    $fname = date('Ymd_His') . '_' . substr(md5(uniqid('', true)), 0, 6) . '.' . $ext;
    $absDir = dirname(__DIR__) . '/public/' . $rel;
    @mkdir($absDir, 0775, true);
    $dest = $absDir . '/' . $fname;
    if (!move_uploaded_file($tmp, $dest)) return [$existing, 'Sorry — the photo could not be saved.', ''];
    news_shrink($dest, $info['mime']);
    $now = @getimagesize($dest) ?: $info;
    $fit = ($now[1] > $now[0] * 1.15) ? 'whole' : 'cover';   // taller than wide -> show it all
    return [$rel . '/' . $fname, '', $fit];
}

/** Scale a too-large upload down in place (keeps pages fast; ignored if GD is absent). */
function news_shrink($abs, $mime, $max = 1600) {
    if (!function_exists('imagecreatetruecolor')) return;
    $info = @getimagesize($abs);
    if (!$info || ($info[0] <= $max && $info[1] <= $max)) return;
    try {
        switch ($mime) {
            case 'image/jpeg': $src = @imagecreatefromjpeg($abs); break;
            case 'image/png':  $src = @imagecreatefrompng($abs);  break;
            case 'image/gif':  $src = @imagecreatefromgif($abs);  break;
            case 'image/webp': $src = function_exists('imagecreatefromwebp') ? @imagecreatefromwebp($abs) : null; break;
            default: $src = null;
        }
        if (!$src) return;
        $r = min($max / $info[0], $max / $info[1]);
        $w = max(1, (int)round($info[0] * $r)); $h = max(1, (int)round($info[1] * $r));
        $dst = imagecreatetruecolor($w, $h);
        if ($mime === 'image/png' || $mime === 'image/gif') {
            imagealphablending($dst, false); imagesavealpha($dst, true);
            imagefill($dst, 0, 0, imagecolorallocatealpha($dst, 0, 0, 0, 127));
        }
        imagecopyresampled($dst, $src, 0, 0, 0, 0, $w, $h, $info[0], $info[1]);
        if ($mime === 'image/png')      imagepng($dst, $abs, 7);
        elseif ($mime === 'image/gif')  imagegif($dst, $abs);
        elseif ($mime === 'image/webp' && function_exists('imagewebp')) imagewebp($dst, $abs, 85);
        else                            imagejpeg($dst, $abs, 86);
        imagedestroy($src); imagedestroy($dst);
    } catch (\Throwable $e) { /* keep the original if anything goes wrong */ }
}

/** Short teaser for a card; the whole story lives on the announcement's own page. */
function news_teaser($body, $len = 190) {
    $body = trim(preg_replace('/\s+/', ' ', strip_tags($body)));
    if (function_exists('mb_strlen')) {
        if (mb_strlen($body) <= $len) return [$body, false];
        return [rtrim(mb_substr($body, 0, $len), " ,.;:—-") . '…', true];
    }
    if (strlen($body) <= $len) return [$body, false];
    return [rtrim(substr($body, 0, $len), " ,.;:-") . '…', true];
}

/** One announcement card, shared by the Family News page and the full archive. */
function news_card($p) {
    $cat = news_cat($p['category']);
    list($teaser, $more) = news_teaser($p['body']);
    $fit  = ($p['photo_fit'] ?? 'cover') === 'whole' ? ' fit' : '';
    $href = 'news_view.php?id=' . (int)$p['id'];
    ob_start(); ?>
    <article class="fn-card">
      <a class="fn-photo <?= e($cat[2]) . $fit ?>" href="<?= e($href) ?>"<?= $p['photo'] ? ' style="background-image:url(\''.e($p['photo']).'\')"' : ' data-empty="1"' ?>>
        <?php if ($p['photo'] && $fit): ?><span class="fn-photoin" style="background-image:url('<?= e($p['photo']) ?>')"></span><?php endif; ?>
        <span class="fn-tag <?= e($cat[2]) ?>"><?= e($cat[0]) ?></span>
        <?php if (!$p['photo']): ?><span class="fn-mono"><?= news_mono($p['title']) ?></span><?php endif; ?>
        <?php if ($p['sample']): ?><span class="fn-ex">Example</span><?php endif; ?>
      </a>
      <div class="fn-body">
        <?php if ($p['date_label']): ?><div class="fn-date"><?= e($p['date_label']) ?></div><?php endif; ?>
        <h3><a href="<?= e($href) ?>"><?= e($p['title']) ?></a></h3>
        <?php if ($teaser): ?><p><?= e($teaser) ?></p><?php endif; ?>
        <a class="fn-more" href="<?= e($href) ?>"><?= $more ? 'Read the full story' : 'Open' ?> &rsaquo;</a>
        <div class="fn-meta"><span title="Likes"><?= news_icon('heart') ?> <?= (int)$p['likes'] ?></span><span title="Comments"><?= news_icon('chat') ?> <?= (int)$p['comments'] ?></span></div>
      </div>
    </article>
    <?php return ob_get_clean();
}
