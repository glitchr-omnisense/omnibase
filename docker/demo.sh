#!/bin/sh
# serve (default): the schema into SQLite, the bundles' public files, the
# container compiled, then a plain PHP dev server on :8000.
# check: the container and the templates linted, the plugins' routes counted.
# bare [--all] [--each] [--json] [class...]: the Fondation (tests/Fondation.php),
# or the classes named, loaded with Composer's autoloader alone - no kernel, no
# container - and every class or file of the framework loaded on the way named;
# on var/bare/vendor of the core, the core installed alone (composer install
# --no-dev), made first when missing or when the core's composer.json changed.
# console ...: bin/console.
# test <plugin> [args]: the plugin's PHPUnit suite (vendor/omnibase/<plugin>/phpunit.xml.dist),
# or this bundle's with no plugin named, in the test environment (.env.test)
# on a fresh SQLite database.
set -e
cd /srv/demo
case "${1:-serve}" in
    serve)
        rm -rf var/cache/*
        php bin/console doctrine:schema:create --no-interaction 2>/dev/null || php bin/console doctrine:schema:update --force --complete --no-interaction
        php bin/console assets:install public --no-interaction
        # Warmed again now the bundles' files are there: the commands above
        # booted the kernel, and the webpack warmer, before they were. The
        # shared pools too (var/share: the router's compiled routes), which
        # outlive the container in the var volume.
        rm -rf var/cache/* var/share/*
        php bin/console cache:warmup
        exec php -S 0.0.0.0:8000 -t public ;;
    check)
        rm -rf var/cache/*
        php bin/console lint:container
        php bin/console lint:twig templates
        for p in marketplace_ forum_ mailbox_ forge_ git_ admin; do printf '%-12s %s routes\n' "$p" "$(php bin/console debug:router --format=json | grep -c "\"$p")"; done ;;
    console) shift; exec php bin/console "$@" ;;
    bare)
        shift
        php vendor/omniscient/omnibase/docker/bin/bare --install
        exec php vendor/omniscient/omnibase/docker/bin/bare "$@" ;;
    test)
        shift
        case "${1:-}" in
            # The core's own: its BareTest measures the Fondation on var/bare/vendor, made here.
            ""|-*) suite=vendor/omniscient/omnibase; php "$suite/docker/bin/bare" --install ;;
            *) suite="vendor/omnibase/$1"; shift ;;
        esac
        # The test environment (.env then .env.test, see tests/harness.php),
        # a fresh container and a fresh SQLite database with the schema of
        # every entity the bundles map.
        export APP_ENV=test
        export HARNESS_SUITE_BOOTSTRAP="$suite/tests/bootstrap.php"
        # The demonstration's tests compile theirs without debug
        # (var/cache/<env>_demo_test): nothing refreshes it, and var/ is a
        # volume that outlives the run.
        # The pools of var/share (the router's compiled routes) last as long:
        # a route added since was never generated.
        # Removed first: the previous suite's container may list bundles this
        # one has not (the blog's, the classroom's: config/bundles.php).
        rm -rf var/cache/test var/cache/*_demo_test var/share/test var/share/demo var/test.db
        # The bundles' public files, linked, before the cache is made: a page
        # asked of the kernel links its scripts and stylesheets only when the
        # webpack warmer found them (bundles/base/entrypoints.json). The
        # container this compiles is removed again, warmed after the files.
        php bin/console assets:install public --symlink --env=test --no-interaction --quiet
        rm -rf var/cache/test
        php bin/console doctrine:schema:create --env=test --no-interaction --quiet
        # A gigabyte: PHPUnit keeps every test case to the end of the run, and the core's
        # suite (its kernels, its entity managers) came to the image's 512M with step AU4's.
        exec php -d memory_limit=1G vendor/bin/phpunit -c "$suite/phpunit.xml.dist" --bootstrap tests/harness.php "$@" ;;
    *) exec "$@" ;;
esac
