(function () {
  var $ = function (id) { return document.getElementById(id); };
  var status = $('status');

  function el(tag, cls, text) {
    var e = document.createElement(tag);
    if (cls) e.className = cls;
    if (text != null) e.textContent = text;
    return e;
  }

  // Embers: transform/opacity only; skipped for reduced motion.
  if (!matchMedia('(prefers-reduced-motion: reduce)').matches) {
    var box = $('embers');
    for (var i = 0; i < 26; i++) {
      var s = document.createElement('i');
      s.style.left = Math.random() * 100 + '%';
      s.style.setProperty('--dx', (Math.random() * 120 - 60) + 'px');
      s.style.animationDuration = (7 + Math.random() * 9) + 's';
      s.style.animationDelay = (-Math.random() * 14) + 's';
      box.appendChild(s);
    }
  }

  function render(d) {
    $('shop-name').textContent = d.shop;
    var list = $('products');
    var pattern = d.icons || '';
    (d.products || []).forEach(function (p, n) {
      var li = el('li', 'tag');
      var ico = el('span', 'ico');
      var img = document.createElement('img');
      img.width = 40; img.height = 40; img.alt = '';
      if (n > 8) img.loading = 'lazy';
      img.src = pattern.replace('{id}', encodeURIComponent(p.id));
      ico.appendChild(img);
      var t = el('div', 'txt');
      t.appendChild(el('h3', 'name', p.name));
      t.appendChild(el('p', 'price', p.price));
      li.appendChild(ico); li.appendChild(t);
      list.appendChild(li);
    });
    if (d.books) {
      $('books-price').textContent = d.books.price;
      d.books.list.forEach(function (b) { $('books').appendChild(el('li', '', b)); });
    }
    if (d.requests) {
      $('req-text').textContent = d.requests.text;
      $('req-ex').textContent = d.requests.examples;
      d.requests.contacts.forEach(function (c) { $('contacts').appendChild(el('li', '', c)); });
    }
    if (d.casino) $('casino-status').textContent = d.casino.status;
    ['s-prices', 's-books', 's-req', 's-casino'].forEach(function (id) { $(id).hidden = false; });
    status.hidden = true;
  }

  fetch('/shop/data.json')
    .then(function (r) { if (!r.ok) throw new Error(r.status); return r.json(); })
    .then(render)
    .catch(function () {
      status.className = 'status err';
      status.textContent = 'The forge fires will not light: the price list could not be loaded. Please refresh the page or try again shortly.';
    });
})();
