#!/bin/bash
echo "= Start"

function error {
	echo
	echo "# $1"
	exit
}

PHP_PUBLIC="./public"
PHP_PORT="8080"
[ ! -z "$1" ] && PHP_PORT="$1"

PHP_BIN="`command -v php`"
[ -z "$PHP_BIN" ] && PHP_BIN="docker run -it --rm -v $PWD:/app -w /app -p $PHP_PORT:$PHP_PORT php php"

$PHP_BIN -S 0.0.0.0:$PHP_PORT -t $PHP_PUBLIC

echo
echo "- Server stopped".
