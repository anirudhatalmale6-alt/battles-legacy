/* "Show password" — one small button per form, under the last password box.
 *
 *  William could not sign in on his phone. The password had capitals and two
 *  hyphens in it, the box hides what you type, and a phone keyboard quietly
 *  corrects things while you are not looking. He had no way to see what was
 *  actually in the box, so there was nothing to debug — only "it does not work".
 *
 *  On the change-password form there are three boxes and the button reveals all
 *  of them together, which is the point: you can see that the new one and the
 *  repeat of it really do match.
 *
 *  Deliberately a word, not an eye icon. Half the family are in their seventies
 *  and an eye with a line through it is not obvious if you have not met it.
 */
(function () {
  var boxes = document.querySelectorAll('input[type=password]');
  if (!boxes.length) return;

  /* Group by form, so a page with two separate forms gets a button on each and
     one form's button never reveals the other form's boxes. */
  var forms = [], groups = [];
  for (var i = 0; i < boxes.length; i++) {
    var f = boxes[i].form || document.body;
    var at = forms.indexOf(f);
    if (at === -1) { forms.push(f); groups.push([boxes[i]]); }
    else groups[at].push(boxes[i]);
  }

  for (var g = 0; g < groups.length; g++) wire(groups[g]);

  function wire(list) {
    var btn = document.createElement('button');
    btn.type = 'button';               /* never submits the form */
    btn.className = 'pwshow';
    var many = list.length > 1;
    var showTxt = many ? 'Show passwords' : 'Show password';
    var hideTxt = many ? 'Hide passwords' : 'Hide password';
    btn.textContent = showTxt;
    btn.setAttribute('aria-pressed', 'false');

    btn.addEventListener('click', function () {
      var showing = list[0].type === 'text';
      for (var i = 0; i < list.length; i++) list[i].type = showing ? 'password' : 'text';
      btn.textContent = showing ? showTxt : hideTxt;
      btn.setAttribute('aria-pressed', showing ? 'false' : 'true');
    });

    /* Inserted after the last box, never wrapped around it: moving an input in
       the DOM would throw away the autofocus the page just gave it, and would
       also drop anything already typed on some browsers. */
    var last = list[list.length - 1];
    last.insertAdjacentElement('afterend', btn);
  }
})();
