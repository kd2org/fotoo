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
    var album_check = null;
    var xhr = new XMLHttpRequest;

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

    function uploadPicture(index, element_index)
    {
        var file = document.getElementById('f_files').files[index];

        if (!(/^image\/jpe?g$/i.test(file.type)))
        {
            uploadPicture(index+1, element_index);
            return;
        }

        var current = document.getElementById('albumParent').childNodes[element_index];
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

                var params = "album_append=1&name=" + encodeURIComponent(name.value) + "&album=" + encodeURIComponent(album_id);
                params += "&filename=" + encodeURIComponent(file.name);
                params += "&content=" + encodeURIComponent(img.src.substr(img.src.indexOf(',') + 1));

                xhr.open('POST', config.base_url + '?album', true);
                xhr.setRequestHeader("Content-type", "application/x-www-form-urlencoded");
                xhr.setRequestHeader("Content-length", params.length);
                xhr.setRequestHeader("Connection", "close");

                xhr.onreadystatechange = function () {
                    if (xhr.readyState == 4 && xhr.status == 200)
                    {
                        progress.innerHTML = "Uploaded <b>&#10003;</b>";
                        img.parentNode.removeChild(img);

                        if (index + 1 < document.getElementById('f_files').files.length)
                        {
                            uploadPicture(index+1, element_index+1);
                        }
                        else
                        {
                            location.href = config.album_page_url + album_id + (config.album_page_url.indexOf('?') ? '&c=' : '?c=') + album_check;
                        }
                    }
                };

                xhr.send(params);
                params = null;
            }
        );
    }

    window.onload = function ()
    {
        if (!FileReader && !window.URL)
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

        document.getElementById('f_submit').style.display = 'none';

        var parent = document.getElementById('albumParent');

        // Mode album
        if (parent)
        {
            document.getElementById("f_files").onchange = function ()
            {
                if (this.files.length < 1)
                {
                    return false;
                }

                if (parent.firstChild && parent.firstChild.nodeType == Node.TEXT_NODE)
                {
                    parent.removeChild(parent.firstChild);
                }

                var found = new Array;
                var to_resize = new Array;

                for (var i = 0; i < this.files.length; i++)
                {
                    var file = this.files[i];

                    if (!(/^image\/jpe?g$/i.test(file.type)))
                    {
                        continue;
                    }

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

                    var thumb = document.createElement('div');
                    thumb.className = 'thumb';

                    var progress = document.createElement('p');

                    caption.appendChild(name);
                    fig.appendChild(thumb);
                    fig.appendChild(progress);
                    fig.appendChild(caption);
                    parent.appendChild(fig);

                    to_resize.push(new Array(file, thumb, progress));
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

                function resizeFromList()
                {
                    if (to_resize.length < 1)
                    {
                        can_submit = true;
                        document.getElementById('f_submit').style.display = 'inline';
                        return;
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
                document.getElementById('f_submit').style.display = 'none';

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
                        var txt = xhr.responseText.split('/');
                        album_id = txt[0];
                        album_check = txt[1];
                        uploadPicture(0, 0);
                    }
                };

                xhr.send(params);

                return false;
            };
        }
        else // Single file mode
        {
            var parent = document.getElementById('resizeParent');

            var figure = document.createElement('figure');
            var thumb = document.createElement('div');

            var progress = document.createElement('figcaption');
            progress.innerHTML = 'Please select a picture...';

            figure.appendChild(progress);
            figure.appendChild(thumb);
            parent.appendChild(figure);

            document.getElementById("f_file").onchange = function ()
            {
                if (!this.files.length)
                {
                    return false;
                }

                progress.style.display = "block";
                thumb.innerHTML = '';
                document.getElementById('f_submit').style.display = 'none';
                can_submit = false;

                if (/^image\/jpe?g$/i.test(this.files[0].type))
                {
                    can_submit = false;

                    resize(
                        this.files[0],
                        config.thumb_width, // thumb size
                        thumb, // thumb resized
                        progress,
                        function () {
                            if (thumb.firstChild)
                            {
                                progress.style.display = "none";
                            }
                            can_submit = true;
                            document.getElementById('f_submit').style.display = 'inline';
                        }
                    );
                }
                else
                {
                    var r = new RegExp('\.(' + config.allowed_formats.join('|') + ')$', 'i');

                    if (/^image\//i.test(this.files[0].type) && r.test(this.files[0].name))
                    {
                        progress.innerHTML = "Image is recognized.";
                        can_submit = true;
                        document.getElementById('f_submit').style.display = 'inline';
                    }
                    else
                    {
                        progress.innerHTML = 'The chosen file is not an image: ' + this.files[0].type;
                        document.getElementById('f_submit').style.display = 'none';
                        return false;
                    }
                }

                if (document.getElementById("f_name").value != last_filename)
                    return;

                last_filename = this.files[0].name;
                document.getElementById("f_name").value = cleanFileName(this.files[0].name);
            }

            document.getElementById("f_upload").onsubmit = function ()
            {
                if (can_submit == 2)
                {
                    return true;
                }

                if (!can_submit)
                {
                    alert('File is loading, please wait...');
                    return false;
                }

                var file = document.getElementById('f_file');

                if (!file.files.length)
                {
                    alert('You must choose a file before sending.');
                    return false;
                }

                var div_img = document.createElement('div');
                div_img.style.display = "none";

                parent.appendChild(div_img);

                can_submit = false;
                document.getElementById('f_submit').style.display = 'none';

                if (/^image\/jpe?g$/i.test(file.files[0].type))
                {
                    var progress = document.createElement('span');
                    parent.firstChild.appendChild(progress);

                    resize(
                        file.files[0],
                        -config.max_width, // thumb size
                        div_img, // thumb resized
                        progress,
                        function () {
                            progress.innerHTML = "Uploading... <img class=\"loading\" src=\"" + loading_gif + "\" alt=\"\" />";

                            var img = div_img.firstChild;

                            var name = document.createElement('input');
                            name.type = 'hidden';
                            name.name = file.name + '[name]';
                            name.value = file.value.replace(/^.*[\/\\]([^\/\\]*)$/, '$1');
                            file.parentNode.appendChild(name);

                            file.type = "hidden";
                            file.name = file.name + "[content]";
                            file.value = img.src.substr(img.src.indexOf(',') + 1);

                            can_submit = 2;
                            document.getElementById('f_upload').submit();
                        }
                    );

                    return false;
                }
                else
                {
                    var progress = document.createElement('p');
                    progress.innerHTML = "Uploading... <img class=\"loading\" src=\"" + loading_gif + "\" alt=\"\" />";
                    parent.firstChild.appendChild(progress);
                    return true;
                }
            };
        }
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

        if ((/^image\/jpe?g/.test($file.type)))
        {
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
        else
        {
            return false;
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

            onresample(canvas.toDataURL("image/jpeg", 0.75));
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
