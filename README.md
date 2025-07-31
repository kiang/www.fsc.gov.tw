# FSC Penalty Cases Crawler

A PHP crawler that extracts penalty case data from the Financial Supervisory Commission (FSC) of Taiwan RSS feed and saves them as individual JSON files.

## Features

- Fetches penalty cases from FSC RSS feed
- Extracts key fields from each case including:
  - 發文日期 (Document Date)
  - 發文字號 (Document Number)
  - 受處分人 (Penalized Entity)
  - 營利事業統一編號 (Business Registration Number)
  - 代表人或管理人姓名 (Representative Name)
  - 裁罰時間 (Penalty Date)
  - 罰鍰金額 (Penalty Amount)
- Saves each case as a separate JSON file using dataserno as filename
- Automated scheduling via cron with git version control
- Handles various document formats and field name variations

## Requirements

- PHP 7.4+
- Composer
- SimplePie library
- Git

## Installation

1. Clone the repository
2. Install dependencies:
   ```bash
   composer install
   ```
3. Make sure the logs and docs/cases directories exist:
   ```bash
   mkdir -p logs docs/cases
   ```

## Usage

### Manual Execution

Run the crawler manually:
```bash
php crawler.php
```

### Automated Execution

Set up the cron job to run periodically:
```bash
# Add to crontab (example: run every 6 hours)
0 */6 * * * /path/to/your/project/cron.sh
```

The cron script will:
1. Run the crawler
2. Automatically commit new/updated cases to git
3. Push changes to remote repository
4. Log all activities to `logs/cron.log`

## Output

- Individual JSON files are saved in `docs/cases/` directory
- Each file is named using the case's dataserno (e.g., `201205170005.json`)
- Files contain extracted metadata and structured field data

## Field Extraction

The crawler handles various document formats and field name variations:
- `受處分人` may also appear as `相對人`, `受裁罰之對象`, `受處分人名稱`, etc.
- Supports numbered list formats (一、二、三、...)
- Extracts penalty amounts in multiple formats (萬元, 新臺幣, etc.)

## Data Source

RSS Feed: https://www.fsc.gov.tw/RSS/Messages?serno=201202290003&language=chinese

## License

MIT License - see LICENSE file for details