<?php
$site_page = [];
require $_SERVER['DOCUMENT_ROOT'] . '/includes/bootstrap.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <?php page_head(); ?>
<style>
  /* one cell size drives the whole grid, so it fits a 320px phone and stops growing at 42px */
  .tnt { --s: clamp(24px, calc((100vw - 120px) / 10), 42px); display: flex; flex-direction: column; align-items: center; }
  .tnt > .crumbs, .tnt > .page-title, .tnt > .page-lede { align-self: stretch; }

  .grid-wrapper {
    max-width: 100%;
    padding: 12px;
    background: var(--card);
    border: 1px solid var(--line);
    border-radius: var(--radius-lg);
  }

  .col-labels, .row {
    display: grid;
    grid-template-columns: 20px repeat(10, var(--s)) 20px;
    margin-bottom: 4px;
  }

  .col-labels.bottom { margin: 4px 0 0; }

  .col-labels span, .row-label {
    display: flex;
    align-items: center;
    justify-content: center;
    color: var(--muted);
    font-size: 12px;
  }

  .cell {
    width: calc(var(--s) - 4px);
    height: calc(var(--s) - 4px);
    margin: 2px;
    padding: 0;
    background: var(--sunken);
    border: 1px solid var(--line);
    border-radius: 6px;
    color: var(--muted);
    font: 10px var(--font);
    cursor: pointer;
    transition: background var(--fast), border-color var(--fast), color var(--fast);
    user-select: none;
  }

  .cell:hover { background: var(--card2); border-color: var(--line-strong); color: var(--text); }
  .cell.selected { background: var(--accent-soft); border-color: var(--btn-line); color: var(--accent); }

  .readout {
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    gap: 14px;
    width: 100%;
    max-width: 620px;
    min-height: 90px;
    margin-top: 16px;
    padding: 16px;
    background: var(--card);
    border: 1px solid var(--line);
    border-radius: var(--radius-lg);
  }

  .readout .placeholder { color: var(--muted); font-size: 14px; }
  .readout .target-label { color: var(--accent); font-size: 22px; font-weight: 700; letter-spacing: .08em; }

  .settings { display: grid; grid-template-columns: repeat(auto-fit, minmax(80px, 1fr)); gap: 12px 24px; width: 100%; }
  .setting { display: flex; flex-direction: column; align-items: center; gap: 3px; }
  .setting .label { color: var(--muted); font-size: 12px; line-height: 1.3; text-align: center; }
  .setting .value { font-size: 26px; font-weight: 700; }
</style>
</head>
<body>
    <?php site_header(); ?>
    <main class="page tnt" id="main">
    <?= site_breadcrumbs(site_current_path()) ?>
<h1 class="page-title">TNT Cannon Targeting</h1>
<p class="page-lede">Pick a target square to get the cannon settings.</p>

<div class="grid-wrapper">
  <div class="col-labels">
    <span></span>
    <span>0</span><span>1</span><span>2</span><span>3</span><span>4</span>
    <span>5</span><span>6</span><span>7</span><span>8</span><span>9</span>
    <span></span>
  </div>
  <div id="grid"></div>
  <div class="col-labels bottom">
    <span></span>
    <span>0</span><span>1</span><span>2</span><span>3</span><span>4</span>
    <span>5</span><span>6</span><span>7</span><span>8</span><span>9</span>
    <span></span>
  </div>
</div>

<div class="readout" id="readout">
  <span class="placeholder">Select a square to view cannon settings</span>
</div>

<script>
  const rows = ['A','B','C','D','E','F','G','H','I','J'];

  const ctRaw = `37,4,8,40
31,2,8,4
15,4,6,25
5,5,5,26
39,6,6,39
26,5,5,5
25,6,4,15
4,8,2,31
40,8,4,37
36,8,3,24
11,3,7,30
45,2,8,7
40,5,7,41
35,5,6,36
4,6,4,35
36,6,5,35
41,7,5,40
7,8,2,45
30,7,3,11
27,8,2,26
20,3,7,33
6,5,6,47
47,4,7,30
37,5,6,38
48,7,7,48
38,6,5,37
30,7,4,47
47,6,5,6
33,7,3,20
29,8,2,34
24,3,7,36
23,2,7,11
19,3,6,16
5,4,5,14
33,5,5,33
14,5,4,5
16,6,3,19
11,7,2,23
36,7,3,24
17,8,1,13
24,2,7,25
10,3,6,28
30,3,6,21
33,3,6,5
31,4,5,14
5,6,3,33
21,6,3,30
28,6,3,10
25,7,2,24
20,8,1,43
33,2,7,28
47,2,7,17
36,4,6,39
25,5,5,46
18,5,4,37
46,5,5,25
39,6,4,36
17,7,2,47
28,7,2,33
43,8,3,42
37,3,7,41
22,4,6,47
13,4,5,40
48,3,6,12
41,4,5,22
12,6,3,48
40,5,4,13
47,6,4,22
41,7,3,37
44,8,3,45
41,3,7,42
47,4,7,45
1,3,5,21
8,3,5,4
21,4,4,21
4,5,3,8
21,5,3,1
45,7,4,47
42,7,3,41
38,7,2,23
7,3,6,44
34,2,6,25
20,3,5,31
8,4,4,34
28,4,4,28
34,4,4,8
31,5,3,20
25,6,2,34
44,6,3,7
40,7,2,29
12,2,6,35
42,2,6,27
26,3,5,35
14,4,4,40
32,4,4,32
40,4,4,14
35,5,3,26
27,6,2,42
35,6,2,12
42,7,2,33`;

  const settings = ctRaw.trim().split('\n').map(line => {
    const [a, b, c, d] = line.split(',').map(Number);
    return { a, b, c, d };
  });

  // Build label->index map: A0=0, A1=1, ... J9=99
  const labelToIndex = {};
  rows.forEach((r, ri) => {
    for (let c = 0; c < 10; c++) {
      labelToIndex[r + c] = ri * 10 + c;
    }
  });

  const grid = document.getElementById('grid');
  const readout = document.getElementById('readout');
  let selectedCell = null;

  rows.forEach(r => {
    const rowEl = document.createElement('div');
    rowEl.className = 'row';

    const rowLabel = document.createElement('div');
    rowLabel.className = 'row-label';
    rowLabel.textContent = r;
    rowEl.appendChild(rowLabel);

    for (let c = 0; c < 10; c++) {
      const label = r + c;
      const idx = labelToIndex[label];
      const cell = document.createElement('button');
      cell.type = 'button';
      cell.className = 'cell';
      cell.textContent = label;
      cell.dataset.label = label;
      cell.dataset.idx = idx;

      cell.addEventListener('click', () => {
        if (selectedCell) selectedCell.classList.remove('selected');
        cell.classList.add('selected');
        selectedCell = cell;
        showSettings(label, idx);
      });

      rowEl.appendChild(cell);
    }

    // right label
    const rowLabelR = document.createElement('div');
    rowLabelR.className = 'row-label';
    rowLabelR.textContent = r;
    rowEl.appendChild(rowLabelR);

    grid.appendChild(rowEl);
  });

  function showSettings(label, idx) {
    const s = settings[idx];
    readout.innerHTML = `
      <div class="target-label">${label}</div>
      <div class="settings">
        <div class="setting">
          <span class="label">Left<br>Minecart</span>
          <span class="value">${s.a}</span>
        </div>
        <div class="setting">
          <span class="label">Left<br>Lectern</span>
          <span class="value">${s.b}</span>
        </div>
        <div class="setting">
          <span class="label">Right<br>Lectern</span>
          <span class="value">${s.c}</span>
        </div>
        <div class="setting">
          <span class="label">Right<br>Minecart</span>
          <span class="value">${s.d}</span>
        </div>
      </div>
    `;
  }
</script>
    </main>
    <?php site_footer(); ?>
</body>
</html>