(function () {
  var $ = function (id) { return document.getElementById(id); };
  function el(tag, cls, text) {
    var e = document.createElement(tag);
    if (cls) e.className = cls;
    if (text != null) e.textContent = text;
    return e;
  }
  function fail(msg) {
    var s = $('status');
    s.className = 'status error';
    s.textContent = msg;
  }
  fetch('/shop/data.json', { cache: 'no-cache' })
    .then(function (r) { if (!r.ok) throw new Error('HTTP ' + r.status); return r.json(); })
    .then(render)
    .catch(function (e) { fail('The gates will not open: the price list could not be loaded (' + e.message + '). Please try again shortly.'); });

  function render(d) {
    $('shopname').textContent = d.shop;
    var pat = d.icons;
    d.products.forEach(function (p, i) {
      var li = el('li', 'plaque');
      var inner = el('div', 'inner');
      var img = new Image(40, 40);
      img.alt = '';
      if (i > 11) img.loading = 'lazy';
      img.src = pat.replace('{id}', encodeURIComponent(p.id));
      img.onerror = function () { img.style.visibility = 'hidden'; };
      var t = el('div', 'txt');
      t.appendChild(el('h3', '', p.name));
      t.appendChild(el('p', 'price', p.price));
      inner.appendChild(img); inner.appendChild(t); li.appendChild(inner);
      $('products').appendChild(li);
    });
    $('bookprice').textContent = d.books.price;
    d.books.list.forEach(function (b) {
      var li = el('li', 'rune');
      li.appendChild(el('span', '', b));
      $('books').appendChild(li);
    });
    var r = d.requests, req = $('req');
    req.appendChild(el('p', '', r.text));
    req.appendChild(el('p', 'muted', r.examples));
    var c = el('ul', 'contacts');
    r.contacts.forEach(function (n) { c.appendChild(el('li', '', n)); });
    req.appendChild(c);
    var cs = $('casino');
    cs.appendChild(el('p', 'seal', d.casino.status));
    cs.appendChild(el('p', 'muted', 'The doors are still being carved.'));
    $('status').hidden = true;
    $('content').hidden = false;
  }
})();
