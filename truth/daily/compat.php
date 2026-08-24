<?php
declare(strict_types=1);

if (!function_exists('mb_substr')) {
    function mb_substr(string $value, int $start, ?int $length = null, ?string $encoding = null): string {
        return $length === null ? substr($value, $start) : substr($value, $start, $length);
    }
}
if (!function_exists('mb_strtolower')) {
    function mb_strtolower(string $value, ?string $encoding = null): string { return strtolower($value); }
}
if (!function_exists('mb_strlen')) {
    function mb_strlen(string $value, ?string $encoding = null): int { return strlen($value); }
}

function tw_parse_feed_compat(string $xml, string $source, int $limit): array {
    if (function_exists('simplexml_load_string')) return tw_parse_feed($xml, $source, $limit);
    return tw_parse_feed_regex($xml, $source, $limit);
}

function tw_parse_feed_regex(string $xml, string $source, int $limit): array {
    $items = [];
    if (preg_match_all('~<item\\b[^>]*>(.*?)</item>~is', $xml, $matches)) {
        foreach ($matches[1] as $block) {
            $title = tw_clean_text(tw_xml_tag_value($block, 'title'), 500);
            $link = trim(tw_xml_tag_value($block, 'link'));
            $dateRaw = trim(tw_xml_tag_value($block, 'pubDate'));
            $summary = tw_clean_text(tw_xml_tag_value($block, 'description'), 1000);
            if ($title === '' || !filter_var($link, FILTER_VALIDATE_URL)) continue;
            $items[] = tw_feed_item($source, $title, $link, $dateRaw, $summary);
            if (count($items) >= $limit) break;
        }
        return $items;
    }
    if (preg_match_all('~<entry\\b[^>]*>(.*?)</entry>~is', $xml, $matches)) {
        foreach ($matches[1] as $block) {
            $title = tw_clean_text(tw_xml_tag_value($block, 'title'), 500);
            $link = '';
            if (preg_match('~<link\\b[^>]*href=["\']([^"\']+)["\'][^>]*/?>~is', $block, $m)) {
                $link = html_entity_decode($m[1], ENT_QUOTES | ENT_HTML5, 'UTF-8');
            }
            $dateRaw = tw_xml_tag_value($block, 'published') ?: tw_xml_tag_value($block, 'updated');
            $summary = tw_clean_text(tw_xml_tag_value($block, 'summary') ?: tw_xml_tag_value($block, 'content'), 1000);
            if ($title === '' || !filter_var($link, FILTER_VALIDATE_URL)) continue;
            $items[] = tw_feed_item($source, $title, $link, $dateRaw, $summary);
            if (count($items) >= $limit) break;
        }
    }
    return $items;
}

function tw_xml_tag_value(string $block, string $tag): string {
    $q = preg_quote($tag, '~');
    if (!preg_match('~<' . $q . '\\b[^>]*>(.*?)</' . $q . '>~is', $block, $m)) return '';
    $value = preg_replace('~^\\s*<!\\[CDATA\\[(.*)\\]\\]>\\s*$~is', '$1', $m[1]) ?? $m[1];
    return html_entity_decode(trim(strip_tags($value)), ENT_QUOTES | ENT_HTML5, 'UTF-8');
}
