<?php
declare(strict_types=1);

function fs_env(): array {
    $serverRoot = trim((string)($_SERVER['DOCUMENT_ROOT'] ?? ''));
    $docRoot = $serverRoot !== '' ? realpath($serverRoot) : false;
    if (!$docRoot) $docRoot = realpath(dirname(__DIR__, 2)) ?: dirname(__DIR__, 2);
    $home = dirname($docRoot);
    $privateRoot = $home . '/site-private/project-unveiled-funnel-servant';
    return [
        'doc_root' => $docRoot,
        'home' => $home,
        'private_root' => $privateRoot,
        'data_dir' => $privateRoot . '/data',
        'config_file' => $privateRoot . '/config.php',
        'analytics_dir' => $home . '/site-private/project-unveiled-analytics/data',
        'campaign_root' => $docRoot . '/campaigns',
        'backup_root' => $home . '/site-backups',
        'app_dir' => __DIR__,
    ];
}
function fs_h(mixed $value): string { return htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }
function fs_clean(mixed $value, int $limit = 1000): string {
    $text = trim((string)$value);
    $text = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', $text) ?? '';
    return function_exists('mb_substr') ? mb_substr($text, 0, $limit, 'UTF-8') : substr($text, 0, $limit);
}
function fs_slug(mixed $value, string $fallback = 'campaign'): string {
    $slug = strtolower(fs_clean($value, 160));
    $slug = preg_replace('/[^a-z0-9]+/', '-', $slug) ?? '';
    $slug = trim($slug, '-');
    return $slug !== '' ? substr($slug, 0, 72) : $fallback;
}
function fs_dir(string $path, int $mode = 0750): bool { return is_dir($path) || @mkdir($path, $mode, true); }
function fs_write_atomic(string $path, string $content, int $mode = 0640): bool {
    if (!fs_dir(dirname($path))) return false;
    try { $suffix = bin2hex(random_bytes(5)); } catch (Throwable $e) { $suffix = uniqid('', true); }
    $tmp = $path . '.tmp-' . $suffix;
    if (@file_put_contents($tmp, $content, LOCK_EX) === false) return false;
    @chmod($tmp, $mode);
    if (!@rename($tmp, $path)) { @unlink($tmp); return false; }
    return true;
}
function fs_read_json(string $path, mixed $default = []): mixed {
    if (!is_file($path)) return $default;
    $raw = @file_get_contents($path);
    if (!is_string($raw) || trim($raw) === '') return $default;
    $value = json_decode($raw, true);
    return $value === null && json_last_error() !== JSON_ERROR_NONE ? $default : $value;
}
function fs_write_json(string $path, mixed $value, int $mode = 0640): bool {
    $json = json_encode($value, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    return is_string($json) && fs_write_atomic($path, $json . "\n", $mode);
}
function fs_append_jsonl(string $path, array $row): bool {
    if (!fs_dir(dirname($path))) return false;
    $line = json_encode($row, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    if (!is_string($line)) return false;
    return @file_put_contents($path, $line . "\n", FILE_APPEND | LOCK_EX) !== false;
}
function fs_config(): array {
    $env = fs_env();
    if (!is_file($env['config_file'])) throw new RuntimeException('Private Funnel Servant is not configured.');
    $config = require $env['config_file'];
    if (!is_array($config) || empty($config['password_hash']) || empty($config['secret'])) throw new RuntimeException('Private configuration is invalid.');
    return $config;
}
function fs_base_url(array $config): string { return rtrim((string)($config['base_url'] ?? 'https://bobsome1.com'), '/'); }
function fs_default_settings(): array {
    return [
        'enabled' => false,
        'mode' => 'scout_draft',
        'timezone' => 'America/Chicago',
        'run_interval_hours' => 6,
        'daily_brief_hour' => 8,
        'daily_brief_minute' => 0,
        'max_actions_per_day' => 3,
        'approval_required' => true,
        'website_auto_publish_approved' => false,
        'social_auto_publish' => false,
        'financial_actions' => false,
        'paused_until_utc' => null,
        'last_run_utc' => null,
        'next_run_utc' => null,
        'owner_email' => 'thebobsomest1@gmail.com',
        'email_reports' => false,
        'community_comments_enabled' => true,
        'community_reviews_enabled' => true,
        'community_email_notifications' => false,
        'community_auto_publish' => false,
        'community_owner_name' => 'Robert J. Hayes',
        'community_disabled_chapters' => [],
        'preferred_post_hour' => 19,
        'preferred_review_hour' => 19,
        'autopilot_preferred_platform' => 'facebook',
        'autopilot_campaign_focus' => 'rotate',
        'autopilot_quality_gate' => true,
        'installed_version' => '6.3.0-autopilot-quality',
    ];
}
function fs_settings(): array {
    $env = fs_env();
    $settings = fs_read_json($env['data_dir'] . '/settings.json', []);
    if (!is_array($settings)) $settings = [];
    return array_replace(fs_default_settings(), $settings);
}
function fs_save_settings(array $settings): bool {
    $env = fs_env();
    $safe = array_replace(fs_default_settings(), $settings);
    $safe['approval_required'] = true;
    $safe['social_auto_publish'] = false;
    $safe['community_auto_publish'] = false;
    $safe['financial_actions'] = false;
    $safe['autopilot_quality_gate'] = true;
    return fs_write_json($env['data_dir'] . '/settings.json', $safe);
}
function fs_log(string $type, string $message, array $context = []): void {
    $env = fs_env();
    fs_append_jsonl($env['data_dir'] . '/action-log.jsonl', [
        't' => gmdate('c'), 'type' => $type, 'message' => $message, 'context' => $context,
    ]);
}
function fs_allowed_destination(string $url, array $config): bool {
    $url = trim($url);
    if ($url === '') return false;
    if ($url[0] === '/') return !str_starts_with($url, '//');
    $parts = parse_url($url);
    if (!is_array($parts) || !in_array(strtolower((string)($parts['scheme'] ?? '')), ['http','https'], true)) return false;
    $host = strtolower((string)($parts['host'] ?? ''));
    $siteHost = strtolower((string)(parse_url(fs_base_url($config), PHP_URL_HOST) ?? 'bobsome1.com'));
    return in_array($host, [$siteHost, 'www.' . $siteHost, 'paypal.me', 'paypal.com', 'www.paypal.com'], true);
}
function fs_utm_url(string $destination, array $params, array $config): string {
    if (!fs_allowed_destination($destination, $config)) $destination = '/book/read/';
    if ($destination[0] === '/') $destination = fs_base_url($config) . $destination;
    $parts = parse_url($destination);
    if (!is_array($parts)) return $destination;
    $query = [];
    if (!empty($parts['query'])) parse_str((string)$parts['query'], $query);
    foreach ($params as $key => $value) {
        $v = fs_slug($value, 'campaign');
        if ($v !== '') $query[$key] = $v;
    }
    $scheme = $parts['scheme'] ?? 'https'; $host = $parts['host'] ?? '';
    $port = isset($parts['port']) ? ':' . $parts['port'] : '';
    $path = $parts['path'] ?? '/'; $fragment = isset($parts['fragment']) ? '#' . $parts['fragment'] : '';
    return $scheme . '://' . $host . $port . $path . ($query ? '?' . http_build_query($query) : '') . $fragment;
}
function fs_destination_map(): array {
    return [
        'reader' => ['/book/read/', 'Complete Reader'],
        'chapter-01' => ['/book/read/chapter-01.html', 'Chapter 1 — Truth Is Not Afraid of Questions'],
        'chapter-03' => ['/book/read/chapter-03.html', 'Chapter 3 — When Rome Put Its Hand on the Cross'],
        'chapter-04' => ['/book/read/chapter-04.html', 'Chapter 4 — Arius and Athanasius'],
        'chapter-07' => ['/book/read/chapter-07.html', 'Chapter 7 — Kingdom or Costume?'],
        'chapter-08' => ['/book/read/chapter-08.html', 'Chapter 8 — Sons and Daughters of the Light'],
        'chapter-11' => ['/book/read/chapter-11.html', 'Chapter 11 — Voices Beyond the Shelf'],
        'chapter-13' => ['/book/read/chapter-13.html', 'Chapter 13 — Tear the Veil'],
        'timeline' => ['/book/timeline.html', 'Interactive Historical Timeline'],
        'research' => ['/book/research.html', 'Research and Evidence'],
        'support' => ['/book/read/support-right-hand.html', 'Support Right Hand Ministry'],
        'paypal' => ['https://paypal.me/Bobsome1975', 'Direct PayPal Support'],
    ];
}
function fs_analytics_summary(int $days = 30, int $offsetDays = 0): array {
    $env = fs_env();
    $summary = [
        'days' => $days, 'offset_days' => $offsetDays, 'sessions' => 0, 'pageviews' => 0,
        'next' => 0, 'shares' => 0, 'support' => 0, 'paypal' => 0, 'complete' => 0,
        'pages' => [], 'sources' => [], 'campaigns' => [], 'contents' => [], 'events' => [],
        'pages_per_session' => 0.0, 'progression_rate' => 0.0, 'share_rate' => 0.0,
        'support_rate' => 0.0, 'paypal_rate' => 0.0, 'from_utc' => '', 'to_utc' => '',
    ];
    $to = time() - ($offsetDays * 86400); $from = $to - ($days * 86400);
    $summary['from_utc'] = gmdate('c', $from); $summary['to_utc'] = gmdate('c', $to);
    if (!is_dir($env['analytics_dir'])) return $summary;
    $sessions = [];
    foreach (glob($env['analytics_dir'] . '/*.jsonl') ?: [] as $file) {
        $handle = @fopen($file, 'rb'); if (!$handle) continue;
        while (($line = fgets($handle)) !== false) {
            $row = json_decode($line, true); if (!is_array($row)) continue;
            $ts = strtotime((string)($row['t'] ?? '')) ?: 0;
            if ($ts < $from || $ts >= $to) continue;
            $event = (string)($row['event'] ?? ''); $session = (string)($row['session'] ?? '');
            if ($session !== '') $sessions[$session] = true;
            $summary['events'][$event] = ($summary['events'][$event] ?? 0) + 1;
            if ($event === 'pageview') {
                $summary['pageviews']++;
                $path = (string)($row['path'] ?? '/'); $summary['pages'][$path] = ($summary['pages'][$path] ?? 0) + 1;
                $source = trim((string)($row['source'] ?? 'direct')) ?: 'direct'; $summary['sources'][$source] = ($summary['sources'][$source] ?? 0) + 1;
                $campaign = trim((string)($row['campaign'] ?? '')); if ($campaign !== '') $summary['campaigns'][$campaign] = ($summary['campaigns'][$campaign] ?? 0) + 1;
                $content = trim((string)($row['content'] ?? '')); if ($content !== '') $summary['contents'][$content] = ($summary['contents'][$content] ?? 0) + 1;
            } elseif ($event === 'chapter_next') $summary['next']++;
            elseif ($event === 'share_click') $summary['shares']++;
            elseif ($event === 'support_page_click') $summary['support']++;
            elseif ($event === 'paypal_click') $summary['paypal']++;
            elseif ($event === 'book_complete') $summary['complete']++;
        }
        fclose($handle);
    }
    $summary['sessions'] = count($sessions);
    $s = max(1, $summary['sessions']);
    $summary['pages_per_session'] = round($summary['pageviews'] / $s, 2);
    $summary['progression_rate'] = round(($summary['next'] / $s) * 100, 1);
    $summary['share_rate'] = round(($summary['shares'] / $s) * 100, 1);
    $summary['support_rate'] = round(($summary['support'] / $s) * 100, 1);
    $summary['paypal_rate'] = round(($summary['paypal'] / $s) * 100, 1);
    foreach (['pages','sources','campaigns','contents','events'] as $key) arsort($summary[$key]);
    return $summary;
}
function fs_top_key(array $map, string $fallback = 'none'): string { return $map ? (string)array_key_first($map) : $fallback; }

function fs_source_is_attributed(string $source): bool {
    $source = fs_slug($source, '');
    return $source !== '' && !in_array($source, ['direct','unknown','none','none-yet','native','campaign','unset','not-set'], true);
}
function fs_source_platform_map(): array {
    return [
        'facebook'=>['label'=>'Facebook','where'=>'Facebook through Post Now','medium'=>'organic_social'],
        'messenger'=>['label'=>'Facebook Messenger','where'=>'Facebook Messenger to people you already know','medium'=>'personal_invitation'],
        'instagram'=>['label'=>'Instagram','where'=>'Instagram through Post Now','medium'=>'organic_social'],
        'threads'=>['label'=>'Threads','where'=>'Threads through Post Now','medium'=>'organic_social'],
        'tiktok'=>['label'=>'TikTok','where'=>'TikTok through Post Now','medium'=>'organic_social'],
        'youtube'=>['label'=>'YouTube','where'=>'YouTube Shorts or a Community post','medium'=>'organic_social'],
        'x'=>['label'=>'X','where'=>'X through Post Now','medium'=>'organic_social'],
        'twitter'=>['label'=>'X','where'=>'X through Post Now','medium'=>'organic_social'],
        'linkedin'=>['label'=>'LinkedIn','where'=>'LinkedIn through Post Now','medium'=>'organic_social'],
        'reddit'=>['label'=>'Reddit','where'=>'an appropriate Reddit community that permits the link','medium'=>'organic_social'],
        'whatsapp'=>['label'=>'WhatsApp','where'=>'WhatsApp to people you already know','medium'=>'personal_invitation'],
        'telegram'=>['label'=>'Telegram','where'=>'Telegram to an appropriate contact or channel','medium'=>'organic_social'],
        'email'=>['label'=>'Email','where'=>'a permission-based email to existing contacts','medium'=>'email'],
        'medium'=>['label'=>'Medium','where'=>'a Medium story with the reader link','medium'=>'referral'],
        'snapchat'=>['label'=>'Snapchat','where'=>'Snapchat through Post Now','medium'=>'organic_social'],
        'gumroad'=>['label'=>'Gumroad','where'=>'Gumroad only when a finished product is ready','medium'=>'referral'],
    ];
}
function fs_source_signal(array $analytics, array $settings = []): array {
    $sources = is_array($analytics['sources'] ?? null) ? $analytics['sources'] : [];
    $map = fs_source_platform_map(); $attributed = []; $unattributed = 0; $total = 0;
    foreach ($sources as $source => $count) {
        $slug = fs_slug($source, ''); $count = max(0, (int)$count); $total += $count;
        if (!fs_source_is_attributed($slug)) { $unattributed += $count; continue; }
        $canonical = $slug === 'twitter' ? 'x' : $slug;
        $attributed[$canonical] = ($attributed[$canonical] ?? 0) + $count;
    }
    arsort($attributed);
    $top = $attributed ? (string)array_key_first($attributed) : '';
    $topViews = $top !== '' ? (int)$attributed[$top] : 0;
    $fallback = fs_slug($settings['autopilot_preferred_platform'] ?? 'facebook', 'facebook');
    if (!isset($map[$fallback])) $fallback = 'facebook';
    $recommended = $top !== '' ? $top : $fallback;
    $label = $map[$recommended]['label'] ?? ucfirst($recommended);
    $attributedTotal = array_sum($attributed);
    $confidence = $topViews >= 10 ? 'high' : ($topViews >= 3 ? 'moderate' : ($topViews > 0 ? 'low' : 'unattributed'));
    if ($top === '') {
        $note = $unattributed > 0
            ? 'Most measured visits are direct or otherwise unattributed. “Direct” does not identify a social platform, so Autopilot will run a clean tagged test instead of pretending it knows the source.'
            : 'There is not enough attributed traffic yet. Autopilot will use the selected fallback channel as a test, not as a proven winner.';
    } else {
        $note = $label . ' has the strongest attributed traffic signal with ' . $topViews . ' tagged page view' . ($topViews === 1 ? '' : 's') . '. Confidence is ' . $confidence . '.';
    }
    return [
        'raw_top'=>fs_top_key($sources, 'none yet'), 'total_views'=>$total, 'unattributed_views'=>$unattributed,
        'attributed_views'=>$attributedTotal, 'attributed_sources'=>$attributed, 'top_source'=>$top,
        'top_views'=>$topViews, 'recommended_platform'=>$recommended, 'recommended_label'=>$label,
        'recommended_where'=>$map[$recommended]['where'] ?? ($label . ' through Post Now'),
        'recommended_medium'=>$map[$recommended]['medium'] ?? 'organic_social',
        'confidence'=>$confidence, 'note'=>$note,
    ];
}
function fs_campaign_templates(): array {
    return [
        'questions'=>[
            'title'=>'Questioning Religion Is Not Questioning God',
            'hook'=>'Most of my life, I thought questioning religion meant questioning God. Those are not the same thing.',
            'body'=>'Project Unveiled is not an attack on God or Jesus. It is an invitation to examine the difference between faith, truth, and the religious systems built around them.\n\nYou do not have to agree with every conclusion. You only have to be willing to look honestly.',
            'cta'=>'Read Chapter One free:', 'destination'=>'/book/read/chapter-01.html',
            'content_label'=>'questions-not-god', 'hashtags'=>'#ProjectUnveiled #TruthIsNotAfraid #SeekTheTruth',
        ],
        'truth'=>[
            'title'=>'Truth Is Not Afraid of Questions',
            'hook'=>'A belief that cannot survive an honest question was never protected by silence.',
            'body'=>'Project Unveiled invites readers to seek, ask, examine the evidence, and separate living faith from inherited fear. The complete book is free to read, and every major claim should be checked rather than blindly accepted.',
            'cta'=>'Begin with Chapter One:', 'destination'=>'/book/read/chapter-01.html',
            'content_label'=>'truth-questions', 'hashtags'=>'#ProjectUnveiled #HonestQuestions #Truth',
        ],
        'kingdom'=>[
            'title'=>'Kingdom or Costume?',
            'hook'=>'Jesus said His disciples would be known by love—not branding, buildings, status, or religious performance.',
            'body'=>'When a religious system produces fear, dependence, superiority, and exclusion instead of love, freedom, truth, and restoration, it deserves honest examination. Project Unveiled asks where the living Way ended and the costume began.',
            'cta'=>'Read Kingdom or Costume:', 'destination'=>'/book/read/chapter-07.html',
            'content_label'=>'kingdom-or-costume', 'hashtags'=>'#ProjectUnveiled #KingdomOrCostume #ByLove',
        ],
        'love'=>[
            'title'=>'By Love. That Is the Test.',
            'hook'=>'Not by branding. Not by buildings. Not by religious performance. By love.',
            'body'=>'The clearest test Jesus gave was not institutional success but how people love one another. Project Unveiled examines what happens when the sign is replaced by the system—and why truth, freedom, and restoration still matter.',
            'cta'=>'Enter the free reader:', 'destination'=>'/book/read/',
            'content_label'=>'by-love-test', 'hashtags'=>'#ProjectUnveiled #ByLove #TheWay',
        ],
        'veil'=>[
            'title'=>'Who Hung the Veil Back Up?',
            'hook'=>'If the veil was torn open, why were people taught that access to God still depended on a religious gatekeeper?',
            'body'=>'Project Unveiled investigates the difference between the access Jesus opened and the systems later built around that access. The point is not rebellion for its own sake. The point is truth that can withstand examination.',
            'cta'=>'Read the free investigation:', 'destination'=>'/book/read/chapter-05.html',
            'content_label'=>'who-hung-veil', 'hashtags'=>'#ProjectUnveiled #TearTheVeil #SeekAndFind',
        ],
    ];
}
function fs_select_campaign_template(array $analytics, array $settings): array {
    $templates = fs_campaign_templates(); $focus = fs_slug($settings['autopilot_campaign_focus'] ?? 'rotate', 'rotate');
    if (isset($templates[$focus])) return $templates[$focus] + ['key'=>$focus];
    $keys = array_keys($templates); $seed = (int)gmdate('z') + (int)($analytics['sessions'] ?? 0) + (int)($analytics['next'] ?? 0);
    $key = $keys[$seed % count($keys)]; return $templates[$key] + ['key'=>$key];
}
function fs_destination_without_tracking(string $destination, array $config): string {
    $destination = trim($destination); if (!fs_allowed_destination($destination, $config)) $destination = '/book/read/chapter-01.html';
    if ($destination !== '' && $destination[0] === '/') return fs_base_url($config) . $destination;
    $parts = parse_url($destination); if (!is_array($parts)) return fs_base_url($config) . '/book/read/chapter-01.html';
    $query = []; if (!empty($parts['query'])) parse_str((string)$parts['query'], $query);
    foreach (array_keys($query) as $key) if (str_starts_with(strtolower((string)$key), 'utm_')) unset($query[$key]);
    $scheme=$parts['scheme']??'https';$host=$parts['host']??'';$port=isset($parts['port'])?':'.$parts['port']:'';$path=$parts['path']??'/';$fragment=isset($parts['fragment'])?'#'.$parts['fragment']:'';
    return $scheme.'://'.$host.$port.$path.($query?'?'.http_build_query($query):'').$fragment;
}
function fs_copy_without_embedded_urls(string $text): string {
    $text = preg_replace('~https?://[^\\s<]+~iu', '', $text) ?? $text;
    $text = preg_replace('/[ \\t]+\\n/u', "\\n", $text) ?? $text;
    $text = preg_replace('/\\n{3,}/u', "\\n\\n", $text) ?? $text;
    return trim($text);
}
function fs_campaign_quality(string $title, string $hook, string $body, string $cta, string $destination, array $config): array {
    $critical=[];$warnings=[];$all=strtolower($title."\\n".$hook."\\n".$body);
    $internal=['repeat the source','strongest traffic source','last 7 days','current data shows','same channel identified','produced readers','page views was','run one focused follow-up','campaign brief'];
    foreach($internal as$phrase)if(str_contains($all,$phrase))$critical[]='Internal analytics wording is exposed in public copy: “'.$phrase.'”.';
    if(str_contains(strtolower($body),'utm_source=')||str_contains(strtolower($hook),'utm_source='))$critical[]='A stale tracked URL is embedded in the copy. Tracking must be generated after the platform is selected.';
    if(preg_match('~https?://~i',$body)||preg_match('~https?://~i',$hook))$warnings[]='A URL is embedded in the message instead of being kept in the destination field.';
    if(trim($title)===''||fs_text_len($title)<12)$critical[]='Public title is missing or too weak.';
    if(trim($hook)==='')$warnings[]='Opening hook is missing.';
    if(trim($body)==='')$critical[]='Main message is missing.';
    if(trim($cta)==='')$warnings[]='Call to action is missing.';
    if(!fs_allowed_destination($destination,$config))$critical[]='Destination is not an allowed Project Unveiled URL.';
    if(!str_contains(strtolower($body),'project unveiled'))$warnings[]='Main message does not identify Project Unveiled.';
    $score=max(0,100-count($critical)*35-count($warnings)*8);
    return ['score'=>$score,'blocked'=>!empty($critical),'critical'=>$critical,'warnings'=>$warnings,'status'=>!empty($critical)?'blocked':($score>=90?'ready':($score>=75?'review':'revise'))];
}
function fs_apply_public_campaign(array $brief, array $template, string $platform, array $signal, array $config): array {
    $campaign = fs_slug($template['title'].'-'.gmdate('Ymd'), 'project-unveiled-'.gmdate('Ymd'));
    $destination = fs_destination_without_tracking((string)$template['destination'], $config);
    $tracked = fs_utm_url($destination, [
        'utm_source'=>$platform, 'utm_medium'=>$signal['recommended_medium'] ?? 'organic_social',
        'utm_campaign'=>$campaign, 'utm_content'=>$template['content_label'] ?? 'autopilot-post'
    ], $config);
    $copy = trim((string)$template['hook']."\\n\\n".(string)$template['body']."\\n\\n".(string)$template['cta']);
    $quality = fs_campaign_quality((string)$template['title'],(string)$template['hook'],(string)$template['body'],(string)$template['cta'],$destination,$config);
    return array_replace($brief,[
        'public_title'=>$template['title'],'public_hook'=>$template['hook'],'public_body'=>$template['body'],'public_cta'=>$template['cta'],
        'exact_copy'=>$copy,'destination_url'=>$destination,'tracked_url'=>$tracked,'platform'=>$platform,'campaign_slug'=>$campaign,
        'content_label'=>$template['content_label'],'hashtags'=>$template['hashtags'],'source_signal'=>$signal,'quality'=>$quality,
    ]);
}

function fs_local_datetime(array $settings, int $hour, int $minute = 0, int $daysAhead = 0): DateTimeImmutable {
    try { $tz = new DateTimeZone((string)$settings['timezone']); } catch (Throwable $e) { $tz = new DateTimeZone('America/Chicago'); }
    $now = new DateTimeImmutable('now', $tz);
    $candidate = $now->setTime($hour, $minute)->modify('+' . max(0, $daysAhead) . ' day');
    if ($candidate <= $now->modify('+45 minutes')) $candidate = $candidate->modify('+1 day');
    return $candidate;
}
function fs_when(DateTimeImmutable $dt): array { return ['local' => $dt->format('l, F j, Y \a\t g:i A T'), 'utc' => $dt->setTimezone(new DateTimeZone('UTC'))->format('c')]; }
function fs_brief(string $title, int $priority, string $what, string $where, DateTimeImmutable $when, array $how, string $why, array $success, string $next, string $copy = '', string $url = ''): array {
    $times = fs_when($when);
    return [
        'id' => substr(hash('sha256', $title . '|' . $times['utc'] . '|' . $where), 0, 12),
        'title' => $title, 'priority' => $priority, 'what' => $what, 'where' => $where,
        'when_local' => $times['local'], 'when_utc' => $times['utc'], 'how' => $how,
        'why' => $why, 'success_check' => $success, 'next_action' => $next,
        'exact_copy' => $copy, 'tracked_url' => $url, 'status' => 'open', 'created_at_utc' => gmdate('c'),
    ];
}
function fs_generate_action_briefs(array $analytics, array $previous, array $settings, array $config): array {
    $briefs=[];$postHour=(int)$settings['preferred_post_hour'];$reviewHour=(int)$settings['preferred_review_hour'];
    $sessions=(int)($analytics['sessions']??0);$next=(int)($analytics['next']??0);$shares=(int)($analytics['shares']??0);$supportClicks=(int)($analytics['support']??0);
    $signal=fs_source_signal($analytics,$settings);$template=fs_select_campaign_template($analytics,$settings);$platform=(string)$signal['recommended_platform'];
    if($sessions===0){
        $first=fs_brief('Launch a clean Chapter 1 invitation',1,'Publish one clear reader invitation with a fresh tracked destination.',$signal['recommended_where'],fs_local_datetime($settings,$postHour),[
            'Use the public title and copy shown below.','Keep analytics language out of the public message.','Load the draft into Post Now so the selected platform generates its own UTM link.','Attach one strong Project Unveiled image.','Confirm the post only after you see it live.'
        ],'No reliable attributed audience exists yet. This is a measured test, not a claim that one platform is already proven.',['3 reading sessions','1 reader continues beyond the landing page','A clean attributed source appears in the next report'],'Review the tagged results after 24 hours.');
        $briefs[]=fs_apply_public_campaign($first,$template,$platform,$signal,$config);
        $warm=fs_campaign_templates()['questions'];
        $second=fs_brief('Invite five warm readers',2,'Personally invite five people likely to read and answer one specific question.','Facebook Messenger or ordinary text message to people you already know',fs_local_datetime($settings,18,30),[
            'Choose people who are likely to read rather than merely react.','Personalize the first sentence.','Ask what single sentence stayed with them.','Do not lead with donations.','Record responses separately from website traffic.'
        ],'Warm invitations produce useful first-reader feedback when broad traffic is still thin.',['5 personal invitations','3 reading sessions','1 written response'],'Repeat only with people similar to those who actually read.');
        $briefs[]=fs_apply_public_campaign($second,$warm,'messenger',array_replace($signal,['recommended_medium'=>'personal_invitation']),$config);
    }else{
        $strategyTitle=$signal['top_source']!==''?'Follow up through attributed '.$signal['recommended_label'].' traffic':'Run a clean attribution test';
        $why=$signal['top_source']!==''
            ?$signal['note'].' The public message still needs a new reader-facing hook rather than a report about the analytics.'
            :$signal['note'];
        $first=fs_brief($strategyTitle,1,'Publish one fresh reader-facing campaign through the recommended channel.',$signal['recommended_where'],fs_local_datetime($settings,$postHour),[
            'Use the public campaign title—not the internal strategy title.','Do not mention traffic totals, direct traffic, or “strongest source” in the post.','Keep the destination untagged in the editor; Post Now generates platform-specific tracking.','Use one call to action only.','Check reader progression after 24 hours.'
        ],$why,['At least '.max(3,(int)ceil($sessions*.25)).' new sessions','At least 1 chapter-next click','A clearly attributed source in the report'],'Keep the channel only if it produces readers who continue, not merely clicks.');
        $briefs[]=fs_apply_public_campaign($first,$template,$platform,$signal,$config);
    }
    if($next===0||(float)($analytics['progression_rate']??0)<25){
        $t=fs_campaign_templates()['truth'];
        $b=fs_brief('Repair reader progression',2,'Send readers directly to Chapter 1 with one clear promise.','Chapter 1 campaign through '.$signal['recommended_label'],fs_local_datetime($settings,10,0,1),[
            'Send traffic directly to Chapter 1.','Verify the next-chapter control on mobile.','Do not rewrite the chapter during this test.','Use one outcome: permission to ask honest questions.','Judge the test after 10 new Chapter 1 sessions.'
        ],'Traffic is not yet producing enough movement into the book.',['10 Chapter 1 sessions','3 next-chapter clicks','2.0 or more pages per session'],'If progression stays below 25%, test a shorter introduction before Chapter 1.');
        $briefs[]=fs_apply_public_campaign($b,$t,$platform,$signal,$config);
    }elseif($shares===0){
        $t=fs_campaign_templates()['love'];
        $b=fs_brief('Ask earned readers for one share',2,'Ask readers who received value to share one doorway with one sincere seeker.',$signal['recommended_where'],fs_local_datetime($settings,$postHour,0,1),[
            'Lead with one useful line.','Ask for one share, not a mass blast.','Do not combine sharing with a donation request.','Use the tracked reader destination.','Measure referred progression for 48 hours.'
        ],'Readers are moving, but earned sharing has not yet been measured.',['2 share clicks','3 referral sessions','1 referred reader continues'],'Thank anyone who shares and ask what line mattered.');
        $briefs[]=fs_apply_public_campaign($b,$t,$platform,$signal,$config);
    }elseif($supportClicks===0&&(float)($analytics['pages_per_session']??0)>=2){
        $supportTemplate=['title'=>'Keep Project Unveiled Free to Read','hook'=>'Project Unveiled remains free because truth should not be locked behind a paywall.','body'=>'Those who believe in the work can help carry continued writing, hosting, research, printing, outreach, technology, and practical service. Support is voluntary, and the complete online book remains free.','cta'=>'See exactly what support helps accomplish:','destination'=>'/book/read/support-right-hand.html','content_label'=>'support-after-value','hashtags'=>'#ProjectUnveiled #SupportTheWork'];
        $b=fs_brief('Introduce support after value',2,'Explain the support path only after readers have received value.','Facebook or the strongest attributed reader channel',fs_local_datetime($settings,$postHour,0,1),[
            'Explain what support pays for.','State that the online book remains free.','Link to the support explanation—not directly to PayPal.','Do not claim gifts are tax-deductible.','Measure support-page and PayPal clicks separately.'
        ],'Multi-page readers have shown interest, making a transparent support explanation appropriate.',['3 support-page visits','1 PayPal click','No reduction in reader progression'],'Revise the explanation if readers open the support page but do not understand the next step.');
        $briefs[]=fs_apply_public_campaign($b,$supportTemplate,$platform,$signal,$config);
    }
    $briefs[]=fs_brief('Review the numbers and decide',3,'Run a 24-hour review and choose keep, revise, or stop.','Private Dashboard and Publishing Report',fs_local_datetime($settings,$reviewHour,0,1),[
        'Compare attributed sessions, progression, shares, and support clicks.','Separate confirmed posts from handoffs and preparation.','Do not call direct traffic a platform.','Change one variable at a time.','Select no more than three active moves.'
    ],'The current period has '.$sessions.' sessions versus '.(int)($previous['sessions']??0).' previously. Decisions should follow attributable behavior.',['A written keep/revise/stop decision','One next campaign selected','No unsupported source claims'],'Run NEXT THREE MOVES after recording the result.');
    return array_slice($briefs,0,max(1,min(3,(int)($settings['max_actions_per_day']??3))));
}

function fs_format_brief(array $b): string {
    $lines = [
        'ACTION BRIEF — ' . ($b['title'] ?? 'Next Move'),
        '', 'WHAT', (string)($b['what'] ?? ''), '', 'WHERE', (string)($b['where'] ?? ''), '',
        'WHEN', (string)($b['when_local'] ?? ''), '', 'HOW'
    ];
    foreach ((array)($b['how'] ?? []) as $i => $step) $lines[] = ($i + 1) . '. ' . $step;
    $lines[] = ''; $lines[] = 'WHY'; $lines[] = (string)($b['why'] ?? '');
    if (!empty($b['exact_copy'])) { $lines[] = ''; $lines[] = 'EXACT COPY'; $lines[] = (string)$b['exact_copy']; }
    if (!empty($b['tracked_url'])) { $lines[] = ''; $lines[] = 'TRACKED LINK'; $lines[] = (string)$b['tracked_url']; }
    $lines[] = ''; $lines[] = 'SUCCESS CHECK';
    foreach ((array)($b['success_check'] ?? []) as $metric) $lines[] = '• ' . $metric;
    $lines[] = ''; $lines[] = 'NEXT ACTION'; $lines[] = (string)($b['next_action'] ?? '');
    return implode("\n", $lines);
}
function fs_campaign_from_brief(array $brief, array $config, string $source = 'manual'): array {
    $platform=fs_slug($brief['platform']??'facebook','facebook');if(!isset(fs_platforms()[$platform])||$platform==='native')$platform='facebook';
    $title=fs_clean($brief['public_title']??$brief['title']??'Project Unveiled',180);
    $hook=fs_copy_without_embedded_urls(fs_clean($brief['public_hook']??'',800));
    $body=fs_copy_without_embedded_urls(fs_clean($brief['public_body']??$brief['exact_copy']??'',6000));
    $cta=fs_copy_without_embedded_urls(fs_clean($brief['public_cta']??'Read free:',300));
    if($hook!==''&&str_starts_with($body,$hook))$body=trim(substr($body,strlen($hook)));
    $destination=fs_destination_without_tracking((string)($brief['destination_url']??$brief['tracked_url']??'/book/read/chapter-01.html'),$config);
    $campaign=fs_slug($brief['campaign_slug']??($title.'-'.gmdate('Ymd')),'project-unveiled-'.gmdate('Ymd'));
    $content=fs_slug($brief['content_label']??'autopilot-post','autopilot-post');
    $medium=fs_source_platform_map()[$platform]['medium']??'organic_social';
    $tracked=fs_utm_url($destination,['utm_source'=>$platform,'utm_medium'=>$medium,'utm_campaign'=>$campaign,'utm_content'=>$content],$config);
    $quality=fs_campaign_quality($title,$hook,$body,$cta,$destination,$config);
    $id=substr(bin2hex(random_bytes(8)),0,12);$slug='servant-'.fs_slug($title,'campaign').'-'.strtolower(substr($id,0,4));
    $post=trim(implode("

",array_filter([$hook,$body,$cta],static fn($v)=>trim((string)$v)!=='')));
    $spec=['slug'=>$slug,'title'=>$title.' | Project Unveiled','headline'=>$title,'lead'=>$body!==''?$body:$hook,'primary_label'=>$cta!==''?rtrim($cta,':'):'Read Project Unveiled','primary_url'=>$tracked,'secondary_label'=>'Review the Research','secondary_url'=>'/book/research.html','campaign'=>$campaign,'indexable'=>false];
    return [
        'id'=>$id,'created_at_utc'=>gmdate('c'),'source'=>$source,'status'=>'draft','approved_at_utc'=>null,'published_at_utc'=>null,
        'generation_version'=>'6.4-draft-repair','title'=>$title,'strategy_title'=>fs_clean($brief['title']??'',180),'platform'=>$platform,
        'goal'=>str_contains(strtolower((string)($brief['what']??'')),'support')?'support':'read',
        'hook'=>$hook,'body'=>$body,'cta'=>$cta,'hashtags'=>fs_clean($brief['hashtags']??'',600),
        'destination_url'=>$destination,'tracked_url'=>$tracked,'campaign_slug'=>$campaign,'content_label'=>$content,
        'post_copy'=>$post,'quality'=>$quality,'action_brief'=>$brief,'landing_spec'=>$spec,
    ];
}

function fs_build_landing_html(array $spec, array $config): string {
    $title = fs_clean($spec['title'] ?? 'Project Unveiled', 180);
    $headline = fs_clean($spec['headline'] ?? $title, 260);
    $lead = fs_clean($spec['lead'] ?? '', 1200);
    $primaryLabel = fs_clean($spec['primary_label'] ?? 'Read Now', 90);
    $secondaryLabel = fs_clean($spec['secondary_label'] ?? 'Research', 90);
    $primaryUrl = fs_clean($spec['primary_url'] ?? '/book/read/', 700);
    $secondaryUrl = fs_clean($spec['secondary_url'] ?? '/book/research.html', 700);
    if (!fs_allowed_destination($primaryUrl, $config)) $primaryUrl = '/book/read/';
    if (!fs_allowed_destination($secondaryUrl, $config)) $secondaryUrl = '/book/research.html';
    $robots = !empty($spec['indexable']) ? 'index,follow' : 'noindex,nofollow';
    return '<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><meta name="robots" content="' . fs_h($robots) . '"><title>' . fs_h($title) . '</title><meta name="description" content="' . fs_h($lead) . '"><style>body{margin:0;background:#070605;color:#f4ead2;font:18px/1.65 Arial,sans-serif}.wrap{max-width:980px;margin:auto;padding:7vh 20px}.panel{background:linear-gradient(145deg,#17130d,#0c0a07);border:1px solid #d7ad51;padding:clamp(28px,6vw,68px);box-shadow:0 30px 90px #000}h1{font:700 clamp(38px,8vw,82px)/1.02 Georgia,serif;color:#f3d47d;margin:.15em 0}.eyebrow{letter-spacing:.2em;text-transform:uppercase;color:#bda66c;font-weight:800}.actions{display:flex;gap:14px;flex-wrap:wrap;margin-top:30px}.button{display:inline-block;padding:14px 21px;background:#d7ad51;color:#080706;text-decoration:none;font-weight:900;border:1px solid #fff1aa}.secondary{background:transparent;color:#f4ead2}.legal{font-size:14px;color:#bdb49f;margin-top:30px}</style></head><body><main class="wrap"><section class="panel"><div class="eyebrow">Project Unveiled</div><h1>' . fs_h($headline) . '</h1><p>' . nl2br(fs_h($lead)) . '</p><div class="actions"><a class="button" href="' . fs_h($primaryUrl) . '">' . fs_h($primaryLabel) . '</a><a class="button secondary" href="' . fs_h($secondaryUrl) . '">' . fs_h($secondaryLabel) . '</a></div><p class="legal">Built by the private Project Unveiled Funnel Servant. The complete online book remains free.</p></section></main></body></html>';
}
function fs_load_drafts(): array { $e=fs_env(); $v=fs_read_json($e['data_dir'].'/drafts.json', []); return is_array($v)?$v:[]; }
function fs_save_drafts(array $drafts): bool { $e=fs_env(); return fs_write_json($e['data_dir'].'/drafts.json', array_values($drafts)); }
function fs_find_draft(array $drafts, string $id): ?array { foreach ($drafts as $d) if (is_array($d) && (string)($d['id']??'')===$id) return $d; return null; }
function fs_update_draft(array $drafts, array $updated): array { foreach ($drafts as $i=>$d) if (is_array($d)&&(string)($d['id']??'')===(string)($updated['id']??'')) $drafts[$i]=$updated; return $drafts; }
function fs_publish_draft(string $id, array $config): array {
    $env = fs_env(); $drafts = fs_load_drafts(); $draft = fs_find_draft($drafts, $id);
    if (!$draft) return [false, 'Draft not found.'];
    if (($draft['status'] ?? '') !== 'approved') return [false, 'Draft must be approved before publishing.'];
    $spec = is_array($draft['landing_spec'] ?? null) ? $draft['landing_spec'] : [];
    $slug = fs_slug($spec['slug'] ?? $draft['title'] ?? 'campaign');
    $targetDir = $env['campaign_root'] . '/' . $slug; $target = $targetDir . '/index.html';
    $backupDir = $env['private_root'] . '/published-backups/' . $slug;
    fs_dir($env['campaign_root'], 0755); fs_write_atomic($env['campaign_root'] . '/.htaccess', "Options -Indexes\n", 0644); fs_dir($targetDir, 0755);
    if (is_file($target)) { fs_dir($backupDir); @copy($target, $backupDir . '/' . gmdate('Ymd-His') . '.html'); }
    $html = fs_build_landing_html($spec, $config);
    if (!fs_write_atomic($target, $html, 0644)) return [false, 'Could not write public campaign page.'];
    $draft['status']='published'; $draft['published_at_utc']=gmdate('c'); $draft['public_url']=fs_base_url($config).'/campaigns/'.$slug.'/';
    $drafts=fs_update_draft($drafts,$draft); fs_save_drafts($drafts);
    $published=fs_read_json($env['data_dir'].'/published.json',[]); if(!is_array($published))$published=[];
    $published[$slug]=['id'=>$id,'slug'=>$slug,'title'=>$draft['title']??$slug,'url'=>$draft['public_url'],'published_at_utc'=>$draft['published_at_utc']];
    fs_write_json($env['data_dir'].'/published.json',$published);
    fs_log('publish','Approved campaign page published',['id'=>$id,'slug'=>$slug]);
    return [true, 'Published: ' . $draft['public_url']];
}
function fs_rollback_last(array $config): array {
    $env=fs_env(); $published=fs_read_json($env['data_dir'].'/published.json',[]); if(!is_array($published)||!$published)return[false,'No published servant campaign exists.'];
    uasort($published,fn($a,$b)=>strcmp((string)($b['published_at_utc']??''),(string)($a['published_at_utc']??'')));
    $row=reset($published); $slug=fs_slug($row['slug']??''); $target=$env['campaign_root'].'/'.$slug.'/index.html';
    $backupDir=$env['private_root'].'/published-backups/'.$slug; $backups=glob($backupDir.'/*.html')?:[]; rsort($backups);
    if($backups){ if(!@copy($backups[0],$target))return[false,'Backup exists but could not be restored.']; fs_log('rollback','Restored previous campaign page',['slug'=>$slug]); return[true,'Restored the previous version of /campaigns/'.$slug.'/']; }
    if(is_file($target))@unlink($target); @rmdir(dirname($target)); unset($published[$slug]); fs_write_json($env['data_dir'].'/published.json',$published); fs_log('rollback','Removed last campaign page; no older backup existed',['slug'=>$slug]); return[true,'Removed the last published campaign because no older version existed.'];
}
function fs_save_report(array $report): void {
    $env=fs_env(); $reports=fs_read_json($env['data_dir'].'/reports.json',[]); if(!is_array($reports))$reports=[];
    array_unshift($reports,$report); fs_write_json($env['data_dir'].'/reports.json',array_slice($reports,0,120));
    fs_write_json($env['data_dir'].'/latest-report.json',$report);
}
function fs_run_review(array $config, array $settings, string $source='manual'): array {
    $current=fs_analytics_summary(7,0); $previous=fs_analytics_summary(7,7); $thirty=fs_analytics_summary(30,0);
    $briefs=fs_generate_action_briefs($current,$previous,$settings,$config);
    $report=['id'=>substr(bin2hex(random_bytes(8)),0,12),'created_at_utc'=>gmdate('c'),'generation_version'=>'6.3-autopilot-quality','source'=>$source,'current_7'=>$current,'previous_7'=>$previous,'current_30'=>$thirty,'briefs'=>$briefs];
    fs_save_report($report); fs_log('review','Traffic review generated',['source'=>$source,'sessions'=>$current['sessions'],'briefs'=>count($briefs)]);
    return $report;
}
function fs_draft_quality(array $draft, array $config): array {
    return is_array($draft['quality'] ?? null)
        ? $draft['quality']
        : fs_campaign_quality(
            (string)($draft['title'] ?? ''),
            (string)($draft['hook'] ?? ''),
            (string)($draft['body'] ?? $draft['post_copy'] ?? ''),
            (string)($draft['cta'] ?? ''),
            (string)($draft['destination_url'] ?? $draft['tracked_url'] ?? ''),
            $config
        );
}
function fs_quarantine_unsafe_drafts(array $drafts, array $config): array {
    $changed = 0;
    foreach ($drafts as $index => $draft) {
        if (!is_array($draft)) continue;
        $quality = fs_draft_quality($draft, $config);
        $status = (string)($draft['status'] ?? 'draft');
        $draft['quality'] = $quality;
        if (!empty($quality['blocked'])) {
            $draft['legacy_quality_blocked'] = true;
            if (in_array($status, ['draft','approved'], true)) {
                $draft['prior_status'] = $status;
                $draft['status'] = 'quarantined';
                $draft['quarantined_at_utc'] = $draft['quarantined_at_utc'] ?? gmdate('c');
                $draft['quarantine_reason'] = 'Blocked legacy/internal copy cannot enter Post Now.';
            }
            $drafts[$index] = $draft;
            $changed++;
        }
    }
    return [$drafts, $changed];
}
function fs_fresh_public_brief(array $report, array $config): ?array {
    $briefs = (array)($report['briefs'] ?? []);
    if (!$briefs || !is_array($briefs[0])) return null;
    $brief = $briefs[0];
    $settings = fs_settings();
    $candidate = fs_campaign_from_brief($brief, $config, 'autopilot-preview');
    $quality = fs_draft_quality($candidate, $config);
    $hasPublicFields = trim((string)($brief['public_title'] ?? '')) !== '' && trim((string)($brief['public_body'] ?? '')) !== '';
    if ($hasPublicFields && empty($quality['blocked'])) return $brief;

    $analytics = is_array($report['current_7'] ?? null) ? $report['current_7'] : fs_analytics_summary(7, 0);
    $signal = fs_source_signal($analytics, $settings);
    $template = fs_select_campaign_template($analytics, $settings);
    $platform = (string)($signal['recommended_platform'] ?? 'facebook');
    $rebuilt = fs_apply_public_campaign($brief, $template, $platform, $signal, $config);
    $rebuilt['repair_note'] = 'Public copy rebuilt by v6.4 because the stored brief was legacy, incomplete, or blocked.';
    return $rebuilt;
}
function fs_auto_draft(array $report, array $config): ?array {
    $brief = fs_fresh_public_brief($report, $config);
    if (!$brief) return null;

    $draft = fs_campaign_from_brief($brief, $config, 'autopilot');
    $quality = fs_draft_quality($draft, $config);
    if (!empty($quality['blocked'])) {
        fs_log('draft_repair_failed', 'Fresh campaign remained blocked and was not saved', ['score'=>$quality['score'],'critical'=>$quality['critical']]);
        return null;
    }

    $draft['quality'] = $quality;
    $draft['autopilot_key'] = gmdate('Y-m-d') . '-' . fs_slug((string)($draft['campaign_slug'] ?? $draft['title'] ?? 'campaign')) . '-v64';
    $drafts = fs_load_drafts();
    [$drafts, $quarantined] = fs_quarantine_unsafe_drafts($drafts, $config);

    foreach ($drafts as $existing) {
        if (!is_array($existing)) continue;
        $existingQuality = fs_draft_quality($existing, $config);
        if (($existing['autopilot_key'] ?? '') === $draft['autopilot_key']
            && empty($existingQuality['blocked'])
            && in_array((string)($existing['status'] ?? ''), ['draft','approved'], true)) {
            if ($quarantined > 0) fs_save_drafts($drafts);
            return null;
        }
    }

    array_unshift($drafts, $draft);
    fs_save_drafts(array_slice($drafts, 0, 150));
    fs_log('draft', 'Autopilot created a fresh quality-approved campaign draft', [
        'id'=>$draft['id'], 'title'=>$draft['title'], 'score'=>$quality['score'], 'quarantined'=>$quarantined
    ]);
    return $draft;
}

function fs_chat_history(): array { $e=fs_env(); $v=fs_read_json($e['data_dir'].'/chat.json',[]); return is_array($v)?$v:[]; }
function fs_chat_add(string $role,string $message,array $meta=[]):void{ $e=fs_env();$h=fs_chat_history();$h[]=['id'=>substr(bin2hex(random_bytes(7)),0,10),'t'=>gmdate('c'),'role'=>$role,'message'=>$message,'meta'=>$meta];fs_write_json($e['data_dir'].'/chat.json',array_slice($h,-300)); }
function fs_draft_list_text(array $drafts): string { if(!$drafts)return"No private drafts exist yet.";$lines=[];foreach(array_slice($drafts,0,10)as$d){if(!is_array($d))continue;$lines[]=(string)($d['id']??'')." — ".(string)($d['status']??'draft')." — ".(string)($d['title']??'Campaign');}return implode("\n",$lines); }
function fs_bot_reply(string $message, array $config): string {
    $settings=fs_settings(); $m=trim($message); $u=strtoupper($m); $env=fs_env();
    if($u==='AUTO STATUS'){ $pause=$settings['paused_until_utc']?:'not paused'; return "AUTOPILOT STATUS\nEnabled: ".($settings['enabled']?'YES':'NO')."\nMode: ".$settings['mode']."\nInterval: every ".$settings['run_interval_hours']." hours\nPaused until: ".$pause."\nLast run: ".($settings['last_run_utc']?:'never')."\nNext run: ".($settings['next_run_utc']?:'after cron is configured')."\nWebsite publishing: approved drafts only, ".($settings['website_auto_publish_approved']?'ON':'OFF')."\nSocial posting: MANUAL ONLY\nFinancial actions: DISABLED"; }
    if($u==='AUTO ON'){ $settings['enabled']=true;$settings['paused_until_utc']=null;fs_save_settings($settings);fs_log('settings','Autopilot enabled through chat');return"Autopilot is ON in guarded Scout + Draft mode. It analyzes traffic and prepares private drafts. It cannot post to social media, spend money, touch PayPal, or publish an unapproved page."; }
    if($u==='AUTO OFF'){ $settings['enabled']=false;fs_save_settings($settings);fs_log('settings','Autopilot disabled through chat');return"Autopilot is OFF. The private dashboard and manual commands still work."; }
    if(preg_match('/^PAUSE FOR\s+(\d+)\s+HOURS?$/i',$m,$match)){ $hours=max(1,min(168,(int)$match[1]));$settings['paused_until_utc']=gmdate('c',time()+$hours*3600);fs_save_settings($settings);fs_log('settings','Autopilot paused',['hours'=>$hours]);return"Autopilot is paused for {$hours} hours, until ".$settings['paused_until_utc']." UTC."; }
    if($u==='RUN TRAFFIC REVIEW'||$u==='NEXT THREE MOVES'||str_contains(strtolower($m),'what where when')||$u==='WHAT NEXT?'){
        $report=fs_run_review($config,$settings,'chat');$parts=["BOB'S NEXT THREE MOVES"];
        foreach((array)$report['briefs'] as $i=>$b){$parts[]="\nMOVE ".($i+1)."\n".fs_format_brief($b);}return implode("\n",$parts);
    }
    if($u==='BUILD NEXT CAMPAIGN'){
        $report=fs_run_review($config,$settings,'fresh-draft-chat');
        $draft=fs_auto_draft($report,$config);if(!$draft)return"A current quality-approved draft already exists today, or the fresh draft could not pass the locked quality gate. Use SHOW DRAFTS.";
        return"PRIVATE DRAFT CREATED\nID: ".$draft['id']."\nTitle: ".$draft['title']."\nStatus: draft\n\n".fs_format_brief($draft['action_brief'])."\n\nNext command: APPROVE CAMPAIGN ".$draft['id'];
    }
    if($u==='SHOW DRAFTS')return"PRIVATE DRAFT QUEUE\n".fs_draft_list_text(fs_load_drafts());
    if(preg_match('/^APPROVE CAMPAIGN\s+([a-f0-9]{6,20})$/i',$m,$match)){
        $id=strtolower($match[1]);$drafts=fs_load_drafts();$d=fs_find_draft($drafts,$id);if(!$d)return"Draft {$id} was not found.";$d['status']='approved';$d['approved_at_utc']=gmdate('c');$drafts=fs_update_draft($drafts,$d);fs_save_drafts($drafts);fs_log('approve','Campaign approved',['id'=>$id]);return"Campaign {$id} is APPROVED. It is still private. To publish its website funnel, type: PUBLISH CAMPAIGN {$id}";
    }
    if(preg_match('/^PUBLISH CAMPAIGN\s+([a-f0-9]{6,20})$/i',$m,$match)){ $id=strtolower($match[1]);$d=fs_find_draft(fs_load_drafts(),$id);if(!$d)return"Draft {$id} was not found.";if(($d['status']??'')!=='approved')return"Approve it first with: APPROVE CAMPAIGN {$id}";return"Publishing changes the public website. Confirm with this exact command:\nCONFIRM PUBLISH {$id}"; }
    if(preg_match('/^CONFIRM PUBLISH\s+([a-f0-9]{6,20})$/i',$m,$match)){[$ok,$msg]=fs_publish_draft(strtolower($match[1]),$config);return($ok?'SUCCESS — ':'FAILED — ').$msg;}
    if($u==='ROLL BACK LAST PUBLISH')return"Rollback changes the public website. Confirm with this exact command:\nCONFIRM ROLLBACK";
    if($u==='CONFIRM ROLLBACK'){[$ok,$msg]=fs_rollback_last($config);return($ok?'SUCCESS — ':'FAILED — ').$msg;}
    $lower=strtolower($m);
    if(str_contains($lower,'traffic')||str_contains($lower,'next move')||str_contains($lower,'what should')){ $report=fs_run_review($config,$settings,'chat');return"BOB'S NEXT THREE MOVES\n\n".implode("\n\n",array_map('fs_format_brief',(array)$report['briefs'])); }
    if(str_contains($lower,'build')||str_contains($lower,'campaign')||str_contains($lower,'facebook')||str_contains($lower,'messenger')||str_contains($lower,'youtube')||str_contains($lower,'podcast')||str_contains($lower,'instagram')||str_contains($lower,'tiktok')||str_contains($lower,'snapchat')||str_contains($lower,'threads')||str_contains($lower,'gumroad')||str_contains($lower,'post now')){
        $current=fs_analytics_summary(7);$previous=fs_analytics_summary(7,7);$briefs=fs_generate_action_briefs($current,$previous,$settings,$config);$brief=$briefs[0]??null;if(!$brief)return"I could not generate a safe campaign brief.";
        if(str_contains($lower,'youtube')||str_contains($lower,'video'))$brief['where']='YouTube Shorts and Facebook Reels';
        if(str_contains($lower,'messenger'))$brief['where']='Facebook Messenger to people you already know';
        if(str_contains($lower,'podcast'))$brief['where']='Beyond the Shelf podcast episode and its website notes';
        if(str_contains($lower,'instagram'))$brief['where']='Instagram through the private Post Now launchpad';
        if(str_contains($lower,'tiktok'))$brief['where']='TikTok through the private Post Now launchpad';
        if(str_contains($lower,'snapchat'))$brief['where']='Snapchat through the private Post Now launchpad';
        if(str_contains($lower,'threads'))$brief['where']='Threads through the private Post Now launchpad';
        if(str_contains($lower,'gumroad'))$brief['where']='Gumroad New Product through the private Post Now launchpad';
        $draft=fs_campaign_from_brief($brief,$config,'chat');$drafts=fs_load_drafts();array_unshift($drafts,$draft);fs_save_drafts(array_slice($drafts,0,150));fs_log('draft','Chat created a private campaign draft',['id'=>$draft['id']]);
        return"I built a private draft.\nID: ".$draft['id']."\nStatus: DRAFT — not public\n\n".fs_format_brief($brief)."\n\nNext command: APPROVE CAMPAIGN ".$draft['id'];
    }
    return"I am your private Project Unveiled Funnel Servant. I work in guarded mode and always explain WHAT, WHERE, WHEN, HOW, WHY, SUCCESS CHECK, and NEXT ACTION.\n\nUseful commands:\nAUTO STATUS\nAUTO ON\nAUTO OFF\nPAUSE FOR 24 HOURS\nRUN TRAFFIC REVIEW\nNEXT THREE MOVES\nBUILD NEXT CAMPAIGN\nSHOW DRAFTS\nAPPROVE CAMPAIGN <id>\nPUBLISH CAMPAIGN <id>\nROLL BACK LAST PUBLISH\n\nI prepare platform-specific posts and open the private Post Now launchpad. I do not make an unattended public social post, spend money, touch PayPal, or publish unapproved website pages.";
}

function fs_trim_chars(string $text, int $limit): string {
    $text = trim($text);
    if ($limit < 1) return '';
    if (function_exists('mb_strlen') && function_exists('mb_substr')) {
        if (mb_strlen($text, 'UTF-8') <= $limit) return $text;
        return rtrim(mb_substr($text, 0, max(1, $limit - 1), 'UTF-8')) . '…';
    }
    if (strlen($text) <= $limit) return $text;
    if ($limit <= 3) return substr($text, 0, $limit);
    return rtrim(substr($text, 0, $limit - 3)) . '...';
}
function fs_default_publisher(array $config): array {
    return [
        'title' => 'Truth Is Not Afraid of Questions',
        'hook' => 'Most of my life, I thought questioning religion meant questioning God. Those are not the same thing.',
        'body' => "Project Unveiled is now available to read free online. It is not an attack on God, Jesus, Scripture, or sincere believers. It confronts dead religion, fear-based control, spiritual performance, and every system that teaches people to stop seeking.\n\nRead it. Question it. Check the sources.",
        'cta' => 'Begin Chapter One free:',
        'url' => fs_base_url($config) . '/book/read/chapter-01.html',
        'image_url' => fs_base_url($config) . '/images/chapter-01-truth-questions.png',
        'video_url' => '',
        'hashtags' => '#ProjectUnveiled #TruthIsNotAfraid #BeyondTheShelf #SpiritualAwakening',
        'campaign_slug' => 'project-unveiled-launch',
        'content_label' => 'truth-questions',
        'gumroad_title' => 'Project Unveiled — Audiobook and Supporter Edition',
        'gumroad_price' => '9.99',
        'gumroad_description' => 'A supporter edition of Project Unveiled with the complete audiobook, chapter files, original artwork, and future updates. The complete web reader remains free.',
        'mastodon_instance' => 'https://mastodon.social',
        'updated_at_utc' => gmdate('c'),
    ];
}
function fs_publisher(): array {
    $env = fs_env(); $config = fs_config();
    $saved = fs_read_json($env['data_dir'] . '/social-publisher.json', []);
    if (!is_array($saved)) $saved = [];
    return array_replace(fs_default_publisher($config), $saved);
}
function fs_public_http_url(string $url): bool {
    $parts = parse_url(trim($url));
    return is_array($parts) && in_array(strtolower((string)($parts['scheme'] ?? '')), ['https','http'], true) && !empty($parts['host']);
}
function fs_save_publisher(array $spec): bool {
    $env = fs_env(); $config = fs_config(); $base = fs_default_publisher($config);
    $safe = [
        'title' => fs_clean($spec['title'] ?? $base['title'], 180),
        'hook' => fs_clean($spec['hook'] ?? $base['hook'], 600),
        'body' => fs_clean($spec['body'] ?? $base['body'], 6000),
        'cta' => fs_clean($spec['cta'] ?? $base['cta'], 240),
        'url' => fs_clean($spec['url'] ?? $base['url'], 1200),
        'image_url' => fs_clean($spec['image_url'] ?? $base['image_url'], 1200),
        'video_url' => fs_clean($spec['video_url'] ?? '', 1200),
        'hashtags' => fs_clean($spec['hashtags'] ?? $base['hashtags'], 600),
        'campaign_slug' => fs_slug($spec['campaign_slug'] ?? $base['campaign_slug'], 'project-unveiled-launch'),
        'content_label' => fs_slug($spec['content_label'] ?? $base['content_label'], 'post'),
        'gumroad_title' => fs_clean($spec['gumroad_title'] ?? $base['gumroad_title'], 180),
        'gumroad_price' => preg_match('/^\d{1,4}(?:\.\d{1,2})?$/', trim((string)($spec['gumroad_price'] ?? '9.99'))) ? trim((string)$spec['gumroad_price']) : '9.99',
        'gumroad_description' => fs_clean($spec['gumroad_description'] ?? $base['gumroad_description'], 6000),
        'mastodon_instance' => fs_clean($spec['mastodon_instance'] ?? $base['mastodon_instance'], 300),
        'updated_at_utc' => gmdate('c'),
    ];
    if (!fs_allowed_destination($safe['url'], $config)) $safe['url'] = $base['url'];
    foreach (['image_url','video_url'] as $key) if ($safe[$key] !== '' && !fs_public_http_url($safe[$key])) $safe[$key] = '';
    $m = parse_url($safe['mastodon_instance']);
    if (!is_array($m) || strtolower((string)($m['scheme'] ?? '')) !== 'https' || empty($m['host'])) $safe['mastodon_instance'] = 'https://mastodon.social';
    return fs_write_json($env['data_dir'] . '/social-publisher.json', $safe);
}
function fs_platforms(): array {
    return [
        'native' => ['label'=>'Phone Share Sheet','mode'=>'native','note'=>'Hands text, the tracked link, and an optional local media file to your phone share sheet. It does not claim a public post occurred.'],
        'facebook' => ['label'=>'Facebook','mode'=>'intent','note'=>'Copies the Facebook caption and opens Facebook’s link-sharing composer. Paste the caption, add media, review, and post.'],
        'instagram' => ['label'=>'Instagram','mode'=>'native','note'=>'Uses the phone share sheet. Select Instagram, add or confirm the visual, then make the final post.'],
        'threads' => ['label'=>'Threads','mode'=>'native','note'=>'Uses the phone share sheet. Select Threads, review the prepared text and link, then post.'],
        'tiktok' => ['label'=>'TikTok','mode'=>'native','note'=>'Uses the phone share sheet when supported. Attach a local video or open TikTok Upload as the fallback.'],
        'snapchat' => ['label'=>'Snapchat','mode'=>'native','note'=>'Uses the phone share sheet when supported. Add the image or video and make the final Story or Snap yourself.'],
        'x' => ['label'=>'X','mode'=>'intent','note'=>'Opens a prefilled X composer with platform-specific tracked copy.'],
        'youtube' => ['label'=>'YouTube','mode'=>'dashboard','note'=>'Copies the video title and description, then opens YouTube Studio. Upload the video and publish after review.'],
        'linkedin' => ['label'=>'LinkedIn','mode'=>'intent','note'=>'Copies the full LinkedIn post and opens LinkedIn link sharing.'],
        'pinterest' => ['label'=>'Pinterest','mode'=>'intent','note'=>'Opens Pinterest’s Pin creator with the destination, image, and description when artwork is available.'],
        'reddit' => ['label'=>'Reddit','mode'=>'intent','note'=>'Opens a prepared Reddit link post. Choose an appropriate community and follow its rules.'],
        'bluesky' => ['label'=>'Bluesky','mode'=>'intent','note'=>'Opens Bluesky’s official compose intent with a 300-character post.'],
        'mastodon' => ['label'=>'Mastodon','mode'=>'intent','note'=>'Opens a prefilled composer on the Mastodon instance saved above.'],
        'tumblr' => ['label'=>'Tumblr','mode'=>'intent','note'=>'Opens Tumblr’s share composer with the title, caption, and tracked destination.'],
        'medium' => ['label'=>'Medium','mode'=>'dashboard','note'=>'Copies the long-form version and opens a new Medium story.'],
        'substack' => ['label'=>'Substack','mode'=>'dashboard','note'=>'Copies newsletter-ready copy and opens Substack publishing.'],
        'whatsapp' => ['label'=>'WhatsApp','mode'=>'intent','note'=>'Opens a prepared WhatsApp share. Choose the person or group yourself.'],
        'telegram' => ['label'=>'Telegram','mode'=>'intent','note'=>'Opens a prepared Telegram share. Choose the destination yourself.'],
        'discord' => ['label'=>'Discord','mode'=>'native','note'=>'Uses the phone share sheet when supported. Choose Discord and the correct server or conversation.'],
        'gumroad' => ['label'=>'Gumroad','mode'=>'dashboard','note'=>'Copies the complete listing kit and opens Gumroad’s new-product dashboard. Nothing is sold until you publish the product.'],
    ];
}
function fs_platform_tracked_url(string $platform, array $spec): string {
    $base = trim((string)($spec['url'] ?? ''));
    $parts = parse_url($base); if (!is_array($parts) || empty($parts['host'])) return $base;
    $query = []; if (!empty($parts['query'])) parse_str((string)$parts['query'], $query);
    $campaign = fs_slug($spec['campaign_slug'] ?? ($query['utm_campaign'] ?? 'project-unveiled-launch'), 'project-unveiled-launch');
    $content = fs_slug($spec['content_label'] ?? 'post', 'post');
    $query['utm_source'] = fs_slug($platform, 'social');
    $query['utm_medium'] = 'organic_social';
    $query['utm_campaign'] = $campaign;
    $query['utm_content'] = 'post-now-' . fs_slug($platform, 'social') . '-' . $content;
    $scheme = $parts['scheme'] ?? 'https'; $host = $parts['host']; $port = isset($parts['port']) ? ':' . $parts['port'] : '';
    $path = $parts['path'] ?? '/'; $fragment = isset($parts['fragment']) ? '#' . $parts['fragment'] : '';
    return $scheme . '://' . $host . $port . $path . '?' . http_build_query($query) . $fragment;
}
function fs_publisher_message(array $spec, string $url): string {
    $parts = array_filter([
        trim((string)($spec['hook'] ?? '')),
        trim((string)($spec['body'] ?? '')),
        trim((string)($spec['cta'] ?? '')),
        trim($url),
        trim((string)($spec['hashtags'] ?? '')),
    ], static fn($v) => $v !== '');
    return implode("\n\n", $parts);
}
function fs_publisher_master(array $spec): string { return fs_publisher_message($spec, fs_platform_tracked_url('native', $spec)); }
function fs_text_len(string $text): int { return function_exists('mb_strlen') ? mb_strlen($text, 'UTF-8') : strlen($text); }
function fs_social_preserve_url(string $lead, string $url, string $tags, int $limit): string {
    $tail = "\n\n" . $url;
    $roomForTags = $limit - fs_text_len($tail) - 2;
    if ($tags !== '' && $roomForTags >= 12) $tail .= "\n\n" . fs_trim_chars($tags, $roomForTags);
    $available = max(0, $limit - fs_text_len($tail));
    $front = $available > 0 ? rtrim(fs_trim_chars($lead, $available)) : '';
    return $front !== '' ? $front . $tail : ltrim($tail);
}
function fs_platform_copy(string $platform, array $spec): string {
    $title = trim((string)($spec['title'] ?? '')); $hook = trim((string)($spec['hook'] ?? '')); $body = trim((string)($spec['body'] ?? ''));
    $cta = trim((string)($spec['cta'] ?? '')); $url = fs_platform_tracked_url($platform, $spec); $tags = trim((string)($spec['hashtags'] ?? ''));
    if ($platform === 'x') return fs_social_preserve_url(trim($hook . "\n\n" . $cta), $url, fs_trim_chars($tags, 60), 280);
    if ($platform === 'threads') return fs_social_preserve_url(trim($hook . "\n\n" . $body), $url, fs_trim_chars($tags, 70), 500);
    if ($platform === 'bluesky') return fs_social_preserve_url(trim($hook . "\n\n" . $cta), $url, '', 300);
    if ($platform === 'mastodon') return fs_social_preserve_url(trim($hook . "\n\n" . $body), $url, fs_trim_chars($tags, 70), 500);
    if ($platform === 'snapchat') return fs_social_preserve_url(trim($title . ' — ' . $hook), $url, '', 250);
    return match ($platform) {
        'instagram' => fs_trim_chars(trim($hook . "\n\n" . $body . "\n\n" . $cta . "\n" . $url . "\n\n" . $tags), 2200),
        'tiktok' => fs_trim_chars(trim($hook . "\n\n" . $cta . " " . $url . "\n\n" . $tags), 2200),
        'youtube' => $title . "\n\n" . trim($hook . "\n\n" . $body . "\n\nRead the connected chapter:\n" . $url . "\n\n" . $tags),
        'linkedin' => trim($hook . "\n\n" . $body . "\n\n" . $cta . "\n" . $url . "\n\n" . $tags),
        'pinterest' => fs_trim_chars(trim($title . "\n\n" . $hook), 500),
        'reddit' => trim($hook . "\n\n" . $body . "\n\n" . $url),
        'medium', 'substack' => trim("# " . $title . "\n\n" . $hook . "\n\n" . $body . "\n\n" . $cta . "\n\n" . $url),
        'gumroad' => "PRODUCT TITLE\n" . trim((string)($spec['gumroad_title'] ?? '')) . "\n\nPRICE\n$" . trim((string)($spec['gumroad_price'] ?? '')) . "\n\nDESCRIPTION\n" . trim((string)($spec['gumroad_description'] ?? '')) . "\n\nSOURCE / READER LINK\n" . $url . "\n\nIMAGE ASSET\n" . (trim((string)($spec['image_url'] ?? '')) ?: 'Not supplied') . "\n\nVIDEO ASSET\n" . (trim((string)($spec['video_url'] ?? '')) ?: 'Not supplied') . "\n\nDELIVERY CHECKLIST\n- Upload the finished audio, PDF, study guide, or supporter files\n- Add cover art\n- Preview checkout\n- Make a test purchase\n- Publish only after the files and price are correct",
        default => fs_publisher_message($spec, $url),
    };
}
function fs_platform_url(string $platform, array $spec): string {
    $copy = fs_platform_copy($platform, $spec); $url = fs_platform_tracked_url($platform, $spec); $title = trim((string)($spec['title'] ?? '')); $hook = trim((string)($spec['hook'] ?? ''));
    $image = trim((string)($spec['image_url'] ?? '')); $instance = rtrim(trim((string)($spec['mastodon_instance'] ?? 'https://mastodon.social')), '/');
    $target = match ($platform) {
        'facebook' => 'https://www.facebook.com/sharer/sharer.php?u=' . rawurlencode($url),
        'instagram' => 'https://www.instagram.com/',
        'threads' => 'https://www.threads.net/',
        'tiktok' => 'https://www.tiktok.com/upload?lang=en',
        'snapchat' => 'https://web.snapchat.com/',
        'x' => 'https://x.com/intent/post?text=' . rawurlencode($copy),
        'youtube' => 'https://studio.youtube.com/',
        'linkedin' => 'https://www.linkedin.com/sharing/share-offsite/?url=' . rawurlencode($url),
        'pinterest' => $image !== '' ? 'https://www.pinterest.com/pin/create/button/?url=' . rawurlencode($url) . '&media=' . rawurlencode($image) . '&description=' . rawurlencode($copy) : 'https://www.pinterest.com/pin-creation-tool/',
        'reddit' => 'https://www.reddit.com/submit?url=' . rawurlencode($url) . '&title=' . rawurlencode($title),
        'bluesky' => 'https://bsky.app/intent/compose?text=' . rawurlencode($copy),
        'mastodon' => $instance . '/share?text=' . rawurlencode($copy),
        'tumblr' => 'https://www.tumblr.com/widgets/share/tool?canonicalUrl=' . rawurlencode($url) . '&title=' . rawurlencode($title) . '&caption=' . rawurlencode($copy),
        'medium' => 'https://medium.com/new-story',
        'substack' => 'https://substack.com/home/post/publish',
        'whatsapp' => 'https://wa.me/?text=' . rawurlencode($copy),
        'telegram' => 'https://t.me/share/url?url=' . rawurlencode($url) . '&text=' . rawurlencode($hook),
        'discord' => 'https://discord.com/channels/@me',
        'gumroad' => 'https://gumroad.com/products/new',
        default => $url,
    };
    return fs_public_http_url($target) ? $target : fs_base_url(fs_config()) . '/owner/funnel-servant/?tab=publisher';
}
function fs_social_event_labels(): array {
    return [
        'copy_ready'=>'Copy prepared',
        'composer_opened'=>'Composer/dashboard opened',
        'share_handoff'=>'Handed to share target',
        'share_canceled'=>'Share canceled',
        'share_failed'=>'Share failed',
        'confirmed_posted'=>'Owner confirmed posted',
        'confirmed_not_posted'=>'Owner confirmed not posted',
        'asset_opened'=>'Media asset opened',
        'kit_exported'=>'Publishing kit exported',
    ];
}
function fs_social_method_label(array $row): string {
    $platform = (string)($row['platform'] ?? 'native');
    $platformLabel = fs_clean($row['platform_label'] ?? ucfirst($platform), 100);
    $event = (string)($row['event'] ?? '');
    $method = (string)($row['method'] ?? '');
    $detail = strtolower((string)($row['detail'] ?? ''));
    if ($event === 'confirmed_posted' || $event === 'confirmed_not_posted') return 'Owner confirmation';
    if ($event === 'copy_ready') return 'Owner clipboard preparation';
    if ($event === 'asset_opened') return 'Media link opened';
    if ($event === 'kit_exported') return 'File download';
    if (str_contains($detail, 'direct open')) return $platformLabel . ' direct link';
    return match ($method) {
        'native' => $platform === 'native' ? 'Phone share sheet' : $platformLabel . ' via phone share sheet',
        'intent' => $platformLabel . ' web composer',
        'dashboard' => $platformLabel . ' publishing dashboard',
        default => $platformLabel . ' manual handoff',
    };
}
function fs_social_draft_id(array $row): string {
    $existing = fs_clean($row['draft_id'] ?? '', 90);
    if ($existing !== '') return $existing;
    $content = (string)($row['content_label'] ?? '');
    if (preg_match('/^draft-([a-z0-9-]{4,80})$/', $content, $m)) return (string)$m[1];
    return '';
}
function fs_social_campaign_aliases(): array {
    $env = fs_env(); $aliases = fs_read_json($env['data_dir'] . '/social-campaign-aliases.json', []);
    return is_array($aliases) ? $aliases : [];
}
function fs_social_campaign_alias_save(string $campaign, string $name): bool {
    $campaign = fs_slug($campaign, ''); $name = fs_clean($name, 180);
    if ($campaign === '' || $name === '') return false;
    $aliases = fs_social_campaign_aliases(); $aliases[$campaign] = $name;
    ksort($aliases); return fs_write_json(fs_env()['data_dir'] . '/social-campaign-aliases.json', $aliases);
}
function fs_social_archive_map(): array {
    $map = fs_read_json(fs_env()['data_dir'] . '/social-activity-archive.json', []);
    return is_array($map) ? $map : [];
}
function fs_social_archive_update(array $ids, bool $archive, string $reason = 'owner'): int {
    $map = fs_social_archive_map(); $changed = 0; $reason = fs_clean($reason, 120);
    foreach (array_unique($ids) as $id) {
        $id = strtolower(fs_clean($id, 90));
        if (!preg_match('/^[a-f0-9]{8,64}$/', $id)) continue;
        if ($archive) {
            if (!isset($map[$id])) { $map[$id] = ['archived_at_utc'=>gmdate('c'),'reason'=>$reason]; $changed++; }
        } elseif (isset($map[$id])) { unset($map[$id]); $changed++; }
    }
    if ($changed > 0) fs_write_json(fs_env()['data_dir'] . '/social-activity-archive.json', $map);
    return $changed;
}
function fs_social_activity_add(string $platform, string $event, array $spec, array $meta = []): array {
    $platforms = fs_platforms(); $labels = fs_social_event_labels();
    if (!isset($platforms[$platform])) $platform = 'native';
    if (!isset($labels[$event])) $event = 'share_failed';
    try { $id = bin2hex(random_bytes(6)); } catch (Throwable $e) { $id = substr(hash('sha256', uniqid('', true)), 0, 12); }
    $row = [
        'id'=>$id,
        't_utc'=>gmdate('c'),
        'platform'=>$platform,
        'platform_label'=>$platforms[$platform]['label'],
        'event'=>$event,
        'event_label'=>$labels[$event],
        'campaign'=>fs_slug($spec['campaign_slug'] ?? 'project-unveiled-launch', 'project-unveiled-launch'),
        'campaign_name'=>fs_clean($spec['title'] ?? 'Campaign', 180),
        'content_label'=>fs_slug($spec['content_label'] ?? 'post', 'post'),
        'title'=>fs_clean($spec['title'] ?? 'Campaign', 180),
        'method'=>$platforms[$platform]['mode'],
        'tracked_url'=>fs_platform_tracked_url($platform, $spec),
        'target'=>in_array($event, ['composer_opened','share_handoff'], true) ? fs_platform_url($platform, $spec) : '',
        'detail'=>fs_clean($meta['detail'] ?? '', 300),
        'confirmed_by_owner'=>$event === 'confirmed_posted',
    ];
    $row['draft_id'] = fs_social_draft_id($row);
    $row['method_label'] = fs_social_method_label($row);
    $env = fs_env(); fs_append_jsonl($env['data_dir'] . '/social-activity.jsonl', $row);
    fs_log('social_' . $event, $labels[$event], ['platform'=>$platform,'campaign'=>$row['campaign'],'activity_id'=>$id]);
    return $row;
}
function fs_social_activity(int $limit = 150): array {
    $env = fs_env(); $file = $env['data_dir'] . '/social-activity.jsonl';
    $lines = is_file($file) ? file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) : [];
    if (!is_array($lines)) return [];
    $rows = [];
    foreach (array_reverse(array_slice($lines, -max(1, min(1000, $limit)))) as $line) {
        $row = json_decode((string)$line, true); if (is_array($row)) $rows[] = $row;
    }
    return $rows;
}
function fs_social_activity_enriched(int $limit = 1000): array {
    $rows = fs_social_activity($limit); $archives = fs_social_archive_map(); $aliases = fs_social_campaign_aliases();
    $publisher = fs_publisher(); $currentCampaign = fs_slug($publisher['campaign_slug'] ?? '', ''); $currentTitle = fs_clean($publisher['title'] ?? '', 180);
    foreach ($rows as &$row) {
        $campaign = fs_slug($row['campaign'] ?? 'project-unveiled-launch', 'project-unveiled-launch');
        $row['campaign'] = $campaign;
        $fallbackName = fs_clean($row['campaign_name'] ?? ($row['title'] ?? ''), 180);
        if (isset($aliases[$campaign]) && trim((string)$aliases[$campaign]) !== '') $row['campaign_name'] = fs_clean($aliases[$campaign], 180);
        elseif ($campaign === $currentCampaign && $currentTitle !== '') $row['campaign_name'] = $currentTitle;
        elseif ($fallbackName !== '') $row['campaign_name'] = $fallbackName;
        else $row['campaign_name'] = ucwords(str_replace('-', ' ', $campaign));
        $row['draft_id'] = fs_social_draft_id($row);
        $row['method_label'] = fs_social_method_label($row);
        $row['archived'] = isset($archives[(string)($row['id'] ?? '')]);
        $row['archive_meta'] = $row['archived'] ? $archives[(string)$row['id']] : null;
    }
    unset($row); return $rows;
}
function fs_social_report_filters(array $source): array {
    $platforms = fs_platforms(); $platform = fs_clean($source['platform'] ?? 'all', 30);
    if ($platform !== 'all' && !isset($platforms[$platform])) $platform = 'all';
    $status = fs_clean($source['report_status'] ?? 'all', 30);
    if (!in_array($status, ['all','confirmed','preparation','handoff','problems','archived'], true)) $status = 'all';
    $dateFrom = fs_clean($source['date_from'] ?? '', 10); if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateFrom)) $dateFrom = '';
    $dateTo = fs_clean($source['date_to'] ?? '', 10); if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateTo)) $dateTo = '';
    $confirmedOnly = !empty($source['confirmed_only']); $includeArchived = !empty($source['include_archived']);
    if ($confirmedOnly) $status = 'confirmed';
    if ($status === 'archived') $includeArchived = true;
    return ['platform'=>$platform,'status'=>$status,'date_from'=>$dateFrom,'date_to'=>$dateTo,'confirmed_only'=>$confirmedOnly,'include_archived'=>$includeArchived];
}
function fs_social_local_date(string $utc, string $timezone): string {
    try { return (new DateTimeImmutable($utc))->setTimezone(new DateTimeZone($timezone))->format('Y-m-d'); }
    catch (Throwable $e) { return ''; }
}
function fs_social_filter_rows(array $rows, array $filters, string $timezone = 'America/Chicago'): array {
    $result = [];
    foreach ($rows as $row) {
        if (!is_array($row)) continue;
        $archived = !empty($row['archived']);
        if (!$filters['include_archived'] && $archived) continue;
        if ($filters['platform'] !== 'all' && (string)($row['platform'] ?? '') !== $filters['platform']) continue;
        $date = fs_social_local_date((string)($row['t_utc'] ?? ''), $timezone);
        if ($filters['date_from'] !== '' && $date !== '' && $date < $filters['date_from']) continue;
        if ($filters['date_to'] !== '' && $date !== '' && $date > $filters['date_to']) continue;
        $event = (string)($row['event'] ?? ''); $status = $filters['status'];
        $matches = match ($status) {
            'confirmed' => $event === 'confirmed_posted',
            'preparation' => in_array($event, ['copy_ready','composer_opened','asset_opened','kit_exported'], true),
            'handoff' => $event === 'share_handoff',
            'problems' => in_array($event, ['share_canceled','share_failed','confirmed_not_posted'], true),
            'archived' => $archived,
            default => true,
        };
        if (!$matches) continue;
        $result[] = $row;
    }
    return $result;
}
function fs_social_group_rows(array $rows): array {
    $groups = [];
    foreach ($rows as $row) {
        if (!is_array($row)) continue;
        $campaign = (string)($row['campaign'] ?? 'project-unveiled-launch'); $content = (string)($row['content_label'] ?? 'post');
        $key = substr(hash('sha256', $campaign . '|' . $content), 0, 16);
        if (!isset($groups[$key])) $groups[$key] = [
            'key'=>$key,'campaign'=>$campaign,'campaign_name'=>(string)($row['campaign_name'] ?? $row['title'] ?? 'Campaign'),
            'content_label'=>$content,'draft_id'=>(string)($row['draft_id'] ?? ''),'latest_utc'=>(string)($row['t_utc'] ?? ''),
            'tracked_url'=>(string)($row['tracked_url'] ?? ''),'rows'=>[],'platforms'=>[],'confirmed_count'=>0,
            'problem_count'=>0,'archived_count'=>0,'unarchived_preparation_count'=>0,
        ];
        $groups[$key]['rows'][] = $row;
        $p = (string)($row['platform_label'] ?? $row['platform'] ?? ''); if ($p !== '') $groups[$key]['platforms'][$p] = true;
        $event = (string)($row['event'] ?? '');
        if ($event === 'confirmed_posted') $groups[$key]['confirmed_count']++;
        if (in_array($event, ['share_canceled','share_failed','confirmed_not_posted'], true)) $groups[$key]['problem_count']++;
        if (!empty($row['archived'])) $groups[$key]['archived_count']++;
        elseif ($event !== 'confirmed_posted') $groups[$key]['unarchived_preparation_count']++;
    }
    foreach ($groups as &$group) { $group['platforms'] = array_keys($group['platforms']); $group['event_count'] = count($group['rows']); }
    unset($group); return array_values($groups);
}
function fs_social_activity_summary(array $rows): array {
    $summary = ['confirmed_posted'=>0,'share_handoff'=>0,'composer_opened'=>0,'copy_ready'=>0,'problems'=>0,'archived'=>0];
    foreach ($rows as $row) {
        $event = (string)($row['event'] ?? '');
        if (isset($summary[$event])) $summary[$event]++;
        if (in_array($event, ['share_canceled','share_failed','confirmed_not_posted'], true)) $summary['problems']++;
        if (!empty($row['archived'])) $summary['archived']++;
    }
    return $summary;
}
function fs_social_latest_by_platform(array $rows): array {
    $latest = [];
    foreach ($rows as $row) { $p = (string)($row['platform'] ?? ''); if ($p !== '' && !isset($latest[$p])) $latest[$p] = $row; }
    return $latest;
}
function fs_social_local_time(string $utc, string $timezone = 'America/Chicago'): string {
    try { return (new DateTimeImmutable($utc))->setTimezone(new DateTimeZone($timezone))->format('M j, Y g:i A T'); }
    catch (Throwable $e) { return $utc; }
}
function fs_csv_cell(string $value): string { return preg_match('/^[=+\-@]/', $value) ? "'" . $value : $value; }
function fs_export_social_activity_csv(array $rows, string $timezone = 'America/Chicago'): string {
    $fp = fopen('php://temp', 'w+'); if (!$fp) return '';
    fputcsv($fp, ['Local Time','Platform','Event','Campaign Name','Tracking Campaign','Draft ID','Content Label','Launch Method','Title','Tracked URL','Detail','Owner Confirmed','Archived']);
    foreach (array_reverse($rows) as $row) fputcsv($fp, array_map('fs_csv_cell', [
        fs_social_local_time((string)($row['t_utc'] ?? ''), $timezone),
        (string)($row['platform_label'] ?? $row['platform'] ?? ''),
        (string)($row['event_label'] ?? $row['event'] ?? ''),
        (string)($row['campaign_name'] ?? $row['title'] ?? ''),
        (string)($row['campaign'] ?? ''),
        (string)($row['draft_id'] ?? fs_social_draft_id($row)),
        (string)($row['content_label'] ?? ''),
        (string)($row['method_label'] ?? fs_social_method_label($row)),
        (string)($row['title'] ?? ''),
        (string)($row['tracked_url'] ?? ''),
        (string)($row['detail'] ?? ''),
        !empty($row['confirmed_by_owner']) ? 'YES' : 'NO',
        !empty($row['archived']) ? 'YES' : 'NO',
    ]));
    rewind($fp); $csv = stream_get_contents($fp); fclose($fp); return is_string($csv) ? $csv : '';
}
function fs_publisher_from_draft(string $id, array $config): array {
    $draft=fs_find_draft(fs_load_drafts(),$id);if(!$draft)return[false,'Draft not found.'];
    $brief=is_array($draft['action_brief']??null)?$draft['action_brief']:[];
    $title=fs_clean($draft['title']??$brief['public_title']??'Project Unveiled',180);
    $hook=fs_copy_without_embedded_urls(fs_clean($draft['hook']??$brief['public_hook']??'',800));
    $body=fs_copy_without_embedded_urls(fs_clean($draft['body']??$brief['public_body']??$draft['post_copy']??'',6000));
    $cta=fs_copy_without_embedded_urls(fs_clean($draft['cta']??$brief['public_cta']??'Read free:',300));
    $destination=fs_destination_without_tracking((string)($draft['destination_url']??$brief['destination_url']??$draft['tracked_url']??'/book/read/chapter-01.html'),$config);
    $quality=fs_campaign_quality($title,$hook,$body,$cta,$destination,$config);
    if(!empty($quality['blocked'])){
        $reasons=implode(' ',array_slice((array)$quality['critical'],0,3));
        fs_log('publisher_quality_block','Draft blocked from Post Now',['id'=>$id,'score'=>$quality['score'],'critical'=>$quality['critical']]);
        return[false,'Quality gate blocked this older draft. '.$reasons.' Build a fresh campaign after running Traffic Review.'];
    }
    $spec=fs_publisher();$spec['title']=$title;$spec['hook']=$hook;$spec['body']=$body;$spec['cta']=$cta;$spec['url']=$destination;
    $spec['hashtags']=fs_clean($draft['hashtags']??$brief['hashtags']??$spec['hashtags'],600);
    $spec['campaign_slug']=fs_slug($draft['campaign_slug']??$brief['campaign_slug']??($title.'-'.gmdate('Ymd')),'project-unveiled-campaign');
    $spec['content_label']=fs_slug($draft['content_label']??$brief['content_label']??('draft-'.$id),'draft-'.$id);
    if(!fs_save_publisher($spec))return[false,'Could not load the draft into Social Publisher.'];
    fs_log('publisher','Quality-checked draft loaded into Social Publisher',['id'=>$id,'score'=>$quality['score'],'campaign'=>$spec['campaign_slug']]);
    return[true,'Draft loaded into Post Now with a clean destination and quality score '.$quality['score'].'/100. Platform tracking will be generated when you choose a channel.'];
}

function fs_export_social_kit(array $spec): string {
    $lines = [
        'PROJECT UNVEILED — SOCIAL PUBLISHING KIT',
        'Generated: ' . gmdate('c'),
        'Campaign: ' . fs_slug($spec['campaign_slug'] ?? 'project-unveiled-launch'),
        'Content label: ' . fs_slug($spec['content_label'] ?? 'post'),
        'Image: ' . (trim((string)($spec['image_url'] ?? '')) ?: 'Not supplied'),
        'Video: ' . (trim((string)($spec['video_url'] ?? '')) ?: 'Not supplied'),
        '',
        'IMPORTANT: This kit prepares copy and links. Only an owner-confirmed activity entry means you reported the post as public.',
        '',
    ];
    foreach (fs_platforms() as $key => $platform) {
        if ($key === 'native') continue;
        $lines[] = str_repeat('=', 64); $lines[] = strtoupper((string)$platform['label']); $lines[] = str_repeat('-', 64);
        $lines[] = 'TRACKED URL: ' . fs_platform_tracked_url($key, $spec);
        $lines[] = 'OPEN: ' . fs_platform_url($key, $spec);
        $lines[] = '';
        $lines[] = fs_platform_copy($key, $spec); $lines[] = '';
    }
    return implode("\n", $lines) . "\n";
}
function fs_cron_command(): string { $php=PHP_BINARY?:'/usr/local/bin/php';return escapeshellarg($php).' -q '.escapeshellarg(__DIR__.'/cron.php').' >/dev/null 2>&1'; }



/* Reader Community v6: public submissions remain private until owner moderation. */
function fs_community_file(): string { $e=fs_env(); return $e['data_dir'].'/reader-community.json'; }
function fs_community_rate_file(): string { $e=fs_env(); return $e['data_dir'].'/reader-community-rate.json'; }
function fs_community_default_data(): array { return ['version'=>1,'submissions'=>[],'updated_at_utc'=>null]; }
function fs_community_load(): array {
    $data=fs_read_json(fs_community_file(),fs_community_default_data());
    if(!is_array($data))$data=fs_community_default_data();
    if(!isset($data['submissions'])||!is_array($data['submissions']))$data['submissions']=[];
    return $data;
}
function fs_community_mutate(callable $mutator): mixed {
    $file=fs_community_file();fs_dir(dirname($file));$lockPath=$file.'.lock';$lock=@fopen($lockPath,'c+');
    if(!$lock||!@flock($lock,LOCK_EX))throw new RuntimeException('Could not lock reader community data.');
    try{
        $data=fs_community_load();$result=$mutator($data);$data['updated_at_utc']=gmdate('c');
        if(!fs_write_json($file,$data))throw new RuntimeException('Could not save reader community data.');
        return $result;
    }finally{@flock($lock,LOCK_UN);@fclose($lock);}
}
function fs_community_id(): string { try{$r=bin2hex(random_bytes(8));}catch(Throwable $e){$r=uniqid('',true);}return 'rc-'.gmdate('YmdHis').'-'.substr(preg_replace('/[^a-z0-9]/i','',$r)??'',0,12); }
function fs_community_text(mixed $value,int $limit=4000): string { return trim(strip_tags(fs_clean($value,$limit))); }
function fs_community_client_hash(array $config): string {
    $ip=(string)($_SERVER['REMOTE_ADDR']??'unknown');$ua=fs_clean($_SERVER['HTTP_USER_AGENT']??'unknown',500);
    return hash_hmac('sha256',$ip.'|'.$ua,(string)$config['secret']);
}
function fs_community_token(string $kind,string $chapter,int $issued,array $config): string {
    $scope=$kind.'|'.$chapter.'|'.$issued.'|'.fs_community_client_hash($config);
    return hash_hmac('sha256',$scope,(string)$config['secret']);
}
function fs_community_verify_token(string $token,string $kind,string $chapter,int $issued,array $config): bool {
    if($issued<time()-7200||$issued>time()+60)return false;
    return $token!==''&&hash_equals(fs_community_token($kind,$chapter,$issued,$config),$token);
}
function fs_community_rate_allow(string $clientHash,int $limitHour=5,int $limitDay=15): bool {
    $file=fs_community_rate_file();fs_dir(dirname($file));$lock=@fopen($file.'.lock','c+');if(!$lock||!@flock($lock,LOCK_EX))return false;
    try{
        $data=fs_read_json($file,[]);if(!is_array($data))$data=[];$now=time();
        foreach($data as $k=>$times){if(!is_array($times)){unset($data[$k]);continue;}$data[$k]=array_values(array_filter($times,fn($t)=>(int)$t>$now-86400));if(!$data[$k])unset($data[$k]);}
        $times=is_array($data[$clientHash]??null)?$data[$clientHash]:[];
        $hour=count(array_filter($times,fn($t)=>(int)$t>$now-3600));$day=count($times);
        if($hour>=$limitHour||$day>=$limitDay){fs_write_json($file,$data);return false;}
        $times[]=$now;$data[$clientHash]=$times;return fs_write_json($file,$data);
    }finally{@flock($lock,LOCK_UN);@fclose($lock);}
}
function fs_community_spam_score(string $name,string $body,string $honeypot,int $openedAt): int {
    $score=0;if(trim($honeypot)!=='')$score+=100;if($openedAt<=0||time()-$openedAt<4)$score+=50;
    if(substr_count(strtolower($body),'http://')+substr_count(strtolower($body),'https://')>2)$score+=40;
    if(preg_match('/(.)\\1{8,}/u',$body))$score+=20;if(fs_text_len($name)<2||fs_text_len($body)<3)$score+=20;
    return $score;
}
function fs_community_enabled(string $kind,string $chapter,array $settings): bool {
    if($kind==='review')return !empty($settings['community_reviews_enabled']);
    if(empty($settings['community_comments_enabled']))return false;
    $disabled=is_array($settings['community_disabled_chapters']??null)?$settings['community_disabled_chapters']:[];
    return !in_array($chapter,$disabled,true);
}
function fs_community_add(array $row): array {
    return fs_community_mutate(function(array &$data)use($row){$data['submissions'][]=$row;return $row;});
}
function fs_community_find(array $data,string $id): ?array { foreach($data['submissions']??[] as $row)if(is_array($row)&&(string)($row['id']??'')===$id)return $row;return null; }
function fs_community_public(string $kind,string $chapter='',int $limit=100): array {
    $data=fs_community_load();$rows=[];
    foreach($data['submissions'] as $row){if(!is_array($row)||($row['kind']??'')!==$kind||($row['status']??'')!=='approved')continue;if($kind==='comment'&&($row['chapter']??'')!==$chapter)continue;
        $rows[]=[
            'id'=>(string)($row['id']??''),'kind'=>$kind,'chapter'=>(string)($row['chapter']??''),'page_title'=>(string)($row['page_title']??''),
            'name'=>(string)($row['name']??'Reader'),'title'=>(string)($row['title']??''),'body'=>(string)($row['body']??''),
            'rating'=>(int)($row['rating']??0),'reader_completed'=>!empty($row['reader_completed']),'verified'=>!empty($row['verified']),
            'featured'=>!empty($row['featured']),'parent_id'=>(string)($row['parent_id']??''),'created_at_utc'=>(string)($row['created_at_utc']??''),
            'owner_reply'=>(string)($row['owner_reply']??''),'owner_reply_at_utc'=>(string)($row['owner_reply_at_utc']??''),
        ];
    }
    usort($rows,function($a,$b)use($kind){if($kind==='review'&&($a['featured']??false)!==($b['featured']??false))return $a['featured']?-1:1;return strcmp((string)$b['created_at_utc'],(string)$a['created_at_utc']);});
    return array_slice($rows,0,max(1,min(250,$limit)));
}
function fs_community_summary(?array $data=null): array {
    $data=$data??fs_community_load();$summary=['pending'=>0,'approved_comments'=>0,'approved_reviews'=>0,'featured'=>0,'verified'=>0,'average_rating'=>0.0,'top_chapter'=>'none','top_chapter_count'=>0,'total'=>0];$ratings=[];$chapters=[];
    foreach($data['submissions']??[] as $row){if(!is_array($row))continue;$summary['total']++;$status=(string)($row['status']??'pending');if($status==='pending')$summary['pending']++;if($status!=='approved')continue;
        if(($row['kind']??'')==='review'){$summary['approved_reviews']++;$rating=(int)($row['rating']??0);if($rating>=1&&$rating<=5)$ratings[]=$rating;if(!empty($row['featured']))$summary['featured']++;if(!empty($row['verified']))$summary['verified']++;}
        if(($row['kind']??'')==='comment'){$summary['approved_comments']++;$chapter=(string)($row['chapter']??'unknown');$chapters[$chapter]=($chapters[$chapter]??0)+1;}
    }
    if($ratings)$summary['average_rating']=round(array_sum($ratings)/count($ratings),1);if($chapters){arsort($chapters);$summary['top_chapter']=(string)array_key_first($chapters);$summary['top_chapter_count']=(int)current($chapters);}return $summary;
}
function fs_community_filtered(string $status='all',string $kind='all',int $limit=300): array {
    $rows=[];foreach(fs_community_load()['submissions'] as $row){if(!is_array($row))continue;if($status!=='all'&&($row['status']??'')!==$status)continue;if($kind!=='all'&&($row['kind']??'')!==$kind)continue;$rows[]=$row;}
    usort($rows,fn($a,$b)=>strcmp((string)($b['created_at_utc']??''),(string)($a['created_at_utc']??'')));return array_slice($rows,0,max(1,min(1000,$limit)));
}
function fs_community_moderate(string $id,string $operation,array $input=[]): array {
    return fs_community_mutate(function(array &$data)use($id,$operation,$input){
        foreach($data['submissions'] as $i=>$row){if(!is_array($row)||(string)($row['id']??'')!==$id)continue;
            if($operation==='delete'){array_splice($data['submissions'],$i,1);return [true,'Submission permanently deleted.'];}
            if(in_array($operation,['approve','reject','hide','pending'],true)){$row['status']=$operation==='approve'?'approved':($operation==='hide'?'hidden':$operation);$row['moderated_at_utc']=gmdate('c');}
            elseif($operation==='feature')$row['featured']=true;elseif($operation==='unfeature')$row['featured']=false;
            elseif($operation==='verify')$row['verified']=true;elseif($operation==='unverify')$row['verified']=false;
            elseif($operation==='reply'){$row['owner_reply']=fs_community_text($input['owner_reply']??'',2000);$row['owner_reply_at_utc']=$row['owner_reply']!==''?gmdate('c'):null;}
            elseif($operation==='edit'){$row['name']=fs_community_text($input['name']??$row['name']??'',100);$row['title']=fs_community_text($input['title']??$row['title']??'',180);$row['body']=fs_community_text($input['body']??$row['body']??'',5000);}
            else return [false,'Unknown moderation action.'];
            $row['moderation_note']=fs_community_text($input['moderation_note']??($row['moderation_note']??''),500);$row['updated_at_utc']=gmdate('c');$data['submissions'][$i]=$row;return [true,'Reader submission updated.'];
        }return [false,'Reader submission not found.'];
    });
}
function fs_community_export_csv(array $rows,string $timezone='America/Chicago'): string {
    $out=fopen('php://temp','w+');fputcsv($out,['ID','Kind','Status','When','Chapter/Page','Name','Rating','Title','Body','Parent ID','Featured','Verified','Owner Reply','Source URL','Moderation Note']);
    foreach($rows as $r){if(!is_array($r))continue;fputcsv($out,[fs_csv_cell((string)($r['id']??'')),(string)($r['kind']??''),(string)($r['status']??''),fs_social_local_time((string)($r['created_at_utc']??''),$timezone),(string)($r['chapter']??$r['page_title']??''),fs_csv_cell((string)($r['name']??'')),(int)($r['rating']??0),fs_csv_cell((string)($r['title']??'')),fs_csv_cell((string)($r['body']??'')),(string)($r['parent_id']??''),!empty($r['featured'])?'yes':'no',!empty($r['verified'])?'yes':'no',fs_csv_cell((string)($r['owner_reply']??'')),fs_csv_cell((string)($r['source_url']??'')),fs_csv_cell((string)($r['moderation_note']??''))]);}
    rewind($out);$csv=stream_get_contents($out);fclose($out);return "\\xEF\\xBB\\xBF".($csv?:'');
}
function fs_community_notify(array $row,array $settings,array $config): void {
    if(empty($settings['community_email_notifications']))return;$to=filter_var((string)($settings['owner_email']??''),FILTER_VALIDATE_EMAIL);if(!$to)return;
    $siteHost=(string)(parse_url(fs_base_url($config),PHP_URL_HOST)??'bobsome1.com');$kind=($row['kind']??'comment')==='review'?'book review':'chapter comment';
    $subject='Project Unveiled: new '.$kind.' awaiting approval';$body="A new reader submission is waiting in your private moderation dashboard.\\n\\nType: ".$kind."\\nName: ".($row['name']??'Reader')."\\nPage: ".($row['page_title']??$row['chapter']??'')."\\nRating: ".($row['rating']??'')."\\n\\n".($row['body']??'')."\\n\\nModerate: ".fs_base_url($config)."/owner/funnel-servant/?tab=community";
    $headers=['Content-Type: text/plain; charset=UTF-8','From: Project Unveiled <no-reply@'.$siteHost.'>'];$ok=@mail((string)$to,$subject,$body,implode("\\r\\n",$headers));fs_log('community_email',$ok?'Reader notification sent':'Reader notification failed',['id'=>$row['id']??'']);
}
