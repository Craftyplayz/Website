(function () {
  var $ = function (id) { return document.getElementById(id); };
  function txt(tag, cls, t) { var e = document.createElement(tag); if (cls) e.className = cls; e.textContent = t; return e; }
  fetch('/shop/data.json').then(function (r) {
    if (!r.ok) throw new Error('HTTP ' + r.status);
    return r.json();
  }).then(function (d) {
    $('shopname').textContent = d.shop;
    document.title = d.shop + ' · The Palantír';
    var g = $('products');
    d.products.forEach(function (p, i) {
      var c = document.createElement('li'); c.className = 'card';
      c.style.setProperty('--d', Math.min(i, 12) * 40 + 'ms');
      var img = new Image(); img.width = 40; img.height = 40; img.alt = '';
      if (i > 5) img.loading = 'lazy';
      img.src = d.icons.replace('{id}', encodeURIComponent(p.id));
      var b = document.createElement('div'); b.className = 'txt';
      b.appendChild(txt('span', 'name', p.name)); b.appendChild(txt('span', 'price', p.price));
      c.appendChild(img); c.appendChild(b); g.appendChild(c);
    });
    $('bookprice').textContent = d.books.price;
    d.books.list.forEach(function (n) { $('booklist').appendChild(txt('li', 'chip', n)); });
    $('reqtext').textContent = d.requests.text;
    $('reqex').textContent = d.requests.examples;
    d.requests.contacts.forEach(function (n) { $('contacts').appendChild(txt('li', 'chip', n + ' · Discord')); });
    $('casinostatus').textContent = d.casino.status;
    ['wares', 'books', 'requests', 'casino'].forEach(function (s) { $(s).hidden = false; });
    $('status').hidden = true;
  }).catch(function (e) {
    var s = $('status'); s.className = 'err';
    s.textContent = 'The stone is dark: the price list could not be loaded (' + e.message + '). Please try again shortly.';
  });
})();
