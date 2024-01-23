(function () {
  $(document).ready(function(){
    var tempMenu = $('#HeaderMenu').html();
    var viewFullScreen = document.getElementById("view-fullscreen");
    if (viewFullScreen) {
        viewFullScreen.addEventListener("click", function () {
            tempMenu = $('#HeaderMenu').html();
            $("#HeaderMenu").empty();
            $('#cancel-fullscreen').removeClass('invisible');
            $(this).addClass('invisible');

            //Activa el evento para salir del fullScreen
            //cancelFullScreen();

            var docElm = document.documentElement;
            if (docElm.requestFullscreen) {
                docElm.requestFullscreen();
            }
            else if (docElm.msRequestFullscreen) {
                docElm = document.body; //overwrite the element (for IE)
                docElm.msRequestFullscreen();
            }
            else if (docElm.mozRequestFullScreen) {
                docElm.mozRequestFullScreen();
            }
            else if (docElm.webkitRequestFullScreen) {
                docElm.webkitRequestFullScreen();
            }
        }, false);
    }

    //function cancelFullScreen(){
      var cancelFullScreen = document.getElementById("cancel-fullscreen");
      if (cancelFullScreen) {
          cancelFullScreen.addEventListener("click", function () {
              if (document.exitFullscreen) {
                  document.exitFullscreen();
              }
              else if (document.msExitFullscreen) {
                  document.msExitFullscreen();
              }
              else if (document.mozCancelFullScreen) {
                  document.mozCancelFullScreen();
              }
              else if (document.webkitCancelFullScreen) {
                  document.webkitCancelFullScreen();
              }

              $("#HeaderMenu").html(tempMenu);
              tempMenu = '';
              $(this).addClass('invisible');
              $('#view-fullscreen').removeClass('invisible');
          }, false);
      }
    //}

    var fullscreenState = document.getElementById("fullscreen-state");
    if (fullscreenState) {
        document.addEventListener("fullscreenchange", function () {
            fullscreenState.innerHTML = (document.fullscreenElement)? "" : "not ";
        }, false);

        document.addEventListener("msfullscreenchange", function () {
            fullscreenState.innerHTML = (document.msFullscreenElement)? "" : "not ";
        }, false);

        document.addEventListener("mozfullscreenchange", function () {
            fullscreenState.innerHTML = (document.mozFullScreen)? "" : "not ";
        }, false);

        document.addEventListener("webkitfullscreenchange", function () {
            fullscreenState.innerHTML = (document.webkitIsFullScreen)? "" : "not ";
        }, false);
    }

    var marioVideo = document.getElementById("mario-video")
        videoFullscreen = document.getElementById("video-fullscreen");

    if (marioVideo && videoFullscreen) {
        videoFullscreen.addEventListener("click", function (evt) {
            if (marioVideo.requestFullscreen) {
                marioVideo.requestFullscreen();
            }
            else if (marioVideo.msRequestFullscreen) {
                marioVideo.msRequestFullscreen();
            }
            else if (marioVideo.mozRequestFullScreen) {
                marioVideo.mozRequestFullScreen();
            }
            else if (marioVideo.webkitRequestFullScreen) {
                marioVideo.webkitRequestFullScreen();
                /*
                    *Kept here for reference: keyboard support in full screen
                    * marioVideo.webkitRequestFullScreen(Element.ALLOW_KEYBOARD_INPUT);
                */
            }
        }, false);
    }

    document.addEventListener("keydown", function(e) {
      if (e.keyCode == 27) {
        $("#HeaderMenu").html(tempMenu);
        tempMenu = '';
        window.location = '';
      }
    }, false);
  });
})();
