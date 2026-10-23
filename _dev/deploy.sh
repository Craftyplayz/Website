#!/usr/bin/env bash
# Upload changed files to the live site over SSH (key: ~/.ssh/ionos).
# Usage: bash _dev/deploy.sh [--dry-run]
# Uploads every file in HEAD whose committed contents differ from the server, and removes
# files deleted in git since the last deploy. Untracked server files (php/config.php,
# private/.htpasswd, caches), skip-worktree files (private/.htaccess) and the files listed
# in the skip step below are never touched.
set -euo pipefail
HOST=u112162320@access961561004.webspace-data.io
DIR=Craftyplayz.com
STAMP=.craftyplayz-deployed   # last deployed commit, in the SFTP home (outside the web root)
SSH=(ssh -i ~/.ssh/ionos -o BatchMode=yes "$HOST")
cd "$(git rev-parse --show-toplevel)"

[ "$(git branch --show-current)" = main ] || { echo "Switch to main first."; exit 1; }
[ -z "$(git status --porcelain)" ] || { echo "Commit or stash your changes first."; exit 1; }
dry=; [ "${1:-}" = --dry-run ] && dry=1
tmp=$(mktemp -d); trap 'rm -rf "$tmp"' EXIT

# compare git blob hashes, not files on disk: the PC checks out CRLF, the server has the committed LF
# never upload: skip-worktree files, plus tracked files the live site writes to itself
{ git ls-files -v | sed -n 's/^S //p'; echo minecraft/projects/metro/stations.json; } > "$tmp/skip"
# shortcut: paths with quotes, backslashes or newlines come out quoted and get skipped; use -z if one appears
git -c core.quotepath=false ls-tree -r --format='%(objecttype)%x09%(objectname)%x09%(path)' HEAD |
  awk -F'\t' 'NR==FNR{s[$0];next} $1=="blob" && !($3 in s){print $2"\t"$3}' "$tmp/skip" - | sort > "$tmp/local"
cut -f2 "$tmp/local" | "${SSH[@]}" "cd $DIR"' && while IFS= read -r f; do [ -f "$f" ] && printf "%s\n" "$f"; done > ~/.deploy-list
  git hash-object --no-filters --stdin-paths < ~/.deploy-list | paste - ~/.deploy-list; rm -f ~/.deploy-list' | sort > "$tmp/remote"
comm -23 "$tmp/local" "$tmp/remote" | cut -f2 > "$tmp/upload"

last=$("${SSH[@]}" "cat $STAMP 2>/dev/null" || true)
if [ -n "$last" ] && git cat-file -e "$last^{commit}" 2>/dev/null; then
  git -c core.quotepath=false diff --name-only --diff-filter=D "$last" HEAD | grep -vxFf "$tmp/skip" > "$tmp/delete" || true
else
  echo "No deploy record on the server yet: files deleted in git won't be removed this time."
  : > "$tmp/delete"
fi

echo "Upload: $(wc -l < "$tmp/upload") files"; sed 's/^/  + /' "$tmp/upload"
echo "Delete: $(wc -l < "$tmp/delete") files"; sed 's/^/  - /' "$tmp/delete"
[ -z "$dry" ] || { echo "(dry run, nothing changed)"; exit 0; }

if [ -s "$tmp/upload" ]; then
  while IFS= read -r f; do
    mkdir -p "$tmp/files/$(dirname "$f")"; git cat-file blob "HEAD:$f" > "$tmp/files/$f"
  done < "$tmp/upload"
  tr '\n' '\0' < "$tmp/upload" | tar -C "$tmp/files" -cf - --null -T - | "${SSH[@]}" "cd $DIR && tar -xf -"
fi
[ -s "$tmp/delete" ] && tr '\n' '\0' < "$tmp/delete" | "${SSH[@]}" "cd $DIR && xargs -0 rm -f --"
git rev-parse HEAD | "${SSH[@]}" "cat > $STAMP"
echo "Deployed $(git rev-parse --short HEAD)."
