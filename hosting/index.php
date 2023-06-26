<?php
// Fotoo Hosting single-file release version 3.0.0
?><?php if (isset($_GET["js"])): header("Content-Type: text/javascript"); ?>
(function () {
    if (!Array.prototype.indexOf)
    {
        Array.prototype.indexOf = function(elt /*, from*/)
        {
            var len = this.length >>> 0;

            var from = Number(arguments[1]) || 0;
            from = (from < 0) ? Math.ceil(from) : Math.floor(from);

            if (from < 0)
            {
                from += len;
            }

            for (; from < len; from++)
            {
                if (from in this && this[from] === elt)
                    return from;
            }

            return -1;
        };
    }

    var files = [];
    var can_submit = true;
    var last_filename = '';
    var loading_gif = 'data:image/gif;base64,R0lGODlhEAAQAPIAAP%2F%2F%2FwAAAMLCwkJCQgAAAGJiYoKCgpKSkiH%2BGkNyZWF0ZWQgd2l0aCBhamF4bG9hZC5pbmZvACH5BAAKAAAAIf8LTkVUU0NBUEUyLjADAQAAACwAAAAAEAAQAAADMwi63P4wyklrE2MIOggZnAdOmGYJRbExwroUmcG2LmDEwnHQLVsYOd2mBzkYDAdKa%2BdIAAAh%2BQQACgABACwAAAAAEAAQAAADNAi63P5OjCEgG4QMu7DmikRxQlFUYDEZIGBMRVsaqHwctXXf7WEYB4Ag1xjihkMZsiUkKhIAIfkEAAoAAgAsAAAAABAAEAAAAzYIujIjK8pByJDMlFYvBoVjHA70GU7xSUJhmKtwHPAKzLO9HMaoKwJZ7Rf8AYPDDzKpZBqfvwQAIfkEAAoAAwAsAAAAABAAEAAAAzMIumIlK8oyhpHsnFZfhYumCYUhDAQxRIdhHBGqRoKw0R8DYlJd8z0fMDgsGo%2FIpHI5TAAAIfkEAAoABAAsAAAAABAAEAAAAzIIunInK0rnZBTwGPNMgQwmdsNgXGJUlIWEuR5oWUIpz8pAEAMe6TwfwyYsGo%2FIpFKSAAAh%2BQQACgAFACwAAAAAEAAQAAADMwi6IMKQORfjdOe82p4wGccc4CEuQradylesojEMBgsUc2G7sDX3lQGBMLAJibufbSlKAAAh%2BQQACgAGACwAAAAAEAAQAAADMgi63P7wCRHZnFVdmgHu2nFwlWCI3WGc3TSWhUFGxTAUkGCbtgENBMJAEJsxgMLWzpEAACH5BAAKAAcALAAAAAAQABAAAAMyCLrc%2FjDKSatlQtScKdceCAjDII7HcQ4EMTCpyrCuUBjCYRgHVtqlAiB1YhiCnlsRkAAAOwAAAAAAAAAAAA%3D%3D';
    var album_hash = null;
    var album_key = null;

    function cleanFileName(filename)
    {
        filename = filename.replace(/[\\\\]/g, "/");
        filename = filename.split("/");
        filename = filename[filename.length - 1];
        filename = filename.split(".");
        filename = filename[0];
        filename = filename.replace(/\s+/g, "-");
        filename = filename.replace(/[^a-zA-Z0-9_.-]/ig, "");
        filename = filename.substr(0, 30);
        filename = filename.replace(/(^[_.-]+|[_.-]+$)/g, "");
        return filename;
    }

    function uploadPicture(index)
    {
        var file = files[index];

        if (!(/^image\/(?:jpe?g|webp)$/i.test(file.type)))
        {
            uploadPicture(index+1);
            return;
        }

        var current = document.getElementById('albumParent').querySelectorAll('figure')[index];
        var resized_img = document.createElement('div');
        resized_img.style.display = "none";

        var name = current.getElementsByTagName('input')[0];
        name.disabled = true;

        var progress = document.createElement('span');
        current.appendChild(progress);

        resize(
            file,
            -config.max_width,
            resized_img,
            progress,
            function()
            {
                var img = resized_img.firstChild

                progress.innerHTML = "Uploading... <img class=\"loading\" src=\"" + loading_gif + "\" alt=\"\" />";

                var params = new URLSearchParams({
                    'name': name.value,
                    'filename': file.name,
                    'content': img.src.substr(img.src.indexOf(',') + 1)
                });

                if (album_hash) {
                    params.append('album', album_hash);
                    params.append('key', album_key);
                }
                else {
                    params.append('private', document.getElementById('f_private').checked ? 1 : 0);
                }

                fetch(config.base_url + '?upload', {
                    'method': 'POST',
                    'mode': 'same-origin',
                    'headers': {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    'body': params
                }).then((r) => {
                    if (!r.ok) {
                        console.error(r);
                        throw Error('Upload failed');
                    }

                    progress.innerHTML = "Uploaded <b>&#10003;</b>";
                    img.parentNode.removeChild(img);

                    if (!album_hash) {
                        r.text().then((url) => location.href = url);
                        return;
                    }

                    index++;

                    if (index < files.length)
                    {
                        uploadPicture(index);
                    }
                    else
                    {
                        location.href = config.album_page_url + album_hash + (config.album_page_url.indexOf('?') ? '&c=' : '?c=') + album_key;
                    }
                });

                delete params;
            }
        );
    }

    window.onload = function ()
    {
        if (!FileReader && !window.URL)
            return false;

        document.querySelectorAll('.submit').forEach((e) => e.style.display = 'none');

        var parent = document.getElementById('albumParent');
        var found = new Array;
        var to_resize = new Array;

        var filesInput = document.getElementById("f_files");

        filesInput.style.display = 'none';

        var btn = document.createElement('button');
        btn.type = 'button';
        btn.innerText = 'Add files...';
        btn.className = 'add';
        btn.onclick = () => filesInput.click();
        parent.prepend(btn);

        // Mode album
        filesInput.onchange = function ()
        {
            if (this.files.length < 1)
            {
                return false;
            }

            for (var i = 0; i < this.files.length; i++)
            {
                var file = this.files[i];

                if (!(/^image\/(?:jpe?g|webp|png|gif|svg)/i.test(file.type))) {
                    continue;
                }

                var t = document.getElementById('f_title');

                if (!t.value) {
                    t.value = cleanFileName(file.name);
                }

                var id = encodeURIComponent(file.name + file.type + file.size);

                if (found.indexOf(id) != -1)
                {
                    continue;
                }

                var fig = document.createElement('figure');
                fig.id = id;
                var caption = document.createElement('figcaption');
                var name = document.createElement('input');
                name.type = 'text';
                name.value = cleanFileName(file.name);

                var btn = document.createElement('button');
                btn.type = 'button';
                btn.innerText = '✖';
                btn.onclick = function () {
                    var f = this.parentNode.parentNode;
                    var i = found.indexOf(f.id);
                    found.splice(i, 1);
                    files.splice(i, 1)
                    f.remove();
                };

                var thumb = document.createElement('div');
                thumb.className = 'thumb';

                var progress = document.createElement('p');

                caption.appendChild(name);
                caption.appendChild(btn);
                fig.appendChild(thumb);
                fig.appendChild(progress);
                fig.appendChild(caption);
                parent.appendChild(fig);

                to_resize.push(new Array(file, thumb, progress));
                found.push(id);
                files.push(file);
            }

            function resizeFromList()
            {
                if (to_resize.length < 1)
                {
                    can_submit = true;
                    document.querySelectorAll('.submit').forEach((e) => e.style.display = 'block');
                    return;
                }
                else {
                    document.querySelectorAll('.submit').forEach((e) => e.style.display = 'none');
                }

                var current = to_resize[0];
                resize(
                    current[0], // file
                    config.thumb_width, // size
                    current[1], // image resized
                    current[2], // progress element
                    function () {
                        current[2].parentNode.removeChild(current[2]);
                        resizeFromList();
                    }
                );

                to_resize.splice(0, 1);
            }

            resizeFromList();
        };

        var form = document.getElementById("f_upload");
        form.onsubmit = function (e)
        {
            if (!can_submit)
            {
                alert('A file is loading, please wait...');
                return false;
            }

            if (document.getElementById('f_title').value.replace('/[\s]/g', '') == '')
            {
                alert('Title is mandatory.');
                return false;
            }

            if (files.length < 1)
            {
                alert('No file is selected.');
                return false;
            }

            can_submit = false;
            document.querySelectorAll('.submit').forEach((e) => e.style.display = 'none');

            var xhr = new XMLHttpRequest;
            var url = config.base_url + '?upload';

            if (files.length > 1) {
                var params = new URLSearchParams({
                    'album': 'new',
                    'title': document.getElementById('f_title').value,
                    'private': document.getElementById('f_private').checked ? 1 : 0
                });

                fetch(form.action, {
                    method: 'POST',
                    mode: 'same-origin',
                    cache: 'no-cache',
                    headers: {
                        "Content-Type": "application/x-www-form-urlencoded"
                    },
                    body: params
                }).then((r) => r.json()).then((j) => {
                    album_hash = j.hash;
                    album_key = j.key;
                    uploadPicture(0);
                });
            }
            else {
                uploadPicture(0);
            }

            e.preventDefault();
            return false;
        };
    };

    var canvas = document.createElement("canvas");

    function resize($file, $size, $img, $progress, $onload)
    {
        this._url = null;

        function resampled(data)
        {
            var img = ($img.lastChild || $img.appendChild(new Image));
            img.src = data;
            img.className = "preview";

            if (this._url && (window.URL || window.webkitURL).revokeObjectURL)
            {
                (window.URL || window.webkitURL).revokeObjectURL(this._url);
                this._url = null;
            }

            if ($onload)
            {
                $onload();
            }
            else
            {
                can_submit = true;
            }
        }

        function load(e) {
            Resample(
                this.result,
                this._resize,
                null,
                resampled
            );
        }

        function abort(e) {
            can_submit = true;
        }

        function error(e) {
            can_submit = true;
        }

        var size = parseInt($size, 10);

        if ($progress)
        {
            $progress.innerHTML = "Resizing... <img class=\"loading\" src=\"" + loading_gif + "\" alt=\"\" />";
        }

        can_submit = false;

        if (!(window.URL || window.webkitURL) && FileReader)
        {
            var file = new FileReader;
            file.onload = load;
            file.onabort = abort;
            file.onerror = error;
            file._resize = size;
            file.readAsDataURL($file);
        }
        else
        {
            var url = (window.URL || window.webkitURL).createObjectURL($file);
            this._url = url;
            Resample(url, size, null, resampled);
        }
    }

    var Resample = (function (canvas)
    {
        function Resample(img, width, height, onresample)
        {
            var load = typeof img == "string",
                i = load || img;

            if (load)
            {
                i = new Image;
                // with propers callbacks
                i.onload = onload;
                i.onerror = onerror;
            }

            i._onresample = onresample;
            i._width = width;
            i._height = height;
            load ? (i.src = img) : onload.call(img);
        }

        function onerror()
        {
            throw ("not found: " + this.src);
        }

        function onload()
        {
            var img = this,
                width = img._width,
                height = img._height,
                onresample = img._onresample
            ;

            if (height == null && width < 0)
            {
                var max_mp = Math.abs(width) * Math.abs(width);
                var img_mp = img.width * img.height;

                if (img_mp > max_mp)
                {
                    var ratio = img_mp / max_mp;
                    height = round(img.height / ratio);
                    width = round(img.width / ratio);
                }
                else
                {
                    width = img.width;
                    height = img.height;
                }
            }
            else if (height == null)
            {
                if (img.width > img.height)
                {
                    height = round(img.height * width / img.width)
                }
                else if (img.width == img.height)
                {
                    height = width;
                }
                else
                {
                    height = width;
                    width = round(img.width * height / img.height);
                }

                if (img.width < width && img.height < height)
                {
                    width = img.width, height = img.height;
                }
            }

            width = Math.abs(width);
            height = Math.abs(height);

            delete img._onresample;
            delete img._width;
            delete img._height;

            const dpr = window.devicePixelRatio || 1;
            canvas.width = width * dpr;
            canvas.height = height * dpr;

            context.drawImage(
                img, // original image
                0, // starting x point
                0, // starting y point
                img.width, // image width
                img.height, // image height
                0, // destination x point
                0, // destination y point
                width, // destination width
                height // destination height
            );

            var r = canvas.toDataURL("image/webp", 0.75);

            if (!r.match(/image\/webp/)) {
                r = canvas.toDataURL("image/jpeg", 0.75);
            }

            onresample(r);
            context.clearRect(0, 0, canvas.width, canvas.height);
        }

        var context = canvas.getContext("2d"),
            round = Math.round;

        return Resample;
    }
    (
        canvas
    ));
} ());
<?php exit; endif; ?><?php if (isset($_GET["css"])): header("Content-Type: text/css"); ?>
html, body, div, span, applet, object, iframe,
h1, h2, h3, h4, h5, h6, p, blockquote, pre,
a, abbr, acronym, address, big, cite, code,
del, dfn, em, img, ins, kbd, q, s, samp,
small, strike, strong, sub, sup, tt, var,
b, u, i, center,
dl, dt, dd, ol, ul, li,
fieldset, form, label, legend,
table, caption, tbody, tfoot, thead, tr, th, td,
article, aside, canvas, details, embed,
figure, figcaption, footer, header, hgroup,
menu, nav, output, ruby, section, summary,
time, mark, audio, video {
	margin: 0;
	padding: 0;
	border: 0;
	font-size: 100%;
	font: inherit;
	vertical-align: baseline;
}
/* HTML5 display-role reset for older browsers */
article, aside, details, figcaption, figure,
footer, header, hgroup, menu, nav, section {
	display: block;
}
body {
	line-height: 1;
}
ol, ul {
	list-style: none;
}
blockquote, q {
	quotes: none;
}
blockquote:before, blockquote:after,
q:before, q:after {
	content: '';
	content: none;
}
table {
	border-collapse: collapse;
	border-spacing: 0;
}

h1 { font-size: 200%; }
h2 { font-size: 150%; }
h3 { font-size: 125%; }
h4 { font-size: 112.5%; }
h6 { font-size: 80%; }

body {
	background: #c4c0aa url('data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAG0AAAB%2BBAMAAADclIJQAAAAAXNSR0IArs4c6QAAAA9QTFRFoKh%2Br7KQs7aTu7qexsCrr1fZwwAAAAFiS0dEAIgFHUgAAAAJcEhZcwAACxMAAAsTAQCanBgAAAAHdElNRQfcCR0WLiQp5Kn4AAADu0lEQVRYw%2B1XUXbjIAwUxgeADQdwGh%2FADjqAbXT%2FM%2B0HCATBebvddtt9Gz6atvFgIc1IDAAAKAMAbgMAACADeY071Mu%2B3bz3HjdY%2BF8qyI%2B4cILuGjYoO%2BIDzgU4WQuUr9zU4AZaznAGMB%2FHLQAA6siB4w7nyy31bwPjNIUnMNBHH3d5DgOgbpyO1uewkuuECzFImRJ1u5sH3JjqDbPIJ0rYjYjoXnaJkeREpMwGANDybDPdrb1RzgMDqKo7YJXkHPJAXJUh1Aek8loUZ%2BOTZhZwMONS4cYFQHJtb1mn04M6fRPyn4UzoIQ8cEslqoSQX0IlVUVd8UUGAIB4K6rz4zbBbalGcMEAuIMpnT6pbEsnwkXySBNWAipJdCFzCGslKSRa8wtSQmdTtt3rUgmkSERiWin2QKlW49YhdD693tqHFJI8eaO8vf5FBqV8P8wqLt7gYfdumOUxxrntRJrq5q8lzBxEOl%2FFKZkATUSla5TS5HDc0UuApnC1ud0IabIgQFEFnGPjiMVUeLQ91U2FucG0HQvpmoq6ZjTUBAPQRHdbdUhNlFqbpklh6NSfGUjBe%2B99TACFt1vKlwsow2mqpG5IcU28KWu%2BgvXIZK21VjQn6jypz%2Fq5LtqbpwecOh9W1FZK4vA4nQDz1DBKJB6COcWVhKWQM%2B6ywTlMyCjhKA%2BM5fmoMj2cwnrGqScKlefDIG8ZA8pZFa80uRLppGgAQFcZuRB5FKweN8lAWYeqNWpaAUDQNr6BD5gCnqeWJrg2V4%2FYqPmASWnj0rA5X4wye6L0uYMkBuit5Zpp2RppxAdMrXwINWecmB4p0tROqMIBGUHauo8NNAHAGIKsPOPcJnH1sHK02gutoU1k3NTg6S1zJqI9jWoeOfzEfIjp0wr3h7%2FCsFcjp3RK2nsjvBV7ggtFxbbzMMIb7aVwRcvIjbs%2FrGKv7l26dXphN0xo7igydaoRpzKPt1nGPU5CDlN5EjJisTBO0VkCkLwnVjJfwcrB3N6dqQqD4Z8AKmtz2M%2FabNxw5vOH8lMKiZVd%2FMPO46sIV4yPcZGFM8KvLJE9zJ%2BBVqSpO3MuROH%2BZq219seWxGpgSLQRRGpGI6gb8dpK5bhEAvbycV%2Fu4%2BZv5%2BP0p%2Fm46eXj%2FsTHDf%2BMj1MvH%2FfrPs68w8fZd%2Fm49cTHzV%2Ft46jycdcP9nGde5aqfZz6PR93tAR4%2BbiXj0slZh93fJaPm7%2BNj1v%2BCx%2B3fZaP08cTrX9DH4fv8nEDkvc5Vx%2Fp49a%2F7%2BPsu32c%2F1Mf9xMJCg1tRkA9NQAAAABJRU5ErkJggg%3D%3D');
	color: #000;
	padding: 1em;
	font-family: "Trebuchet MS", Arial, Helvetica, sans-serif;;
}

a { color: darkblue; }
a:hover { color: darkred; }

body > header, #page, body > footer {
	width: 90%;
	margin: 1em auto;
	border-radius: 1em;
	background: rgba(213, 214, 182, 0.75);
	padding: 1em;
	text-align: center;
}

body > header h1 a {
	text-decoration: none;
	color: #000;
	font-style: italic;
}

body > header h2 {
	color: darkred;
}

body > footer {
	font-size: .9em;
	color: #666;
}

nav {
	margin: .5em 0;
}

nav ul li {
	display: inline-block;
	margin: .2em .5em;
}

nav ul li a {
	color: #000;
	border-radius: .5em;
	background: rgba(255, 255, 255, 0.5);
	padding: .2em .4em;
}

nav ul li a:hover {
	background: #fff;
	color: darkred;
}

input[type=submit] {
	font-size: 112.5%;
	padding: .3em;
	cursor: pointer;
}

label {
	cursor: pointer;
}
label:hover {
	border-bottom: 1px dashed #fff;
}

input[type=text], input[type=file], input[type=password] {
	padding: .3em;
	width: 95%;
}

fieldset dl dt {
	font-weight: bold;
}

fieldset dl dd {
	margin: .5em;
}

fieldset {
	width: 50%;
	margin: 0 auto;
}

.info {
	margin: .8em 0;
	color: #666;
}

.picture footer {
	margin: 1em 0;
}

.picture footer.context {
	background: rgb(220, 220, 220);
	background: rgba(255, 255, 255, 0.25);
	border-radius: .5em;
	padding: 1em;
	max-width: 650px;
	margin: 1em auto;
}

.picture footer.context img {
	max-width: 200px;
	max-height: 150px;
}

.picture footer.context figure {
	position: relative;
	width: 200px;
	height: 180px;
	margin: 0;
}

.picture footer.context figure b {
	font-size: 100px;
	line-height: 150px;
	width: 200px;
	height: 150px;
	position: absolute;
	display: block;
	top: 0;
	left: 0;
	color: rgb(255, 255, 255);
	color: rgba(255, 255, 255, 0.5);
	text-shadow: 0px 0px 10px #000;
}

.examples {
	margin: 1rem auto;
	max-width: 40em;
}

.examples dt {
	margin: .8rem 0;
	font-weight: bold;
	text-align: left;
}

.examples input[type=button] {
	float: right;
	padding: .3em .5em;
	background: rgba(255, 255, 255, 0.5);
	border: none;
	border-radius: .3em;
	cursor: pointer;
}

.examples input[type=button]:hover {
	color: darkred;
	box-shadow: 0px 0px 5px orange;
}

.examples input[type=text], .admin input[type=text], .examples textarea {
	background: rgba(213, 214, 182, 0.5);
	border: 1px solid #fff;
	border-radius: .5em;
	font-family: "Courier New", Courier, monospace;
	width: calc(100% - 1em);
	font-size: 10pt;
	padding: .5em;
}

figure {
	display: inline-block;
	margin: 1em;
	vertical-align: middle;
	position: relative;
	min-width: 150px;
}

figure figcaption {
	font-size: small;
	margin-top: .5em;
}

#albumParent figcaption {
	display: flex;
	justify-content: stretch;
}

#albumParent figcaption button {
	font-size: 1.5em;
	cursor: pointer;
	color: #666;
	border: 1px solid #666;
	background: rgba(255, 255, 255, 0.5);
	margin-left: .2em;
}

figure a:hover img {
	box-shadow: 0px 0px 10px #000;
	background: #fff;
}

figure span {
	background: rgb(0, 0, 0);
	background: rgba(0, 0, 0, 0.75);
	color: #fff;
	position: absolute;
	padding: .5em 1em;
	font-weight: bold;
	top: 1em;
	left: 0;
}

figure span.private {
	left: unset;
	right: 0;
	background: rgb(150, 0, 0);
	background: rgba(150, 0, 0, 0.75);
}

.pagination .selected {
	font-weight: bold;
	font-size: 125%;
}

p.admin, #albumParent {
	background: rgb(100, 100, 100);
	background: rgba(0, 0, 0, 0.25);
	padding: 1em;
	width: 50%;
	margin: .8em auto;
	border-radius: .8em;
	color: #fff;
}

#albumParent {
	width: 90%;
}

#albumParent .add {
	border: none;
	border-radius: .2em;
	cursor: pointer;
	display: block;
	padding: .2em;
	padding-left: 2em;
	font-size: 2em;
	margin: .5rem auto;
	background: no-repeat .5em center rgba(0, 0, 0, 0.2);
	box-shadow: 0px 0px 5px #fff;
	background-image: url('data:image/svg+xml;utf8,<svg viewBox="0 0 512 512" xmlns="http://www.w3.org/2000/svg"><path d="m296 384h-80c-13.3 0-24-10.7-24-24v-168h-87.7c-17.8 0-26.7-21.5-14.1-34.1l152.1-152.2c7.5-7.5 19.8-7.5 27.3 0l152.2 152.2c12.6 12.6 3.7 34.1-14.1 34.1h-87.7v168c0 13.3-10.7 24-24 24zm216-8v112c0 13.3-10.7 24-24 24h-464c-13.3 0-24-10.7-24-24v-112c0-13.3 10.7-24 24-24h136v8c0 30.9 25.1 56 56 56h80c30.9 0 56-25.1 56-56v-8h136c13.3 0 24 10.7 24 24zm-124 88c0-11-9-20-20-20s-20 9-20 20 9 20 20 20 20-9 20-20zm64 0c0-11-9-20-20-20s-20 9-20 20 9 20 20 20 20-9 20-20z"/></svg>');
	background-size: 32px 32px;
	transition: background-color .2s, color .2s, box-shadow .2s;
}

#albumParent .add:hover {
	color: #fff;
	box-shadow: 0px 0px 5px orange;
	background-color: rgba(0, 0, 0, 0.5)
}

#albumParent img.loading, #albumParent span b {
	background: #fff;
	padding: .5em;
	border-radius: 1em;
	vertical-align: middle;
	color: #000;
	display: inline-block;
	line-height: 16px;
}

#albumParent img {
	box-shadow: 0px 0px 10px #000;
}

article h2 {
	margin-bottom: .5em;
}

p.error {
	color: red;
	margin: .8em;
}

p.admin a {
	color: darkred;
}

.albums figure {
	display: block;
}

.albums figure img {
	vertical-align: middle;
	margin: .5em;
}

.albums figure span {
	margin: 0 auto;
	left: 0px;
	right: 0px;
	bottom: 40%;
	top: auto;
}<?php exit; endif; ?><?php

class Fotoo_Hosting
{
	static private $base_index = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';

	private $db = null;
	private $config = null;

	public function __construct(&$config)
	{
		$init = file_exists($config->db_file) ? false : true;
		$this->db = new SQLite3($config->db_file);

		if (!$this->db)
		{
			throw new FotooException("SQLite database init error.");
		}

		if ($init)
		{
			$this->db->exec('
				CREATE TABLE pictures (
					hash TEXT PRIMARY KEY NOT NULL,
					filename TEXT NOT NULL,
					date INT NOT NULL,
					format TEXT NOT NULL,
					width INT NOT NULL,
					height INT NOT NULL,
					thumb INT NOT NULL DEFAULT 0,
					private INT NOT NULL DEFAULT 0,
					size INT NOT NULL DEFAULT 0,
					album TEXT NULL,
					ip TEXT NULL
				);

				CREATE INDEX date ON pictures (private, date);
				CREATE INDEX album ON pictures (album);

				CREATE TABLE albums (
					hash TEXT PRIMARY KEY NOT NULL,
					title TEXT NOT NULL,
					date INT NOT NULL,
					private INT NOT NULL DEFAULT 0
				);
			');
		}

		$this->config =& $config;

		if (!file_exists($config->storage_path))
			mkdir($config->storage_path);
	}

    public function isClientBanned()
    {
    	if (!empty($_COOKIE['bstats']))
    		return true;

    	if (count($this->config->banned_ips) < 1)
    		return false;

        if (!empty($_SERVER['REMOTE_ADDR']) && self::isIpBanned($_SERVER['REMOTE_ADDR'], $this->config->banned_ips))
        {
        	return true;
        }

        if (!empty($_SERVER['HTTP_X_FORWARDED_FOR']) && filter_var($_SERVER['HTTP_X_FORWARDED_FOR'], FILTER_VALIDATE_IP) 
        	&& self::isIpBanned($_SERVER['HTTP_X_FORWARDED_FOR'], $this->config->banned_ips))
        {
        	return true;
        }

        if (!empty($_SERVER['HTTP_CLIENT_IP']) && filter_var($_SERVER['HTTP_CLIENT_IP'], FILTER_VALIDATE_IP) 
        	&& self::isIpBanned($_SERVER['HTTP_CLIENT_IP'], $this->config->banned_ips))
        {
        	return true;
        }

        return false;
    }

    public function setBanCookie()
    {
    	return setcookie('bstats', md5(time()), time()+10*365*24*3600, '/');
    }

    static public function getIPAsString()
    {
    	$out = '';

        if (!empty($_SERVER['REMOTE_ADDR']))
        {
            $out .= $_SERVER['REMOTE_ADDR'];
        }

        if (!empty($_SERVER['HTTP_X_FORWARDED_FOR']) && filter_var($_SERVER['HTTP_X_FORWARDED_FOR'], FILTER_VALIDATE_IP))
        {
        	$out .= (!empty($out) ? ', ' : '') . 'X-Forwarded-For: ' . $_SERVER['HTTP_X_FORWARDED_FOR'];
        }

        if (!empty($_SERVER['HTTP_CLIENT_IP']) && filter_var($_SERVER['HTTP_CLIENT_IP'], FILTER_VALIDATE_IP))
        {
        	$out .= (!empty($out) ? ', ' : '') . 'Client-IP: ' . $_SERVER['HTTP_CLIENT_IP'];
        }

        return $out;
    }

    /**
     * Returns an integer if $ip is in addresses given in $check array
     * This integer may be used to store the IP address in database eventually
     *
     * Examples:
     * - check_ip('192.168.1.102', array('192.168.1.*'))
     * - check_ip('2a01:e34:ee89:c060:503f:d19b:b8fa:32fd', array('2a01::*'))
     * - check_ip('2a01:e34:ee89:c060:503f:d19b:b8fa:32fd', array('2a01:e34:ee89:c06::/64'))
     */
    static public function isIpBanned($ip, $check)
    {
        $ip = strtolower(is_null($ip) ? $_SERVER['REMOTE_ADDR'] : $ip);

        if (strpos($ip, ':') === false)
        {
            $ipv6 = false;
            $ip = ip2long($ip);
        }
        else
        {
            $ipv6 = true;
            $ip = bin2hex(inet_pton($ip));
        }

        foreach ($check as $c)
        {
            if (strpos($c, ':') === false)
            {
                if ($ipv6)
                {
                    continue;
                }

                // Check against mask
                if (strpos($c, '/') !== false)
                {
                    list($c, $mask) = explode('/', $c);
                    $c = ip2long($c);
                    $mask = ~((1 << (32 - $mask)) - 1);

                    if (($ip & $mask) == $c)
                    {
                        return $c;
                    }
                }
                elseif (strpos($c, '*') !== false)
                {
                    $c = substr($c, 0, -1);
                    $mask = substr_count($c, '.');
                    $c .= '0' . str_repeat('.0', (3 - $mask));
                    $c = ip2long($c);
                    $mask = ~((1 << (32 - ($mask * 8))) - 1);

                    if (($ip & $mask) == $c)
                    {
                        return $c;
                    }
                }
                else
                {
                    if ($ip == ip2long($c))
                    {
                        return $c;
                    }
                }
            }
            else
            {
                if (!$ipv6)
                {
                    continue;
                }

                // Check against mask
                if (strpos($c, '/') !== false)
                {
                    list($c, $mask) = explode('/', $c);
                    $c = bin2hex(inet_pton($c));
                    $mask = $mask / 4;
                    $c = substr($c, 0, $mask);

                    if (substr($ip, 0, $mask) == $c)
                    {
                        return $c;
                    }
                }
                elseif (strpos($c, '*') !== false)
                {
                    $c = substr($c, 0, -1);
                    $c = bin2hex(inet_pton($c));
                    $c = rtrim($c, '0');

                    if (substr($ip, 0, strlen($c)) == $c)
                    {
                        return $c;
                    }
                }
                else
                {
                    if ($ip == inet_pton($c))
                    {
                        return $c;
                    }
                }
            }
        }

        return false;
    }

	static private function baseConv($num, $base=null)
	{
		if (is_null($base))
			$base = strlen(self::$base_index);

		$index = substr(self::$base_index, 0, $base);

		$out = "";
		for ($t = floor(log10($num) / log10($base)); $t >= 0; $t--)
		{
			$a = floor($num / pow($base, $t));
			$out = $out . substr($index, $a, 1);
			$num = $num - ($a * pow($base, $t));
		}

		return $out;
	}

	static public function getErrorMessage($error)
	{
		switch ($error)
		{
			case UPLOAD_ERR_INI_SIZE:
				return 'The uploaded file exceeds the allowed file size (ini).';
			case UPLOAD_ERR_FORM_SIZE:
				return 'The uploaded file exceeds the allowed file size (html).';
			case UPLOAD_ERR_PARTIAL:
				return 'The uploaded file was only partially uploaded.';
			case UPLOAD_ERR_NO_FILE:
				return 'No file was uploaded.';
			case UPLOAD_ERR_NO_TMP_DIR:
				return 'Missing a temporary folder.';
			case UPLOAD_ERR_CANT_WRITE:
				return 'Failed to write file to disk.';
			case UPLOAD_ERR_EXTENSION:
				return 'A server extension stopped the file upload.';
			case UPLOAD_ERR_INVALID_IMAGE:
				return 'Invalid image format.';
			default:
				return 'Unknown error.';
		}
	}

	protected function _processEncodedUpload(&$file)
	{
		if (!is_array($file))
		{
			return false;
		}

		$file['error'] = $file['size'] = 0;

		if (empty($file['content']))
		{
			$file['error'] = UPLOAD_ERR_NO_FILE;
			return false;
		}

		if (!is_string($file['content']))
		{
			$file['error'] = UPLOAD_ERR_NO_FILE;
			return false;
		}

		$file['content'] = base64_decode($file['content'], true);

		if (empty($file['content']))
		{
			$file['error'] = UPLOAD_ERR_PARTIAL;
			return false;
		}

		$file['size'] = strlen($file['content']);

		if ($file['size'] == 0)
		{
			$file['error'] = UPLOAD_ERR_FORM_SIZE;
			return false;
		}

		$file['tmp_name'] = tempnam(ini_get('upload_tmp_dir') ?: sys_get_temp_dir(), 'tmp_file_');

		if (!$file['tmp_name'])
		{
			$file['error'] = UPLOAD_ERR_NO_TMP_DIR;
			return false;
		}

		if (!file_put_contents($file['tmp_name'], $file['content']))
		{
			$file['error'] = UPLOAD_ERR_CANT_WRITE;
			return false;
		}

		unset($file['content']);

		return true;
	}

	public function upload($file, $name = '', $private = false, $album = null)
	{
		if ($this->isClientBanned())
		{
			throw new FotooException('Upload error: upload not permitted.', -42);
		}

		$client_resize = false;

		if (isset($file['content']) && $this->_processEncodedUpload($file))
		{
			$client_resize = true;
		}

		if (!isset($file['error'])) {
			throw new FotooException("Upload error.", UPLOAD_ERR_NO_FILE);
		}

		if ($file['error'] != UPLOAD_ERR_OK) {
			throw new FotooException("Upload error.", $file['error']);
		}

		if (empty($file['tmp_name'])) {
			throw new FotooException("Upload error.", UPLOAD_ERR_NO_FILE);
		}

		// Make sure tmp_name is from us
		if (!file_exists($file['tmp_name'])
			|| !(is_uploaded_file($file['tmp_name']) || $client_resize)) {
			throw new FotooException("Upload error.", UPLOAD_ERR_NO_FILE);
		}

		if (!empty($name))
		{
			$name = preg_replace('!\s+!', '-', $name);
			$name = preg_replace('![^a-z0-9_.-]!i', '', $name);
			$name = preg_replace('!([_.-]){2,}!', '\\1', $name);
			$name = substr($name, 0, 30);
		}

		if (!trim($name))
		{
			$name = '';
		}

		try {
			$img = new Image($file['tmp_name']);
			$format = strtolower($img->format());

			if (empty($img->getSize()[0]) || !$img->format()
				|| !in_array($format, array_map('strtolower', $this->config->allowed_formats))) {
				throw new \RuntimeException('Invalid image');
			}
		}
		catch (\RuntimeException $e) {
			@unlink($file['tmp_name']);
			throw new FotooException("Invalid image format.", UPLOAD_ERR_INVALID_IMAGE);
		}

		list($width, $height) = $img->getSize();
		$format = $img->format();
		$size = filesize($file['tmp_name']);

		$hash = md5($file['tmp_name'] . time() . $width . $height . $format . $size . $file['name']);
		$dest = $this->config->storage_path . substr($hash, -2);

		if (!file_exists($dest)) {
			mkdir($dest);
		}

		$base = self::baseConv(hexdec(uniqid()));
		$dest .= '/' . $base;
		$ext = '.' . strtolower($format);

		if (trim($name) && !empty($name)) {
			$dest .= '.' . $name;
		}

		$max_mp = $this->config->max_width * $this->config->max_width;
		$img_mp = $width * $height;

		if ($img_mp > $max_mp)
		{
			$ratio = $img_mp / $max_mp;
			$width = round($width / $ratio);
			$height = round($height / $ratio);
			$resize = true;
		}
		else
		{
			$width = $width;
			$height = $height;
			$resize = false;
		}

		// If JPEG or big PNG/GIF, then resize (always resize JPEG to reduce file size)
		if (($format == 'jpeg' || $format == 'webp') && !$client_resize) {
			$resize = true;
		}
		elseif (($format == 'gif' || $format == 'png') && $file['size'] > (1024 * 1024)) {
			$resize = true;
		}

		if ($resize)
		{
			$img->resize($width, $height);
			$img->jpeg_quality = 80;
			$img->save($dest . $ext);
			list($width, $height) = $img->getSize();
			unset($img);
		}
		elseif ($client_resize)
		{
			rename($file['tmp_name'], $dest . $ext);
		}
		else
		{
			move_uploaded_file($file['tmp_name'], $dest . $ext);
		}

		$size = filesize($dest . $ext);

		// Create thumb when needed
		if ($width > $this->config->thumb_width || $height > $this->config->thumb_width
			|| $size > (100 * 1024) || !in_array($format, ['jpeg', 'png', 'webp']))
		{
			$img = new Image($dest . $ext);
			$img->jpeg_quality = 70;
			$img->webp_quality = 70;

			if (in_array('webp', $img->getSupportedFormats())) {
				$thumb_format = 'webp';
				$thumb_ext = '.s.webp';
			}
			elseif ($format !== 'png') {
				$thumb_format = 'jpeg';
				$thumb_ext = '.s.jpeg';
			}
			else {
				$thumb_format = $format;
				$thumb_ext = '.s.' . $format;
			}

			$img->resize(
				($width > $this->config->thumb_width) ? $this->config->thumb_width : $width,
				($height > $this->config->thumb_width) ? $this->config->thumb_width : $height
			);

			$img->save($dest . $thumb_ext, $thumb_format);

			$thumb = true;
		}
		else
		{
			$thumb = false;
			$thumb_format = 0;
		}

		$hash = substr($hash, -2) . '/' . $base;

		$req = $this->db->prepare('INSERT INTO pictures 
			(hash, filename, date, format, width, height, thumb, private, size, album, ip)
			VALUES (:hash, :filename, :date, :format, :width, :height, :thumb, :private, :size, :album, :ip);');

		$req->bindValue(':hash', $hash);
		$req->bindValue(':filename', $name);
		$req->bindValue(':date', time());
		$req->bindValue(':format', strtoupper($format));
		$req->bindValue(':width', (int)$width);
		$req->bindValue(':height', (int)$height);
		$req->bindValue(':thumb', $thumb_format === 0 ? $thumb_format : strtoupper($thumb_format));
		$req->bindValue(':private', $private ? '1' : '0');
		$req->bindValue(':size', (int)$size);
		$req->bindValue(':album', is_null($album) ? NULL : $album);
		$req->bindValue(':ip', self::getIPAsString());

		$req->execute();

		// Automated deletion of IP addresses to comply with local low
		$expiration = time() - ($this->config->ip_storage_expiration * 24 * 3600);
		$this->db->query('UPDATE pictures SET ip = "R" WHERE date < ' . (int)$expiration . ';');

		$url = $this->getUrl(['hash' => $hash, 'filename' => $name, 'format' => strtoupper($format)], true);

		return $url;
	}

	public function get($hash)
	{
		$res = $this->db->querySingle(
			'SELECT * FROM pictures WHERE hash = \''.$this->db->escapeString($hash).'\';',
			true
		);

		if (empty($res))
			return false;

		$file = $this->_getPath($res);
		$th = $this->_getPath($res, 's');

		if (!file_exists($file))
		{
			if (file_exists($th))
				@unlink($th);

			$this->db->exec('DELETE FROM pictures WHERE hash = \''.$res['hash'].'\';');
			return false;
		}

		return $res;
	}

	public function remove($hash, $id = null)
	{
		if (!$this->logged() && !$this->checkRemoveId($hash, $id))
			return false;

		$img = $this->get($hash);

		$file = $this->_getPath($img);

		if (file_exists($file))
			unlink($file);

		return $this->get($hash) ? false : true;
	}

	protected function getListQuery(bool $private = false)
	{
		$where = $private ? '' : 'AND private != 1';
		return sprintf('
			SELECT p.*, COUNT(*) AS count, a.title, a.private AS private
				FROM pictures p
				INNER JOIN albums a ON a.hash = p.album
				WHERE %s
				GROUP BY p.album
			UNION ALL
				SELECT *, 1 AS count, NULL AS title, p.private AS private
				FROM pictures p
				WHERE album IS NULL AND %s',
			$private ? '1' : 'a.private != 1',
			$private ? '1' : 'p.private != 1'
		);
	}

	public function getList($page)
	{
		$begin = ($page - 1) * $this->config->nb_pictures_by_page;
		$private = $this->logged();

		$out = [];
		$res = $this->db->query(sprintf(
			'SELECT * FROM (%s) ORDER BY date DESC LIMIT %d,%d;',
			$this->getListQuery($private),
			$begin,
			$this->config->nb_pictures_by_page
		));

		if (!$res) {
			throw new \RuntimeException($this->db->lastErrorMsg());
		}

		while ($row = $res->fetchArray(SQLITE3_ASSOC))
		{
			$out[] = $row;
		}

		return $out;
	}

	public function countList()
	{
		return $this->db->querySingle(sprintf('SELECT COUNT(*) FROM (%s);', $this->getListQuery($this->logged())));
	}

	public function makeRemoveId($hash)
	{
		return sha1($this->config->storage_path . $hash);
	}

	public function checkRemoveId($hash, $id)
	{
		return sha1($this->config->storage_path . $hash) === $id;
	}

	public function getAlbumPrevNext($album, $current, $order = -1)
	{
		$st = $this->db->prepare('SELECT * FROM pictures WHERE album = :album
			AND rowid '.($order > 0 ? '>' : '<').' (SELECT rowid FROM pictures WHERE hash = :img)
			ORDER BY rowid '.($order > 0 ? 'ASC': 'DESC').' LIMIT 1;');
		$st->bindValue(':album', $album);
		$st->bindValue(':img', $current);
		$res = $st->execute();

		if ($res)
			return $res->fetchArray(SQLITE3_ASSOC);

		return false;
	}

	public function getAlbumExtract($hash)
	{
		$out = array();
		$res = $this->db->query('SELECT * FROM pictures WHERE album = \''.$this->db->escapeString($hash).'\' ORDER BY RANDOM() LIMIT 2;');

		while ($row = $res->fetchArray(SQLITE3_ASSOC))
		{
			$out[] = $row;
		}

		return $out;
	}

	public function countAlbumList()
	{
		$where = $this->logged() ? '' : 'WHERE private != 1';
		return $this->db->querySingle('SELECT COUNT(*) FROM albums '.$where.';');
	}

	public function getAlbum($hash)
	{
		return $this->db->querySingle('SELECT *, strftime(\'%s\', date) AS date FROM albums WHERE hash = \''.$this->db->escapeString($hash).'\';', true);
	}

	public function getAlbumPictures($hash, $page)
	{
		$begin = ($page - 1) * $this->config->nb_pictures_by_page;

		$out = array();
		$res = $this->db->query('SELECT * FROM pictures WHERE album = \''.$this->db->escapeString($hash).'\' ORDER BY date LIMIT '.$begin.','.$this->config->nb_pictures_by_page.';');

		while ($row = $res->fetchArray(SQLITE3_ASSOC))
		{
			$out[] = $row;
		}

		return $out;
	}

	public function getAllAlbumPictures($hash)
	{
		$out = array();
		$res = $this->db->query('SELECT * FROM pictures WHERE album = \''.$this->db->escapeString($hash).'\' ORDER BY date;');

		while ($row = $res->fetchArray(SQLITE3_ASSOC))
		{
			$out[] = $row;
		}

		return $out;
	}

	public function downloadAlbum($hash)
	{
		$album = $this->getAlbum($hash);

		header('Content-Type: application/zip');
		header(sprintf('Content-Disposition: attachment; filename=%s.zip', preg_replace('/[^\w_-]+/U', '', $album['title'])));

		$zip = new ZipWriter('php://output');

		foreach ($this->getAllAlbumPictures($hash) as $picture) {
			$zip->add(sprintf('%s.%s', $picture['filename'], strtolower($picture['format'])), null, $this->_getPath($picture));
		}

		$zip->close();
	}

	public function countAlbumPictures($hash)
	{
		return $this->db->querySingle('SELECT COUNT(*) FROM pictures WHERE album = \''.$this->db->escapeString($hash).'\';');
	}

	public function removeAlbum($hash, $id = null)
	{
		if (!$this->logged() && !$this->checkRemoveId($hash, $id))
			return false;

		$res = $this->db->query('SELECT * FROM pictures WHERE album = \''.$this->db->escapeString($hash).'\';');

		while ($row = $res->fetchArray(SQLITE3_ASSOC))
		{
			$file = $this->_getPath($row);

			if (file_exists($file))
				unlink($file);

			if ($this->get($row['hash']))
				return false;
		}

		$this->db->exec('DELETE FROM albums WHERE hash = \''.$this->db->escapeString($hash).'\';');
		return true;
	}

	protected function _getPath($img, $optional = '')
	{
		return $this->config->storage_path . $img['hash']
			. ($img['filename'] ? '.' . $img['filename'] : '')
			. ($optional ? '.' . $optional : '')
			. '.' . strtolower($img['format']);
	}

	public function getUrl($img, $append_id = false)
	{
		$url = $this->config->image_page_url
			. $img['hash']
			. ($img['filename'] ? '.' . $img['filename'] : '')
			. '.' . strtolower($img['format']);

		if ($append_id)
		{
			$id = $this->makeRemoveId($img['hash']);
			$url .= (strpos($url, '?') !== false) ? '&c=' . $id : '?c=' . $id;
		}

		return $url;
	}

	public function getImageUrl($img)
	{
		$url = $this->config->storage_url . $img['hash'];
		$url.= !empty($img['filename']) ? '.' . $img['filename'] : '';
		$url.= '.' . strtolower($img['format']);
		return $url;
	}

	public function getImageThumbUrl($img)
	{
		if (!$img['thumb'])
		{
			return $this->getImageUrl($img);
		}

		$format = strtolower($img['format']);

		if ((int)$img['thumb'] !== 1) {
			$format = strtolower($img['thumb']);
		}
		elseif ($format != 'jpeg' && $format != 'png')
		{
			$format = 'jpeg';
		}

		$url = $this->config->storage_url . $img['hash'];
		$url.= !empty($img['filename']) ? '.' . $img['filename'] : '';
		$url.= '.s.' . $format;
		return $url;
	}

	public function getShortImageUrl($img)
	{
		return $this->config->image_page_url
			. 'r.' . $img['hash'];
	}

	public function login($password)
	{
		if ($this->config->admin_password === $password)
		{
			@session_start();
			$_SESSION['logged'] = true;
			return true;
		}
		else
		{
			return false;
		}
	}

	public function logged()
	{
		if (array_key_exists(session_name(), $_COOKIE) && !isset($_SESSION))
		{
			session_start();
		}

		return empty($_SESSION['logged']) ? false : true;
	}

	public function logout()
	{
		$this->logged();
		$_SESSION = null;
		session_destroy();
		return true;
	}

	public function createAlbum($title, $private = false)
	{
		if ($this->isClientBanned())
		{
			throw new FotooException('Upload error: upload not permitted.');
		}

		$hash = self::baseConv(hexdec(uniqid()));
		$this->db->exec('INSERT INTO albums (hash, title, date, private)
			VALUES (\''.$this->db->escapeString($hash).'\',
			\''.$this->db->escapeString(trim($title)).'\',
			datetime(\'now\'), \''.(int)(bool)$private.'\');');
		return $hash;
	}

	public function appendToAlbum($album, $name, $file)
	{
		$album = $this->db->querySingle('SELECT * FROM albums WHERE hash = \''.$this->db->escapeString($album).'\';', true);

		if (!$album) {
			throw new FotooException('ALbum not found');
		}

		return $this->upload($file, $name, $album['private'], $album['hash']);
	}
}

?><?php

class Image
{
    static private $init = false;

    protected $libraries = [];

    protected $path = null;
    protected $blob = null;

    protected $width = null;
    protected $height = null;
    protected $type = null;
    protected $format = null;

    protected $pointer = null;
    protected $library = null;

    public $use_gd_fast_resize_trick = true;

    /**
     * WebP quality, from 0 to 100
     * @var integer
     */
    public int $webp_quality = 80;

    /**
     * JPEG quality, from 1 to 100
     * @var integer
     */
    public $jpeg_quality = 90;

    /**
     * Progressive JPEG output?
     * Only supported by GD and Imagick!
     * You can also use the command line tool jpegtran (package libjpeg-progs)
     * to losslessly convert to and from progressive.
     * @var boolean
     */
    public $progressive_jpeg = true;

    /**
     * LZW compression index, used by TIFF and PNG, from 0 to 9
     * @var integer
     */
    public $compression = 9;

    public function __construct($path = null, $library = null)
    {
        $this->libraries = [
            'epeg'    => function_exists('\epeg_open'),
            'imagick' => class_exists('\Imagick', false),
            'gd'      => function_exists('\imagecreatefromjpeg'),
        ];

        if (!self::$init)
        {
            if (empty($path))
            {
                throw new \InvalidArgumentException('Empty source file argument passed');
            }

            if (!is_readable($path))
            {
                throw new \InvalidArgumentException(sprintf('Can\'t read source file: %s', $path));
            }
        }

        if ($library && !self::$init)
        {
            if (!isset($this->libraries[$library]))
            {
                throw new \InvalidArgumentException(sprintf('Library \'%s\' is not supported.', $library));
            }

            if (!$this->libraries[$library])
            {
                throw new \RuntimeException(sprintf('Library \'%s\' is not installed and can not be used.', $library));
            }
        }

        if (!self::$init)
        {
            $this->path = $path;

            try {
                $info = getimagesize($path);
            }
            catch (\Throwable $e) {
                throw new \RuntimeException(sprintf('Invalid image format: %s (%s)', $path, $e->getMessage()), 0, $e);
            }

            if (!$info && function_exists('mime_content_type'))
            {
                $info = ['mime' => mime_content_type($path)];
            }

            if (!$info)
            {
                throw new \RuntimeException(sprintf('Invalid image format: %s', $path));
            }

            $this->init($info, $library);
        }
    }

    static public function getBytesFromINI($size_str)
    {
        if ($size_str == -1)
        {
            return null;
        }

        $unit = strtoupper(substr($size_str, -1));

        switch ($unit)
        {
            case 'G': return (int) $size_str * pow(1024, 3);
            case 'M': return (int) $size_str * pow(1024, 2);
            case 'K': return (int) $size_str * 1024;
            default:  return (int) $size_str;
        }
    }

    static public function getMaxUploadSize($max_user_size = null)
    {
        $sizes = [
            ini_get('upload_max_filesize'),
            ini_get('post_max_size'),
            ini_get('memory_limit'),
            $max_user_size,
        ];

        // Convert to bytes
        $sizes = array_map([self::class, 'getBytesFromINI'], $sizes);

        // Remove sizes that are null or -1 (unlimited)
        $sizes = array_filter($sizes, function ($size) {
            return !is_null($size);
        });

        // Return maximum file size allowed
        return min($sizes);
    }

    protected function init(array $info, $library = null)
    {
        if (isset($info[0]))
        {
            $this->width = $info[0];
            $this->height = $info[1];
        }

        $this->type = $info['mime'];
        $this->format = $this->getFormatFromType($this->type);

        if (!$this->format)
        {
            throw new \RuntimeException('Not an image format: ' . $this->type);
        }

        if ($library)
        {
            $supported_formats = call_user_func([$this, $library . '_formats']);

            if (!in_array($this->format, $supported_formats))
            {
                throw new \RuntimeException(sprintf('Library \'%s\' doesn\'t support files of type \'%s\'.', $library, $this->type));
            }
        }
        else
        {
            foreach ($this->libraries as $name => $enabled)
            {
                if (!$enabled)
                {
                    continue;
                }

                $supported_formats = call_user_func([$this, $name . '_formats']);

                if (in_array($this->format, $supported_formats))
                {
                    $library = $name;
                    break;
                }
            }

            if (!$library)
            {
                throw new \RuntimeException('No suitable image library found for type: ' . $this->type);
            }
        }

        $this->library = $library;

        if (!$this->width && !$this->height)
        {
            $this->open();
        }
    }

    public function __get($key)
    {
        if (!property_exists($this, $key))
        {
            throw new \RuntimeException('Unknown property: ' . $key);
        }

        return $this->$key;
    }

    public function __set($key, $value)
    {
        $this->key = $value;
    }

    static public function createFromBlob($blob, $library = null)
    {
        // Trick to allow empty source in constructor
        self::$init = true;
        $obj = new Image(null, $library);

        $info = getimagesizefromstring($blob);

        // Find MIME type
        if (!$info && function_exists('finfo_open'))
        {
            $f = finfo_open(FILEINFO_MIME);
            $info = ['mime' => strstr(finfo_buffer($f, $blob), ';', true)];
            finfo_close($f);
        }

        if (!$info)
        {
            throw new \RuntimeException('Invalid image format, couldn\'t be read: from string');
        }

        $obj->blob = $blob;
        $obj->init($info, $library);

        self::$init = false;

        return $obj;
    }

    /**
     * Open an image file
     */
    public function open()
    {
        if ($this->pointer !== null)
        {
            return true;
        }

        if ($this->path)
        {
            call_user_func([$this, $this->library . '_open']);
        }
        else
        {
            call_user_func([$this, $this->library . '_blob'], $this->blob);
            $this->blob = null;
        }

        if (!$this->pointer)
        {
            throw new \RuntimeException('Invalid image format, couldn\'t be read: ' . $this->path);
        }

        call_user_func([$this, $this->library . '_size']);

        return $this;
    }

    public function __destruct()
    {
        $this->blob = null;
        $this->path = null;

        if ($this->pointer)
        {
            call_user_func([$this, $this->library . '_close']);
        }
    }

    /**
     * Returns image width and height
     * @return array            array(ImageWidth, ImageHeight)
     */
    public function getSize()
    {
        return [$this->width, $this->height];
    }

    /**
     * Crop the current image to this dimensions
     * @param  integer $new_width  Width of the desired image
     * @param  integer $new_height Height of the desired image
     * @return Image
     */
    public function crop($new_width = null, $new_height = null)
    {
        $this->open();

        if (!$new_width)
        {
            $new_width = $new_height = min($this->width, $this->height);
        }

        if (!$new_height)
        {
            $new_height = $new_width;
        }

        $method = $this->library . '_crop';

        if (!method_exists($this, $method))
        {
            throw new \RuntimeException('Crop is not supported by the current library: ' . $this->library);
        }

        $this->$method((int) $new_width, (int) $new_height);
        call_user_func([$this, $this->library . '_size']);

        return $this;
    }

    public function resize($new_width, $new_height = null, $ignore_aspect_ratio = false)
    {
        $this->open();

        if (!$new_height)
        {
            $new_height = $new_width;
        }

        if ($this->width <= $new_width && $this->height <= $new_height)
        {
            // Nothing to do
            return $this;
        }

        $new_height = (int) $new_height;
        $new_width = (int) $new_width;

        call_user_func([$this, $this->library . '_resize'], $new_width, $new_height, $ignore_aspect_ratio);
        call_user_func([$this, $this->library . '_size']);

        return $this;
    }

    public function rotate($angle)
    {
        $this->open();

        if (!$angle)
        {
            return $this;
        }

        $method = $this->library . '_rotate';

        if (!method_exists($this, $method))
        {
            throw new \RuntimeException('Rotate is not supported by the current library: ' . $this->library);
        }

        call_user_func([$this, $method], $angle);
        call_user_func([$this, $this->library . '_size']);

        return $this;
    }

    public function autoRotate()
    {
        $orientation = $this->getOrientation();

        if (!$orientation)
        {
            return $this;
        }

        if (in_array($orientation, [2, 4, 5, 7]))
        {
            $this->flip();
        }

        switch ($orientation)
        {
            case 3:
            case 4:
                return $this->rotate(180);
            case 5:
            case 8:
                return $this->rotate(270);
            case 7:
            case 6:
                return $this->rotate(90);
        }

        return $this;
    }

    public function flip()
    {
        $this->open();
        $method = $this->library . '_flip';

        if (!method_exists($this, $method))
        {
            throw new \RuntimeException('Flip is not supported by the current library: ' . $this->library);
        }

        call_user_func([$this, $method]);

        return $this;
    }

    public function cropResize($new_width, $new_height = null)
    {
        $this->open();

        if (!$new_height)
        {
            $new_height = $new_width;
        }

        $source_aspect_ratio = $this->width / $this->height;
        $desired_aspect_ratio = $new_width / $new_height;

        if ($source_aspect_ratio > $desired_aspect_ratio)
        {
            $temp_height = $new_height;
            $temp_width = (int) ($new_height * $source_aspect_ratio);
        }
        else
        {
            $temp_width = $new_width;
            $temp_height = (int) ($new_width / $source_aspect_ratio);
        }

        return $this->resize($temp_width, $temp_height)->crop($new_width, $new_height);
    }

    public function getSupportedFormats(): array
    {
        return call_user_func([$this, $this->library . '_formats']);
    }

    public function save($destination, $format = null)
    {
        $this->open();

        $supported = call_user_func([$this, $this->library . '_formats']);

        if (is_null($format)) {
            $format = $this->format;
        }
        // Support for multiple output formats
        elseif (is_array($format)) {
            foreach ($format as $f) {
                if (null === $f) {
                    $format = $this->format;
                    break;
                }
                elseif (in_array($f, $supported)) {
                    $format = $f;
                    break;
                }
            }

            if (!is_string($format)) {
                throw new \InvalidArgumentException(sprintf('None of the specified formats %s can be saved by %s', implode(', ', $format), $this->library));
            }
        }

        if (!in_array($format, call_user_func([$this, $this->library . '_formats']))) {
            throw new \InvalidArgumentException('The specified format ' . $format . ' can not be used by ' . $this->library);
        }

        return call_user_func([$this, $this->library . '_save'], $destination, $format);
    }

    public function output($format = null, $return = false)
    {
        $this->open();

        if (is_null($format))
        {
            $format = $this->format;
        }

        if (!in_array($format, call_user_func([$this, $this->library . '_formats'])))
        {
            throw new \InvalidArgumentException('The specified format ' . $format . ' can not be used by ' . $this->library);
        }

        return call_user_func([$this, $this->library . '_output'], $format, $return);
    }

    public function format()
    {
        return $this->format;
    }

    protected function getCropGeometry($w, $h, $new_width, $new_height)
    {
        $proportion_src = $w / $h;
        $proportion_dst = $new_width / $new_height;

        $x = $y = 0;
        $out_w = $new_width;
        $out_h = $new_height;

        if ($proportion_src > $proportion_dst)
        {
            $out_w = $out_h * $proportion_dst;
            $x = round(($w - $out_w) / 2);
        }
        else
        {
            $out_h = $out_h / $proportion_dst;
            $y = round(($h - $out_h) / 2);
        }

        return [$x, $y, round($out_w), round($out_h)];
    }

    /**
     * Returns the format name from the MIME type
     * @param  string $type MIME type
     * @return Format: jpeg, gif, svg, etc.
     */
    public function getFormatFromType($type)
    {
        switch ($type)
        {
            // Special cases
            case 'image/svg+xml':   return 'svg';
            case 'application/pdf': return 'pdf';
            case 'image/vnd.adobe.photoshop': return 'psd';
            case 'image/x-icon': return 'bmp';
            case 'image/webp': return 'webp';
            default:
                if (preg_match('!^image/([\w\d]+)$!', $type, $match))
                {
                    return $match[1];
                }

                return false;
        }
    }

    static public function getLibrariesForFormat($format)
    {
        self::$init = true;
        $im = new Image;
        self::$init = false;

        $libraries = [];

        foreach ($im->libraries as $name => $enabled)
        {
            if (!$enabled)
            {
                continue;
            }

            if (in_array($format, call_user_func([$im, $name . '_formats'])))
            {
                $libraries[] = $name;
            }
        }

        return $libraries;
    }

    /**
     * Returns orientation of a JPEG file according to its EXIF tag
     * @link  http://magnushoff.com/jpeg-orientation.html See to interpret the orientation value
     * @return integer|boolean An integer between 1 and 8 or false if no orientation tag have been found
     */
    public function getOrientation()
    {
        if ($this->format != 'jpeg') {
            return false;
        }

        $file = fopen($this->path, 'rb');
        rewind($file);

        // Get length of file
        fseek($file, 0, SEEK_END);
        $length = ftell($file);
        rewind($file);

        $sign = 'n';

        if (fread($file, 2) !== "\xff\xd8")
        {
            return false;
        }

        while (!feof($file))
        {
            $marker = fread($file, 2);
            $l = fread($file, 2);

            if (strlen($marker) != 2 || strlen($l) != 2) {
                return false;
            }

            $info = unpack('nlength', $l);
            $section_length = $info['length'];

            if ($marker == "\xff\xe1")
            {
                if (fread($file, 6) != "Exif\x00\x00")
                {
                    return false;
                }

                if (fread($file, 2) == "\x49\x49")
                {
                    $sign = 'v';
                }

                fseek($file, 2, SEEK_CUR);

                $info = unpack(strtoupper($sign) . 'offset', fread($file, 4));
                fseek($file, $info['offset'] - 8, SEEK_CUR);

                $info = unpack($sign . 'tags', fread($file, 2));
                $tags = $info['tags'];

                for ($i = 0; $i < $tags; $i++)
                {
                    $info = unpack(sprintf('%stag', $sign), fread($file, 2));

                    if ($info['tag'] == 0x0112)
                    {
                        fseek($file, 6, SEEK_CUR);
                        $info = unpack(sprintf('%sorientation', $sign), fread($file, 2));
                        return $info['orientation'];
                    }
                    else
                    {
                        fseek($file, 10, SEEK_CUR);
                    }
                }
            }
            else if (is_numeric($marker) && $marker & 0xFF00 && $marker != "\xFF\x00")
            {
                break;
            }
            else
            {
                fseek($file, $section_length - 2, SEEK_CUR);
            }
        }

        return false;
    }

    // EPEG methods //////////////////////////////////////////////////////////
    protected function epeg_open()
    {
        $this->pointer = new \Epeg($this->path);
        $this->format = 'jpeg';
    }

    protected function epeg_formats()
    {
        return ['jpeg'];
    }

    protected function epeg_blob($data)
    {
        $this->pointer = \Epeg::openBuffer($data);
    }

    protected function epeg_size()
    {
        // Do nothing as it only returns the original size of the JPEG
        // not the resized size
        /*
        $size = $this->pointer->getSize();
        $this->width = $size[0];
        $this->height = $size[1];
        */
    }

    protected function epeg_close()
    {
        $this->pointer = null;
    }

    protected function epeg_save($destination, $format)
    {
        $this->pointer->setQuality($this->jpeg_quality);
        return $this->pointer->encode($destination);
    }

    protected function epeg_output($format, $return)
    {
        $this->pointer->setQuality($this->jpeg_quality);

        if ($return)
        {
            return $this->pointer->encode();
        }

        echo $this->pointer->encode();
        return true;
    }

    protected function epeg_crop($new_width, $new_height)
    {
        if (!method_exists($this->pointer, 'setDecodeBounds'))
        {
            throw new \RuntimeException('Crop is not supported by EPEG');
        }

        $x = floor(($this->width - $new_width) / 2);
        $y = floor(($this->height - $new_height) / 2);

        $this->pointer->setDecodeBounds($x, $y, $new_width, $new_height);
    }

    protected function epeg_resize($new_width, $new_height, $ignore_aspect_ratio)
    {
        if (!$ignore_aspect_ratio)
        {
            $in_ratio = $this->width / $this->height;

            $out_ratio = $new_width / $new_height;

            if ($in_ratio >= $out_ratio)
            {
                $new_height = $new_width / $in_ratio;
            }
            else
            {
                $new_width = $new_height * $in_ratio;
            }
        }

        $this->width = $new_width;
        $this->height = $new_height;

        $this->pointer->setDecodeSize($new_width, $new_height, true);
    }

    // Imagick methods ////////////////////////////////////////////////////////
    protected function imagick_open()
    {
        try {
            $this->pointer = new \Imagick($this->path);
        }
        catch (\ImagickException $e)
        {
            throw new \RuntimeException('Unable to open file: ' . $this->path, false, $e);
        }

        $this->format = strtolower($this->pointer->getImageFormat());
    }

    protected function imagick_formats()
    {
        return array_map('strtolower', (new \Imagick)->queryFormats());
    }

    protected function imagick_blob($data)
    {
        try {
            $this->pointer = new \Imagick;
            $this->pointer->readImageBlob($data);
        }
        catch (\ImagickException $e)
        {
            throw new \RuntimeException('Unable to open data string of length ' . strlen($data), false, $e);
        }

        $this->format = strtolower($this->pointer->getImageFormat());
    }

    protected function imagick_size()
    {
        $this->width = $this->pointer->getImageWidth();
        $this->height = $this->pointer->getImageHeight();
    }

    protected function imagick_close()
    {
        $this->pointer->destroy();
    }

    protected function imagick_save($destination, $format)
    {
        $this->pointer->setImageFormat($format);

        if ($format == 'png')
        {
            $this->pointer->setOption('png:compression-level', 9);
            $this->pointer->setImageCompression(\Imagick::COMPRESSION_LZW);
            $this->pointer->setImageCompressionQuality($this->compression * 10);
        }
        elseif ($format == 'jpeg')
        {
            $this->pointer->setImageCompression(\Imagick::COMPRESSION_JPEG);
            $this->pointer->setImageCompressionQuality($this->jpeg_quality);
            $this->pointer->setInterlaceScheme($this->progressive_jpeg ? \Imagick::INTERLACE_PLANE : \Imagick::INTERLACE_NO);
        }
        elseif ($format == 'webp') {
            $this->pointer->setImageCompressionQuality($this->webp_quality);
        }

        $this->pointer->stripImage();

        if ($format == 'gif' && $this->pointer->getNumberImages() > 1) {
            // writeImages is buggy in old versions of Imagick
            return file_put_contents($destination, $this->pointer->getImagesBlob());
        }
        else {
            return $this->pointer->writeImage($destination);
        }
    }

    protected function imagick_output($format, $return)
    {
        $this->pointer->setImageFormat($format);

        if ($format == 'png')
        {
            $this->pointer->setOption('png:compression-level', 9);
            $this->pointer->setImageCompression(\Imagick::COMPRESSION_LZW);
            $this->pointer->setImageCompressionQuality($this->compression * 10);
            $this->pointer->stripImage();
        }
        else if ($format == 'jpeg')
        {
            $this->pointer->setImageCompression(\Imagick::COMPRESSION_JPEG);
            $this->pointer->setImageCompressionQuality($this->jpeg_quality);
            $this->pointer->setInterlaceScheme($this->progressive_jpeg ? \Imagick::INTERLACE_PLANE : \Imagick::INTERLACE_NO);
        }
        elseif ($format == 'webp') {
            $this->pointer->setImageCompressionQuality($this->webp_quality);
        }

        if ($format == 'gif' && $this->pointer->getNumberImages() > 1) {
            $res = $this->pointer->getImagesBlob();
        }
        else {
            $res = (string) $this->pointer;
        }

        if ($return) {
            return $res;
        }

        echo $res;
        return true;
    }

    protected function imagick_crop($new_width, $new_height)
    {
        $src_x = floor(($this->width - $new_width) / 2);
        $src_y = floor(($this->height - $new_height) / 2);

        // Detect animated GIF
        if ($this->format == 'gif')
        {
            $this->pointer = $this->pointer->coalesceImages();

            do {
                $this->pointer->cropImage($new_width, $new_height, $src_x, $src_y);
                $this->pointer->setImagePage($new_width, $new_height, 0, 0);
            } while ($this->pointer->nextImage());

            $this->pointer = $this->pointer->deconstructImages();
        }
        else
        {
            $this->pointer->cropImage($new_width, $new_height, $src_x, $src_y);
            $this->pointer->setImagePage($new_width, $new_height, 0, 0);
        }
    }

    protected function imagick_resize($new_width, $new_height, $ignore_aspect_ratio = false)
    {
        // Detect animated GIF
        if ($this->format == 'gif' && $this->pointer->getNumberImages() > 1)
        {
            $image = $this->pointer->coalesceImages();

            foreach ($image as $frame)
            {
                $frame->thumbnailImage($new_width, $new_height, !$ignore_aspect_ratio);
                $frame->setImagePage($new_width, $new_height, 0, 0);
            }

            $this->pointer = $image->deconstructImages();
        }
        else
        {
            $this->pointer->resizeImage($new_width, $new_height, \Imagick::FILTER_CATROM, 1, !$ignore_aspect_ratio, false);
        }
    }

    protected function imagick_rotate($angle)
    {
        $pixel = new \ImagickPixel('#00000000');

        if ($this->format == 'gif' && $this->pointer->getNumberImages() > 1) {
            $image = $this->pointer->coalesceImages();

            foreach ($image as $frame) {
                $frame->rotateImage($pixel, $angle);
                $frame->setImageOrientation(\Imagick::ORIENTATION_UNDEFINED);
            }

            $this->pointer = $image->deconstructImages();
        }
        else {
            $this->pointer->rotateImage($pixel, $angle);
            $this->pointer->setImageOrientation(\Imagick::ORIENTATION_UNDEFINED);
        }
    }

    protected function imagick_flip()
    {
        if ($this->format == 'gif' && $this->pointer->getNumberImages() > 1) {
            $image = $this->pointer->coalesceImages();

            foreach ($image as $frame) {
                $frame->flopImage();
            }

            $this->pointer = $image->deconstructImages();
        }
        else {
            $this->pointer->flopImage();
        }
    }

    // GD methods /////////////////////////////////////////////////////////////
    protected function gd_open()
    {
        $this->pointer = call_user_func('imagecreatefrom' . $this->format, $this->path);

        if ($this->format == 'png' || $this->format == 'gif') {
            imagealphablending($this->pointer, false);
            imagesavealpha($this->pointer, true);
        }
    }

    protected function gd_formats()
    {
        $supported = imagetypes();
        $formats = [];

        if (\IMG_PNG & $supported)
            $formats[] = 'png';

        if (\IMG_GIF & $supported)
            $formats[] = 'gif';

        if (\IMG_JPEG & $supported)
            $formats[] = 'jpeg';

        if (\IMG_WBMP & $supported)
            $formats[] = 'wbmp';

        if (\IMG_XPM & $supported)
            $formats[] = 'xpm';

        if (function_exists('imagecreatefromwebp'))
            $formats[] = 'webp';

        return $formats;
    }

    protected function gd_blob($data)
    {
        $this->pointer = imagecreatefromstring($data);

        if ($this->format == 'png' || $this->format == 'gif') {
            imagealphablending($this->pointer, false);
            imagesavealpha($this->pointer, true);
        }
    }

    protected function gd_size()
    {
        $this->width = imagesx($this->pointer);
        $this->height = imagesy($this->pointer);
    }

    protected function gd_close()
    {
        return imagedestroy($this->pointer);
    }

    protected function gd_save($destination, $format)
    {
        if ($format == 'jpeg')
        {
            imageinterlace($this->pointer, (int)$this->progressive_jpeg);
        }

        switch ($format)
        {
            case 'png':
                return imagepng($this->pointer, $destination, $this->compression, PNG_NO_FILTER);
            case 'gif':
                return imagegif($this->pointer, $destination);
            case 'jpeg':
                return imagejpeg($this->pointer, $destination, $this->jpeg_quality);
            case 'webp':
                return imagewebp($this->pointer, $destination, $this->webp_quality);
            default:
                throw new \InvalidArgumentException('Image format ' . $format . ' is unknown.');
        }
    }

    protected function gd_output($format, $return)
    {
        if ($return)
        {
            ob_start();
        }

        $res = $this->gd_save(null, $format);

        if ($return)
        {
            return ob_get_clean();
        }

        return $res;
    }

    protected function gd_create($w, $h)
    {
        $new = imagecreatetruecolor((int)$w, (int)$h);

        if ($this->format == 'png' || $this->format == 'gif')
        {
            imagealphablending($new, false);
            imagesavealpha($new, true);
            imagefilledrectangle($new, 0, 0, (int)$w, (int)$h, imagecolorallocatealpha($new, 255, 255, 255, 127));
        }

        return $new;
    }

    protected function gd_crop($new_width, $new_height)
    {
        $new = $this->gd_create($new_width, $new_height);

        $src_x = floor(($this->width - $new_width) / 2);
        $src_y = floor(($this->height - $new_height) / 2);

        imagecopy($new, $this->pointer, 0, 0, $src_x, $src_y, (int)$new_width, (int)$new_height);
        imagedestroy($this->pointer);
        $this->pointer = $new;
    }

    protected function gd_resize($new_width, $new_height, $ignore_aspect_ratio)
    {
        if (!$ignore_aspect_ratio)
        {
            $in_ratio = $this->width / $this->height;

            $out_ratio = $new_width / $new_height;

            if ($in_ratio >= $out_ratio)
            {
                $new_height = $new_width / $in_ratio;
            }
            else
            {
                $new_width = $new_height * $in_ratio;
            }
        }

        $new = $this->gd_create((int)$new_width, (int)$new_height);

        if ($this->use_gd_fast_resize_trick)
        {
            $this->gd_fastimagecopyresampled($new, $this->pointer, 0, 0, 0, 0, (int)$new_width, (int)$new_height, $this->width, $this->height, 2);
        }
        else
        {
            imagecopyresampled($new, $this->pointer, 0, 0, 0, 0, (int)$new_width, (int)$new_height, $this->width, $this->height);
        }

        imagedestroy($this->pointer);
        $this->pointer = $new;
    }

    protected function gd_flip()
    {
        imageflip($this->pointer, IMG_FLIP_HORIZONTAL);
    }

    protected function gd_rotate($angle)
    {
        // GD is using counterclockwise
        $angle = -($angle);

        $this->pointer = imagerotate($this->pointer, (int)$angle, 0);
    }

    protected function gd_fastimagecopyresampled(&$dst_image, $src_image, $dst_x, $dst_y, $src_x, $src_y, $dst_w, $dst_h, $src_w, $src_h, $quality = 3)
    {
        // Plug-and-Play fastimagecopyresampled function replaces much slower imagecopyresampled.
        // Just include this function and change all "imagecopyresampled" references to "fastimagecopyresampled".
        // Typically from 30 to 60 times faster when reducing high resolution images down to thumbnail size using the default quality setting.
        // Author: Tim Eckel - Date: 09/07/07 - Version: 1.1 - Project: FreeRingers.net - Freely distributable - These comments must remain.
        //
        // Optional "quality" parameter (defaults is 3). Fractional values are allowed, for example 1.5. Must be greater than zero.
        // Between 0 and 1 = Fast, but mosaic results, closer to 0 increases the mosaic effect.
        // 1 = Up to 350 times faster. Poor results, looks very similar to imagecopyresized.
        // 2 = Up to 95 times faster.  Images appear a little sharp, some prefer this over a quality of 3.
        // 3 = Up to 60 times faster.  Will give high quality smooth results very close to imagecopyresampled, just faster.
        // 4 = Up to 25 times faster.  Almost identical to imagecopyresampled for most images.
        // 5 = No speedup. Just uses imagecopyresampled, no advantage over imagecopyresampled.

        if (empty($src_image) || empty($dst_image) || $quality <= 0)
        {
            return false;
        }

        if ($quality < 5 && (($dst_w * $quality) < $src_w || ($dst_h * $quality) < $src_h))
        {
            $temp = imagecreatetruecolor(intval($dst_w * $quality + 1), intval($dst_h * $quality + 1));
            imagecopyresized($temp, $src_image, 0, 0, (int)$src_x, (int)$src_y, intval($dst_w * $quality + 1), intval($dst_h * $quality + 1), (int)$src_w, (int)$src_h);
            imagecopyresampled($dst_image, $temp, (int)$dst_x, (int)$dst_y, 0, 0, (int)$dst_w, (int)$dst_h, intval($dst_w * $quality), intval($dst_h * $quality));
            imagedestroy($temp);
        }
        else
        {
            imagecopyresampled($dst_image, $src_image, (int) $dst_x, (int) $dst_y, (int) $src_x, (int) $src_y, (int) $dst_w, (int) $dst_h, (int) $src_w, (int) $src_h);
        }

        return true;
    }
}

?>
<?php
/*
	This file is part of KD2FW -- <http://dev.kd2.org/>

	Copyright (c) 2001-2019 BohwaZ <http://bohwaz.net/>
	All rights reserved.

	KD2FW is free software: you can redistribute it and/or modify
	it under the terms of the GNU Affero General Public License as published by
	the Free Software Foundation, either version 3 of the License, or
	(at your option) any later version.

	Foobar is distributed in the hope that it will be useful,
	but WITHOUT ANY WARRANTY; without even the implied warranty of
	MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
	GNU Affero General Public License for more details.

	You should have received a copy of the GNU Affero General Public License
	along with Foobar.  If not, see <https://www.gnu.org/licenses/>.
*/

/**
 * Very simple ZIP Archive writer
 *
 * for specs see http://www.pkware.com/appnote
 * Inspired by https://github.com/splitbrain/php-archive/blob/master/src/Zip.php
 */
class ZipWriter
{
	protected $compression = 0;
	protected $pos = 0;
	protected $handle;
	protected $directory = [];
	protected $closed = false;

	/**
	 * Create a new ZIP file
	 *
	 * @param string $file
	 * @throws RuntimeException
	 */
	public function __construct($file)
	{
		$this->handle = fopen($file, 'wb');

		if (!$this->handle)
		{
			throw new RuntimeException('Could not open ZIP file for writing: ' . $file);
		}
	}

	/**
	 * Sets compression rate (0 = no compression)
	 *
	 * @param integer $compression 0 to 9
	 * @return void
	 */
	public function setCompression(int $compression): void
	{
		$compression = (int) $compression;
		$this->compression = max(min($compression, 9), 0);
	}

	/**
	 * Write to the current ZIP file
	 * @param string $data
	 * @return void
	 */
	protected function write(string $data): void
	{
		// We can't use fwrite and ftell directly as ftell doesn't work on some pointers
		// (eg. php://output)
		fwrite($this->handle, $data);
		$this->pos += strlen($data);
	}

	/**
	 * Returns the content of the ZIP file
	 *
	 * @return string
	 */
	public function get(): string
	{
		fseek($this->handle, 0);
		return stream_get_contents($this->handle);
	}

	public function __destruct()
	{
		$this->close();
	}

	/**
	 * Add a file to the current Zip archive using the given $data as content
	 *
	 * @param string $file File name
	 * @param string|null $data binary content of the file to add
	 * @param string|null $source Source file to use if no data is supplied
	 * @throws LogicException
	 * @throws RuntimeException
	 */
	public function add(string $file, ?string $data = null, ?string $source = null): void
	{
		if ($this->closed)
		{
			throw new LogicException('Archive has been closed, files can no longer be added');
		}

		if (null === $data && null === $source) {
			throw new LogicException('No source file or data has been supplied');
		}

		$source_handle = null;

		if ($data === null)
		{
			$csize = $size = filesize($source);
			list(, $crc) = unpack('N', hash_file('crc32b', $source, true));
			$source_handle = fopen($source, 'r');

			if ($this->compression)
			{
				// Unfortunately it's not possible to use stream_filter_append
				// to compress data on the fly, as it's not working correctly
				// with php://output, php://temp and php://memory streams
				throw new RuntimeException('Compression is not supported with external files');
			}
		}
		else
		{
			$size = strlen($data);
			$crc  = crc32($data);

			if ($this->compression)
			{
				// Compress data
				$data = gzdeflate($data, $this->compression);
			}

			$csize  = strlen($data);
		}

		$offset = $this->pos;

		// write local file header
		$this->write($this->makeRecord(false, $file, $size, $csize, $crc, null));

		// we store no encryption header

		// Store uncompressed external file
		if ($source_handle)
		{
			$this->pos += stream_copy_to_stream($source_handle, $this->handle);
			fclose($source_handle);
		}
		// Store compressed or uncompressed file
		// that was supplied
		else
		{
			// write data
			$this->write($data);
		}

		// we store no data descriptor

		// add info to central file directory
		$this->directory[] = $this->makeRecord(true, $file, $size, $csize, $crc, $offset);
	}

	/**
	 * Add the closing footer to the archive
	 * @throws LogicException
	 */
	public function finalize(): void
	{
		if ($this->closed)
		{
			throw new LogicException('The ZIP archive has been closed. Files can no longer be added.');
		}

		// write central directory
		$offset = $this->pos;
		$directory = implode('', $this->directory);
		$this->write($directory);

		$end_record = "\x50\x4b\x05\x06" // end of central dir signature
			. "\x00\x00" // number of this disk
			. "\x00\x00" // number of the disk with the start of the central directory
			. pack('v', count($this->directory)) // total number of entries in the central directory on this disk
			. pack('v', count($this->directory)) // total number of entries in the central directory
			. pack('V', strlen($directory)) // size of the central directory
			. pack('V', $offset) // offset of start of central directory with respect to the starting disk number
			. "\x00\x00"; // .ZIP file comment length
		$this->write($end_record);

		$this->directory = [];
		$this->closed = true;
	}

	/**
	 * Close the file handle
	 * @return void
	 */
	public function close(): void
	{
		if (!$this->closed)
		{
			$this->finalize();
		}

		if ($this->handle)
		{
			fclose($this->handle);
		}

		$this->handle = null;
	}

	/**
	 * Creates a record, local or central
	 * @param  boolean $central  TRUE for a central file record, FALSE for a local file header
	 * @param  string  $filename File name
	 * @param  integer $size     File size
	 * @param  integer $compressed_size
	 * @param  string  $crc      CRC32 of the file contents
	 * @param  integer|null  $offset
	 * @return string
	 */
	protected function makeRecord(bool $central, string $filename, int $size, int $compressed_size, string $crc, ?int $offset): string
	{
		$header = ($central ? "\x50\x4b\x01\x02\x0e\x00" : "\x50\x4b\x03\x04");

		list($filename, $extra) = $this->encodeFilename($filename);

		$header .=
			"\x14\x00" // version needed to extract - 2.0
			. "\x00\x08" // general purpose flag - bit 11 set = enable UTF-8 support
			. ($this->compression ? "\x08\x00" : "\x00\x00") // compression method - none
			. "\x01\x80\xe7\x4c" //  last mod file time and date
			. pack('V', $crc) // crc-32
			. pack('V', $compressed_size) // compressed size
			. pack('V', $size) // uncompressed size
			. pack('v', strlen($filename)) // file name length
			. pack('v', strlen($extra)); // extra field length

		if ($central)
		{
			$header .=
				"\x00\x00" // file comment length
				. "\x00\x00" // disk number start
				. "\x00\x00" // internal file attributes
				. "\x00\x00\x00\x00" // external file attributes  @todo was 0x32!?
				. pack('V', $offset); // relative offset of local header
		}

		$header .= $filename;
		$header .= $extra;

		return $header;
	}

	protected function encodeFilename(string $original): array
	{
		// For epub/opendocument files
		if (!preg_match('//u', $original) || $original == 'mimetype') {
			return [$original, ''];
		}

		$data = "\x01" // version
			. pack('V', crc32($original))
			. $original;

		return [
			$original,
			"\x70\x75" // tag
			. pack('v', strlen($data)) // length of data
			. $data
		];
	}
}

?>
<?php
/**
    Fotoo Hosting
    Copyright 2010-2012 BohwaZ - http://dev.kd2.org/
    Licensed under the GNU AGPLv3

    This software is free software: you can redistribute it and/or modify
    it under the terms of the GNU Affero General Public License as published by
    the Free Software Foundation, either version 3 of the License, or
    (at your option) any later version.

    This software is distributed in the hope that it will be useful,
    but WITHOUT ANY WARRANTY; without even the implied warranty of
    MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
    GNU General Public License for more details.

    You should have received a copy of the GNU Affero General Public License
    along with this software. If not, see <http://www.gnu.org/licenses/>.
*/

error_reporting(E_ALL);

if (!version_compare(phpversion(), '5.3', '>='))
{
    die("You need at least PHP 5.2 to use this application.");
}

if (!class_exists('SQLite3'))
{
    die("You need PHP SQLite3 extension to use this application.");
}

define('UPLOAD_ERR_INVALID_IMAGE', 42);

class FotooException extends Exception {}

function exception_error_handler($errno, $errstr, $errfile, $errline )
{
    return;
    // For @ ignored errors
    if (error_reporting() & $errno) return;
    throw new ErrorException($errstr, 0, $errno, $errfile, $errline);
}

set_error_handler("exception_error_handler");

//require_once __DIR__ . '/ZipWriter.php';

class Fotoo_Hosting_Config
{
    private $db_file = null;
    private $storage_path = null;

    private $base_url = null;
    private $storage_url = null;
    private $image_page_url = null;
    private $album_page_url = null;

    private $max_width = null;

    private $thumb_width = null;

    private $title = null;

    private $max_file_size = null;
    private $allow_upload = null;
    private $allow_album_zip = null;
    private $nb_pictures_by_page = null;

    private $admin_password = null;
    private $banned_ips = null;
    private $ip_storage_expiration = null;

    public function __set($key, $value)
    {
        switch ($key)
        {
            case 'max_width':
            case 'thumb_width':
            case 'max_file_size':
            case 'nb_pictures_by_page':
            case 'ip_storage_expiration':
                $this->$key = (int) $value;
                break;
            case 'db_file':
            case 'storage_path':
            case 'base_url':
            case 'storage_url':
            case 'title':
            case 'image_page_url':
            case 'album_page_url':
            case 'admin_password':
                $this->$key = (string) $value;
                break;
            case 'banned_ips':
                $this->$key = (array) $value;
                break;
            case 'allow_upload':
            case 'allow_album_zip':
                $this->$key = is_bool($value) ? (bool) $value : $value;
                break;
            case 'allowed_formats':
                if (is_string($value))
                {
                    $value = explode(',', strtoupper(str_replace(' ', '', $value)));
                }
                else
                {
                    $value = (array) $value;
                }

                // If Imagick is not present then we can't process images different than JPEG, GIF and PNG
                foreach ($value as $f=>$format)
                {
                    $format = strtoupper($format);
                    static $base_support = ['png', 'jpeg', 'gif', 'webp'];

                    if (!in_array($format, $base_support) && !class_exists('Imagick'))
                    {
                        unset($value[$f]);
                    }
                }

                $this->$key = $value;

                break;
            default:
                throw new FotooException("Unknown configuration property $key");
        }
    }

    public function __get($key)
    {
        if (isset($this->$key))
            return $this->$key;
        else
            throw new FotooException("Unknown configuration property $key");
    }

    public function exportJSON()
    {
        $vars = get_object_vars($this);

        unset($vars['db_file']);
        unset($vars['storage_path']);
        unset($vars['admin_password']);

        return json_encode($vars);
    }

    public function __construct()
    {
        // Defaults
        $this->db_file = dirname(__FILE__) . '/datas.db';
        $this->storage_path = dirname(__FILE__) . '/i/';
        $proto = !empty($_SERVER['HTTPS']) ? 'https' : 'http';
        $this->base_url = $proto . '://'. $_SERVER['HTTP_HOST'] . dirname($_SERVER['PHP_SELF']);

        if ($this->base_url[strlen($this->base_url) - 1] != '/')
            $this->base_url .= '/';

        $this->storage_url = $this->base_url . str_replace(dirname(__FILE__) . '/', '', $this->storage_path);
        $this->image_page_url = $this->base_url . '?';
        $this->album_page_url = $this->base_url . '?a=';

        if (substr(basename($_SERVER['PHP_SELF']), 0, 5) != 'index')
            $this->base_url .= basename($_SERVER['PHP_SELF']);

        $this->max_width = 1920;
        $this->thumb_width = 320;

        $this->title = 'Fotoo Image Hosting service';

        $size = self::return_bytes(ini_get('upload_max_filesize'));
        $post = self::return_bytes(ini_get('post_max_size'));

        if ($post < $size)
            $size = $post;

        $memory = self::return_bytes(ini_get('memory_limit'));

        if ($memory > 0 && $memory < $size)
            $size = $memory;

        $this->max_file_size = $size;
        $this->allow_upload = true;
        $this->allow_album_zip = false;
        $this->admin_password = 'fotoo';
        $this->banned_ips = [];
        $this->ip_storage_expiration = 366;
        $this->nb_pictures_by_page = 20;

        $this->allowed_formats = ['png', 'jpeg', 'gif', 'svg', 'webp'];
    }

    static public function return_bytes ($size_str)
    {
        switch (substr($size_str, -1))
        {
            case 'G': case 'g': return (int)$size_str * pow(1024, 3);
            case 'M': case 'm': return (int)$size_str * pow(1024, 2);
            case 'K': case 'k': return (int)$size_str * 1024;
            default: return $size_str;
        }
    }
}

function escape($str)
{
    return htmlspecialchars($str, ENT_QUOTES, 'utf-8', false);
}

//require_once __DIR__ . '/class.fotoo_hosting.php';
//require_once __DIR__ . '/class.image.php';

$config = new Fotoo_Hosting_Config;

$config_file = __DIR__ . '/config.php';

if (file_exists($config_file))
{
    require $config_file;
}

// Check upload access
if (!is_bool($config->allow_upload) && is_callable($config->allow_upload))
{
    $config->allow_upload = (bool) call_user_func($config->allow_upload);
}

$fh = new Fotoo_Hosting($config);

if ($fh->isClientBanned())
{
    $fh->setBanCookie();
}

if (!empty($_GET['delete']))
{
    $id = !empty($_GET['c']) ? trim($_GET['c']) : false;

    if ($fh->remove($_GET['delete'], $id))
    {
        header('Location: '.$config->base_url.'?list');
    }
    else
    {
        echo "Can't delete picture";
    }

    exit;
}
elseif (!empty($_GET['deleteAlbum']))
{
    $id = !empty($_GET['c']) ? trim($_GET['c']) : false;

    if ($fh->removeAlbum($_GET['deleteAlbum'], $id))
    {
        header('Location: ' . $config->base_url . '?list');
    }
    else
    {
        echo "Can't delete album";
    }

    exit;
}
elseif (!empty($_POST['delete']) && $fh->logged())
{
    foreach ($_POST['pictures'] as $pic)
    {
        $fh->remove($pic, null);
    }

    foreach ($_POST['albums'] as $album)
    {
        $fh->removeAlbum($album, null);
    }

    header('Location: ' . $config->base_url . '?list');
    exit;
}

if (isset($_GET['upload'], $_POST['album']) && $_POST['album'] === 'new') {
    if (empty($_POST['title'])) {
        http_response_code(400);
        die("Bad Request");
    }

    try {
        $hash = $fh->createAlbum($_POST['title'], empty($_POST['private']) ? false : true);
        $key = $fh->makeRemoveId($hash);
        http_response_code(200);
        echo json_encode(compact('hash', 'key'));
        exit;
    }
    catch (FotooException $e) {
        http_response_code(400);
        die("Upload not permitted.");
    }
}
elseif (isset($_GET['upload'], $_POST['album'])) {
    if (!$fh->checkRemoveId($_POST['album'], $_POST['key'])) {
        http_response_code(401);
        die("Invalid key");
    }

    if (empty($_POST['content']) || !isset($_POST['name'], $_POST['filename'])) {
        http_response_code(400);
        die("Wrong Request");
    }

    try {
        $url = $fh->appendToAlbum($_POST['album'], $_POST['name'], ['content' => $_POST['content'], 'name' => $_POST['filename']]);
        http_response_code(201);
    }
    catch (FotooException $e) {
        http_response_code(400);
        echo $e->getMessage();
    }

    exit;
}
// Single image upload, no album
elseif (isset($_GET['upload'], $_POST['content'], $_POST['filename'], $_POST['name'], $_POST['private'])) {
    try {
        $url = $fh->upload([
            'content' => $_POST['content'],
            'name' => $_POST['filename']
        ], $_POST['name'], $_POST['private']);

        http_response_code(200);
        echo $url;
    }
    catch (FotooException $e) {
        http_response_code(400);
        echo $e->getMessage();
    }

    exit;
}

if (isset($_GET['upload']))
{
    $error = false;

    if (empty($_FILES['upload']) && empty($_POST['upload']))
    {
        $error = UPLOAD_ERR_INI_SIZE;
    }
    else
    {
        try {
            $url = $fh->upload(!empty($_FILES['upload']) ? $_FILES['upload'] : $_POST['upload'],
                isset($_POST['name']) ? trim($_POST['name']) : '',
                isset($_POST['private']) ? (bool) $_POST['private'] : false
            );
        }
        catch (FotooException $e)
        {
            if ($e->getCode())
                $error = $e->getCode();
            else
                throw $e;
        }
    }

    if ($error)
    {
        $url = $config->base_url . '?error=' . $error;
        header('Location: ' . $url);
        exit;
    }
    else
    {
        header('Location: ' . $url);
        exit;
    }
}

$html = $title = '';

if (isset($_GET['logout']))
{
    $fh->logout();
    header('Location: ' . $config->base_url);
    exit;
}
elseif (isset($_GET['login']))
{
    $title = 'Login';
    $error = '';

    if (!empty($_POST['password']))
    {
        if ($fh->login(trim($_POST['password'])))
        {
            header('Location: ' . $config->base_url);
            exit;
        }
        else
        {
            $error = '<p class="error">Wrong password.</p>';
        }
    }

    $html = '
        <article class="browse">
            <h2>'.$title.'</h2>
            '.$error.'
            <form method="post" action="' . $config->base_url . '?login">
            <fieldset>
                <dl>
                    <dt><label for="f_password">Password</label></dt>
                    <dd><input type="password" name="password" id="f_password" /></dd>
                </dl>
            </fieldset>
            <p class="submit">
                <input type="submit" id="f_submit" value="Login" />
            </p>
            </form>
        </article>
    ';
}
elseif (isset($_GET['list']))
{
    $title = 'Browse images';

    if (!empty($_GET['list']) && is_numeric($_GET['list']))
        $page = (int) $_GET['list'];
    else
        $page = 1;

    $list = $fh->getList($page);
    $max = $fh->countList();

    $html = '';

    if ($fh->logged())
    {
        $html .= '<form method="post" action="" onsubmit="return confirm(\'Delete all the checked pictures and albums?\');">
        <p class="admin">
            <input type="button" value="Check / uncheck all" onclick="var l = this.form.querySelectorAll(\'input[type=checkbox]\'), s = l[0].checked; for (var i = 0; i < l.length; i++) { l[i].checked = s ? false : true; }" />
        </p>';
    }

    $html .= '
        <article class="browse">
            <h2>'.$title.'</h2>';

    foreach ($list as $img)
    {
        $thumb_url = $fh->getImageThumbUrl($img);

        if ($img['album']) {
            $url = $config->album_page_url . rawurlencode($img['album']);
            $html .= sprintf(
                '<figure>
                    <a href="%s">%s<span class="count"><b>%d</b> images</span><img src="%s" alt="%s" /></a>
                    <figcaption><a href="%1$s">%5$s</b></a></figcaption>
                    %s
                </figure>',
                escape($url),
                $img['private'] ? '<span class="private">Private</span>' : '',
                $img['count'],
                $thumb_url,
                escape($img['title']),
                !$fh->logged() ? '' : '<label><input type="checkbox" name="albums[]" value="' . escape($img['album']) . '" /> Delete</label>'
            );
        }
        else {
            $url = $fh->getUrl($img);
            $html .= sprintf(
                '<figure>
                    <a href="%s">%s<img src="%s" alt="%s" /></a>
                    <figcaption><a href="%1$s">%4$s</a></figcaption>
                    %s
                </figure>',
                escape($url),
                $img['private'] ? '<span class="private">Private</span>' : '',
                $thumb_url,
                escape(preg_replace('![_-]!', ' ', $img['filename'])),
                !$fh->logged() ? '' : '<label><input type="checkbox" name="pictures[]" value="' . escape($img['hash']) . '" /> Delete</label>'
            );
        }
    }

    $html .= '
        </article>';


    if ($fh->logged())
    {
        $html .= '
        <p class="admin submit">
            <input type="submit" name="delete" value="Delete checked pictures and albums" />
        </p>
        </form>';
    }

    if ($max > $config->nb_pictures_by_page)
    {
        $max_page = ceil($max / $config->nb_pictures_by_page);
        $html .= '
        <nav class="pagination">
            <ul>
        ';

        for ($p = 1; $p <= $max_page; $p++)
        {
            $html .= '<li'.($page == $p ? ' class="selected"' : '').'><a href="?list='.$p.'">'.$p.'</a></li>';
        }

        $html .= '
            </ul>
        </nav>';
    }
}
elseif (!empty($_GET['a']))
{
    $album = $fh->getAlbum($_GET['a']);

    if (empty($album))
    {
        header('HTTP/1.1 404 Not Found', true, 404);
        echo '
            <h1>Not Found</h1>
            <p><a href="'.$config->base_url.'">'.$config->title.'</a></p>
        ';
        exit;
    }

    if (!empty($_POST['download']) && $config->allow_album_zip) {
        $fh->downloadAlbum($album['hash']);
        exit;
    }

    $title = $album['title'];

    if (!empty($_GET['p']) && is_numeric($_GET['p']))
        $page = (int) $_GET['p'];
    else
        $page = 1;

    $list = $fh->getAlbumPictures($album['hash'], $page);
    $max = $fh->countAlbumPictures($album['hash']);

    $bbcode = '[b][url=' . $config->album_page_url . $album['hash'] . ']' . $album['title'] . "[/url][/b]\n";

    foreach ($fh->getAllAlbumPictures($album['hash']) as $img)
    {
        $label = $img['filename'] ? escape(preg_replace('![_-]!', ' ', $img['filename'])) : 'View image';
        $bbcode .= '[url='.$fh->getImageUrl($img).'][img]'.$fh->getImageThumbUrl($img)."[/img][/url] ";
    }

    $html = '
        <script type="text/javascript">
        var copy = (e, c) => {
            if (typeof e === \'string\') {
                e = document.querySelector(e);
            }

            e.select();
            e.setSelectionRange(0, e.value.length);
            navigator.clipboard.writeText(e.value);

            if (!c) {
                return;
            }

            c.value = \'Copied!\';
            window.setTimeout(() => c.value = \'Copy\', 5000);
        };
        </script>
        <article class="browse">
            <h2>'.escape($title).'</h2>
            <p class="info">
                Uploaded on <time datetime="'.date(DATE_W3C, $album['date']).'">'.@strftime('%c', $album['date']).'</time>
                | '.(int)$max.' picture'.((int)$max > 1 ? 's' : '').'
            </p>
            <aside class="examples">
                <dt>Share this album using this URL: <input type="button" onclick="copy(\'#url\', this);" value="Copy" /></dt>
                <dd><input type="text" id="url" onclick="this.select();" value="'.escape($config->album_page_url . $album['hash']).'" /></dd>
                <dt>All pictures for a forum (BBCode): <input type="button" onclick="copy(\'#all\', this);" value="Copy" /></dt>
                <dd><textarea id="all" cols="70" rows="3" onclick="this.select(); this.setSelectionRange(0, this.value.length); navigator.clipboard.writeText(this.value);">'.escape($bbcode).'</textarea></dd>
                <dd></dd>
            </aside>';

    if ($config->allow_album_zip) {
        $html .= '
        <form method="post" action="">
        <p>
            <input type="submit" name="download" value="Download full album as a ZIP file" />
        </p>
        </form>';
    }

    if ($fh->logged())
    {
        $html .= '
        <p class="admin">
            <a href="?deleteAlbum='.rawurlencode($album['hash']).'" onclick="return confirm(\'Really?\');">Delete album</a>
        </p>';
    }
    elseif (!empty($_GET['c']))
    {
        $url = $config->album_page_url . $album['hash'] 
            . (strpos($config->album_page_url, '?') !== false ? '&c=' : '?c=') 
            . $fh->makeRemoveId($album['hash']);

        $html .= '
        <p class="admin">
            <a href="?deleteAlbum='.rawurlencode($album['hash']).'&amp;c='.rawurldecode($_GET['c']).'" onclick="return confirm(\'Really?\');">Delete album</a>
        </p>
        <p class="admin">
            Keep this URL in your favorites to be able to delete this album later:<br />
            <input type="text" onclick="this.select();" value="'.escape($url).'" />
        </p>';
    }

    foreach ($list as &$img)
    {
        $thumb_url = $fh->getImageThumbUrl($img);
        $url = $fh->getUrl($img);

        $label = $img['filename'] ? escape(preg_replace('![_-]!', ' ', $img['filename'])) : 'View image';

        $html .= '
        <figure>
            <a href="'.$url.'">'.($img['private'] ? '<span class="private">Private</span>' : '').'<img src="'.$thumb_url.'" alt="'.$label.'" /></a>
            <figcaption><a href="'.$url.'">'.$label.'</a></figcaption>
        </figure>';
    }

    $html .= '
        </article>';

    if ($max > $config->nb_pictures_by_page)
    {
        $max_page = ceil($max / $config->nb_pictures_by_page);
        $html .= '
        <nav class="pagination">
            <ul>
        ';

        $url = $config->album_page_url . $album['hash'] . ((strpos($config->album_page_url, '?') === false) ? '?p=' : '&amp;p=');

        for ($p = 1; $p <= $max_page; $p++)
        {
            $html .= '<li'.($page == $p ? ' class="selected"' : '').'><a href="'.$url.$p.'">'.$p.'</a></li>';
        }

        $html .= '
            </ul>
        </nav>';
    }
}
elseif (!isset($_GET['album']) && !isset($_GET['error']) && !empty($_SERVER['QUERY_STRING']))
{
    $query = explode('.', $_SERVER['QUERY_STRING']);
    $hash = ($query[0] == 'r') ? $query[1] : $query[0];
    $img = $fh->get($hash);

    if (empty($img))
    {
        header('HTTP/1.1 404 Not Found', true, 404);
        echo '
            <h1>Not Found</h1>
            <p><a href="'.$config->base_url.'">'.$config->title.'</a></p>
        ';
        exit;
    }

    $img_url = $fh->getImageUrl($img);

    if ($query[0] == 'r')
    {
        header('Location: '.$img_url);
        exit;
    }

    $url = $fh->getUrl($img);
    $thumb_url = $fh->getImageThumbUrl($img);
    $short_url = $fh->getShortImageUrl($img);
    $title = $img['filename'] ? $img['filename'] : 'Image';

    // Short URL auto discovery
    header('Link: <'.$short_url.'>; rel=shorturl');

    $bbcode = '[url='.$img_url.'][img]'.$thumb_url.'[/img][/url]';
    $html_code = '<a href="'.$img_url.'"><img src="'.$thumb_url.'" alt="'.(trim($img['filename']) ? $img['filename'] : '').'" /></a>';

    $size = $img['size'];
    if ($size > (1024 * 1024))
        $size = round($size / 1024 / 1024, 2) . ' MB';
    elseif ($size > 1024)
        $size = round($size / 1024, 2) . ' KB';
    else
        $size = $size . ' B';

    $html = '
    <article class="picture">
        <header>
            '.(trim($img['filename']) ? '<h2>' . escape(strtr($img['filename'], '-_.', '   ')) . '</h2>' : '').'
            <p class="info">
                Uploaded on <time datetime="'.date(DATE_W3C, $img['date']).'">'.@strftime('%c', $img['date']).'</time>
                | Size: '.$img['width'].' × '.$img['height'].'
            </p>
        </header>
        <figure>
            <a href="'.$img_url.'">'.($img['private'] ? '<span class="private">Private</span>' : '').'<img src="'.$thumb_url.'" alt="'.escape($title).'" /></a>
        </figure>
        <footer>
            <p>
                <a href="'.$img_url.'">View full size ('.strtoupper($img['format']).', '.$size.')</a>
            </p>
        </footer>';

    if (!empty($img['album']))
    {
        $prev = $fh->getAlbumPrevNext($img['album'], $img['hash'], -1);
        $next = $fh->getAlbumPrevNext($img['album'], $img['hash'], 1);
        $album = $fh->getAlbum($img['album']);

        $html .= '
        <footer class="context">';

        if ($prev)
        {
            $thumb_url = $fh->getImageThumbUrl($prev);
            $url = $fh->getUrl($prev);
            $label = $prev['filename'] ? escape(preg_replace('![_-]!', ' ', $prev['filename'])) : 'View image';

            $html .= '
            <figure class="prev">
                <a href="'.$url.'"><b>&larr;</b><img src="'.$thumb_url.'" alt="'.$label.'" /></a>
                <figcaption><a href="'.$url.'">'.$label.'</a></figcaption>
            </figure>';
        }
        else
        {
            $html .= '<figure class="prev"><b>…</b></figure>';
        }

        $html .= '
            <figure>
                <h3>Album:</h3>
                <h2><a href="' . $config->album_page_url . $album['hash'] . '"> ' . escape($album['title']) .'</a></h2></figure
            </figure>';

        if ($next)
        {
            $thumb_url = $fh->getImageThumbUrl($next);
            $url = $fh->getUrl($next);
            $label = $next['filename'] ? escape(preg_replace('![_-]!', ' ', $next['filename'])) : 'View image';

            $html .= '
            <figure class="prev">
                <a href="'.$url.'"><img src="'.$thumb_url.'" alt="'.$label.'" /><b>&rarr;</b></a>
                <figcaption><a href="'.$url.'">'.$label.'</a></figcaption>
            </figure>';
        }
        else
        {
            $html .= '<figure class="next"><b>…</b></figure>';
        }

        $html .= '
            </footer>';
    }

    if ($fh->logged())
    {
        $html .= '
        <p class="admin">
            IP address: ' . escape(is_null($img['ip']) ? 'Not available' : ($img['ip'] == 'R' ? 'Automatically removed from database' : $img['ip'])) . '
        </p>
        <p class="admin">
            <a href="?delete='.rawurlencode($img['hash']).'" onclick="return confirm(\'Really?\');">Delete picture</a>
        </p>';
    }
    elseif (!empty($_GET['c']))
    {
        $html .= '
        <p class="admin">
            <a href="?delete='.rawurlencode($img['hash']).'&amp;c='.rawurldecode($_GET['c']).'" onclick="return confirm(\'Really?\');">Delete picture</a>
        </p>
        <p class="admin">
            Keep this URL in your favorites to be able to delete this picture later:<br />
            <input type="text" onclick="this.select();" value="'.$fh->getUrl($img, true).'" />
        </p>';
    }

    $html .= '
        <aside class="examples">
            <dt>Short URL for full size <input type="button" onclick="copy(\'#url\', this);" value="Copy" /></dt>
            <dd><input type="text" onclick="this.select();" value="'.escape($short_url).'" id="url" /></dd>
            <dt>BBCode <input type="button" onclick="copy(\'#bbcode\', this);" value="Copy" /></dt>
            <dd><textarea cols="70" rows="3" onclick="this.select();" id="bbcode">'.escape($bbcode).'</textarea></dd>
            <dt>HTML code <input type="button" onclick="copy(\'#html\', this);" value="Copy" /></dt>
            <dd><textarea cols="70" rows="3" onclick="this.select();" id="html">'.escape($html_code).'</textarea></dd>
        </aside>
    </article>
    ';
}
elseif (!$config->allow_upload)
{
    $html = '<p class="error">Uploading is not allowed.</p>';
}
else
{
    $js_url = file_exists(__DIR__ . '/upload.js')
        ? $config->base_url . 'upload.js?2023'
        : $config->base_url . '?js&2023';

    $html = '
        <script type="text/javascript">
        var config = '.$config->exportJSON().';
        </script>';

    if (!empty($_GET['error']))
    {
        $html .= '<p class="error">'.escape(Fotoo_Hosting::getErrorMessage($_GET['error'])).'</p>';
    }

    $max_file_size = $config->max_file_size - 1024;
    $max_file_size_human = round($config->max_file_size / 1024 / 1024, 2);
    $formats = implode(', ', array_map('strtoupper', $config->allowed_formats));

    $html .= <<<EOF
    <form method="post" enctype="multipart/form-data" action="{$config->base_url}?upload" id="f_upload">
    <input type="hidden" name="MAX_FILE_SIZE" value="{$max_file_size}" />
    <article class="upload">
        <header>
            <h2>Upload images</h2>
            <p class="info">
                Maximum file size: {$max_file_size_human} MB
                | Image types accepted: {$formats}
            </p>
        </header>
        <fieldset>
            <dl>
                <dt><label for="f_title">Title:</label></dt>
                <dd><input type="text" name="title" id="f_title" maxlength="100" required="required" /></dd>
                <dt><label for="f_private">Private</label></dt>
                <dd class="private"><label><input type="checkbox" name="private" id="f_private" value="1" />
                    (If checked, the pictures won't be listed in &quot;browse images&quot;)</label></dd>
                <dd id="f_file_container"><input type="file" name="upload" id="f_files" multiple="multiple" accept="image/jpeg,image/webp,image/png,image/gif,image/svg+xml" required="required" /></dd>
            </dl>
            <p class="submit">
                <input type="submit" value="Upload" />
            </p>
        </fieldset>
        <div id="albumParent"></div>
        <p class="submit">
            <input type="submit" value="Upload" />
        </p>
    </article>
    </form>
    <script type="text/javascript" src="{$js_url}"></script>
EOF;

}

$css_url = file_exists(__DIR__ . '/style.css')
    ? $config->base_url . 'style.css?2023'
    : $config->base_url . '?css&2023';

echo '<!DOCTYPE html>
<html>
<head>
    <meta name="charset" content="utf-8" />
    <title>'.($title ? escape($title) . ' - ' : '').$config->title.'</title>
    <meta name="viewport" content="width=device-width" />
    <link rel="stylesheet" type="text/css" href="'.$css_url.'" />
</head>

<body>
<header>
    <h1><a href="'.$config->base_url.'">'.$config->title.'</a></h1>
    '.($fh->logged() ? '<h2>(admin mode)</h2>' : '').'
    <nav>
        <ul>
            <li><a href="'.$config->base_url.'">Upload</a></li>
            <li><a href="'.$config->base_url.'?list">Browse images</a></li>
        </ul>
    </nav>
</header>
<div id="page">
    '.$html.'
</div>
<footer>
    Powered by Fotoo Hosting application from <a href="http://kd2.org/">KD2.org</a>
    | '.($fh->logged() ? '<a href="'.$config->base_url.'?logout">Logout</a>' : '<a href="'.$config->base_url.'?login">Login</a>').'
</footer>
</body>
</html>';


?>