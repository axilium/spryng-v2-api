# Releasing and installing

This package is distributed straight from GitHub. It is **not** on Packagist,
and nothing here submits it: a package only appears there when someone registers
it by hand. Consumers add the repository to their own `composer.json` instead.

## Installing in a project

Add the repository once, then require the package as usual:

```json
{
    "repositories": [
        {
            "type": "vcs",
            "url": "https://github.com/axilium/spryng-v2-api"
        }
    ],
    "require": {
        "axilium/spryng-v2-api": "^1.0"
    }
}
```

```bash
composer update axilium/spryng-v2-api
```

Composer reads the tags from GitHub and resolves `^1.0` against them, exactly as
it would against Packagist. The package name comes from `composer.json`, not from
the repository URL, so the two do not have to match.

The repository is public, so no token is needed. Should it ever become private,
every consumer needs a GitHub token with read access, in Docksal and on every
server that runs `composer install`:

```bash
composer config --global github-oauth.github.com <token>
```

## Verify before you tag

```bash
composer validate --strict
composer install
vendor/bin/phpunit
```

### What ends up in the release

The repository carries a fair amount that consumers have no use for: the
documentation mirror in `docs/`, the generators in `tools/`, the test suite, the
Docksal environment. All of it is listed twice, once in `.gitattributes` as
`export-ignore` and once under `archive.exclude` in `composer.json`.

The two lists do different jobs and both are needed:

- `.gitattributes` drives `git archive`, which is what GitHub serves for a tag
  and what Composer downloads with `--prefer-dist`, the default.
- `archive.exclude` drives `composer archive`, which is used when a tarball is
  built without git in the picture.

Check the result before tagging:

```bash
git archive HEAD | tar -t
```

Neither list applies to `composer require --prefer-source`, which clones the
repository. That is expected: someone asking for source is asking for
everything, tests included. Keep both lists in step when adding a directory.

## Tag a release

Composer derives every version from git tags, with or without a leading `v`.

```bash
git tag -a v1.0.0 -m "1.0.0"
git push origin v1.0.0
```

**Do not put a `version` key in `composer.json`.** It overrides tag detection
and leaves you with a package that cannot be updated properly.

Until you tag, the package only has a `dev-main` branch version, which nobody
can install with a normal `^1.0` constraint.

## Releasing again later

```bash
# make changes, update CHANGELOG.md,
# bump SpryngClient::VERSION to match
git commit -am "feat: add scheduled sending"
git push
git tag -a v1.1.0 -m "1.1.0"
git push origin v1.1.0
```

Consumers pick it up with `composer update axilium/spryng-v2-api`.

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
