<?php
declare(strict_types=1);

/**
 * Project Unveiled — Self-Updating Print Edition
 *
 * Normal:
 *   /book/print-edition.php
 *
 * Include chapter images:
 *   /book/print-edition.php?images=1
 *
 * Download the assembled HTML:
 *   /book/print-edition.php?download=1
 */

$siteRoot = dirname(__DIR__);
$includeImages = isset($_GET['images']) && $_GET['images'] === '1';
$downloadHtml = isset($_GET['download']) && $_GET['download'] === '1';

$documents = [
    ['chapter-01.html', 'Chapter One'],
    ['chapter-02.html', 'Chapter Two'],
    ['chapter-03.html', 'Chapter Three'],
    ['chapter-04.html', 'Chapter Four'],
    ['chapter-05.html', 'Chapter Five'],
    ['chapter-06.html', 'Chapter Six'],
    ['chapter-07.html', 'Chapter Seven'],
    ['chapter-08.html', 'Chapter Eight'],
    ['chapter-09.html', 'Chapter Nine'],
    ['chapter-10.html', 'Chapter Ten'],
    ['chapter-11.html', 'Chapter Eleven'],
    ['chapter-12.html', 'Chapter Twelve'],
    ['chapter-13.html', 'Chapter Thirteen'],
    ['about-author.html', 'About the Author'],
    ['appendix-a.html', 'Appendix A'],
    ['appendix-b.html', 'Appendix B'],
    ['appendix-c.html', 'Appendix C'],
    ['appendix-d.html', 'Appendix D'],
    ['appendix-e.html', 'Appendix E'],
];

function h(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function classOrIdContains(DOMElement $element, array $needles): bool
{
    $haystack = strtolower(
        $element->getAttribute('class') . ' ' .
        $element->getAttribute('id') . ' ' .
        $element->getAttribute('role')
    );

    foreach ($needles as $needle) {
        if (str_contains($haystack, $needle)) {
            return true;
        }
    }

    return false;
}

function absoluteUrl(string $url, string $sourceFile): string
{
    $url = trim(html_entity_decode($url, ENT_QUOTES | ENT_HTML5, 'UTF-8'));

    if (
        $url === '' ||
        str_starts_with($url, '#') ||
        preg_match('~^(?:https?:|mailto:|tel:|data:)~i', $url)
    ) {
        return $url;
    }

    if (str_starts_with($url, '//')) {
        return 'https:' . $url;
    }

    if (str_starts_with($url, '/')) {
        return 'https://bobsome1.com' . $url;
    }

    $base = '/book/read/' . basename($sourceFile);
    $directory = dirname($base);
    $combined = $directory . '/' . $url;

    $segments = [];
    foreach (explode('/', $combined) as $segment) {
        if ($segment === '' || $segment === '.') {
            continue;
        }

        if ($segment === '..') {
            array_pop($segments);
            continue;
        }

        $segments[] = $segment;
    }

    return 'https://bobsome1.com/' . implode('/', $segments);
}

function innerHtml(DOMNode $node): string
{
    $html = '';

    foreach ($node->childNodes as $child) {
        $html .= $node->ownerDocument?->saveHTML($child) ?? '';
    }

    return $html;
}

function cleanDocument(
    string $html,
    string $sourceFile,
    bool $includeImages
): array {
    $dom = new DOMDocument('1.0', 'UTF-8');
    libxml_use_internal_errors(true);

    $loaded = $dom->loadHTML(
        '<?xml encoding="UTF-8">' . $html,
        LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD
    );

    libxml_clear_errors();

    if (!$loaded) {
        return ['', 'Could not parse the source page.'];
    }

    $xpath = new DOMXPath($dom);
    $main = $xpath->query('//main')->item(0);

    if (!$main instanceof DOMElement) {
        $main = $xpath->query('//article')->item(0);
    }

    if (!$main instanceof DOMElement) {
        $main = $xpath->query('//body')->item(0);
    }

    if (!$main instanceof DOMElement) {
        return ['', 'No printable content container was found.'];
    }

    $removeTags = [
        'nav', 'header', 'footer', 'script', 'style', 'button',
        'form', 'input', 'textarea', 'select', 'option', 'noscript',
        'iframe', 'audio', 'video', 'canvas', 'svg'
    ];

    foreach ($removeTags as $tag) {
        $nodes = [];
        foreach ($xpath->query('.//' . $tag, $main) ?: [] as $node) {
            $nodes[] = $node;
        }

        foreach ($nodes as $node) {
            $node->parentNode?->removeChild($node);
        }
    }

    $uiNeedles = [
        'nav', 'toolbar', 'control', 'button', 'share', 'progress',
        'breadcrumb', 'menu', 'response', 'comment', 'form',
        'chapter-actions', 'reader-actions', 'hero-actions',
        'site-header', 'site-footer', 'topbar'
    ];

    $elements = [];
    foreach ($xpath->query('.//*', $main) ?: [] as $element) {
        if ($element instanceof DOMElement) {
            $elements[] = $element;
        }
    }

    foreach ($elements as $element) {
        if (
            $element->getAttribute('aria-hidden') === 'true' ||
            classOrIdContains($element, $uiNeedles)
        ) {
            $element->parentNode?->removeChild($element);
            continue;
        }

        // Remove event handlers, inline layout, and interactive attributes.
        $attributes = [];
        foreach ($element->attributes ?: [] as $attribute) {
            $attributes[] = $attribute->name;
        }

        foreach ($attributes as $attributeName) {
            if (
                str_starts_with(strtolower($attributeName), 'on') ||
                in_array(strtolower($attributeName), [
                    'style', 'contenteditable', 'tabindex',
                    'aria-expanded', 'aria-controls'
                ], true)
            ) {
                $element->removeAttribute($attributeName);
            }
        }

        if ($element->tagName === 'a' && $element->hasAttribute('href')) {
            $element->setAttribute(
                'href',
                absoluteUrl($element->getAttribute('href'), $sourceFile)
            );
        }

        if ($element->tagName === 'img') {
            if (!$includeImages) {
                $element->parentNode?->removeChild($element);
            } elseif ($element->hasAttribute('src')) {
                $element->setAttribute(
                    'src',
                    absoluteUrl($element->getAttribute('src'), $sourceFile)
                );
                $element->setAttribute('loading', 'eager');
            }
        }
    }

    // Remove empty wrappers left behind after UI/image cleanup.
    $all = [];
    foreach ($xpath->query('.//*', $main) ?: [] as $element) {
        if ($element instanceof DOMElement) {
            $all[] = $element;
        }
    }

    for ($index = count($all) - 1; $index >= 0; $index--) {
        $element = $all[$index];

        if (
            in_array($element->tagName, ['div', 'section', 'figure', 'span'], true) &&
            trim($element->textContent ?? '') === '' &&
            !$element->getElementsByTagName('img')->length
        ) {
            $element->parentNode?->removeChild($element);
        }
    }

    $printHtml = innerHtml($main);
    $textLength = mb_strlen(trim(strip_tags($printHtml)));

    if ($textLength < 300) {
        return [$printHtml, 'Extracted content appears unusually short (' . $textLength . ' characters).'];
    }

    return [$printHtml, ''];
}

$sections = [];
$errors = [];

foreach ($documents as [$filename, $fallbackTitle]) {
    $relativePath = 'book/read/' . $filename;
    $fullPath = $siteRoot . '/' . $relativePath;

    if (!is_file($fullPath)) {
        $errors[] = $relativePath . ' was not found.';
        continue;
    }

    $source = file_get_contents($fullPath);

    if ($source === false) {
        $errors[] = $relativePath . ' could not be read.';
        continue;
    }

    [$content, $warning] = cleanDocument($source, $relativePath, $includeImages);

    $title = $fallbackTitle;

    if (preg_match('~<h1\b[^>]*>(.*?)</h1>~isu', $content, $matches)) {
        $candidate = trim(html_entity_decode(strip_tags($matches[1]), ENT_QUOTES | ENT_HTML5, 'UTF-8'));

        if ($candidate !== '') {
            $title = $candidate;
        }
    }

    $anchor = preg_replace('~[^a-z0-9]+~', '-', strtolower($filename));
    $anchor = trim($anchor ?? $filename, '-');

    $sections[] = [
        'title' => $title,
        'anchor' => $anchor,
        'content' => $content,
        'source' => 'https://bobsome1.com/' . $relativePath,
        'warning' => $warning,
    ];
}

if ($downloadHtml) {
    header('Content-Disposition: attachment; filename="project-unveiled-public-edition.html"');
}
?>
<!doctype html>
<html lang="en-US">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <meta name="robots" content="noindex,follow">
    <title>Project Unveiled — Complete Public Print Edition</title>
    <style>
        :root{
            color-scheme:light dark;
            --paper:#fffdf8;
            --ink:#1b1813;
            --muted:#665f54;
            --gold:#8a671f;
            --rule:#c9b98f;
        }
        *{box-sizing:border-box}
        html{scroll-behavior:smooth}
        body{
            margin:0;
            background:#17130d;
            color:#eee6d6;
            font-family:Georgia,"Times New Roman",serif;
            line-height:1.7;
        }
        .screen-tools{
            position:sticky;
            top:0;
            z-index:20;
            display:flex;
            flex-wrap:wrap;
            gap:10px;
            align-items:center;
            justify-content:center;
            padding:12px;
            background:rgba(8,7,5,.96);
            border-bottom:1px solid rgba(211,176,93,.35);
            font-family:Arial,sans-serif;
        }
        .screen-tools a,.screen-tools button{
            appearance:none;
            border:1px solid #d2ab53;
            background:#15110b;
            color:#f5dfac;
            padding:10px 14px;
            border-radius:999px;
            text-decoration:none;
            cursor:pointer;
            font:inherit;
        }
        .book{
            width:min(900px,calc(100% - 24px));
            margin:28px auto 70px;
            background:var(--paper);
            color:var(--ink);
            box-shadow:0 22px 70px rgba(0,0,0,.42);
        }
        .cover{
            min-height:92vh;
            display:grid;
            place-content:center;
            text-align:center;
            padding:70px 9%;
            border:18px solid #17120a;
            outline:1px solid #a98638;
            outline-offset:-28px;
        }
        .eyebrow{
            text-transform:uppercase;
            letter-spacing:.2em;
            font:700 .76rem/1.3 Arial,sans-serif;
            color:var(--gold);
        }
        .cover h1{
            margin:.35em 0 .2em;
            font-size:clamp(3.2rem,10vw,6.7rem);
            line-height:.92;
            letter-spacing:-.045em;
        }
        .subtitle{
            max-width:670px;
            margin:0 auto;
            font-size:1.2rem;
            color:var(--muted);
        }
        .author{
            margin-top:42px;
            font-size:1.15rem;
            letter-spacing:.08em;
            text-transform:uppercase;
        }
        .edition-note{
            margin-top:48px;
            font:italic .98rem/1.6 Georgia,serif;
            color:var(--muted);
        }
        .frontmatter,.chapter{
            padding:62px 10%;
        }
        .frontmatter{
            border-top:1px solid var(--rule);
        }
        .frontmatter h2,.chapter h1,.chapter h2{
            line-height:1.14;
        }
        .toc{
            columns:2;
            column-gap:42px;
            padding-left:1.2em;
        }
        .toc li{
            break-inside:avoid;
            margin:0 0 .48em;
        }
        .toc a{
            color:var(--ink);
            text-decoration:none;
            border-bottom:1px dotted var(--rule);
        }
        .chapter{
            border-top:1px solid var(--rule);
        }
        .chapter + .chapter{
            break-before:page;
            page-break-before:always;
        }
        .chapter h1{
            font-size:2.45rem;
            margin-top:0;
        }
        .chapter h2{
            margin-top:2.1em;
            font-size:1.65rem;
        }
        .chapter h3{
            margin-top:1.65em;
            font-size:1.28rem;
        }
        .chapter p{
            orphans:3;
            widows:3;
        }
        .chapter blockquote{
            margin:1.5em 0;
            padding:.25em 1.25em;
            border-left:4px solid var(--gold);
            color:#51493e;
        }
        .chapter img{
            display:block;
            max-width:100%;
            height:auto;
            margin:1.4em auto;
            break-inside:avoid;
        }
        .chapter a{
            color:#624914;
            overflow-wrap:anywhere;
        }
        .source-note{
            margin-top:3em;
            padding-top:1em;
            border-top:1px solid var(--rule);
            font:italic .82rem/1.5 Arial,sans-serif;
            color:var(--muted);
        }
        .warning{
            margin:1em 0;
            padding:10px 12px;
            border:1px solid #c18d22;
            background:#fff4d4;
            color:#4d3710;
            font:700 .86rem/1.45 Arial,sans-serif;
        }
        .error-panel{
            margin:30px;
            padding:18px;
            border:2px solid #ad3f3f;
            background:#ffe9e9;
            color:#551717;
        }
        @page{
            size:Letter;
            margin:.72in .68in .72in .78in;
        }
        @media print{
            body{background:#fff;color:#000}
            .screen-tools{display:none!important}
            .book{
                width:auto;
                margin:0;
                box-shadow:none;
                background:#fff;
            }
            .cover{
                min-height:9.1in;
                border:12px solid #111;
                outline:1px solid #777;
                outline-offset:-20px;
                break-after:page;
                page-break-after:always;
            }
            .frontmatter{
                break-after:page;
                page-break-after:always;
            }
            .chapter{
                padding:0;
                border-top:0;
            }
            .source-note{
                color:#555;
            }
            a{
                color:inherit!important;
                text-decoration:none!important;
            }
        }
        @media(max-width:700px){
            .toc{columns:1}
            .frontmatter,.chapter{padding:42px 8%}
            .cover{padding:60px 8%;border-width:10px}
        }
    </style>
</head>
<body>
    <nav class="screen-tools" aria-label="Print edition tools">
        <a href="/book/">Book Home</a>
        <a href="/book/read/">Reader Home</a>
        <button type="button" onclick="window.print()">Print / Save as PDF</button>
        <?php if ($includeImages): ?>
            <a href="/book/print-edition.php">Text-First Edition</a>
        <?php else: ?>
            <a href="/book/print-edition.php?images=1">Include Images</a>
        <?php endif; ?>
        <a href="/book/print-edition.php?download=1<?= $includeImages ? '&images=1' : '' ?>">Download HTML</a>
    </nav>

    <main class="book">
        <section class="cover">
            <p class="eyebrow">Complete Public Edition</p>
            <h1>Project<br>Unveiled</h1>
            <p class="subtitle">Truth Is Not Afraid of Questions</p>
            <p class="author">Robert J. Hayes</p>
            <p class="edition-note">
                Public web edition assembled directly from Bobsome1.com.<br>
                Source hardening, documented corrections, and editorial review remain active.
            </p>
        </section>

        <section class="frontmatter" id="contents">
            <p class="eyebrow">Contents</p>
            <h2>Table of Contents</h2>

            <?php if ($errors): ?>
                <div class="error-panel">
                    <strong>Assembly warnings:</strong>
                    <ul>
                        <?php foreach ($errors as $error): ?>
                            <li><?= h($error) ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>

            <ol class="toc">
                <?php foreach ($sections as $section): ?>
                    <li><a href="#<?= h($section['anchor']) ?>"><?= h($section['title']) ?></a></li>
                <?php endforeach; ?>
            </ol>

            <p>
                This edition is designed for reading, review, printing, and saving
                through the browser's PDF printer. It remains synchronized with the
                public HTML chapters.
            </p>
        </section>

        <?php foreach ($sections as $section): ?>
            <article class="chapter" id="<?= h($section['anchor']) ?>">
                <?php if ($section['warning'] !== ''): ?>
                    <p class="warning"><?= h($section['warning']) ?></p>
                <?php endif; ?>

                <?= $section['content'] ?>

                <p class="source-note">
                    Public source:
                    <a href="<?= h($section['source']) ?>"><?= h($section['source']) ?></a>
                </p>
            </article>
        <?php endforeach; ?>
    </main>
</body>
</html>