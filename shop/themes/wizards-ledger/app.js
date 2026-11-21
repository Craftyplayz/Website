const $ = id => document.getElementById(id);
const esc = s => String(s).replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
fetch('/shop/data.json').then(r => { if (!r.ok) throw 0; return r.json(); }).then(d => {
  $('shop').textContent = d.shop;
  $('wares').innerHTML = d.products.map((p, i) =>
    `<li><img src="${esc(d.icons.replace('{id}', encodeURIComponent(p.id)))}" width="32" height="32" alt="" ${i > 8 ? 'loading="lazy"' : ''}>` +
    `<span class="n">${esc(p.name)}</span><span class="p">${esc(p.price)}</span></li>`).join('');
  $('bookprice').textContent = d.books.price;
  $('books').innerHTML = d.books.list.map(b => `<li>${esc(b)}</li>`).join('');
  $('reqtext').textContent = d.requests.text;
  $('reqex').textContent = d.requests.examples;
  $('contacts').innerHTML = d.requests.contacts.map(c => `<span>${esc(c)}</span>`).join('');
  $('casino').textContent = d.casino.status;
  $('status').remove();
  $('content').hidden = false;
}).catch(() => {
  const s = $('status'); s.className = 'status err';
  s.textContent = 'The ledger could not be opened. The price list failed to load; please try again shortly.';
});
