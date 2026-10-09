// Homepage slideshow (replaces PHP scandir + shuffle)
(function () {
    var mobile = /Android|webOS|iPhone|iPad|iPod|BlackBerry|IEMobile|Opera Mini/i.test(navigator.userAgent);
    var name = mobile ? 'slideshow-homepagemobile' : 'slideshow-homepage';
    var folder = mobile ? '/images/SlideShows/homepagemobile/' : '/images/SlideShows/homepage/';
    var box = document.querySelector('.slideshow');

    site.json('/data/manifests/' + name + '.json').then(function (files) {
        site.shuffle(files).forEach(function (file) {
            var img = document.createElement('img');
            img.src = folder + encodeURIComponent(file);
            img.alt = 'Slideshow Image';
            box.appendChild(img);
        });
        var images = box.querySelectorAll('img');
        if (!images.length) return;
        var current = 0;
        function change() {
            images[current].style.opacity = 0;
            current = (current + 1) % images.length;
            images[current].style.opacity = 1;
            setTimeout(change, 5000);
        }
        change();
    });
})();
