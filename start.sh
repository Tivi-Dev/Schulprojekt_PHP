#!/usr/bin/env bash
set -euo pipefail
projektordner="$(cd -- "$(dirname -- "${BASH_SOURCE[0]}")" && pwd)"
skript="verwaltung.php"
if [ "${1:-}" = "--test" ]; then
    skript="tests/test.php"
elif [ "$#" -ne 0 ]; then
    printf 'Aufruf: ./start.sh [--test]\n' >&2
    exit 1
fi
if command -v php >/dev/null 2>&1; then
    exec php "$projektordner/$skript"
fi
if [ -x "$projektordner/.runtime/bin/php" ]; then
    export SCHULPROJEKT_PHP_LIB="$projektordner/.runtime/lib"
    export LD_LIBRARY_PATH="$SCHULPROJEKT_PHP_LIB${LD_LIBRARY_PATH:+:$LD_LIBRARY_PATH}"
    export PHPRC="$projektordner/.runtime/php.ini"
    export PHP_INI_SCAN_DIR=""
    exec "$projektordner/.runtime/bin/php" "$projektordner/$skript"
fi
printf 'PHP CLI fehlt. Bitte PHP ab Version 8.0 mit ctype installieren.\n' >&2
printf 'Unter Ubuntu zum Beispiel: sudo apt install php-cli\n' >&2
exit 1
