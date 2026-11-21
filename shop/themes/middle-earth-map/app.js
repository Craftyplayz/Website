(function () {
  var $ = function (id) { return document.getElementById(id); };
  var status = $('status');
  function esc(s) {
    return String(s).replace(/[&<>"']/g, function (c) {
      return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c];
    });
  }
  function render(d) {
    document.title = d.shop + ' · A Traveller’s Map';
    $('shopname').textContent = d.shop;
    $('currency').textContent = d.currency || 'diamonds';
    $('products').innerHTML = d.products.map(function (p, i) {
      var src = esc(d.icons.replace('{id}', encodeURIComponent(p.id)));
      return '<li><img src="' + src + '" width="32" height="32" alt=""' + (i > 8 ? ' loading="lazy"' : '') + '>' +
        '<span class="nm">' + esc(p.name) + '</span><span class="pr">' + esc(p.price) + '</span></li>';
    }).join('');
    $('books-price').textContent = d.books.price;
    $('books').innerHTML = d.books.list.map(function (b) { return '<li>' + esc(b) + '</li>'; }).join('');
    $('req-text').textContent = d.requests.text;
    $('req-ex').textContent = d.requests.examples;
    $('contacts').innerHTML = d.requests.contacts.map(function (c) { return '<li>' + esc(c) + ' <small>on Discord</small></li>'; }).join('');
    $('casino').textContent = d.casino.status;
    status.hidden = true;
    $('content').hidden = false;
  }
  fetch('/shop/data.json').then(function (r) {
    if (!r.ok) throw new Error('HTTP ' + r.status);
    return r.json();
  }).then(render).catch(function (e) {
    status.className = 'status error';
    status.textContent = 'The map could not be unrolled: the price list failed to load (' + e.message + '). Please refresh, or try again shortly.';
  });
})();
