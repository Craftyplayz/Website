(function () {
  var $ = function (id) { return document.getElementById(id); };
  var st = $('status');
  function el(tag, cls, text) {
    var e = document.createElement(tag);
    if (cls) e.className = cls;
    if (text != null) e.textContent = text;
    return e;
  }
  fetch('/shop/data.json').then(function (r) {
    if (!r.ok) throw new Error('HTTP ' + r.status);
    return r.json();
  }).then(function (d) {
    $('shop-name').textContent = d.shop;
    var ul = $('products');
    d.products.forEach(function (p, i) {
      var li = el('li', 'niche');
      var img = new Image(40, 40);
      img.alt = '';
      if (i > 8) img.loading = 'lazy';
      img.src = d.icons.replace('{id}', encodeURIComponent(p.id));
      var box = el('div', 'icon'); box.appendChild(img);
      var t = el('div', 'txt');
      t.appendChild(el('h3', 'name', p.name));
      t.appendChild(el('p', 'price', p.price));
      li.appendChild(box); li.appendChild(t);
      ul.appendChild(li);
    });
    $('book-price').textContent = d.books.price;
    d.books.list.forEach(function (b) { $('book-list').appendChild(el('li', null, b)); });
    $('req-text').textContent = d.requests.text;
    $('req-ex').textContent = d.requests.examples;
    d.requests.contacts.forEach(function (c) { $('contacts').appendChild(el('li', null, c + ' on Discord')); });
    $('casino-status').textContent = d.casino.status;
    document.querySelectorAll('.storey').forEach(function (s) { s.hidden = false; });
    st.hidden = true;
  }).catch(function (e) {
    st.className = 'status error';
    st.textContent = 'The gates of Isengard are shut: the price list could not be loaded (' + e.message + '). Please try again shortly.';
  });
})();
