# syntax=docker/dockerfile:1
#
# Standalone, runnable demo of glitchr/base-bundle — a bare Symfony skeleton
# (no theme, no app code) with the bundle installed from this very checkout,
# a SQLite database, and one flat page touring a few bundle features:
# the App\ <-> Base\ namespace mirroring, the shipped entity layer, the
# database-backed setting bag, the obfuscator and the translator.
#
# Build from the repository root (the local checkout is what gets installed):
#
#   docker build -t base-bundle-demo .
#   docker run --rm -p 8000:8000 base-bundle-demo
#   → http://localhost:8000/
#
# The glitchr companion packages (base-plugin, doctrine-dc2type, well-known,
# backup-manager, ux-google) are fetched from gitlab.glitchr.dev (public).

FROM php:8.4-cli

# --- system deps + php extensions the bundle stack needs --------------------
RUN apt-get update \
    && apt-get install -y --no-install-recommends \
        git unzip libicu-dev libzip-dev libpng-dev libjpeg-dev libwebp-dev libfreetype-dev libxslt1-dev libmagickwand-dev \
    && rm -rf /var/lib/apt/lists/*

RUN docker-php-ext-configure gd --with-jpeg --with-webp --with-freetype \
    && docker-php-ext-install -j"$(nproc)" intl zip gd bcmath ftp xsl exif \
    && pecl install imagick igbinary \
    && docker-php-ext-enable imagick igbinary

COPY --from=composer:2 /usr/bin/composer /usr/local/bin/composer
ENV COMPOSER_ALLOW_SUPERUSER=1

# --- bare Symfony skeleton ---------------------------------------------------
WORKDIR /srv/demo
RUN composer create-project symfony/skeleton . --no-interaction --no-progress

# The glitchr packages are branch releases (N.x-dev); plugins patch vendor
# code at install time (doctrine-dc2type restores DC2Type column comments on
# DBAL 4, base-plugin hooks third-party packages).
RUN composer config minimum-stability dev \
    && composer config prefer-stable true \
    && composer config extra.symfony.allow-contrib true \
    && composer config allow-plugins.glitchr/base-plugin true \
    && composer config allow-plugins.glitchr/doctrine-dc2type true \
    && composer config allow-plugins.endroid/installer true \
    && composer config allow-plugins.php-http/discovery true \
    && composer config repositories.backup-manager vcs https://gitlab.glitchr.dev/public-repository/agnostic/backup-manager \
    && composer config repositories.base-plugin vcs https://gitlab.glitchr.dev/public-repository/symfony/bundle/base/plugin \
    && composer config repositories.doctrine-dc2type vcs https://gitlab.glitchr.dev/public-repository/symfony/bundle/doctrine-dc2type \
    && composer config repositories.well-known vcs https://gitlab.glitchr.dev/public-repository/symfony/bundle/well-known \
    && composer config repositories.ux-google vcs https://gitlab.glitchr.dev/public-repository/symfony/bundle/ux/google-api

# --- this checkout becomes the installed package -----------------------------
# (.dockerignore keeps .git, node_modules, var and vendor out of the context)
COPY . /srv/base-bundle
# --no-scripts: Flex recipes and cache:clear would compile the container
# against recipe defaults BEFORE the demo config overlay below exists (the
# google/recaptcha contrib recipe even breaks it). The overlay ships every
# config file the demo needs; the container is compiled at first run instead.
RUN composer config repositories.base-bundle '{"type": "path", "url": "/srv/base-bundle", "options": {"symlink": false}}' \
    && composer require "glitchr/base-bundle:*@dev" --no-interaction --no-progress --no-scripts \
    # the google/recaptcha contrib recipe references ReCaptcha\RequestMethod\Curl,
    # which google/recaptcha 1.5 no longer ships — and the demo needs no recaptcha
    && rm -f config/packages/google_recaptcha.yaml

# --- demo app overlay: config, one controller, one template ------------------
COPY example/app/ ./

# composer's recipe scripts compiled the container BEFORE the overlay was
# copied — drop that stale cache so the first warmup starts clean.
# The bundle's warmers (icons, translations, images) need more than the
# 128M default.
RUN rm -rf var/cache \
    && echo "memory_limit = 512M" > /usr/local/etc/php/conf.d/zz-demo.ini

EXPOSE 8000

# Schema into SQLite (idempotent-ish: ignored if it already exists), then a
# plain PHP dev server.
CMD ["sh", "-c", "php bin/console doctrine:schema:create --no-interaction || true; php bin/console assets:install public --no-interaction && php bin/console cache:warmup && php -S 0.0.0.0:8000 -t public"]
