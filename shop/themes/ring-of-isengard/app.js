(function () {
  var $ = function (id) { return document.getElementById(id); };
  function el(tag, cls, text) {
    var e = document.createElement(tag);
    if (cls) e.className = cls;
    if (text != null) e.textContent = text;
    return e;
  }
  fetch('/shop/data.json')
    .then(function (r) { if (!r.ok) throw new Error('HTTP ' + r.status); return r.json(); })
    .then(render)
    .catch(function (e) {
      var s = $('status');
      s.className = 'status error';
      s.textContent = 'The gates are barred: the price list could not be loaded (' + e.message + '). Please try again later.';
    });

  function render(d) {
    $('status').hidden = true;
    $('shopname').textContent = d.shop;
    var ul = $('products');
    d.products.forEach(function (p, i) {
      var li = el('li', 'tablet');
      var img = new Image(40, 40);
      img.alt = '';
      if (i > 8) img.loading = 'lazy';
      img.src = d.icons.replace('{id}', encodeURIComponent(p.id));
      var box = el('span', 'icon'); box.appendChild(img);
      var t = el('div', 'txt');
      t.appendChild(el('h3', null, p.name));
      t.appendChild(el('p', 'price', p.price));
      li.appendChild(box); li.appendChild(t);
      ul.appendChild(li);
    });
    $('prices').hidden = false;

    $('bookprice').textContent = d.books.price;
    d.books.list.forEach(function (b) { $('booklist').appendChild(el('li', null, b)); });
    $('books').hidden = false;

    $('reqtext').textContent = d.requests.text;
    $('reqex').textContent = d.requests.examples;
    d.requests.contacts.forEach(function (c) { $('contacts').appendChild(el('li', null, c + ' on Discord')); });
    $('requests').hidden = false;

    $('casinostatus').textContent = d.casino.status;
    $('casino').hidden = false;
  }
})();
