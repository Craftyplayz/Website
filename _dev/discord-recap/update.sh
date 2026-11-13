#!/usr/bin/env bash
# Rebuild one person's Discord recap stats and upload them to /private/discord/<name>/ on the live site.
# Usage: bash _dev/discord-recap/update.sh <unzipped Discord package folder> <name> [--quick]
# data.js is gitignored (public repo), so deploy.sh never sends it; this script is the only way up.
# A new person also needs their folder's index.php committed + deployed, and a server-only .htaccess/.htpasswd
# (copy private/discord/spooky's on the server) unless they share the /private/ login.
set -euo pipefail
cd "$(dirname "$0")"
[ -d "${1:-}/Messages" ] && [ -n "${2:-}" ] || { echo "Usage: bash $0 <unzipped Discord package folder> <name> [--quick]"; exit 1; }
py -I build.py "$@"
ssh -i ~/.ssh/ionos -o BatchMode=yes u112162320@access961561004.webspace-data.io \
  "mkdir -p Craftyplayz.com/private/discord/$2 && cat > Craftyplayz.com/private/discord/$2/data.js" < "../../private/discord/$2/data.js"
echo "Uploaded. Check https://www.craftyplayz.com/private/discord/$2/"
