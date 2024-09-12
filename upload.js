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

    var parent, filesInput;
    var found = [];
    var to_resize = [];
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

    function upload(progress, name, filename, file, thumb)
    {
        progress.innerHTML = "Uploading... <img class=\"loading\" src=\"" + loading_gif + "\" alt=\"\" />";

        var params = new FormData;

        params.append('name', name);
        params.append('filename', filename);
        params.append('thumb', thumb);

        if (typeof file === 'string') {
            params.append('content', file);
        }
        else {
            params.append('file', file);
        }

        if (album_hash) {
            params.append('album', album_hash);
            params.append('key', album_key);
        }
        else {
            params.append('private', document.getElementById('f_private').checked ? 1 : 0);
            params.append('expiry', document.getElementById('f_expiry').value);
        }

        return fetch(config.base_url + '?upload', {
            'method': 'POST',
            'mode': 'same-origin',
            'body': params
        }).then((r) => {
            if (!r.ok) {
                console.error(r);
                throw Error('Upload failed');
            }

            progress.innerHTML = "Uploaded <b>&#10003;</b>";

            if (!album_hash) {
                return r.text().then((url) => location.href = url);
            }

            return r.text();
        });
    }

    function uploadPicture(index)
    {
        var file = files[index];

        if (!file) {
            if (album_hash) {
                location.href = config.album_page_url + album_hash + (config.album_page_url.indexOf('?') ? '&c=' : '?c=') + album_key;
            }
            return;
        }

        var current = document.getElementById('albumParent').querySelectorAll('figure')[index];
        current.scrollIntoView({behavior: 'smooth'});
        var resized_img = document.createElement('div');
        resized_img.style.display = "none";

        var name = current.getElementsByTagName('input')[0];
        name.disabled = true;

        var progress = document.createElement('span');
        current.appendChild(progress);

        var thumb = current.querySelector('img').src;
        thumb = thumb.substr(thumb.indexOf(',') + 1);

        if (!(/^image\/(?:jpe?g|webp)$/i.test(file.type)))
        {
            // Upload SVG/PNG/GIF, etc: no client-side resize
            upload(progress, name.value, file.name, file, thumb).then(() => uploadPicture(index+1));
            return;
        }

        resize(
            file,
            -config.max_width,
            resized_img,
            progress,
            () => {
                var img = resized_img.firstChild

                upload(progress, name.value, file.name, img.src.substr(img.src.indexOf(',') + 1), thumb).then(() => {
                    img.remove();
                    uploadPicture(index+1);
                });
            }
        );
    }

    // Detect directories to dismiss them
    // see https://web.dev/patterns/files/drag-and-drop-directories/
    const supportsFileSystemAccessAPI = 'getAsFileSystemHandle' in DataTransferItem.prototype;
    const supportsWebkitGetAsEntry = 'webkitGetAsEntry' in DataTransferItem.prototype;

    function isItemFile(item) {
        if (item.kind !== 'file') {
            return false;
        }
        else if (supportsFileSystemAccessAPI && item.getAsFileSystemHandle().kind == 'directory') {
            return false;
        }
        else if (supportsWebkitGetAsEntry && (entry = item.webkitGetAsEntry()) && entry.isDirectory) {
            return false;
        }
        else {
            return true;
        }
    }

    function resizeFromList()
    {
        if (to_resize.length < 1) {
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

    function addFiles(files)
    {
        if (files.length < 1) {
            return;
        }

        for (var i = 0; i < files.length; i++) {
            addFile(files[i]);
        }

        resizeFromList();
    }

    function addFile(file)
    {
        if (!(/^image\/(?:jpe?g|webp|png|gif|svg)/i.test(file.type))) {
            return;
        }

        let file_name = file.name === 'image.png' ? file.name.replace(/\./, '-' + (+(new Date)) + '.') : file.name;
        var t = document.getElementById('f_title');

        if (!t.value) {
            t.value = cleanFileName(file_name);
        }

        var id = encodeURIComponent(file_name + file.type + file.size);

        if (found.indexOf(id) != -1) {
            return;
        }

        var fig = document.createElement('figure');
        fig.id = id;
        var caption = document.createElement('figcaption');
        var name = document.createElement('input');
        name.type = 'text';
        name.value = cleanFileName(file_name);

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

        to_resize.push([file, thumb, progress]);
        found.push(id);
        files.push(file);
    }

    window.onload = function ()
    {
        if (!FileReader && !window.URL) {
            return false;
        }

        document.querySelectorAll('.submit').forEach((e) => e.style.display = 'none');

        parent = document.getElementById('albumParent');
        filesInput = document.getElementById("f_files");

        filesInput.style.display = 'none';

        var btn = document.createElement('button');
        btn.type = 'button';
        btn.innerText = 'Add images…';
        btn.className = 'add icon select';
        btn.onclick = () => filesInput.click();
        parent.prepend(btn);

        var help = document.createElement('p');
        help.innerText = 'Drag and drop, copy/paste, or click this button to add images.';
        help.className = 'help';
        parent.prepend(help);

        // Paste image directly
        window.addEventListener('paste', (e) => {
            e.preventDefault();

            const files = [...e.clipboardData.items]
                .filter(isItemFile)
                .map(item => item.getAsFile());

            addFiles(files);
        });

        // Mode album
        filesInput.onchange = (e) => {
            e.preventDefault();
            addFiles(filesInput.files);
        };

        var drag_elements = [];

        document.body.addEventListener('dragover', (e) => {
            e.preventDefault();
            e.stopPropagation();
        });

        document.body.addEventListener('dragenter', (e) => {
            drag_elements.push(e.target);

            e.preventDefault();
            e.stopPropagation();

            if (drag_elements.length == 1) {
                document.body.classList.add('dropping');
            }
        });

        document.body.addEventListener('dragleave', (e) => {
            var idx = drag_elements.indexOf(e.target);

            if (idx === -1) {
                return;
            }

            drag_elements.splice(idx, 1);

            e.preventDefault();
            e.stopPropagation();

            if (drag_elements.length === 0) {
                document.body.classList.remove('dropping');
            }
        });

        document.body.addEventListener('drop', (e) => {
            e.preventDefault();
            e.stopPropagation();
            document.body.classList.remove('dropping');

            drag_elements = [];

            const files = [...e.dataTransfer.items]
                .filter(isItemFile)
                .map(item => item.getAsFile());

            addFiles(files);
        });

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

            if (files.length < 1) {
                alert('No file is selected.');
                return false;
            }

            can_submit = false;
            document.querySelectorAll('.submit').forEach((e) => e.style.display = 'none');
            document.querySelectorAll('#albumParent button').forEach((e) => e.style.display = 'none');
            document.querySelectorAll('#albumParent input').forEach((e) => e.disabled = true);

            var url = config.base_url + '?upload';

            if (files.length > 1) {
                var params = new URLSearchParams({
                    'album': 'new',
                    'title': document.getElementById('f_title').value,
                    'private': document.getElementById('f_private').checked ? 1 : 0,
                    'expiry': document.getElementById('f_expiry').value
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

            // Calculate max image size by counting number of pixels
            if (height === null && width < 0)
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
            else if (height === null)
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

            const dpr = 1;
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
