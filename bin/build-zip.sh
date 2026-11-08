#!/usr/bin/env bash
#
# Build dist/fraudnova-<version>.zip, the installable plugin.
#
set -euo pipefail

cd "$(dirname "$0")/.."

VERSION="$(sed -n 's/^[ *]*Version:[[:space:]]*\([^[:space:]]*\).*/\1/p' fraudnova.php | head -n 1)"
STABLE="$(sed -n 's/^Stable tag:[[:space:]]*\([^[:space:]]*\).*/\1/p' readme.txt | head -n 1)"

if [ -z "$VERSION" ]; then
	echo "Could not read Version from fraudnova.php" >&2
	exit 1
fi
CONSTANT="$(sed -n "s/^define([[:space:]]*'FRAUDNOVA_VERSION',[[:space:]]*'\([^']*\)'.*/\1/p" fraudnova.php | head -n 1)"
if [ "$VERSION" != "$CONSTANT" ]; then
	echo "Version mismatch: fraudnova.php header says $VERSION, FRAUDNOVA_VERSION says $CONSTANT" >&2
	exit 1
fi
if [ "$VERSION" != "$STABLE" ]; then
	echo "Version mismatch: fraudnova.php says $VERSION, readme.txt Stable tag says $STABLE" >&2
	exit 1
fi

rm -rf dist
mkdir -p dist/fraudnova
rsync -a --exclude-from=.distignore ./ dist/fraudnova/
(cd dist && zip -qr "fraudnova-$VERSION.zip" fraudnova)

ZIP="dist/fraudnova-$VERSION.zip"
LISTING="$(unzip -l "$ZIP")"

for path in vendor/ tests/ .github/ bin/ docs/ composer.json composer.lock phpcs.xml.dist phpunit.xml.dist README.md CHANGELOG.md; do
	if printf '%s\n' "$LISTING" | awk '{print $4}' | grep -qx "fraudnova/$path.*"; then
		echo "The zip must not contain $path" >&2
		exit 1
	fi
done

echo "$ZIP"
printf '%s\n' "$LISTING" | wc -l
