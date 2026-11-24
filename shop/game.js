(function () {
  var $ = function (id) { return document.getElementById(id); };
  var st = $('status');
  function el(tag, cls, text) {
    var e = document.createElement(tag);
    if (cls) e.className = cls;
    if (text != null) e.textContent = text;
    return e;
  }
  var slug = location.pathname.replace(/^\/shop\/|\/$/g, '');
  fetch('/shop/data.json').then(function (r) {
    if (!r.ok) throw new Error('HTTP ' + r.status);
    return r.json();
  }).then(function (d) {
    var g = d.casino.games.filter(function (x) { return x.slug === slug; })[0];
    if (!g) {
      st.className = 'status error';
      st.textContent = 'There is no casino game called "' + slug + '". Head back to the casino to see every game.';
      return;
    }
    document.title = g.name + ' · Castle Crafty casino';
    $('name').textContent = g.name;
    $('floor').textContent = d.casino.floor;
    $('price').textContent = g.price;
    if (g.players) $('players').textContent = g.players; else $('players').parentNode.hidden = true;
    (g.pricing || []).forEach(function (p) { $('pricing').appendChild(el('li', null, p)); });
    $('pricing').hidden = !g.pricing;
    if (!g.rules.length) $('rules').appendChild(el('li', null, 'Rules coming soon.'));
    $('open').appendChild(el('b', g.open ? 'note open' : 'note', g.open ? 'Open now' : 'Coming soon'));
    if (g.intro) { $('intro').textContent = g.intro; $('intro').hidden = false; }
    // a nested array is the sub-list of the rule before it
    var last;
    g.rules.forEach(function (r) {
      if (Array.isArray(r)) {
        var ul = last.appendChild(el('ul'));
        r.forEach(function (s) { ul.appendChild(el('li', null, s)); });
      } else last = $('rules').appendChild(el('li', null, r));
    });
    if (!g.coords.length) $('where').appendChild(el('p', 'lead', 'Coming soon: this game is still being built.'));
    g.coords.forEach(function (c) {
      $('where').appendChild(el('p', null, c.label + ': ' + c.xyz + ' in the ' + c.dimension));
      var row = $('where').appendChild(el('div', 'tp'));
      row.appendChild(el('code', null, c.tp));
      var b = row.appendChild(el('button', null, 'Copy teleport command'));
      b.type = 'button';
      b.onclick = function () {
        navigator.clipboard.writeText(c.tp).then(function () { b.textContent = 'Copied'; }, function () { b.textContent = 'Copy failed: select the command instead'; });
      };
    });
    document.querySelectorAll('.storey').forEach(function (s) { s.hidden = false; });
    st.hidden = true;
  }).catch(function (e) {
    st.className = 'status error';
    st.textContent = 'The casino door is stuck: the rules could not be loaded (' + e.message + '). Please try again shortly.';
  });
})();
