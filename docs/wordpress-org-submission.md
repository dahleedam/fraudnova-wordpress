# wordpress.org submission — checklist

Everything the directory needs is in this repo. `.wordpress-org/` holds the listing assets; it is excluded from the release zip by `.distignore`.

## 1. Account
- Log in to wordpress.org as **dahleedam** (the owner's existing personal account; the readme's `Contributors:` line names it).
  The uploading account owns the plugin, commits to SVN and answers support-forum threads. To show the brand on the plugin
  page, set that profile's display name to "FraudNova" under https://profiles.wordpress.org/me/profile/edit/.

## 1b. Pre-flight checks (done 2026-11-09)
- **Readme validator** (wordpress.org/plugins/developers/readme-validator/): first run failed on the display name — "woocommerce" is a
  restricted term that may not appear in the display name or permalink. The only remaining note is optional (no donate link).
- **Name = slug.** The permanent slug is derived from the main file's `Plugin Name:` header, and Plugin Check requires the
  readme title to equal that header. Every doc and guide promises `wordpress.org/plugins/fraudnova/`, so both are the plain
  `FraudNova` (the descriptive copy lives in the readme's short-description line). Do not lengthen either before upload.
- **Plugin Check (PCP) 2.2.0 — clean** (`wp plugin check fraudnova --format=table --include-experimental` on the local test store:
  "No errors found"). The one warning it raised, an unescaped HPOS table name in the uninstaller, is fixed with `esc_sql()`.
  Re-run it after any code change; fix every ERROR, judge WARNINGs.
- Guidelines checked by hand: GPL-2.0-or-later in header and readme, no bundled libraries, no output or links on the public site,
  only the FraudNova API is contacted and it is disclosed under "External services", no tracking without the key being saved,
  `Tested up to` only in readme.txt.

## 2. Submit the plugin
What the logged-in form at https://wordpress.org/plugins/developers/add/ asks (read 2026-11-09; 661 plugins were in the queue):
- Three "Step 1" checkboxes: read the FAQ, read the guidelines, tested with Plugin Check and resolved its findings. All true.
- "Naming and ownership" checkboxes: the name is not confusingly similar to anything (searched), and the uploading account
  represents the plugin owner. **The form names the account email (`dahleedam@gmail.com`) and says ownership of a brand-led
  name is verified by that email.** The plugin is called FraudNova, so either switch the wordpress.org account email to the
  `@fraudnova.com` mailbox before uploading, or be ready to prove ownership when the reviewer asks (reply from that mailbox).
- "Trialware" checkbox: no artificial limits in the plugin code. True — credits are a service-side price, nothing in the
  plugin is gated.
- Two acknowledgement checkboxes (guidelines may lead to rejection; hosting is conditional on compliance).
- Upload the zip (max 10 MB): `dist/fraudnova-1.0.0.zip` (also attached to the GitHub release v1.0.0).
- The slug can be changed ONCE after upload, before review starts, via a link on that same page — check it reads `fraudnova`.
  Once approved the plugin cannot be renamed.
- Expect: an automated scan (minutes), then a human review in roughly 1–14 days, usually one email listing required
  changes. Reply by uploading a fixed zip; do not open a second submission.
- On approval you get SVN access: `https://plugins.svn.wordpress.org/fraudnova/`.

## 3. First SVN commit (after approval)
```
svn co https://plugins.svn.wordpress.org/fraudnova fraudnova-svn
cd fraudnova-svn
unzip -o ../fraudnova-1.0.0.zip -d trunk-tmp && rsync -a --delete trunk-tmp/fraudnova/ trunk/ && rm -rf trunk-tmp
cp ../fraudnova-wordpress/.wordpress-org/* assets/
svn add --force trunk assets
svn ci -m "FraudNova 1.0.0" --username fraudnova
svn cp trunk tags/1.0.0 && svn ci -m "Tag 1.0.0"
```
`Stable tag: 1.0.0` in `readme.txt` must match the tag folder.

## 4. Assets (already in `.wordpress-org/`)
| file | size | note |
|---|---|---|
| icon.svg | vector | preferred by the directory |
| icon-256x256.png, icon-128x128.png | 256², 128² | fallbacks |
| banner-1544x500.png, banner-772x250.png | retina + standard | dark background, signal mark, tagline |
| screenshot-1.jpg … screenshot-4.jpg | 800×500 | captions = the `== Screenshots ==` list in readme.txt, same order |

Screenshots came from the local test store (fnstore.test): orders list with the Risk column, the FraudNova order box on a held
order, the Orders settings tab, the Activity tab. Re-capture at a larger size any time; names and order must stay.

## 5. After it is listed
- Swap the install wording back to the directory link: `/docs#wordpress` and the COD guide in the app (`docs/DEPLOY.md` note of 2026-11-08).
- Add the directory URL to the GitHub repo description and to fraudnova.com's integrations list.
