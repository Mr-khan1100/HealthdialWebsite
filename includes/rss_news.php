<?php
/**
 * Fetches health news from Indian media RSS feeds with 1-hour file cache.
 * Returns an array of article arrays compatible with the news.php card format.
 */
function fetchHealthRssNews(int $maxPerFeed = 12): array
{
    $cacheFile = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'hd_rss_health_v3.json';
    $cacheTTL  = 3600;

    if (is_readable($cacheFile) && (time() - filemtime($cacheFile)) < $cacheTTL) {
        $cached = json_decode(file_get_contents($cacheFile), true);
        if (is_array($cached) && count($cached) > 0) return $cached;
    }

    $feeds = [
        ['url' => 'https://timesofindia.indiatimes.com/rssfeeds/3908999.cms',             'source' => 'Times of India'],
        ['url' => 'https://www.hindustantimes.com/feeds/rss/lifestyle/health/rssfeed.xml','source' => 'Hindustan Times'],
        ['url' => 'https://www.thehindu.com/sci-tech/health/feeder/default.rss',          'source' => 'The Hindu'],
        ['url' => 'https://indianexpress.com/section/lifestyle/health/feed/',             'source' => 'Indian Express'],
        ['url' => 'https://www.indiatvnews.com/rssnews/topstory-health.xml',              'source' => 'India TV News'],
    ];

    $articles = [];

    foreach ($feeds as $feed) {
        $xml = hd_fetch_rss_url($feed['url']);
        if (!$xml) continue;

        // Suppress XML warnings from malformed feeds
        $prev = libxml_use_internal_errors(true);
        $rss  = simplexml_load_string($xml);
        libxml_clear_errors();
        libxml_use_internal_errors($prev);

        if (!$rss || !isset($rss->channel->item)) continue;

        $count = 0;
        foreach ($rss->channel->item as $item) {
            if ($count >= $maxPerFeed) break;

            $title   = trim((string)($item->title ?? ''));
            $link    = trim((string)($item->link ?? ''));
            $rawDesc = (string)($item->description ?? '');
            $desc    = trim(strip_tags($rawDesc));

            // Fallback: some feeds (e.g. Indian Express) leave <description> empty
            // but populate <content:encoded>
            if ($desc === '') {
                $content = $item->children('content', true);
                if (isset($content->encoded)) {
                    $desc = trim(strip_tags((string)$content->encoded));
                }
            }
            if ($desc === '') {
                $desc = 'Read the latest health update from ' . $feed['source'] . '.';
            }

            $pubDate = (string)($item->pubDate ?? '');
            $ts      = $pubDate ? strtotime($pubDate) : 0;
            $date    = $ts ? date('d M Y', $ts) : date('d M Y');

            if (!$title || !$link) continue;

            $image = hd_extract_rss_image($item, $rawDesc);

            $articles[] = [
                'id'               => 'rss_' . md5($link),
                'title'            => $title,
                'shortDescription' => mb_substr($desc, 0, 200, 'UTF-8') . (mb_strlen($desc, 'UTF-8') > 200 ? '…' : ''),
                'fullContent'      => nl2br(htmlspecialchars(mb_substr($desc, 0, 1000, 'UTF-8'), ENT_QUOTES, 'UTF-8')),
                'image'            => $image,
                'date'             => $date,
                'readTime'         => '3 min read',
                'externalUrl'      => $link,
                'source'           => $feed['source'],
                '_ts'              => $ts,
            ];
            $count++;
        }
    }

    usort($articles, function ($a, $b) { return $b['_ts'] - $a['_ts']; });

    if (!empty($articles)) {
        @file_put_contents($cacheFile, json_encode($articles));
    }

    return $articles;
}

function hd_fetch_rss_url(string $url): string
{
    if (function_exists('curl_init')) {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => 0,
            CURLOPT_TIMEOUT        => 7,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS      => 3,
            CURLOPT_USERAGENT      => 'Mozilla/5.0 (compatible; HealthDial RSS Reader/2.0)',
            CURLOPT_HTTPHEADER     => ['Accept: application/rss+xml, application/xml, text/xml, */*'],
        ]);
        $res = curl_exec($ch);
        curl_close($ch);
        return $res ?: '';
    }

    $ctx = stream_context_create([
        'http' => [
            'timeout'    => 7,
            'user_agent' => 'Mozilla/5.0 (compatible; HealthDial RSS Reader/2.0)',
            'header'     => "Accept: application/rss+xml, application/xml, text/xml\r\n",
        ],
        'ssl'  => ['verify_peer' => false, 'verify_peer_name' => false],
    ]);
    return (string)@file_get_contents($url, false, $ctx);
}

function hd_extract_rss_image(SimpleXMLElement $item, string $rawDesc): string
{
    // media:content or media:thumbnail
    $media = $item->children('media', true);
    if (isset($media->content)) {
        $u = (string)$media->content->attributes()->url;
        if ($u) return $u;
    }
    if (isset($media->thumbnail)) {
        $u = (string)$media->thumbnail->attributes()->url;
        if ($u) return $u;
    }

    // enclosure with image MIME
    if (isset($item->enclosure)) {
        $type = (string)($item->enclosure->attributes()->type ?? '');
        $url  = (string)($item->enclosure->attributes()->url ?? '');
        if ($url && strpos($type, 'image') !== false) return $url;
    }

    // img tag inside description HTML
    if (preg_match('/<img[^>]+src=["\']([^"\']+\.(?:jpe?g|png|webp|gif)(?:\?[^"\']*)?)["\']/', $rawDesc, $m)) {
        return $m[1];
    }

    return '';
}
