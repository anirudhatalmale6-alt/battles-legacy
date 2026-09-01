/* Send something by text message or Messenger instead of email.
 *
 * Used by the invitations and password links on the Members page and by the
 * replies on the Feedback page. Any button with class "sharebtn" and a
 * data-share attribute works; there is nothing to wire up per page.
 *
 * navigator.share opens the phone's own share sheet, so it is tap, pick the
 * person, send — and it needs no email address, which is the whole point when
 * half the addresses on this site are ten years old and a good few of the
 * "best way to reach me" boxes hold a phone number.
 *
 * It does not exist on most desktop browsers, so there the same button copies
 * the message instead. The button is relabelled on load rather than at the
 * moment of pressing: a button that says "Text it" and then quietly copies is
 * the kind of thing that gets pressed once and never trusted again.
 *
 * The link is inside the message text on purpose. Passing it separately as
 * `url` makes some apps send the link and drop the words, and others send both
 * and show the link twice. One field of plain text behaves the same everywhere.
 */
(function () {
  var canShare = !!(navigator.share && window.isSecureContext);

  function relabel() {
    if (canShare) return;
    var bs = document.querySelectorAll('.sharebtn'), i;
    for (i = 0; i < bs.length; i++) {
      bs[i].textContent = '📋 Copy the whole message';
      bs[i].title = 'This browser has no share sheet. Press to copy the message, then paste it into a text or a message.';
    }
  }
  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', relabel);
  else relabel();

  function legacyCopy(text, ok) {
    var ta = document.createElement('textarea');
    ta.value = text;
    ta.setAttribute('readonly', '');
    ta.style.position = 'fixed'; ta.style.top = '0'; ta.style.left = '-9999px';
    document.body.appendChild(ta);
    ta.focus(); ta.select(); ta.setSelectionRange(0, 99999);
    try { document.execCommand('copy'); ok(); } catch (e) {}
    document.body.removeChild(ta);
  }
  function copyText(text, ok) {
    if (navigator.clipboard && window.isSecureContext) {
      navigator.clipboard.writeText(text).then(ok, function () { legacyCopy(text, ok); });
    } else { legacyCopy(text, ok); }
  }

  document.addEventListener('click', function (ev) {
    var t = ev.target;
    if (!t || typeof t.closest !== 'function') return;
    var b = t.closest('.sharebtn');
    if (!b) return;
    var text = b.getAttribute('data-share') || '';
    if (text === '') return;

    if (canShare) {
      /* A cancelled share sheet rejects. That is the person changing their
         mind, not a fault, so it must not put an error in front of them. */
      try { navigator.share({ title: b.getAttribute('data-title') || '', text: text })
              .catch(function () {}); }
      catch (e) {}
      return;
    }
    var was = b.textContent;
    copyText(text, function () {
      b.textContent = 'Copied — now paste it into a text';
      b.classList.add('is-copied');
      setTimeout(function () { b.textContent = was; b.classList.remove('is-copied'); }, 2600);
    });
  });
})();
