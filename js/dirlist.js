// Renders directory manifests (data/manifests/*.json, produced by build.js)
// in place of the PHP scandir() loops.
//   <div data-dirlist="code-downloads" data-mode="links" data-base="/Code/downloads/"></div>
// modes: links | images | options
(function () {
    document.querySelectorAll('[data-dirlist]').forEach(function (box) {
        var base = box.getAttribute('data-base') || '';
        var mode = box.getAttribute('data-mode') || 'links';
        site.json('/data/manifests/' + box.getAttribute('data-dirlist') + '.json').then(function (files) {
            var h = '';
            files.forEach(function (f) {
                var url = base + encodeURIComponent(f);
                if (mode === 'images') h += '<img src="' + url + '" alt="">';
                else if (mode === 'options') h += '<option value="' + site.esc(f.slice(0, -4)) + '">' + site.esc(f.slice(0, -4)) + '</option>';
                else h += '<a href="' + url + '">' + site.esc(f) + '</a><br>';
            });
            box.innerHTML = h;
        });
    });
})();
