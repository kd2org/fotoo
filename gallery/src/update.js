if (typeof need_update != 'undefined')
{
    var update_done = 0;
    var loading = document.createElement('div');
    loading.style.padding = '5px';
    loading.style.margin = '0px';
    loading.style.fontFamily = 'Sans-serif';
    loading.style.fontSize = '12px';
    loading.style.position = 'absolute';
    loading.style.top = '5px';
    loading.style.left = '5px';
    loading.style.border = '2px solid #ccc';
    loading.style.background = '#fff';
    loading.style.color = '#000';
    loading.style.width = (Math.round(need_update.length * 5) + 100) + 'px';
    loading.id = 'updatingTemp';

    if (typeof update_msg != 'undefined')
        loading.innerHTML = update_msg;
    else
        loading.innerHTML = 'Updating';

    var body = document.getElementsByTagName('body')[0];
    body.appendChild(loading);

    function updateNext()
    {
        var loading = document.getElementById('updatingTemp');

        if (update_done >= need_update.length)
        {
            window.setTimeout('window.location = window.location', 100);
            return;
        }

        var file = need_update[update_done];
        var img = document.createElement('img');
        img.src = update_url + '?updateDir=' + encodeURI(update_dir) + '&updateFile=' + encodeURI(file);
        img.alt = update_done + '/' + need_update.length;
        img.width = Math.round(update_done * 5);
        img.height = 1;
        img.style.borderBottom = "2px solid #000099";
        img.style.verticalAlign = "middle";
        img.style.margin = "0 10px";

        img.onload = function ()
        {
            update_done++;
            var elm = document.getElementById('updatingTemp');

            if (update_done < need_update.length)
                elm.removeChild(this);

            updateNext();
        }

        loading.appendChild(img);
    }

    updateNext();
}