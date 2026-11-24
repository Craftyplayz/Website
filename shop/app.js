(function () {
  var $ = function (id) { return document.getElementById(id); };
  var st = $('status');
  var COLOURS = ['white', 'light_gray', 'gray', 'black', 'brown', 'red', 'orange', 'yellow', 'lime', 'green', 'cyan', 'light_blue', 'blue', 'purple', 'magenta', 'pink'];
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
      $('products').appendChild(el('h3', 'floor', f.label || 'Floor ' + (n + 1) + ' · ' + f.name)).id = slug(f.name);
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
      if (p.link) { var a = t.appendChild(el('a', 'note', p.link.text)); a.href = p.link.url; }
      li.appendChild(box); li.appendChild(t);
      li.dataset.id = p.id;
      if (p.colours) li.dataset.colours = p.colours;
      ul.appendChild(li);
    });
    $('req-text').textContent = d.requests.text;
    $('req-ex').textContent = d.requests.examples;
    d.requests.contacts.forEach(function (c) {
      var link = el('a', null, c.name + ' on Discord');
      link.href = c.url; link.target = '_blank'; link.rel = 'noopener';
      $('contacts').appendChild(el('li')).appendChild(link);
    });
    $('casino-floor').textContent = d.casino.floor;
    $('casino-lead').textContent = d.casino.lead;
    var groups = {};
    [[false, 'Free games'], [true, 'Paid games']].forEach(function (p) {
      $('games').appendChild(el('h3', 'floor', p[1]));
      groups[p[0]] = $('games').appendChild(el('ul', 'niches'));
    });
    d.casino.games.forEach(function (g) {
      var link = el('a', 'niche');
      link.href = '/shop/' + g.slug + '/';
      var img = new Image(40, 40); img.alt = ''; img.loading = 'lazy';
      img.src = d.icons.replace('{id}', encodeURIComponent(g.icon));
      var box = el('div', 'icon'); box.appendChild(img);
      var t = el('div', 'txt');
      t.appendChild(el('h3', 'name', g.name));
      t.appendChild(el('p', 'price', g.price + (g.players ? ' · ' + g.players + ' players' : '')));
      t.appendChild(el('p', g.open ? 'note open' : 'note', g.open ? 'Open now' : 'Coming soon'));
      link.appendChild(box); link.appendChild(t);
      groups[!!g.paid].appendChild(el('li')).appendChild(link);
    });
    // live stock: counted from the shop's chests every 5 min by Z:\shop-stock (server); missing file = no stock lines
    fetch('https://assets.craftyplayz.com/minecraft/shop-stock.json', { cache: 'no-store' }).then(function (r) {
      if (!r.ok) throw Error(r.status);
      return r.json();
    }).then(function (st) {
      var when = 'Counted ' + new Date(st.updated).toLocaleString('en-GB', { dateStyle: 'medium', timeStyle: 'short' });
      document.querySelectorAll('.niche[data-id]').forEach(function (li) {
        // "all colours" products ("colours": "{c}_wool") get the total plus one count per dye colour
        var ids = li.dataset.colours ? COLOURS.map(function (c) { return li.dataset.colours.replace('{c}', c); }) : [li.dataset.id];
        var n = ids.reduce(function (sum, id) { return sum + (st.items[id] || 0); }, 0);
        var p = el('p', n ? 'stock' : 'stock out', n ? n.toLocaleString('en-GB') + ' in stock' : 'Sold out');
        p.title = when;
        var txt = li.querySelector('.txt');
        txt.insertBefore(p, li.querySelector('.price').nextSibling);
        if (!li.dataset.colours || !n) return;
        var grid = el('ul', 'colours');
        ids.forEach(function (id, k) {
          var c = st.items[id] || 0, name = COLOURS[k].replace('_', ' ');
          var cell = grid.appendChild(el('li', c ? '' : 'out'));
          var img = new Image(16, 16); img.alt = ''; img.loading = 'lazy';
          img.src = d.icons.replace('{id}', encodeURIComponent(id));
          cell.appendChild(img);
          cell.appendChild(el('span', '', c ? c.toLocaleString('en-GB') : '0'));
          cell.title = name.charAt(0).toUpperCase() + name.slice(1) + ': ' + (c ? c.toLocaleString('en-GB') + ' in stock' : 'sold out');
        });
        txt.appendChild(grid);
      });
    }).catch(function () { });
    // recent sales, no names: written by the shop-log container on the server
    fetch('https://assets.craftyplayz.com/minecraft/shop-sales.json', { cache: 'no-store' }).then(function (r) {
      if (!r.ok) throw Error(r.status);
      return r.json();
    }).then(function (j) {
      var list = j.sales.slice(0, 8);
      if (!list.length) return;
      var ago = function (t) {
        var m = Math.round((Date.now() / 1000 - t) / 60);
        return m < 60 ? m + ' min ago' : m < 1440 ? Math.round(m / 60) + ' h ago' : Math.round(m / 1440) + ' days ago';
      };
      list.forEach(function (s) {
        var li = $('sales-list').appendChild(el('li'));
        li.appendChild(el('span', '', s.items.map(function (i) { return i.n.toLocaleString('en-GB') + ' ' + i.name; }).join(', ')));
        li.appendChild(el('small', '', ago(s.t)));
      });
      $('sales').hidden = false;
    }).catch(function () { });
    document.querySelectorAll('.storey').forEach(function (s) { s.hidden = false; });
    st.hidden = true;
  }).catch(function (e) {
    st.className = 'status error';
    st.textContent = 'The gates of Isengard are shut: the price list could not be loaded (' + e.message + '). Please try again shortly.';
  });
})();
