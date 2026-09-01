<?php
/** "Stop sending me the monthly note."
 *
 *  No sign-in, no form, no reason asked for. Somebody who does not want the
 *  note should not have to telephone a relative to get out of it, and a family
 *  list where the only way out is to complain to William is a list that costs
 *  him a relationship the first time somebody minds.
 *
 *  The token is per-copy and random, so it identifies the person without ever
 *  putting an address in a URL. It switches off this one note and nothing
 *  else — the account, the password and the site all keep working. */
require __DIR__ . '/../src/bootstrap.php';
require_once __DIR__ . '/../src/notes.php';

$t   = (string)($_GET['t'] ?? '');
$who = '';
$preview = ($t === 'preview');
if (!$preview) $who = note_opt_out($t);

page_head('Family note');
?>
<div class="card panel" style="max-width:560px;margin:40px auto">
  <?php if ($preview): ?>
    <h1 style="text-align:center">This was the preview copy</h1>
    <p class="lede" style="text-align:center">Nothing to switch off here &mdash; this link only does something
      on a note that has actually been sent to somebody.</p>
  <?php elseif ($who !== ''): ?>
    <h1 style="text-align:center">That&rsquo;s done</h1>
    <p class="lede" style="text-align:center">No more family notes will be sent to you, <?= e($who) ?>.
      Anything that was waiting has been taken off the list.</p>
    <p class="muted" style="text-align:center">Your account is untouched &mdash; you can still sign in and
      look at the family history whenever you like. If you change your mind, tell William and he can put you back on.</p>
  <?php else: ?>
    <h1 style="text-align:center">That link has already been used</h1>
    <p class="lede" style="text-align:center">Either it has been used once already, or it was typed in slightly
      differently from the one in the message.</p>
    <p class="muted" style="text-align:center">If you are still getting the note and you would rather not,
      reply to it and William will take you off himself.</p>
  <?php endif; ?>
  <p style="text-align:center;margin-top:18px"><a class="btn gold" href="index.php">Go to the family site</a></p>
</div>
<?php page_foot();
