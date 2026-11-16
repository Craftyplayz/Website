<?php // shared page; data.js gets its file time in the URL so a rebuild is never hidden by a cache
echo str_replace('src="data.js"', 'src="data.js?v=' . @filemtime(__DIR__ . '/data.js') . '"', file_get_contents(__DIR__ . '/../recap.html'));
