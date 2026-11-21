(function () {
  var $ = function (id) { return document.getElementById(id); };
  var status = $('status');

  function li(parent, text) {
    var el = document.createElement('li');
    el.textContent = text;
    parent.appendChild(el);
    return el;
  }

  fetch('/shop/data.json')
    .then(function (r) { if (!r.ok) throw new Error('HTTP ' + r.status); return r.json(); })
    .then(function (d) {
      $('shop-name').textContent = d.shop;
      document.title = d.shop + ' · The tower by moonlight';

      var grid = $('grid');
      d.products.forEach(function (p, i) {
        var el = document.createElement('li');
        var ico = document.createElement('span');
        ico.className = 'ico';
        var img = new Image(40, 40);
        img.alt = '';
        if (i > 5) img.loading = 'lazy';
        img.src = d.icons.replace('{id}', encodeURIComponent(p.id));
        ico.appendChild(img);
        var box = document.createElement('span');
        var n = document.createElement('span'); n.className = 'name'; n.textContent = p.name;
        var pr = document.createElement('span'); pr.className = 'price'; pr.textContent = p.price;
        box.appendChild(n); box.appendChild(pr);
        el.appendChild(ico); el.appendChild(box);
        grid.appendChild(el);
      });

      $('books-price').textContent = d.books.price;
      d.books.list.forEach(function (b) { li($('book-list'), b); });

      $('req-text').textContent = d.requests.text;
      $('req-ex').textContent = d.requests.examples;
      d.requests.contacts.forEach(function (c) { li($('contacts'), c); });

      $('casino-status').textContent = d.casino.status;

      status.hidden = true;
      ['wares', 'books', 'requests', 'casino'].forEach(function (id) { $(id).hidden = false; });
    })
    .catch(function (e) {
      status.className = 'status err';
      status.textContent = 'The scroll could not be unrolled (' + e.message + '). Please try again shortly.';
    });
})();
