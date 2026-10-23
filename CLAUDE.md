# craftyplayz.com

**Before changing anything visual** (a page's HTML, CSS, layout, components, header/menus, `data/site.json`, or adding a new page), read `_dev/handoffs/building-pages.md` in full and follow it: design philosophy, header/`data/site.json` rules, dark-theme tokens, type and spacing, responsive layout, API caching, components and the per-page checklist. Run its checklist (section 16) before finishing.

- `/design/` (`design/index.php`) shows the same design rules with live component samples; it's the page to point other models at. A design rule changed in one changes in the other.
- New pages start from `_templates/page.php`.
- `data/site.json` is the only source of the header menus, search, breadcrumbs and titles; every new or moved page goes there.
- When several sessions share this folder, also follow `_dev/handoffs/README.md` (ports, staying in your tab, git rules).

## After every edit: commit, push, deploy

The owner wants every change live without asking. Once a change is finished and checked:

1. Commit it on `main`.
2. Push to GitHub: `git -c pack.useSparse=false push origin main`.
3. Upload to the live server: `bash _dev/deploy.sh` (SSH key `~/.ssh/ionos`, uploads only changed files; `--dry-run` previews).
