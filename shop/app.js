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
    var lists = {}, last;
    function slug(s) { return s.toLowerCase().replace(/W+/g, '-'); }
    d.floors.forEach(function (f, n) {
      $('products').appendChild(el('h3', 'floor', 'Floor ' + (n + 1) + ' · ' + f.name)).id = slug(f.name);
      f.sections.forEach(function (s) {
        $('products').appendChild(el('h4', 'shelf', s)).id = slug(s);
        last = lists[f.name + '/' + s] = $('products').appendChild(el('ul', 'niches'));
      });
    });
    d.products.forEach(function (p, i) {
      var ul = lists[p.floor + '/' + p.section] || last; // an item with a typo in its floor still shows
      var li = el('li', 'niche');
      var img = new Image(40, 40);
      img.alt = '';
      if (i > 8) img.loading = 'lazy';
      img.src = d.icons.replace('{id}', encodeURIComponent(p.id));
      var box = el('div', 'icon'); box.appendChild(img);
      var t = el('div', 'txt');
      t.appendChild(el('h5', 'name', p.name));
      t.appendChild(el('p', 'price', p.price));
      if (p.note) t.appendChild(el('p', 'note', p.note));
      li.appendChild(box); li.appendChild(t);
      ul.appendChild(li);
    });
    $('req-text').textContent = d.requests.text;
    $('req-ex').textContent = d.requests.examples;
    d.requests.contacts.forEach(function (c) {
      var link = el('a', null, c.name + ' on Discord');
      link.href = c.url; link.target = '_blank'; link.rel = 'noopener';
      $('contacts').appendChild(el('li')).appendChild(link);
    });
    $('casino-status').textContent = d.casino.status;
    document.querySelectorAll('.storey').forEach(function (s) { s.hidden = false; });
    st.hidden = true;
  }).catch(function (e) {
    st.className = 'status error';
    st.textContent = 'The gates of Isengard are shut: the price list could not be loaded (' + e.message + '). Please try again shortly.';
  });
})();
