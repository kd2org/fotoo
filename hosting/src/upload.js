(function () {
    var can_submit = true;
    var last_filename = '';
    var loading_gif = 'data:image/gif;base64,R0lGODlhEAAQAPIAAP%2F%2F%2FwAAAMLCwkJCQgAAAGJiYoKCgpKSkiH%2BGkNyZWF0ZWQgd2l0aCBhamF4bG9hZC5pbmZvACH5BAAKAAAAIf8LTkVUU0NBUEUyLjADAQAAACwAAAAAEAAQAAADMwi63P4wyklrE2MIOggZnAdOmGYJRbExwroUmcG2LmDEwnHQLVsYOd2mBzkYDAdKa%2BdIAAAh%2BQQACgABACwAAAAAEAAQAAADNAi63P5OjCEgG4QMu7DmikRxQlFUYDEZIGBMRVsaqHwctXXf7WEYB4Ag1xjihkMZsiUkKhIAIfkEAAoAAgAsAAAAABAAEAAAAzYIujIjK8pByJDMlFYvBoVjHA70GU7xSUJhmKtwHPAKzLO9HMaoKwJZ7Rf8AYPDDzKpZBqfvwQAIfkEAAoAAwAsAAAAABAAEAAAAzMIumIlK8oyhpHsnFZfhYumCYUhDAQxRIdhHBGqRoKw0R8DYlJd8z0fMDgsGo%2FIpHI5TAAAIfkEAAoABAAsAAAAABAAEAAAAzIIunInK0rnZBTwGPNMgQwmdsNgXGJUlIWEuR5oWUIpz8pAEAMe6TwfwyYsGo%2FIpFKSAAAh%2BQQACgAFACwAAAAAEAAQAAADMwi6IMKQORfjdOe82p4wGccc4CEuQradylesojEMBgsUc2G7sDX3lQGBMLAJibufbSlKAAAh%2BQQACgAGACwAAAAAEAAQAAADMgi63P7wCRHZnFVdmgHu2nFwlWCI3WGc3TSWhUFGxTAUkGCbtgENBMJAEJsxgMLWzpEAACH5BAAKAAcALAAAAAAQABAAAAMyCLrc%2FjDKSatlQtScKdceCAjDII7HcQ4EMTCpyrCuUBjCYRgHVtqlAiB1YhiCnlsRkAAAOwAAAAAAAAAAAA%3D%3D';

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

    function updateNameField()
    {
        if (document.getElementById("f_name").value != last_filename)
            return;

        var filename = this.value;

        filename = filename.replace(/[\\\\]/g, "/");
        filename = filename.split("/");
        filename = filename[filename.length - 1];
        filename = filename.split(".");
        filename = filename[0];
        filename = filename.replace(/\s+/g, "-");
        filename = filename.replace(/[^a-zA-Z0-9_.-]/ig, "");
        filename = filename.substr(0, 30);
        filename = filename.replace(/(^[_.-]+|[_.-]+$)/g, "");

        last_filename = filename;
        document.getElementById("f_name").value = filename;
    };

    window.onload = function ()
    {
        if (!FileReader)
            return false;

        var parent = document.getElementById('resizeParent');

        var div_thumb = document.createElement('div');
        div_thumb.id = "resizedThumb";
        div_thumb.innerHTML = 'Please select a picture...';

        var div_img = document.createElement('div');
        div_img.id = "resizedImg";
        div_img.style.display = "none";

        parent.appendChild(div_img);
        parent.appendChild(div_thumb);

        enableResize(
            document.getElementById("f_file"), // file input
            config.max_width, // size
            document.getElementById("resizedImg"), // image resized
            config.thumb_width, // thumb size
            document.getElementById("resizedThumb") // thumb resized
        );

        document.getElementById("f_upload").onsubmit = checkBeforeSubmit;
        document.getElementById("f_file").addEventListener('change', updateNameField);
    };

    function enableResize($file, $size, $img, $thumb_size, $thumb)
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

        $file.addEventListener("change", function change()
        {
            var size = parseInt($size, 10),
                file;

            if (($file.files || []).length && /^image\/jpeg/.test((file = $file.files[0]).type))
            {
                if ($thumb) $thumb.innerHTML = "Resizing... <img class=\"loading\" src=\"" + loading_gif + "\" alt=\"\" />";
                can_submit = false;

                file = new FileReader;
                file.onload = load;
                file.onabort = abort;
                file.onerror = error;
                file._resize = size;
                file.readAsDataURL($file.files[0]);
            }
            else if (file && /^image\//.test(file.type))
            {
                if ($thumb) $thumb.innerHTML = "Image is recognized, please hit Upload to send.";
            }
            else if (file)
            {
                $thumb.innerHTML = '<p class="warning">The chosen file is not an image.</p>';
            }
        }, false);
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
