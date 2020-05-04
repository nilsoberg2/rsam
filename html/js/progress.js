
////////////////////////////////////////////////////////////////////////////////////////////////////
// PROGRESS UI
//
function Progress(progressDiv) {
    this.progress = progressDiv;
}
Progress.prototype.start = function() {
    this.progress.show();
}
Progress.prototype.stop = function() {
    this.progress.hide();
}


