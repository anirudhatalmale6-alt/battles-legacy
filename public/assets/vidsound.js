/* Start the video when it comes into view, and offer the sound back.
 *
 * William: "I like it the way it is, start when you scroll by." That is
 * Facebook's behaviour, and it is better than the version before it, which
 * started the moment the page opened. On a phone the video sits below the
 * heading, so starting on load meant that by the time you had scrolled down to
 * it the opening card was already gone. Now nobody misses the beginning.
 *
 * It also stops the whole file being pulled down by somebody who never scrolls
 * that far - preload is back to metadata, and the fetch happens when it plays.
 *
 * No browser will start a video with sound unasked, so it starts silent and
 * hands the sound back with one tap - the same bargain Facebook makes, so the
 * family already knows the gesture.
 *
 * The sound button is only drawn once the video is ACTUALLY playing. If the
 * browser refuses - data saver, low power mode, an older phone - nothing
 * appears and the visitor gets the ordinary poster and play button. A "Tap for
 * sound" label over a frozen first frame would be a lie about the state of the
 * page, and that is the shape of bug that gets pressed once and never trusted.
 */
(function () {
  var v = document.querySelector('.fvid-player video');
  if (!v) return;

  var btn = null;
  var userPaused = false;   /* once somebody presses pause, stop deciding for them */

  function remove() {
    if (btn && btn.parentNode) { btn.parentNode.removeChild(btn); }
    btn = null;
  }

  function unmute() {
    v.muted = false;
    /* some browsers leave a formerly muted element sitting at volume 0 */
    if (v.volume === 0) { v.volume = 1; }
    remove();
    if (v.paused) { userPaused = false; v.play(); }
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

  function start() {
    if (userPaused) return;
    var p = v.play();
    if (p && p.catch) { p.catch(function () { /* refused: leave the poster and controls alone */ }); }
  }

  v.addEventListener('playing', offer);
  v.addEventListener('volumechange', function () { if (!v.muted) remove(); });
  v.addEventListener('ended', remove);

  /* A pause the visitor asked for, as opposed to one we caused by scrolling
     away. Only their own is remembered.

     The flag is cleared BY THE HANDLER, not on the line after v.pause(). The
     pause event is queued rather than dispatched there and then, so resetting
     it straight after the call meant every scroll-away was filed as the
     visitor's own pause and the video never started again when they came back.
     The test caught it; reading the code did not. */
  var ours = false;
  v.addEventListener('pause', function () {
    if (ours) { ours = false; } else { userPaused = true; }
  });

  if (!('IntersectionObserver' in window)) { start(); return; }

  /* A quarter of it showing is enough to start, and it only stops once it is
     all but gone. Two different numbers on purpose: one threshold makes it
     stutter on and off when somebody parks the page right at the boundary.
     A quarter rather than a half because on a 360-wide phone the player is
     only a tenth visible when the page opens, and on a laptop 48% — with a
     half, both of those would sit there doing nothing and look broken. */
  new IntersectionObserver(function (entries) {
    for (var i = 0; i < entries.length; i++) {
      var r = entries[i].intersectionRatio;
      if (r >= 0.25) {
        start();
      } else if (r < 0.1 && !v.paused) {
        ours = true; v.pause();
      }
    }
  }, { threshold: [0, 0.1, 0.25, 0.5] }).observe(v);
})();
