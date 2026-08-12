<?php
/** Small view helpers shared across pages. */

function e($s) { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }

function yr($d) { if (preg_match('/\d{4}/', (string)$d, $m)) return $m[0]; return ''; }

function lifespan($p) {
    $b = yr($p['birth_date'] ?? ''); $d = yr($p['death_date'] ?? '');
    if ($b && $d) return "$b – $d";
    if ($b) return "b. $b";
    if ($d) return "d. $d";
    return '';
}

function person_display_name($p) {
    // Members see the real name; the public sees a privatized name for the living.
    if (($p['living'] ?? 0) && !logged_in()) {
        $first = preg_split('/\s+/', trim($p['given'] ?? '')) ?: ['Living'];
        $ini = $p['surname'] ? substr($p['surname'], 0, 1) . '.' : '';
        return trim($first[0] . ' ' . $ini);
    }
    return $p['name'];
}

/** Approved photos for a person the current viewer is allowed to see. */
function person_photos($pid) {
    // Living relatives' photos are only shown to logged-in members.
    $p = one("SELECT living FROM persons WHERE pid=?", [$pid]);
    if ($p && $p['living'] && !logged_in()) return [];
    return all("SELECT * FROM photos WHERE pid=? AND status='approved' ORDER BY is_primary DESC, id", [$pid]);
}

function primary_photo($pid) {
    $ph = person_photos($pid);
    return $ph[0] ?? null;
}

function flash($msg = null) {
    if ($msg !== null) { $_SESSION['flash'][] = $msg; return; }
    $out = $_SESSION['flash'] ?? [];
    unset($_SESSION['flash']);
    return $out;
}

/* ---- video links (shared by Faith and Enterprise) ---- */

/** Pull the YouTube id out of any of the shapes people paste. */
function yt_id($url) {
    $url = trim((string)$url);
    if ($url === '') return '';
    if (preg_match('~(?:youtube\.com/(?:watch\?(?:.*&)?v=|embed/|shorts/|live/)|youtu\.be/)([A-Za-z0-9_-]{11})~i', $url, $m)) return $m[1];
    return '';
}
/** A real thumbnail when we can work one out, so lists aren't rows of grey boxes. */
function video_thumb($url) {
    $id = yt_id($url);
    return $id ? 'https://img.youtube.com/vi/' . $id . '/hqdefault.jpg' : '';
}
/** Normalise a pasted link so "youtube.com/watch?v=x" still opens. */
function video_url($url) {
    $u = trim((string)$url);
    if ($u === '') return '';
    if (!preg_match('~^https?://~i', $u)) $u = 'https://' . $u;
    return $u;
}

/** The picture for a video row: one you uploaded beats a YouTube auto-thumb.
 *  Facebook and Vimeo links can't be read without their API, so for those an
 *  uploaded picture is the only way to avoid a plain play symbol. */
function video_pic($row) {
    $own = trim((string)($row['photo'] ?? ''));
    if ($own !== '') return $own;
    return video_thumb($row['url'] ?? '');
}

function base_url() {
    $b = config('base_url');
    if ($b) return rtrim($b, '/');
    $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    return $scheme . '://' . $host;
}
