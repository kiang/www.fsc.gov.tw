#!/bin/bash

SCRIPT_DIR="$( cd "$( dirname "${BASH_SOURCE[0]}" )" && pwd )"

cd "$SCRIPT_DIR"

/usr/bin/php crawler.php >> logs/cron.log 2>&1

find docs/cases -name "*.json" -mtime +30 -delete