# wordpress.org submission — checklist

Everything the directory needs is in this repo. `.wordpress-org/` holds the listing assets; it is excluded from the release zip by `.distignore`.

## 1. Account
- Register the wordpress.org user **fraudnova** at https://login.wordpress.org/register (the readme's `Contributors:` line names it).
  If the name is taken, register another and change `Contributors:` in `readme.txt` before building the zip.

## 2. Submit the plugin
- Build or take the release zip: `dist/fraudnova-1.0.0.zip` (also attached to the GitHub release v1.0.0).
- Upload it at https://wordpress.org/plugins/developers/add/ while logged in as that user.
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
