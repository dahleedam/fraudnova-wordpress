# Releasing

## 1. Bump the version

Change the version in all four places (`tests/ReadmeTest.php` fails if they disagree):

- `fraudnova.php` – the `Version:` header line.
- `fraudnova.php` – the `FRAUDNOVA_VERSION` constant.
- `readme.txt` – `Stable tag:`. Also add a `= x.y.z =` entry under `== Changelog ==` and `== Upgrade Notice ==`.
- `CHANGELOG.md` – a new `## [x.y.z] - YYYY-MM-DD` section at the top, plus its link at the bottom.

If WordPress or WooCommerce has a new major release, test against it and update `Tested up to:` (in both `fraudnova.php` and `readme.txt`) and `WC tested up to:`.

## 2. Regenerate the translation template

The `Project-Id-Version` header in the `make-pot` script in `composer.json` carries the version – update it, then run:

```sh
composer make-pot
```

This uses WP-CLI's `i18n make-pot` command, installed as a dev dependency (`wp-cli/wp-cli` + `wp-cli/i18n-command`), so no separate WP-CLI install is needed. The `pot` job in CI fails if the committed `languages/fraudnova.pot` is out of date (it ignores the `POT-Creation-Date` line).

## 3. Verify

```sh
composer test
composer lint
bin/build-zip.sh
unzip -l dist/fraudnova-x.y.z.zip
```

`bin/build-zip.sh` refuses to build when `Version:` and `Stable tag:` disagree, and fails if the zip contains development files (`vendor/`, `tests/`, `.github/`, `bin/`, `docs/`, `composer.json`, `phpcs.xml.dist`, `phpunit.xml.dist`, `README.md`, …).

Install the zip on a test site with WooCommerce, place a cash-on-delivery order in test mode and check the order note.

## 4. Tag and GitHub release

```sh
git commit -am "chore: release x.y.z"
git tag vx.y.z
git push origin main --tags
```

The `Release` workflow checks the tag matches the plugin version, runs phpcs and PHPUnit on PHP 7.4, builds the zip and attaches it to a GitHub release.

## 5. WordPress.org

### First submission

1. Upload the zip at https://wordpress.org/plugins/developers/add/ while logged in as the `fraudnova` wordpress.org account.
2. Answer the review team's emails; once approved you get SVN access to `https://plugins.svn.wordpress.org/fraudnova/`.

### Every release (SVN)

```sh
svn checkout https://plugins.svn.wordpress.org/fraudnova/ fraudnova-svn
cd fraudnova-svn

# trunk = the unpacked zip contents
rm -rf trunk/*
rm -rf /tmp/fraudnova-build
unzip -q ../dist/fraudnova-x.y.z.zip -d /tmp/fraudnova-build
cp -R /tmp/fraudnova-build/fraudnova/. trunk/

# tag
svn cp trunk tags/x.y.z

svn add --force trunk tags
[ -d assets ] && svn add --force assets   # once you have added the directory assets
svn status   # remove files deleted since the last release with `svn rm`
svn commit -m "Release x.y.z"
```

The directory serves the version named in `Stable tag:` of `trunk/readme.txt`, so the tag folder must exist before (or in the same commit as) that change.

### Directory assets

Assets live in the top-level `assets/` folder of the SVN repository (not in the plugin zip):

- `icon-256x256.png` (and optionally `icon-128x128.png`)
- `banner-1544x500.png`
- `banner-772x250.png`
- `screenshot-1.png` … `screenshot-4.png`, matching the numbered list under `== Screenshots ==` in `readme.txt`

After changing only `readme.txt` or assets, commit to trunk (and the current tag's `readme.txt`); no new version is needed.
