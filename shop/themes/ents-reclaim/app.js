(function () {
  var $ = function (id) { return document.getElementById(id); };
  function el(tag, cls, text) { var e = document.createElement(tag); if (cls) e.className = cls; if (text != null) e.textContent = text; return e; }
  var st = $('status');
  fetch('/shop/data.json').then(function (r) { if (!r.ok) throw new Error(r.status); return r.json(); }).then(function (d) {
    $('shopname').textContent = d.shop;
    var g = $('grid');
    d.products.forEach(function (p, i) {
      var li = el('li', 'card'), img = new Image();
      img.width = 40; img.height = 40; img.alt = ''; if (i > 7) img.loading = 'lazy';
      img.src = d.icons.replace('{id}', encodeURIComponent(p.id));
      var t = el('div', 'txt'); t.appendChild(el('h3', 'name', p.name)); t.appendChild(el('p', 'price', p.price));
      li.appendChild(img); li.appendChild(t); g.appendChild(li);
    });
    $('bookprice').textContent = d.books.price;
    d.books.list.forEach(function (b) { $('booklist').appendChild(el('li', null, b)); });
    $('reqtext').textContent = d.requests.text;
    $('reqex').textContent = d.requests.examples;
    d.requests.contacts.forEach(function (c) { $('contacts').appendChild(el('li', null, c)); });
    $('casinotext').textContent = d.casino.status;
    ['prices', 'books', 'requests', 'casino'].forEach(function (s) { $(s).hidden = false; });
    st.hidden = true;
  }).catch(function () {
    st.className = 'status err';
    st.textContent = 'The price list could not be loaded. Please refresh the page, or try again shortly.';
  });
})();
