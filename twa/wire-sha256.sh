#!/usr/bin/env bash
# Wire the Android signing-key SHA-256 fingerprint into production
# so assetlinks.json validates the TWA → no Chrome URL bar in the app.
#
# Usage:
#   ./wire-sha256.sh AA:BB:CC:DD:...:FF
#
# Get the SHA-256 from: bubblewrap fingerprint (inside ./build)

set -e

if [ -z "$1" ]; then
	echo "usage: $0 <SHA-256 fingerprint>"
	echo
	echo "Get the fingerprint by running:"
	echo "  cd build && bubblewrap fingerprint"
	echo
	echo "It looks like:  AA:BB:CC:DD:EE:FF:00:11:22:33:44:55:66:77:88:99:AA:BB:CC:DD:EE:FF:00:11:22:33:44:55:66:77:88:99"
	exit 1
fi

SHA="$1"
KEY="$(dirname "$0")/../deploy_key"

echo "Setting la_android_sha256 on production..."
ssh -i "$KEY" -p 22 -o StrictHostKeyChecking=no master_kczyayftkf@142.93.33.182 \
	"cd /home/master/applications/eddftjqqbg/public_html && wp option update la_android_sha256 '$SHA' && wp option get la_android_sha256"

echo
echo "Verifying assetlinks.json now includes the fingerprint..."
sleep 2
curl -s https://loveallah.app/.well-known/assetlinks.json

echo
echo
echo "Done. If the JSON above includes your SHA-256, the TWA will open"
echo "without the Chrome URL bar."
