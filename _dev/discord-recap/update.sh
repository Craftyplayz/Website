#!/usr/bin/env bash
# Rebuild the Discord recap stats and upload them to /private/discord/ on the live site.
# Usage: bash _dev/discord-recap/update.sh <unzipped Discord package folder> [--quick]
# data.js is gitignored (public repo), so deploy.sh never sends it; this script is the only way up.
set -euo pipefail
cd "$(dirname "$0")"
[ -d "${1:-}/Messages" ] || { echo "Usage: bash $0 <unzipped Discord package folder> [--quick]"; exit 1; }
py -I build.py "$@"
ssh -i ~/.ssh/ionos -o BatchMode=yes u112162320@access961561004.webspace-data.io \
  'mkdir -p Craftyplayz.com/private/discord && cat > Craftyplayz.com/private/discord/data.js' < ../../private/discord/data.js
echo "Uploaded. Check https://www.craftyplayz.com/private/discord/"
