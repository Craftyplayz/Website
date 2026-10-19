<?php
$site_page = [];
require $_SERVER['DOCUMENT_ROOT'] . '/includes/bootstrap.php';
?>
<!doctype html>
<html lang="en">
<head>
    <?php page_head(); ?>
<style>
  .fx .container { max-width: 640px; }
  .fx .card {
    margin-bottom: 16px; padding: 16px;
    background: var(--card); border: 1px solid var(--line); border-radius: var(--radius-lg);
  }
  .fx .card h2 { margin: 0 0 12px; font-size: 17px; }
  .fx #dropzone {
    padding: 32px 20px; text-align: center; cursor: pointer;
    background: repeating-linear-gradient(45deg, #262626 0 14px, var(--card) 14px 28px);
    border: 2px dashed var(--line-strong); transition: border-color var(--fast);
  }
  .fx #dropzone.dragover, .fx #dropzone:hover { border-color: var(--accent); }
  .fx .upload-label {
    display: inline-block; padding: 9px 16px; margin: 0;
    background: var(--btn); border: 1px solid var(--btn-line); border-radius: var(--radius);
    color: #fff; font-size: 14px; cursor: pointer; transition: background var(--fast);
  }
  .fx .upload-label:hover { background: #4a9033; }
  .fx #fileName { margin-top: 10px; color: var(--muted); font-size: 13px; word-break: break-all; }
  .fx .field { margin-bottom: 12px; }
  .fx label { display: block; margin-bottom: 4px; color: var(--muted); font-size: 13px; }
  .fx select, .fx input[type="number"] {
    width: 100%; padding: 8px 12px;
    background: #161616; border: 1px solid #4a4a4a; border-radius: var(--radius);
    color: var(--text); font: 14px var(--font); outline: none; transition: border-color var(--fast);
  }
  .fx select:focus, .fx input[type="number"]:focus { border-color: var(--accent); }
  .fx .checkbox-row { display: flex; align-items: center; gap: 8px; }
  .fx .checkbox-row label { margin: 0; color: var(--text); font-size: 14px; }
  .fx input[type="checkbox"] { width: 16px; height: 16px; accent-color: var(--accent); }
  .fx button {
    width: 100%; padding: 10px 16px;
    background: var(--btn2); border: 1px solid var(--line-strong); border-radius: var(--radius);
    color: var(--text); font: 14px var(--font); cursor: pointer; transition: background var(--fast), transform .06s;
  }
  .fx button:hover:not(:disabled) { background: var(--btn2-hover); }
  .fx button:active:not(:disabled) { transform: translateY(1px); }
  .fx button:disabled { opacity: .5; cursor: default; }
  .fx button + button { margin-top: 8px; }
  .fx #processBtn { background: var(--btn); border-color: var(--btn-line); color: #fff; }
  .fx #processBtn:hover:not(:disabled) { background: #4a9033; }
  .fx #progressText { margin-bottom: 8px; color: var(--muted); font-size: 13px; }
  .fx progress { width: 100%; height: 6px; overflow: hidden; border: 0; border-radius: 3px; appearance: none; }
  .fx progress::-webkit-progress-bar { background: var(--btn2); border-radius: 3px; }
  .fx progress::-webkit-progress-value { background: var(--accent); border-radius: 3px; }
  .fx progress::-moz-progress-bar { background: var(--accent); }
  .fx #thumbGrid {
    display: grid; grid-template-columns: repeat(auto-fill, minmax(48px, 1fr)); gap: 6px;
    max-height: 260px; margin-top: 10px; overflow-y: auto;
  }
  .fx #thumbGrid img { width: 100%; aspect-ratio: 1 / 1; border: 1px solid var(--line); border-radius: 6px; image-rendering: pixelated; }
  .fx #frameCount { color: var(--muted); font-size: 14px; }
  .fx .hidden { display: none !important; }
  .fx .error {
    margin-top: 12px; padding: 10px 14px; font-size: 14px;
    background: var(--danger-bg); border: 1px solid var(--danger-line); border-radius: var(--radius);
  }
</style>
</head>
<body>
    <?php site_header(); ?>
    <main class="page fx" id="main">
    <?= site_breadcrumbs(site_current_path()) ?>
<h1 class="page-title">Frame Extractor</h1>
<p class="page-lede">Upload a GIF or MP4, split it into 128×128 frames, download frames or 6×4 collage sheets.</p>

<div class="container">

  <div class="card" id="dropzone">
    <input type="file" id="fileInput" accept=".gif,.mp4,image/gif,video/mp4" hidden>
    <label for="fileInput" class="upload-label">Choose a .gif or .mp4 file</label>
    <div id="fileName"></div>
  </div>

  <div class="card hidden" id="optionsCard">
    <h2>Options</h2>
    <div class="field">
      <label>Output size: 128 × 128 (fixed)</label>
    </div>
    <div class="field hidden" id="videoOptions">
      <label for="captureMode">Frame capture mode (video)</label>
      <select id="captureMode">
        <option value="native">Every rendered frame (recommended — Chrome/Edge)</option>
        <option value="fps">Fixed FPS sampling</option>
      </select>
    </div>
    <div class="field hidden" id="fpsWrap">
      <label for="fpsInput">FPS</label>
      <input type="number" id="fpsInput" value="20" min="1" max="60">
    </div>
    <div class="field checkbox-row">
      <input type="checkbox" id="smoothingInput">
      <label for="smoothingInput">Smooth resize (unchecked = crisp pixel-art resize)</label>
    </div>
    <button id="processBtn">Process</button>
    <div id="errorBox" class="error hidden"></div>
  </div>

  <div class="card hidden" id="progressCard">
    <div id="progressText">Working...</div>
    <progress id="progressBar" value="0" max="100"></progress>
  </div>

  <div class="card hidden" id="previewCard">
    <h2>Preview</h2>
    <div id="frameCount"></div>
    <div id="thumbGrid"></div>
  </div>

  <div class="card hidden" id="downloadCard">
    <h2>Download</h2>
    <button id="downloadFramesBtn">Download frames (ZIP)</button>
    <button id="downloadCollageBtn">Download 6×4 collage sheets (ZIP)</button>
  </div>

</div>

<script src="https://cdnjs.cloudflare.com/ajax/libs/jszip/3.10.1/jszip.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/FileSaver.js/2.0.5/FileSaver.min.js"></script>

<script type="module">
import { parseGIF, decompressFrames } from "https://esm.sh/gifuct-js@2.1.2";

(function () {
  const OUT = 128;

  const fileInput = document.getElementById('fileInput');
  const dropzone = document.getElementById('dropzone');
  const fileNameEl = document.getElementById('fileName');
  const optionsCard = document.getElementById('optionsCard');
  const videoOptions = document.getElementById('videoOptions');
  const captureMode = document.getElementById('captureMode');
  const fpsWrap = document.getElementById('fpsWrap');
  const fpsInput = document.getElementById('fpsInput');
  const smoothingInput = document.getElementById('smoothingInput');
  const processBtn = document.getElementById('processBtn');
  const errorBox = document.getElementById('errorBox');
  const progressCard = document.getElementById('progressCard');
  const progressText = document.getElementById('progressText');
  const progressBar = document.getElementById('progressBar');
  const previewCard = document.getElementById('previewCard');
  const frameCountEl = document.getElementById('frameCount');
  const thumbGrid = document.getElementById('thumbGrid');
  const downloadCard = document.getElementById('downloadCard');
  const downloadFramesBtn = document.getElementById('downloadFramesBtn');
  const downloadCollageBtn = document.getElementById('downloadCollageBtn');

  let currentFile = null;
  let fileKind = null; // 'gif' | 'mp4'
  let baseName = '';
  let outFrames = []; // array of canvases, 128x128

  function resetResults() {
    outFrames = [];
    previewCard.classList.add('hidden');
    downloadCard.classList.add('hidden');
    thumbGrid.innerHTML = '';
    frameCountEl.textContent = '';
    errorBox.classList.add('hidden');
  }

  function showError(msg) {
    errorBox.textContent = msg;
    errorBox.classList.remove('hidden');
  }

  ['dragover', 'dragenter'].forEach(evt => {
    dropzone.addEventListener(evt, e => {
      e.preventDefault();
      dropzone.classList.add('dragover');
    });
  });
  ['dragleave', 'drop'].forEach(evt => {
    dropzone.addEventListener(evt, e => {
      e.preventDefault();
      dropzone.classList.remove('dragover');
    });
  });
  dropzone.addEventListener('drop', e => {
    if (e.dataTransfer.files && e.dataTransfer.files[0]) {
      fileInput.files = e.dataTransfer.files;
      handleFile(e.dataTransfer.files[0]);
    }
  });

  fileInput.addEventListener('change', () => {
    if (fileInput.files[0]) handleFile(fileInput.files[0]);
  });

  function handleFile(file) {
    resetResults();
    const name = file.name.toLowerCase();
    if (name.endsWith('.gif') || file.type === 'image/gif') {
      fileKind = 'gif';
    } else if (name.endsWith('.mp4') || file.type === 'video/mp4') {
      fileKind = 'mp4';
    } else {
      showError('Unsupported file type. Please upload a .gif or .mp4 file.');
      optionsCard.classList.add('hidden');
      return;
    }
    currentFile = file;
    baseName = file.name.replace(/\.[^.]+$/, '');
    fileNameEl.textContent = file.name;
    videoOptions.classList.toggle('hidden', fileKind !== 'mp4');
    fpsWrap.classList.add('hidden');
    optionsCard.classList.remove('hidden');
  }

  captureMode.addEventListener('change', () => {
    fpsWrap.classList.toggle('hidden', captureMode.value !== 'fps');
  });

  processBtn.addEventListener('click', async () => {
    if (!currentFile) return;
    resetResults();
    errorBox.classList.add('hidden');
    processBtn.disabled = true;
    progressCard.classList.remove('hidden');
    progressBar.value = 0;
    progressText.textContent = 'Starting...';

    try {
      if (fileKind === 'gif') {
        outFrames = await processGif(currentFile, smoothingInput.checked, updateProgress);
      } else {
        outFrames = await processMp4(currentFile, smoothingInput.checked, updateProgress);
      }
      if (outFrames.length === 0) {
        throw new Error('No frames were extracted from this file.');
      }
      showPreview(outFrames);
      downloadCard.classList.remove('hidden');
    } catch (err) {
      console.error(err);
      showError(err.message || 'Something went wrong while processing this file.');
    } finally {
      processBtn.disabled = false;
      progressCard.classList.add('hidden');
    }
  });

  function updateProgress(done, total, label) {
    if (total > 0) {
      progressBar.value = Math.round((done / total) * 100);
    }
    progressText.textContent = label || `Processed ${done}${total ? ' / ' + total : ''} frames...`;
  }

  // ---------- GIF processing ----------
  async function processGif(file, smoothing, onProgress) {
    const buffer = await file.arrayBuffer();
    const gif = parseGIF(buffer);
    const frames = decompressFrames(gif, true);
    const gifW = gif.lsd.width;
    const gifH = gif.lsd.height;

    const compositeCanvas = document.createElement('canvas');
    compositeCanvas.width = gifW;
    compositeCanvas.height = gifH;
    const cctx = compositeCanvas.getContext('2d');

    const results = [];

    for (let i = 0; i < frames.length; i++) {
      const f = frames[i];

      const patchCanvas = document.createElement('canvas');
      patchCanvas.width = f.dims.width;
      patchCanvas.height = f.dims.height;
      const pctx = patchCanvas.getContext('2d');
      const imgData = new ImageData(
        new Uint8ClampedArray(f.patch),
        f.dims.width,
        f.dims.height
      );
      pctx.putImageData(imgData, 0, 0);
      cctx.drawImage(patchCanvas, f.dims.left, f.dims.top);

      const outCanvas = document.createElement('canvas');
      outCanvas.width = OUT;
      outCanvas.height = OUT;
      const octx = outCanvas.getContext('2d');
      octx.imageSmoothingEnabled = smoothing;
      octx.drawImage(compositeCanvas, 0, 0, gifW, gifH, 0, 0, OUT, OUT);
      results.push(outCanvas);

      // handle disposal before next frame draws
      if (f.disposalType === 2) {
        cctx.clearRect(f.dims.left, f.dims.top, f.dims.width, f.dims.height);
      }
      // disposalType 3 (restore to previous) is uncommon; treated as no-op here

      onProgress(i + 1, frames.length, `Decoded frame ${i + 1} / ${frames.length}`);
      if (i % 5 === 0) await new Promise(r => setTimeout(r, 0));
    }

    return results;
  }

  // ---------- MP4 processing ----------
  function processMp4(file, smoothing, onProgress) {
    return new Promise((resolve, reject) => {
      const url = URL.createObjectURL(file);
      const video = document.createElement('video');
      video.src = url;
      video.muted = true;
      video.playsInline = true;
      video.preload = 'auto';

      const results = [];
      const canvas = document.createElement('canvas');
      canvas.width = OUT;
      canvas.height = OUT;
      const ctx = canvas.getContext('2d');
      ctx.imageSmoothingEnabled = smoothing;

      function captureFrame() {
        const outCanvas = document.createElement('canvas');
        outCanvas.width = OUT;
        outCanvas.height = OUT;
        const octx = outCanvas.getContext('2d');
        octx.imageSmoothingEnabled = smoothing;
        octx.drawImage(video, 0, 0, video.videoWidth, video.videoHeight, 0, 0, OUT, OUT);
        results.push(outCanvas);
      }

      video.onerror = () => {
        URL.revokeObjectURL(url);
        reject(new Error('Could not load this MP4 file.'));
      };

      video.onloadedmetadata = async () => {
        const useNative = captureMode.value === 'native' && typeof video.requestVideoFrameCallback === 'function';

        if (useNative) {
          let stopped = false;
          const onFrame = (now, metadata) => {
            if (stopped) return;
            captureFrame();
            onProgress(results.length, 0, `Captured ${results.length} frames (${metadata.mediaTime.toFixed(2)}s / ${video.duration.toFixed(2)}s)`);
            video.requestVideoFrameCallback(onFrame);
          };
          video.onended = () => {
            stopped = true;
            URL.revokeObjectURL(url);
            resolve(results);
          };
          try {
            video.requestVideoFrameCallback(onFrame);
            await video.play();
          } catch (e) {
            URL.revokeObjectURL(url);
            reject(new Error('Playback was blocked by the browser. Try the "Fixed FPS sampling" mode instead.'));
          }
        } else {
          // fixed FPS sampling via seek
          const fps = Math.max(1, Math.min(60, parseInt(fpsInput.value, 10) || 20));
          const duration = video.duration;
          const step = 1 / fps;
          const times = [];
          for (let t = 0; t < duration; t += step) times.push(t);
          if (times.length === 0) times.push(0);

          for (let i = 0; i < times.length; i++) {
            await seekTo(video, times[i]);
            captureFrame();
            onProgress(i + 1, times.length, `Captured frame ${i + 1} / ${times.length}`);
          }
          URL.revokeObjectURL(url);
          resolve(results);
        }
      };
    });
  }

  function seekTo(video, t) {
    return new Promise(resolve => {
      const onSeeked = () => {
        video.removeEventListener('seeked', onSeeked);
        resolve();
      };
      video.addEventListener('seeked', onSeeked);
      video.currentTime = t;
    });
  }

  // ---------- Preview ----------
  function showPreview(frames) {
    frameCountEl.textContent = `${frames.length} frame${frames.length === 1 ? '' : 's'} extracted`;
    thumbGrid.innerHTML = '';
    const maxThumbs = 60;
    const showCount = Math.min(frames.length, maxThumbs);
    for (let i = 0; i < showCount; i++) {
      const img = document.createElement('img');
      img.src = frames[i].toDataURL('image/png');
      thumbGrid.appendChild(img);
    }
    previewCard.classList.remove('hidden');
  }

  // ---------- Downloads ----------
  function canvasToBlob(canvas) {
    return new Promise(resolve => canvas.toBlob(blob => resolve(blob), 'image/png'));
  }

  function padIndex(i, total) {
    const len = Math.max(2, String(Math.max(total - 1, 0)).length);
    return String(i).padStart(len, '0');
  }

  downloadFramesBtn.addEventListener('click', async () => {
    downloadFramesBtn.disabled = true;
    downloadFramesBtn.textContent = 'Zipping...';
    try {
      const zip = new JSZip();
      for (let i = 0; i < outFrames.length; i++) {
        const blob = await canvasToBlob(outFrames[i]);
        const idx = padIndex(i, outFrames.length);
        zip.file(`${idx}_${baseName}.png`, blob);
      }
      const content = await zip.generateAsync({ type: 'blob' });
      saveAs(content, `${baseName}_frames.zip`);
    } catch (err) {
      showError('Failed to build the frames ZIP: ' + err.message);
    } finally {
      downloadFramesBtn.disabled = false;
      downloadFramesBtn.textContent = 'Download frames (ZIP)';
    }
  });

  downloadCollageBtn.addEventListener('click', async () => {
    downloadCollageBtn.disabled = true;
    downloadCollageBtn.textContent = 'Zipping...';
    try {
      const cols = 6, rows = 4;
      const perSheet = cols * rows;
      const sheetW = cols * OUT;
      const sheetH = rows * OUT;
      const totalSheets = Math.ceil(outFrames.length / perSheet);

      const zip = new JSZip();
      for (let s = 0; s < totalSheets; s++) {
        const sheetCanvas = document.createElement('canvas');
        sheetCanvas.width = sheetW;
        sheetCanvas.height = sheetH;
        const sctx = sheetCanvas.getContext('2d');
        for (let j = 0; j < perSheet; j++) {
          const idx = s * perSheet + j;
          if (idx >= outFrames.length) break;
          const col = j % cols;
          const row = Math.floor(j / cols);
          sctx.drawImage(outFrames[idx], col * OUT, row * OUT);
        }
        const blob = await canvasToBlob(sheetCanvas);
        const sIdx = padIndex(s, totalSheets);
        zip.file(`${sIdx}_${baseName}_6-4.png`, blob);
      }
      const content = await zip.generateAsync({ type: 'blob' });
      saveAs(content, `${baseName}_collage_6-4.zip`);
    } catch (err) {
      showError('Failed to build the collage ZIP: ' + err.message);
    } finally {
      downloadCollageBtn.disabled = false;
      downloadCollageBtn.textContent = 'Download 6×4 collage sheets (ZIP)';
    }
  });
})();
</script>
    </main>

    <?php site_footer(); ?>
</body>
</html>