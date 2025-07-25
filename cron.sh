#!/bin/bash

SCRIPT_DIR="$( cd "$( dirname "${BASH_SOURCE[0]}" )" && pwd )"

cd "$SCRIPT_DIR"

/usr/bin/php crawler.php >> logs/cron.log 2>&1

git add docs/cases/ >> logs/cron.log 2>&1
git commit -m "Update FSC penalty cases - $(date)" >> logs/cron.log 2>&1
git push origin master >> logs/cron.log 2>&1