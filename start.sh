#!/bin/sh
# Mac / Linux: ./start.sh   (first time: chmod +x start.sh)
cd "$(dirname "$0")" || exit 1
command -v php >/dev/null 2>&1 || { echo "PHP not found. See README.md, Part 2."; exit 1; }
[ -f data/itikode.db ] || php setup.php
echo "ITiKODE is running at http://localhost:8000  (Ctrl+C to stop)"
php -S localhost:8000 router.php
