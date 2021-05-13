#!/bin/bash
echo "= Build"

function error {
	echo
	echo "# $1"
	exit
}

COMPOSER="composer.phar"
PHP_BIN="`command -v php`"
[ -z "$PHP_BIN" ] && PHP_BIN="docker run -it --rm -v $PWD:/app -w /app pauloklaus/phpcli:7.4-cli php"

if [ -f $COMPOSER ];
then
    echo "Updating $COMPOSER..."
    $PHP_BIN $COMPOSER self-update
else
    echo "Download $COMPOSER... "
    COMPOSER_SETUP="composer-setup.php"
    $PHP_BIN -r "copy('https://getcomposer.org/installer', '$COMPOSER_SETUP');"
    $PHP_BIN $COMPOSER_SETUP
    rm -f $COMPOSER_SETUP
fi

OPTIONS="--optimize-autoloader"
[ -z "$1" ] && OPTIONS="--no-dev --ignore-platform-reqs $OPTIONS"

echo "$PHP_BIN $COMPOSER update $OPTIONS"
[ -f "$COMPOSER" ] && $PHP_BIN $COMPOSER update $OPTIONS

echo
echo "- Build completed."
