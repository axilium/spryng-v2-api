# Publishing this package

Start-to-finish instructions for getting this on Packagist. Written for a first
time publisher.

## 0. Pick a name

The package name is `<vendor>/<name>`, lowercase, hyphen separated. The vendor
must be a name you control: your GitHub username or organisation slug. This
scaffold uses `acme/spryng-messaging` as a placeholder.

Do **not** use `spryng` as the vendor. That namespace belongs to Spryng and
publishing under it will confuse users about who maintains this.

### Renaming the placeholder

Three places, and they must agree:

1. `composer.json` -> `name` and the PSR-4 keys under `autoload` and
   `autoload-dev`
2. The `namespace` declaration at the top of every file in `src/` and `tests/`
3. The `use` statements and the User-Agent string in `SpryngClient.php`

From the repository root:

```bash
grep -rl 'Acme\\SpryngMessaging\|Acme\\\\SpryngMessaging\|acme/spryng-messaging' . \
  --exclude-dir=vendor --exclude-dir=.git \
  | xargs sed -i '' \
      -e 's#acme/spryng-messaging#yourvendor/spryng-messaging#g' \
      -e 's#Acme\\\\SpryngMessaging#YourVendor\\\\SpryngMessaging#g' \
      -e 's#Acme\\SpryngMessaging#YourVendor\\SpryngMessaging#g'
```

Drop the `''` after `-i` on Linux; that empty argument is a macOS quirk.

Verify with `composer validate --strict` and `vendor/bin/phpunit`.

## 1. Create the GitHub repository

It must be **public**. Packagist cannot index a private repository without a
paid plan.

```bash
cd spryng-messaging
git init -b main
git add .
git commit -m "Initial implementation of the Spryng Messaging v2 client"
git remote add origin git@github.com:yourvendor/spryng-messaging.git
git push -u origin main
```

## 2. Verify before you tag

```bash
composer validate --strict
composer install
vendor/bin/phpunit
```

`composer validate --strict` catches the mistakes that are painful to fix after
publishing: a malformed name, a missing license, a missing description.

### What ends up in the release

The repository carries a fair amount that consumers have no use for: the
documentation mirror in `docs/`, the generators in `tools/`, the test suite, the
Docksal environment. All of it is listed twice, once in `.gitattributes` as
`export-ignore` and once under `archive.exclude` in `composer.json`.

The two lists do different jobs and both are needed:

- `.gitattributes` drives `git archive`, which is what GitHub serves for a tag
  and what Packagist hands to `composer require` by default.
- `archive.exclude` drives `composer archive`, which is used when a tarball is
  built without git in the picture.

Check the result before tagging:

```bash
git archive HEAD | tar -t
```

Neither list applies to `composer require --prefer-source`, which clones the
repository. That is expected: someone asking for source is asking for
everything, tests included. Keep both lists in step when adding a directory.

## 3. Tag a release

Packagist derives every version from git tags. Composer expects semver, with or
without a leading `v`.

```bash
git tag -a v1.0.0 -m "1.0.0"
git push origin v1.0.0
```

**Do not put a `version` key in `composer.json`.** It overrides tag detection
and leaves you with a package that cannot be updated properly. This is the most
common first-timer mistake.

Until you tag, the package only has a `dev-main` branch version, which nobody
can install with a normal `^1.0` constraint.

## 4. Submit to Packagist

1. Go to <https://packagist.org> and click **Sign up**. Log in with GitHub;
   that makes step 5 a single click instead of a manual webhook.
2. Click **Submit** in the top right.
3. Paste the repository URL: `https://github.com/yourvendor/spryng-messaging`
4. Packagist fetches `composer.json`, validates it and shows a preview. Errors
   here are almost always a name mismatch or invalid JSON.
5. Click **Submit** to confirm.

The package is now live at
`https://packagist.org/packages/yourvendor/spryng-messaging`, and your
`README.md` is its front page.

## 5. Turn on auto-updating

Without this, Packagist only re-reads your repository when you click Update by
hand, so a new tag will not appear.

The easy route: on Packagist go to your profile settings, open the **GitHub**
section and grant access. This installs the Packagist GitHub App on your
account or organisation, and every push and tag triggers a sync.

The manual route, if you prefer not to grant app access: copy your API token
from your Packagist profile, then in the GitHub repository go to
**Settings > Webhooks > Add webhook** with

- Payload URL: `https://packagist.org/api/github?username=<your-packagist-username>`
- Content type: `application/json`
- Secret: your Packagist API token
- Events: just the push event

## 6. Verify

In a scratch directory:

```bash
composer require yourvendor/spryng-messaging
```

If Packagist has not caught up yet, the package page has an **Update** button
that forces a re-read.

## Releasing again later

```bash
# make changes, update CHANGELOG.md,
# bump SpryngClient::VERSION to match
git commit -am "Add scheduled sending"
git push
git tag -a v1.1.0 -m "1.1.0"
git push origin v1.1.0
```

Version numbers:

| Change | Bump |
|---|---|
| Bug fix, no API change | patch, `1.0.1` |
| New method or optional parameter | minor, `1.1.0` |
| Renamed or removed public API, higher PHP requirement | major, `2.0.0` |

Consumers pin on `^1.0`, so anything within 1.x must stay backwards
compatible. On this package that specifically means: public `readonly`
properties on the DTOs are API, new constructor parameters go last with a
default, and new exceptions extend something callers already catch.

## Unpublishing

You can delete a package from Packagist only while it has few installs.
After that it is effectively permanent, and abandoning it properly is done by
marking it abandoned in the Packagist UI, which points users at a replacement.
Choose the name with that in mind.
