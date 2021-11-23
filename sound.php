<script src="https://w.soundcloud.com/player/api.js"></script>

<iframe id="player" width="100%" height="20" scrolling="no" frameborder="no" allow="autoplay" src="https://w.soundcloud.com/player/?url=https%3A//api.soundcloud.com/tracks/<?= $_GET['id'] ?>&color=%23ff5500&inverse=false&auto_play=true&show_user=true"></iframe>

<script>
  var widget = SC.Widget("player");
  widget.bind(SC.Widget.Events.FINISH, function() {
    widget.seekTo(0);
    widget.play();
  });
</script>
