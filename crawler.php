<?php
require_once __DIR__ . '/vendor/autoload.php';

use SimplePie\SimplePie;

class FSCCrawler {
    private $feedUrl;
    private $outputDir;
    
    public function __construct($feedUrl, $outputDir) {
        $this->feedUrl = $feedUrl;
        $this->outputDir = $outputDir;
    }
    
    private function extractFields($htmlContent) {
        $fields = [
            '發文日期' => null,
            '發文字號' => null,
            '受處分人' => null,
            '受處分人姓名或名稱' => null,  // Alternative field name
            '受處分人名稱' => null,  // Alternative field name
            '相對人' => null,  // Alternative field name
            '受裁罰之對象' => null,  // Alternative field name
            '營利事業統一編號' => null,
            '統一號碼' => null,  // Alternative field name
            '代表人或管理人姓名' => null,
            '地址' => null,
            '裁罰時間' => null,
            '主旨' => null
        ];
        
        // First try to extract from text content
        $textContent = strip_tags($htmlContent);
        $textContent = str_replace(["\r\n", "\r"], "\n", $textContent);
        
        foreach ($fields as $fieldName => &$value) {
            // Define next field patterns specific to each field
            $nextFieldStops = [
                '發文日期' => '發文字號|速別|密等|附件|相對人|受處分人|營利事業統一編號|主旨|事實',
                '發文字號' => '速別|密等|附件|相對人|受處分人|營利事業統一編號|主旨|事實',
                '受處分人' => '營利事業統一編號|統一號碼|地址|代表人|主旨|事實|三、',
                '受處分人姓名或名稱' => '營利事業統一編號|統一號碼|地址|代表人|主旨|事實|三、',
                '受處分人名稱' => '營利事業統一編號|統一號碼|地址|代表人|主旨|事實|三、',
                '相對人' => '公司代表人|出生年月日|性別|身分證|地址|主旨|事實|三、',
                '受裁罰之對象' => '營利事業統一編號|統一號碼|地址|代表人|主旨|事實|三、',
                '營利事業統一編號' => '地址|代表人|主旨|事實',
                '統一號碼' => '地址|代表人|主旨|事實',
                '代表人或管理人姓名' => '地址|身分證|主旨|事實',
                '地址' => '代表人|主旨|事實',
                '裁罰時間' => '受處分人|營利事業統一編號|主旨|事實|二、',
                '主旨' => '事實|理由|法令依據'
            ];
            
            $stopPattern = isset($nextFieldStops[$fieldName]) ? $nextFieldStops[$fieldName] : '事實|理由|法令依據|繳款方式|注意事項';
            
            // More precise patterns to extract only the value
            $patterns = [
                '/' . preg_quote($fieldName, '/') . '[：:]\s*([^：\n]*?)(?=' . $stopPattern . '|$)/u',
                '/' . preg_quote($fieldName, '/') . '[：:]\s*([^\n]*?)(?=\n|$)/u'
            ];
            
            foreach ($patterns as $pattern) {
                if (preg_match($pattern, $textContent, $matches)) {
                    $value = trim($matches[1]);
                    $value = rtrim($value, '。');
                    
                    // Clean up text that got captured
                    $value = preg_replace('/(' . $stopPattern . ').*$/u', '', $value);
                    $value = trim($value);
                    
                    // Handle special cases
                    if (($fieldName === '營利事業統一編號' || $fieldName === '統一號碼') && $value === '略') {
                        $value = null;
                    }
                    if ($fieldName === '地址' && ($value === '略' || $value === '同上')) {
                        $value = null;
                    }
                    if ($fieldName === '代表人或管理人姓名' && $value === '略') {
                        $value = null;
                    }
                    if ($fieldName === '相對人' && $value === '略') {
                        $value = null;
                    }
                    
                    if (!empty($value)) {
                        break;
                    }
                }
            }
        }
        
        // Merge alternative field names for 受處分人
        if (!$fields['受處分人']) {
            if ($fields['受處分人姓名或名稱']) {
                $fields['受處分人'] = $fields['受處分人姓名或名稱'];
            } elseif ($fields['受處分人名稱']) {
                $fields['受處分人'] = $fields['受處分人名稱'];
            } elseif ($fields['相對人']) {
                $fields['受處分人'] = $fields['相對人'];
            } elseif ($fields['受裁罰之對象']) {
                $fields['受處分人'] = $fields['受裁罰之對象'];
            }
        }
        unset($fields['受處分人姓名或名稱'], $fields['受處分人名稱'], $fields['相對人'], $fields['受裁罰之對象']);
        
        if (!$fields['營利事業統一編號'] && $fields['統一號碼']) {
            $fields['營利事業統一編號'] = $fields['統一號碼'];
        }
        unset($fields['統一號碼']);
        
        // Extract penalty amount
        $penaltyAmount = null;
        $penaltyPatterns = [
            '/處[以予]\s*新臺幣\s*([0-9,]+)\s*萬元/u',
            '/罰鍰新臺幣[（(]?下同[）)]?\s*([0-9,]+)\s*萬元/u',
            '/罰鍰新臺幣\s*([0-9,]+)\s*萬元/u',
            '/罰鍰\s*([0-9,]+)\s*萬元/u',
            '/核處新臺幣[（(]?下同[）)]?\s*([0-9,]+)\s*萬元/u',
            '/核處罰鍰新臺幣[（(]?下同[）)]?\s*([0-9,]+)\s*萬元/u',
            '/新臺幣\s*([0-9,]+)\s*萬元\s*罰鍰/u',
            '/核處\s*([0-9,]+)\s*萬元\s*罰鍰/u',
            '/核處\s*新臺幣[（(]?以下同[）)]?\s*([0-9,]+)\s*萬元\s*罰鍰/u'
        ];
        
        foreach ($penaltyPatterns as $pattern) {
            if (preg_match($pattern, $textContent, $matches)) {
                $penaltyAmount = str_replace(',', '', $matches[1]) . '萬元';
                break;
            }
        }
        
        $fields['罰鍰金額'] = $penaltyAmount;
        
        return $fields;
    }
    
    public function run() {
        if (!is_dir($this->outputDir)) {
            mkdir($this->outputDir, 0755, true);
        }
        
        $feed = new SimplePie();
        $feed->set_feed_url($this->feedUrl);
        $feed->enable_cache(false);
        $feed->init();
        $feed->handle_content_type();
        
        if ($feed->error()) {
            echo "Error fetching feed: " . $feed->error() . "\n";
            return false;
        }
        
        $items = $feed->get_items();
        $savedCount = 0;
        $errorCount = 0;
        
        foreach ($items as $item) {
            $link = $item->get_link();
            
            // Extract dataserno from link
            $dataserno = null;
            if (preg_match('/dataserno=(\d+)/', $link, $matches)) {
                $dataserno = $matches[1];
            }
            
            // Skip if no dataserno found
            if (!$dataserno) {
                echo "Warning: Could not extract dataserno from link: " . $link . "\n";
                $errorCount++;
                continue;
            }
            
            // Get raw HTML content
            $htmlDescription = $item->get_description();
            $textDescription = strip_tags($htmlDescription);
            
            // Extract fields from HTML content
            $extractedFields = $this->extractFields($htmlDescription);
            
            $case = [
                'dataserno' => $dataserno,
                'title' => $item->get_title(),
                'link' => $link,
                'description' => $textDescription,
                'description_html' => $htmlDescription,
                'pubDate' => $item->get_date('Y-m-d H:i:s'),
                'categories' => [],
                'extracted_fields' => $extractedFields
            ];
            
            $categories = $item->get_categories();
            if ($categories) {
                foreach ($categories as $category) {
                    $case['categories'][] = $category->get_label();
                }
            }
            
            // Save individual case file using dataserno as filename
            $filename = $this->outputDir . '/' . $dataserno . '.json';
            
            $jsonContent = json_encode($case, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
            if ($jsonContent === false) {
                echo "Error encoding case " . $dataserno . ": " . json_last_error_msg() . "\n";
                $errorCount++;
            } elseif (file_put_contents($filename, $jsonContent)) {
                $savedCount++;
            } else {
                echo "Error saving case " . $dataserno . "\n";
                $errorCount++;
            }
        }
        
        echo "Successfully saved " . $savedCount . " cases as individual JSON files\n";
        if ($errorCount > 0) {
            echo "Failed to save " . $errorCount . " cases\n";
        }
        
        return $savedCount > 0;
    }
}

$feedUrl = 'https://www.fsc.gov.tw/RSS/Messages?serno=201202290003&language=chinese';
$outputDir = __DIR__ . '/docs/cases';

$crawler = new FSCCrawler($feedUrl, $outputDir);
$crawler->run();