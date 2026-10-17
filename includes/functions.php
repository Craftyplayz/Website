<?php
// Shared helpers for the site layout. Everything here reads data/site.json,
// so adding a page to that file puts it in the menu, search and breadcrumbs.
// Written for PHP 7.4+ (no PHP 8-only functions).

function h($s)
{
    return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');
}

// URL for a file in the site with ?v=<last modified> so it can be cached for a year
function asset($path)
{
    $file = SITE_ROOT . $path;
    return $path . (is_file($file) ? '?v=' . filemtime($file) : '');
}

function site_data()
{
    static $data = null;
    if ($data === null) {
        $json = @file_get_contents(SITE_ROOT . '/data/site.json');
        $data = $json ? json_decode($json, true) : null;
        if (!is_array($data)) $data = ['name' => 'Crafty Playz', 'sections' => [], 'links' => []];
    }
    return $data;
}

// "/extras/trump.php", "/extras/trump" and "/Extras/Trump/" all become "/extras/trump"
function site_norm($path)
{
    $path = strtolower(rawurldecode((string)parse_url($path, PHP_URL_PATH)));
    $path = preg_replace('#/index\.(php|html?)$#', '/', $path);
    $path = preg_replace('#\.(php|html?)$#', '', $path);
    return rtrim($path, '/');
}

function site_current_path()
{
    $uri = isset($_SERVER['REQUEST_URI']) ? $_SERVER['REQUEST_URI'] : '/';
    return (string)parse_url($uri, PHP_URL_PATH);
}

// Where a path sits in site.json: ['section' => .., 'group' => .., 'page' => ..]
// Exact page match first, then the longest folder page that contains it, then the section.
function site_find($path)
{
    $want = site_norm($path);
    $best = null;
    $bestLen = -1;
    foreach (site_data()['sections'] as $section) {
        foreach ($section['groups'] as $group) {
            foreach ($group['pages'] as $page) {
                $url = site_norm($page['url']);
                if ($url === $want) return ['section' => $section, 'group' => $group, 'page' => $page];
                $isFolder = substr($page['url'], -1) === '/';
                if ($isFolder && $url !== '' && strpos($want . '/', $url . '/') === 0 && strlen($url) > $bestLen) {
                    $best = ['section' => $section, 'group' => $group, 'page' => $page, 'inside' => true];
                    $bestLen = strlen($url);
                }
            }
        }
    }
    if ($best) return $best;
    foreach (site_data()['sections'] as $section) {
        $url = site_norm($section['url']);
        if ($url !== '' && strpos($want . '/', $url . '/') === 0) return ['section' => $section, 'group' => null, 'page' => null];
    }
    foreach (site_data()['links'] as $link) {
        if (site_norm($link['url']) === $want) return ['section' => null, 'group' => null, 'page' => $link];
    }
    return null;
}

function site_page_title($path)
{
    $found = site_find($path);
    return $found && $found['page'] && empty($found['inside']) ? $found['page']['title'] : null;
}

function site_breadcrumbs($path)
{
    if (site_norm($path) === '') return '';
    $crumbs = ['<a href="/">Home</a>'];
    $found = site_find($path);
    if ($found && $found['section']) {
        $crumbs[] = '<a href="' . h($found['section']['url']) . '">' . h($found['section']['title']) . '</a>';
        if ($found['page'] && empty($found['inside']) && site_norm($found['page']['url']) !== site_norm($found['section']['url'])) {
            $crumbs[] = '<span aria-current="page">' . h($found['page']['title']) . '</span>';
        }
    } elseif ($found && $found['page']) {
        $crumbs[] = '<span aria-current="page">' . h($found['page']['title']) . '</span>';
    } else {
        // not in site.json yet: fall back to the folder names
        $parts = array_values(array_filter(explode('/', rawurldecode($path)), 'strlen'));
        $url = '/';
        foreach ($parts as $i => $part) {
            if (preg_match('/^index\.(php|html?)$/', $part)) break;
            $url .= $part . '/';
            $label = ucwords(str_replace(['_', '-'], ' ', preg_replace('/\.(php|html?)$/', '', $part)));
            $crumbs[] = $i < count($parts) - 1 ? '<a href="' . h($url) . '">' . h($label) . '</a>' : '<span aria-current="page">' . h($label) . '</span>';
        }
    }
    return '<nav class="crumbs" aria-label="Breadcrumb">' . implode(' <span class="crumbs-sep">›</span> ', $crumbs) . '</nav>';
}

function site_icon($name)
{
    $icons = [
        'caret' => '<svg viewBox="0 0 10 6" width="10" height="6" aria-hidden="true"><path d="M1 1l4 4 4-4" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"/></svg>',
        'search' => '<svg viewBox="0 0 16 16" width="15" height="15" aria-hidden="true"><circle cx="7" cy="7" r="5" fill="none" stroke="currentColor" stroke-width="1.6"/><path d="M11 11l3.5 3.5" stroke="currentColor" stroke-width="1.6" stroke-linecap="round"/></svg>',
    ];
    return isset($icons[$name]) ? $icons[$name] : '';
}

// The site header. $path defaults to the current URL (header-fragment.php passes one in).
function site_header($path = null)
{
    if ($path === null) $path = site_current_path();
    $data = site_data();
    $found = site_find($path);
    $activeId = $found && $found['section'] ? $found['section']['id'] : null;
    $current = site_norm($path);
    $logo = $found && $found['group'] && !empty($found['group']['logo']) ? $found['group']['logo'] : '/images/catsmall.webp';
    $title = site_page_title($path);

    echo '<header class="site-header" id="header"' . ($title ? ' data-page-title="' . h($title) . '"' : '') . '>';
    echo '<div class="sh-inner">';
    echo '<a class="sh-logo" href="/" aria-label="' . h($data['name']) . ' home"><img src="' . h($logo) . '" alt="" width="36" height="36"></a>';
    echo '<nav class="sh-nav" id="sh-menu" aria-label="Main"><ul class="sh-list">';
    foreach ($data['sections'] as $section) {
        $id = 'sh-m-' . $section['id'];
        $active = $section['id'] === $activeId;
        echo '<li class="sh-item has-menu' . ($active ? ' is-active' : '') . '">';
        echo '<a class="sh-top" href="' . h($section['url']) . '"' . ($active ? ' aria-current="true"' : '') . '>' . h($section['title']) . '</a>';
        echo '<button class="sh-caret" type="button" aria-expanded="false" aria-controls="' . $id . '" aria-label="' . h($section['title']) . ' menu">' . site_icon('caret') . '</button>';
        echo '<div class="sh-mega" id="' . $id . '"><div class="sh-cols">';
        foreach ($section['groups'] as $group) {
            $links = '';
            foreach ($group['pages'] as $page) {
                if (isset($page['nav']) && $page['nav'] === false) continue;
                $here = site_norm($page['url']) === $current ? ' aria-current="page"' : '';
                $links .= '<li><a href="' . h($page['url']) . '"' . $here . '>' . h($page['title']) . '</a></li>';
            }
            if ($links === '') continue;
            echo '<div class="sh-col"><h3>' . h($group['title']) . '</h3><ul>' . $links . '</ul></div>';
        }
        echo '</div><a class="sh-all" href="' . h($section['url']) . '">All ' . h(strtolower($section['title'])) . ' →</a></div>';
        echo '</li>';
    }
    foreach ($data['links'] as $link) {
        $here = site_norm($link['url']) === $current;
        echo '<li class="sh-item' . ($here ? ' is-active' : '') . '"><a class="sh-top" href="' . h($link['url']) . '"' . ($here ? ' aria-current="page"' : '') . '>' . h($link['title']) . '</a></li>';
    }
    echo '</ul></nav>';
    echo '<div class="sh-search" role="search">' . site_icon('search') .
        '<input type="search" id="sh-q" placeholder="Search" aria-label="Search the site" autocomplete="off" spellcheck="false">' .
        '<kbd class="sh-kbd">/</kbd><div class="sh-results" id="sh-results" role="listbox" hidden></div></div>';
    echo '<button class="sh-burger" type="button" aria-expanded="false" aria-controls="sh-menu" aria-label="Menu"><span></span><span></span><span></span></button>';
    echo '</div>';

    // section sub-nav (e.g. the Captainalism shop) when the group asks for one
    if ($found && $found['group'] && !empty($found['group']['subnav'])) {
        echo '<nav class="sh-sub" aria-label="' . h($found['group']['title']) . '"><span class="sh-sub-title">' . h($found['group']['title']) . '</span>';
        foreach ($found['group']['pages'] as $page) {
            $here = site_norm($page['url']) === $current;
            echo '<a href="' . h($page['url']) . '"' . ($here ? ' aria-current="page"' : '') . '>' . h($page['title']) . '</a>';
        }
        echo '</nav>';
    }
    echo '</header>';
}

// The site footer. Breadcrumbs only show here on old-style pages; new pages print them under the header.
function site_footer($path = null, $crumbs = true)
{
    if ($path === null) $path = site_current_path();
    $data = site_data();
    echo '<footer class="site-footer">';
    if ($crumbs) echo site_breadcrumbs($path);
    echo '<nav class="sf-links" aria-label="Sections">';
    foreach ($data['sections'] as $section) echo '<a href="' . h($section['url']) . '">' . h($section['title']) . '</a>';
    foreach ($data['links'] as $link) echo '<a href="' . h($link['url']) . '">' . h($link['title']) . '</a>';
    echo '</nav>';
    echo '<p class="sf-meta">© ' . date('Y') . ' CraftyPlayz<span class="sf-cat" id="catfact"></span></p>';
    echo '</footer>';
}

// <head> contents for new-style pages (see _templates/page.php)
function page_head()
{
    global $site_page;
    $p = is_array($site_page) ? $site_page : [];
    $path = site_current_path();
    $name = site_data()['name'];
    $title = isset($p['title']) ? $p['title'] : site_page_title($path);
    $found = site_find($path);
    $desc = isset($p['description']) ? $p['description'] : ($found && $found['page'] && isset($found['page']['description']) ? $found['page']['description'] : '');

    echo '<meta charset="UTF-8">' . "\n";
    echo '<meta name="viewport" content="width=device-width, initial-scale=1.0">' . "\n";
    echo '<meta name="color-scheme" content="dark">' . "\n";
    echo '<meta name="theme-color" content="#202020">' . "\n";
    echo '<title>' . h($title ? $title . ' · ' . $name : $name) . '</title>' . "\n";
    if ($desc !== '') echo '<meta name="description" content="' . h($desc) . '">' . "\n";
    echo '<link rel="icon" href="/favicon.ico">' . "\n";
    echo '<link rel="stylesheet" href="' . h(asset('/assets/css/global.css')) . '">' . "\n";
    if (!empty($p['components'])) echo '<link rel="stylesheet" href="' . h(asset('/assets/css/components.css')) . '">' . "\n";
    foreach (isset($p['css']) ? (array)$p['css'] : [] as $css) echo '<link rel="stylesheet" href="' . h(asset($css)) . '">' . "\n";
    echo '<script src="' . h(asset('/assets/js/site.js')) . '" defer></script>' . "\n";
    if (!empty($p['ui'])) echo '<script src="' . h(asset('/assets/js/ui.js')) . '" defer></script>' . "\n";
    $GLOBALS['site_head_done'] = true;
}
