<?php

declare(strict_types=1);

/**
 * Renders content/banner.md (Markdown + raw HTML, host-edited, never
 * committed -- see content/banner.md.example) as a small, self-
 * contained HTML document meant to be embedded via <iframe> from
 * index.php.
 *
 * Real DOM isolation, not best-effort cleanup: index.php embeds this
 * endpoint's *output* inside an <iframe>, a genuine browser-enforced
 * separate document. Malformed banner markup structurally cannot break
 * anything outside that iframe. The DOMDocument tag-balancing below is
 * purely so the banner's own box doesn't look visibly broken (an
 * unclosed tag swallowing the rest of the banner) -- it is not a
 * security boundary; the iframe already is one.
 */

require_once __DIR__ . '/lib/Parsedown.php';

function banner_empty_response(): string
{
    return "<!DOCTYPE html>\n<html><head><meta charset=\"utf-8\"></head><body></body></html>";
}

/**
 * Renders $markdown into a complete, tag-balanced HTML document with
 * every link forced to open in a new tab.
 */
function banner_render(string $markdown): string
{
    $parsedown = new Parsedown();

    // Safe mode intentionally left off (Parsedown's default): raw HTML
    // passthrough is wanted here, and is fine, *only* because
    // content/banner.md is exclusively edited directly on the host by
    // the project owner -- never user- or API-submitted. If this
    // render path is ever reused for anything that accepts untrusted
    // input, safe mode (or real sanitization) would need to be added;
    // don't assume this file already does that.
    $bodyHtml = $parsedown->text($markdown);

    $style = <<<'CSS'
        body {
          margin: 8px 12px;
          font-family: "Helvetica Neue", Helvetica, Arial, sans-serif;
          font-size: 14px;
          line-height: 1.42857143;
          color: #333;
          background: #fff;
        }
        a { color: #648fff; }
        CSS;

    // window.location.origin (not '*') as the postMessage target
    // origin: this document and its parent are always same-origin by
    // construction (both served from this app), so scoping delivery to
    // that exact origin is strictly safer than '*' with no downside.
    $resizeScript = <<<'JS'
        function sendHeight() {
          window.parent.postMessage(
            { source: 'site-banner', height: document.documentElement.scrollHeight },
            window.location.origin
          );
        }
        window.addEventListener('DOMContentLoaded', sendHeight);
        window.addEventListener('load', sendHeight);
        JS;

    $html = '<!DOCTYPE html>' . "\n"
        . '<html><head><meta charset="utf-8">'
        . '<style>' . $style . '</style>'
        . '</head><body>' . $bodyHtml
        . '<script>' . $resizeScript . '</script>'
        . '</body></html>';

    return banner_balance_and_link_harden($html);
}

/**
 * Tag-balances $html via DOMDocument and forces target="_blank"
 * rel="noopener" onto every link, regardless of what the source
 * content did or didn't specify.
 */
function banner_balance_and_link_harden(string $html): string
{
    libxml_use_internal_errors(true);
    $dom = new DOMDocument();

    // The '<?xml encoding="utf-8">' prefix is the standard workaround
    // for DOMDocument::loadHTML() otherwise assuming ISO-8859-1 and
    // mangling non-ASCII content. The injected processing-instruction
    // node is removed below so it doesn't appear in the output.
    $dom->loadHTML('<?xml encoding="utf-8">' . $html);
    libxml_clear_errors();

    foreach ($dom->childNodes as $node) {
        if ($node->nodeType === XML_PI_NODE) {
            $dom->removeChild($node);
            break;
        }
    }

    foreach ($dom->getElementsByTagName('a') as $link) {
        $link->setAttribute('target', '_blank');

        $rel = array_filter(explode(' ', trim((string) $link->getAttribute('rel'))));
        if (!in_array('noopener', $rel, true)) {
            $rel[] = 'noopener';
        }
        $link->setAttribute('rel', implode(' ', $rel));
    }

    $rendered = $dom->saveHTML();

    return $rendered === false ? banner_empty_response() : $rendered;
}

function banner_output(): void
{
    $path = __DIR__ . '/content/banner.md';

    if (!is_file($path)) {
        echo banner_empty_response();
        return;
    }

    try {
        $markdown = file_get_contents($path);

        if ($markdown === false) {
            throw new RuntimeException("Could not read {$path}");
        }

        if (trim($markdown) === '') {
            echo banner_empty_response();
            return;
        }

        echo banner_render($markdown);
    } catch (Throwable $e) {
        // Fail closed: never a visible error on the page, only a
        // server-side log entry.
        error_log('banner.php: ' . $e->getMessage());
        echo banner_empty_response();
    }
}

header('Content-Type: text/html; charset=utf-8');
banner_output();
