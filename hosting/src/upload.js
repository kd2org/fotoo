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

    var can_submit = true;
    var last_filename = '';
    var loading_gif = 'data:image/gif;base64,R0lGODlhEAAQAPIAAP%2F%2F%2FwAAAMLCwkJCQgAAAGJiYoKCgpKSkiH%2BGkNyZWF0ZWQgd2l0aCBhamF4bG9hZC5pbmZvACH5BAAKAAAAIf8LTkVUU0NBUEUyLjADAQAAACwAAAAAEAAQAAADMwi63P4wyklrE2MIOggZnAdOmGYJRbExwroUmcG2LmDEwnHQLVsYOd2mBzkYDAdKa%2BdIAAAh%2BQQACgABACwAAAAAEAAQAAADNAi63P5OjCEgG4QMu7DmikRxQlFUYDEZIGBMRVsaqHwctXXf7WEYB4Ag1xjihkMZsiUkKhIAIfkEAAoAAgAsAAAAABAAEAAAAzYIujIjK8pByJDMlFYvBoVjHA70GU7xSUJhmKtwHPAKzLO9HMaoKwJZ7Rf8AYPDDzKpZBqfvwQAIfkEAAoAAwAsAAAAABAAEAAAAzMIumIlK8oyhpHsnFZfhYumCYUhDAQxRIdhHBGqRoKw0R8DYlJd8z0fMDgsGo%2FIpHI5TAAAIfkEAAoABAAsAAAAABAAEAAAAzIIunInK0rnZBTwGPNMgQwmdsNgXGJUlIWEuR5oWUIpz8pAEAMe6TwfwyYsGo%2FIpFKSAAAh%2BQQACgAFACwAAAAAEAAQAAADMwi6IMKQORfjdOe82p4wGccc4CEuQradylesojEMBgsUc2G7sDX3lQGBMLAJibufbSlKAAAh%2BQQACgAGACwAAAAAEAAQAAADMgi63P7wCRHZnFVdmgHu2nFwlWCI3WGc3TSWhUFGxTAUkGCbtgENBMJAEJsxgMLWzpEAACH5BAAKAAcALAAAAAAQABAAAAMyCLrc%2FjDKSatlQtScKdceCAjDII7HcQ4EMTCpyrCuUBjCYRgHVtqlAiB1YhiCnlsRkAAAOwAAAAAAAAAAAA%3D%3D';
    var album_id = null;

    function checkBeforeSubmit()
    {
        if (!can_submit)
        {
            alert('File is loading, please wait...');
            return false;
        }

        var img = document.getElementById("resizedImg").firstChild;

        if (!img || !img.src)
            return true;

        can_submit = false;
        var file = document.getElementById("f_file");

        var name = document.createElement('input');
        name.type = 'hidden';
        name.name = file.name + '[name]';
        name.value = file.value.replace(/^.*[\/\\]([^\/\\]*)$/, '$1');
        file.parentNode.appendChild(name);

        file.type = "hidden";
        file.name = file.name + "[content]";
        file.value = img.src.substr(img.src.indexOf(',') + 1);

        document.getElementById("resizedThumb").innerHTML += "<br />Uploading in progress, please wait... <img class=\"loading\" src=\"" + loading_gif + "\" alt=\"\" />";

        return true;
    }

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
        var current = document.getElementById('albumParent').childNodes[index];
        var xhr = new XMLHttpRequest;

        var name = current.getElementsByTagName('input')[0];
        var img = current.getElementsByTagName('img')[0]

        name.disabled = true;

        var msg = document.createElement('span');
        msg.innerHTML = "Uploading... <img class=\"loading\" src=\"" + loading_gif + "\" alt=\"\" />";
        current.querySelector('.resizedThumb').appendChild(msg);

        var params = "album_append=1&name=" + encodeURIComponent(name.value) + "&album=" + encodeURIComponent(album_id);
        params += "&filename=" + encodeURIComponent(document.getElementById('f_files').files[index].name);
        params += "&content=" + encodeURIComponent(img.src.substr(img.src.indexOf(',') + 1));

        xhr.open('POST', config.base_url + '?album', true);
        xhr.setRequestHeader("Content-type", "application/x-www-form-urlencoded");
        xhr.setRequestHeader("Content-length", params.length);
        xhr.setRequestHeader("Connection", "close");

        xhr.onreadystatechange = function () {
            if (xhr.readyState == 4 && xhr.status == 200)
            {
                msg.innerHTML = "Uploaded <b>✔</b>";

                if (index + 1 < document.getElementById('albumParent').childNodes.length)
                {
                    uploadPicture(index+1);
                }
                else
                {
                    location.href = config.album_page_url + album_id;
                }
            }
        };

        xhr.send(params);
    }

    window.onload = function ()
    {
        if (!FileReader)
            return false;

        if (FileList && XMLHttpRequest)
        {
            var album_li = document.createElement('li');
            var album_a = document.createElement('a');
            album_a.href = '?album';
            album_a.innerHTML = 'Upload an album';
            album_li.appendChild(album_a);
            var link = document.querySelector('header nav ul li:nth-child(2)');
            link.parentNode.insertBefore(album_li, link);
        }

        var parent = document.getElementById('albumParent');

        if (parent)
        {
            document.getElementById("f_files").onchange = function ()
            {
                if (parent.firstChild && parent.firstChild.nodeType == Node.TEXT_NODE)
                {
                    parent.removeChild(parent.firstChild);
                }

                var found = new Array;

                for (var i = 0; i < this.files.length; i++)
                {
                    var file = this.files[i];
                    var id = encodeURIComponent(file.name + file.type + file.size);

                    if (document.getElementById(id))
                    {
                        found.push(id);
                        continue;
                    }

                    var fig = document.createElement('figure');
                    fig.id = id;
                    var caption = document.createElement('figcaption');
                    var name = document.createElement('input');
                    name.type = 'text';
                    name.value = cleanFileName(file.name);

                    var resized_img = document.createElement('div');
                    resized_img.style.display = "none";
                    resized_img.className = 'resizedImage';
                    var resized_thumb = document.createElement('div');
                    resized_thumb.className = 'resizedThumb';

                    caption.appendChild(name);
                    fig.appendChild(resized_img);
                    fig.appendChild(resized_thumb);
                    fig.appendChild(caption);
                    parent.appendChild(fig);

                    resize(
                        file,
                        config.max_width, // size
                        resized_img, // image resized
                        config.thumb_width, // thumb size
                        resized_thumb // thumb resized
                    );

                    found.push(id);
                }

                var l = parent.childNodes.length;
                for (var i = l - 1; i >= 0; i--)
                {
                    if (found.indexOf(parent.childNodes[i].id) == -1)
                    {
                        parent.removeChild(parent.childNodes[i]);
                    }
                }
            };

            document.getElementById("f_upload").onsubmit = function ()
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

                if (document.getElementById("f_files").files.length < 1)
                {
                    alert('No file is selected.');
                    return false;
                }

                var l = parent.childNodes.length;

                if (l < 1)
                {
                    return false;
                }

                can_submit = false;

                var xhr = new XMLHttpRequest;

                var params = "album_create=1&title=" + encodeURIComponent(document.getElementById('f_title').value);
                params += "&private=" + (document.getElementById('f_private').checked ? '1' : '0');

                xhr.open('POST', config.base_url + '?album', true);
                xhr.setRequestHeader("Content-type", "application/x-www-form-urlencoded");
                xhr.setRequestHeader("Content-length", params.length);
                xhr.setRequestHeader("Connection", "close");

                xhr.onreadystatechange = function () {
                    if (xhr.readyState == 4 && xhr.status == 200)
                    {
                        album_id = xhr.responseText;
                        uploadPicture(0);
                    }
                };

                xhr.send(params);

                return false;
            };
        }
        else
        {
            var parent = document.getElementById('resizeParent');

            var div_thumb = document.createElement('div');
            div_thumb.id = "resizedThumb";
            div_thumb.innerHTML = 'Please select a picture...';

            var div_img = document.createElement('div');
            div_img.id = "resizedImg";
            div_img.style.display = "none";

            parent.appendChild(div_img);
            parent.appendChild(div_thumb);

            document.getElementById("f_file").onchange = function ()
            {
                resize(
                    this.files.length ? this.files[0] : null,
                    config.max_width, // size
                    document.getElementById("resizedImg"), // image resized
                    config.thumb_width, // thumb size
                    document.getElementById("resizedThumb") // thumb resized
                );

                if (document.getElementById("f_name").value != last_filename)
                    return;

                var filename = cleanFileName(this.value);

                last_filename = filename;
                document.getElementById("f_name").value = filename;
            }

            document.getElementById("f_upload").onsubmit = checkBeforeSubmit;
        }
    };

    function resize($file, $size, $img, $thumb_size, $thumb)
    {
        function resampled(data)
        {
            var img = ($img.lastChild || $img.appendChild(new Image));
            img.src = data;
            img.className = "preview";

            if ($thumb && $thumb_size)
            {
                var thumb_size = parseInt($thumb_size, 10);
                Resample(
                    data,
                    thumb_size,
                    null,
                    resampledThumb
                );
            }
        }

        function resampledThumb(data)
        {
            $thumb.innerHTML = "";
            var img = ($thumb.lastChild || $thumb.appendChild(new Image));
            img.src = data;
            can_submit = true;
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

        if ((/^image\/jpeg/.test($file.type)))
        {
            if ($thumb) $thumb.innerHTML = "Resizing... <img class=\"loading\" src=\"" + loading_gif + "\" alt=\"\" />";
            can_submit = false;

            var file = new FileReader;
            file.onload = load;
            file.onabort = abort;
            file.onerror = error;
            file._resize = size;
            file.readAsDataURL($file);
        }
        else if ($file && /^image\//.test($file.type))
        {
            if ($thumb) $thumb.innerHTML = "Image is recognized.";
        }
        else if ($file)
        {
            $thumb.innerHTML = '<p class="warning">The chosen file is not an image.</p>';
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

            if (height == null)
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

            delete img._onresample;
            delete img._width;
            delete img._height;

            canvas.width = width;
            canvas.height = height;

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

            onresample(canvas.toDataURL("image/jpeg"));
        }

        var context = canvas.getContext("2d"),
            round = Math.round;

        return Resample;
    }
    (
        this.document.createElement("canvas")
    ));
} ());
