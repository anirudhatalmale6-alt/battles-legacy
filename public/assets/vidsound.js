/* Start the video when the page opens, and offer the sound back.
 *
 * William wanted it moving the moment you land on the page. No browser will
 * start a video with sound unasked, so the only honest way to do that is to
 * start it silent and hand the sound back with one tap - which is exactly what
 * Facebook does, so it is the behaviour the family already knows.
 *
 * The button is only drawn once the video is ACTUALLY playing. If the browser
 * refuses to start it - data saver, low power mode, an older phone - nothing
 * appears and the visitor gets the ordinary poster and play button. A "Tap for
 * sound" label floating over a frozen first frame would be a lie about the
 * state of the page, and that is the shape of bug that gets pressed once and
 * never trusted again.
 */
(function () {
  var v = document.querySelector('.fvid-player video');
  if (!v) return;

  var btn = null;

  function remove() {
    if (btn && btn.parentNode) { btn.parentNode.removeChild(btn); }
    btn = null;
  }

  function unmute() {
    v.muted = false;
    /* Some browsers keep a muted element at volume 0 after unmuting. */
    if (v.volume === 0) { v.volume = 1; }
    remove();
    if (v.paused) { v.play(); }        /* unmuting a paused video should also resume it */
  }

  function offer() {
    if (btn || !v.muted) return;
    btn = document.createElement('button');
    btn.type = 'button';
    btn.className = 'vsnd';
    btn.innerHTML = '<span class="vsnd-ic">&#128266;</span> Tap for sound';
    btn.addEventListener('click', unmute);
    v.parentNode.appendChild(btn);
  }

  /* Only ever offered once the pictures are genuinely moving. */
  v.addEventListener('playing', offer);
  /* If the visitor unmutes with the player's own controls, the button is noise. */
  v.addEventListener('volumechange', function () { if (!v.muted) remove(); });
  v.addEventListener('ended', remove);
})();
