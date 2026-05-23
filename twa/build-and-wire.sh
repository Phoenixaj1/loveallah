#!/usr/bin/env bash
# End-to-end build + SHA-256 wireup
# Runs:
#   1. bubblewrap build (produces app-release-signed.aab)
#   2. bubblewrap fingerprint (extracts SHA-256)
#   3. SSH to production and set la_android_sha256
#   4. Verify assetlinks.json now includes the fingerprint
#
# Run from twa/build/ AFTER bubblewrap init has finished.

set -e

cd "$(dirname "$0")/build" || { echo "build/ doesn't exist — run 'bubblewrap init' first"; exit 1; }

echo "======================================"
echo " 1/4  bubblewrap build"
echo "======================================"
bubblewrap build

if [ ! -f app-release-signed.aab ]; then
	echo "Build did not produce app-release-signed.aab — check logs"
	exit 1
fi

AAB_SIZE=$(stat -c '%s' app-release-signed.aab 2>/dev/null || stat -f%z app-release-signed.aab)
echo "✓ AAB produced: $(pwd)/app-release-signed.aab ($AAB_SIZE bytes)"

echo
echo "======================================"
echo " 2/4  bubblewrap fingerprint"
echo "======================================"
FP_OUTPUT=$(bubblewrap fingerprint 2>&1)
echo "$FP_OUTPUT"
SHA=$(echo "$FP_OUTPUT" | grep -oE '[A-F0-9]{2}(:[A-F0-9]{2}){31}' | head -1)
if [ -z "$SHA" ]; then
	echo "Could not extract SHA-256 from bubblewrap fingerprint output"
	exit 1
fi
echo "✓ SHA-256: $SHA"

echo
echo "======================================"
echo " 3/4  SSH set la_android_sha256"
echo "======================================"
KEY="$(dirname "$0")/../deploy_key"
ssh -i "$KEY" -p 22 -o StrictHostKeyChecking=no master_kczyayftkf@142.93.33.182 \
	"cd /home/master/applications/eddftjqqbg/public_html && wp option update la_android_sha256 '$SHA' && wp cache flush"

echo
echo "======================================"
echo " 4/4  Verify assetlinks.json"
echo "======================================"
sleep 3
RESPONSE=$(curl -s https://loveallah.app/.well-known/assetlinks.json)
echo "$RESPONSE"

if echo "$RESPONSE" | grep -q "$SHA"; then
	echo
	echo "✓✓✓ SUCCESS — TWA will open without Chrome URL bar."
else
	echo
	echo "⚠ assetlinks.json doesn't yet contain the SHA. Cache may need flushing."
	echo "  Re-run: curl https://loveallah.app/.well-known/assetlinks.json"
fi

echo
echo "Next: upload app-release-signed.aab to Play Console (see twa/PLAY-STORE-UPLOAD.md)"
