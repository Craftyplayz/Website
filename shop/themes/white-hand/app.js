(function () {
  var $ = function (id) { return document.getElementById(id); };
  function el(tag, cls, text) {
    var e = document.createElement(tag);
    if (cls) e.className = cls;
    if (text != null) e.textContent = text;
    return e;
  }
  var status = $('status');

  fetch('/shop/data.json', { cache: 'no-cache' })
    .then(function (r) { if (!r.ok) throw new Error('HTTP ' + r.status); return r.json(); })
    .then(render)
    .catch(function (err) {
      status.classList.add('error');
      status.textContent = 'The messenger did not return: the price list could not be loaded (' + err.message + '). Please try again shortly.';
    });

  function render(d) {
    if (d.shop) $('shopname').textContent = d.shop;
    var grid = $('grid');
    (d.products || []).forEach(function (p, i) {
      var li = el('li', 'card');
      var inner = el('div', 'card-in');
      var img = document.createElement('img');
      img.src = d.icons.replace('{id}', encodeURIComponent(p.id));
      img.width = 40; img.height = 40; img.alt = '';
      if (i > 6) img.loading = 'lazy';
      inner.appendChild(img);
      inner.appendChild(el('h3', null, p.name));
      inner.appendChild(el('p', null, p.price));
      li.appendChild(inner);
      grid.appendChild(li);
    });
    var b = d.books || {};
    $('bookprice').textContent = b.price || '';
    (b.list || []).forEach(function (n) { $('booklist').appendChild(el('li', null, n)); });
    var r = d.requests || {};
    $('reqtext').textContent = r.text || '';
    $('reqex').textContent = r.examples || '';
    (r.contacts || []).forEach(function (n) { $('contacts').appendChild(el('li', null, n)); });
    $('casinostatus').textContent = (d.casino && d.casino.status) || '';
    ['wares', 'books', 'requests', 'casino'].forEach(function (id) { $(id).hidden = false; });
    status.hidden = true;
  }
})();
