var slideEvent = false;
var time_slide = 5;
var playing = false;
var current = 0;

var max_width = 0;
var max_height = 0;

function hidePrevious(previous) {
    if (!document.getElementById("picture_"+previous))
        return;

    document.getElementById("picture_"+previous).style.display = "none";
}

function loadPicture(id, previous) {
    var pic = pictures[id];
    var pic_id = "picture_"+id;
    document.body.className = "loading";

    if (slideEvent)
        window.clearTimeout(slideEvent);

    if (!document.getElementById(pic_id)) {
        var width = pic.width;
        var height = pic.height;
        var ratio = false;

        if(width > max_width) {
            if(height <= width)
                ratio = max_width / width;
            else
                ratio = max_height / height;

            width = Math.round(width * ratio);
            height = Math.round(height * ratio);
        }

        if (height > max_height) {
            ratio = max_height / height;

            width = Math.round(width * ratio);
            height = Math.round(height * ratio);
        }

        var img = document.createElement("img");
        img.id = pic_id;
        img.style.display = "block";
        img.style.margin = Math.round((max_height - height) / 2) + "px 0px 0px " + Math.round((max_width - width) / 2) + "px";
        img.src = pic.src;
        img.onclick = goNext;

        if (typeof(previous) != "undefined") {
            img.width = "1";
            img.height = "1";
        }
        else {
            img.width = width;
            img.height = height;
        }

        document.getElementById("slideshow").appendChild(img);

        document.getElementById(pic_id).onload = function() {
            document.body.className = "";

            if (playing)
                slideEvent = window.setTimeout(goNext, time_slide * 1000);

            img.width = width;
            img.height = height;

            if (typeof(previous) != "undefined")
                hidePrevious(previous);

            if (document.getElementById("current_nb"))
                document.getElementById("current_nb").innerHTML = parseInt(current) + 1;
        }
    }
    else {
        document.body.className = "";

        document.getElementById(pic_id).style.display = "block";

        if (playing)
            slideEvent = window.setTimeout(goNext, time_slide * 1000);

        if (typeof(previous) != "undefined")
            hidePrevious(previous);

        if (document.getElementById("current_nb"))
            document.getElementById("current_nb").innerHTML = parseInt(current) + 1;
    }

    document.getElementById("pic_comment").innerHTML = pic.comment;

    window.location.href = "#" + pic.filename;
}

function playPause() {
    max_width = document.body.offsetWidth;
    max_height = document.body.offsetHeight;

    if (playing) {
        playing = false;
        document.getElementById("controlBar").className = "pause";
    }
    else {
        playing = true;
        document.getElementById("controlBar").className = "playing";
    }

    loadPicture(current);
}

function goNext() {
    var previous = current;
    current++;

    if (current >= pictures.length)
        current = 0;

    loadPicture(current, previous);
}

function goPrev() {
    var previous = current;
    current--;

    if (current < 0)
        current = pictures.length - 1;

    loadPicture(current, previous);
}
