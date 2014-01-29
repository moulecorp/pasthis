document.querySelector ('textarea').addEventListener ('keydown', function (evt) {
    if (!evt) return;

    if (evt.keyCode == 9) {
        var start = this.selectionStart;
        var end = this.selectionEnd;
        var target = evt.target;
        var value = target.value;

        target.value = value.substring (0, start) + '\t' + value.substring (end);
        this.selectionStart = this.selectionEnd = start+1;

        evt.preventDefault ();
    }
});
