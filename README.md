# symfony-notes

Deployed with [ox](https://deploywithox.com): deploy a repo to your own server with one command, no Docker. [Docs](https://deploywithox.com/docs) · [Guide for this stack](https://deploywithox.com/docs/guides/symfony)

> **Role in the zoo:** project `symfony-notes` of [oxzoo-live](https://github.com/saurav-codes/oxzoo-live-control/blob/main/zoo/README.md#projects), deployed with [ox](https://deploywithox.com) on server s3 at https://symfony-notes.s3.zoo.sorv.dev. The contract it follows is [DESIGN.md](https://github.com/saurav-codes/oxzoo-live-control/blob/main/zoo/DESIGN.md).

A small notes app (list, create, edit, delete) on Symfony 7.4, Doctrine ORM 3 and MariaDB,
deployed by ox to server s3.

## What it proves

- ox detects a Symfony project from `bin/console` and `composer.lock`: FrankenPHP start,
  `composer install --no-dev`, Doctrine migrations, and the PHP packages from the
  `ext-*` requirements (`php-mysql`, `php-mbstring`, `php-xml`).
- The release runs read-only: the Symfony cache and `var/build.json` are written at build
  time, sessions are off (stateless CSRF), logs go to stderr.
- The build needs no secrets: `composer install` and the cache warmup succeed with neither
  `APP_SECRET` nor `MYSQL_URL` set.
- `MYSQL_URL` from an ox `mysql` service is read by Doctrine directly.
- `/_zoo/probe` does a real Doctrine write, read and delete on MariaDB and checks that every
  migration is applied.

## ox features exercised

Symfony detection, FrankenPHP start, composer build with cache warmup, the migrate step,
a declared `mysql` service (only for this project), a declared health path, and dashboard
secrets.

`ox.toml` declares the health path and `[services] mysql`. Without an `ox.toml`, ox detects
MariaDB from `config/packages/doctrine.yaml` (since ox 438a55d6; before that the app
deployed with no `MYSQL_URL`).

## Variables

| Name | Kind | Value |
| --- | --- | --- |
| `MYSQL_URL` | provided by ox (`mysql` service) | |
| `APP_SECRET` | secret, set on the dashboard | a random string, 32+ chars |
| `ZOO_PANEL_ORIGIN` | plain | `https://zoo-control.s1.zoo.sorv.dev` |

`APP_ENV` and `APP_DEBUG` are set by the detected start (`prod`, `0`), and
`public/index.php` defaults to `prod` when they are absent. In prod the kernel refuses to
serve without `APP_SECRET`.

## Tests

```sh
composer install
php vendor/bin/phpunit
```

Without a database the HTTP and helper tests run and `DbTest` is skipped. For the full
run, point `MYSQL_URL` at a MariaDB, migrate, and run again:

```sh
export MYSQL_URL='mysql://app:...@127.0.0.1:3306/app'
php bin/console doctrine:migrations:migrate --no-interaction
php vendor/bin/phpunit
```

Recorded on 2026-10-09 (PHP 8.5, MariaDB 13.0 local): `OK (11 tests, 44 assertions)`.

## ox check

```
$ ox check symfony-notes
ox check . (manifest: ox.toml)

  app.start                  APP_ENV=prod APP_DEBUG=0 XDG_CONFIG_HOME=/tmp XDG_DATA_HOME=/tmp exec frankenphp php-server --root public --listen 127.0.0.1:$PORT detected:bin/console
  app.health                 /_zoo/health                                         declared
  build.install              APP_ENV=prod composer install --no-dev --no-interaction --prefer-dist --optimize-autoloader detected:composer.lock
  build.migrate              APP_ENV=prod php bin/console doctrine:migrations:migrate --no-interaction --allow-no-migration detected:composer.json
  tools.github:php/frankenphp 1.13.0                                               default
  packages                   composer                                             detected:composer.json
  packages                   php-cli                                              detected:composer.json
  packages                   php-mbstring                                         detected:composer.json
  packages                   php-mysql                                            detected:composer.json
  packages                   php-xml                                              detected:composer.json
  packages                   unzip                                                detected:composer.json
  services.mysql             mysql 11 (only for this project)                     declared

  Provided by ox: PORT, HOST, OX_ENV, OX_PROJECT, OX_RELEASE, OX_DATA_DIR, PUBLIC_URL, PUBLIC_HOST, MYSQL_URL
  Set on the dashboard before the first deploy: APP_SECRET, ZOO_PANEL_ORIGIN
  hint: Symfony runs with var/ read-only: the build warms its cache, so log to php://stderr (Monolog's prod default) and keep sessions out of var/

Ready to deploy.
```
