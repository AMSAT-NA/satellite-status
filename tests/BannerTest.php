<?php
declare(strict_types=1);

namespace AmsatStatus\Tests;

/**
 * Covers frontend/v1/banner.php and index.php's conditional embed of it.
 *
 * content/banner.md is gitignored and host-edited by design (see
 * frontend/v1/content/banner.md.example), so these tests manage its
 * lifecycle themselves -- writing it before a request and always
 * removing it afterward, even on failure, so no test leaks banner
 * state into another test or into a developer's working tree.
 */
final class BannerTest extends TestCase
{
    private const BANNER_PATH = __DIR__ . '/../frontend/v1/content/banner.md';
    private const EXAMPLE_TEMPLATE_PATH = __DIR__ . '/../frontend/v1/content/banner.md.example';

    protected function tearDown(): void
    {
        $this->removeBanner();
        parent::tearDown();
    }

    private function writeBanner(string $content): void
    {
        file_put_contents(self::BANNER_PATH, $content);
    }

    private function removeBanner(): void
    {
        if (is_file(self::BANNER_PATH)) {
            unlink(self::BANNER_PATH);
        }
    }

    public function testRendersMarkdownAndRawHtml(): void
    {
        $this->writeBanner("# Heads up\n\nThis is **bold** and some raw HTML: <em>emphasis</em>.");

        $resp = $this->newGuestClient()->get('/banner.php');
        $body = (string) $resp->getBody();

        $this->assertSame(200, $resp->getStatusCode());
        $this->assertStringContainsString('<h1>Heads up</h1>', $body);
        $this->assertStringContainsString('<strong>bold</strong>', $body);
        $this->assertStringContainsString('<em>emphasis</em>', $body);
    }

    public function testEmptyWhenFileMissing(): void
    {
        $this->removeBanner();

        $resp = $this->newGuestClient()->get('/banner.php');
        $body = (string) $resp->getBody();

        $this->assertSame(200, $resp->getStatusCode());
        $this->assertStringContainsString('<body></body>', $body);
    }

    public function testEmptyWhenFileEmpty(): void
    {
        $this->writeBanner("   \n\n  ");

        $resp = $this->newGuestClient()->get('/banner.php');
        $body = (string) $resp->getBody();

        $this->assertSame(200, $resp->getStatusCode());
        $this->assertStringContainsString('<body></body>', $body);
    }

    public function testForcesTargetBlankAndRelNoopenerOnEveryLink(): void
    {
        $this->writeBanner(
            "A [markdown link](https://example.com/md) here.\n\n"
            . '<a href="https://example.com/raw">a raw link</a>, '
            . '<a href="https://example.com/withrel" rel="nofollow">a link with an existing rel</a>.'
        );

        $body = (string) $this->newGuestClient()->get('/banner.php')->getBody();

        $this->assertStringContainsString(
            '<a href="https://example.com/md" target="_blank" rel="noopener">',
            $body
        );
        $this->assertStringContainsString(
            '<a href="https://example.com/raw" target="_blank" rel="noopener">',
            $body
        );
        // An author-specified rel is preserved, not clobbered -- noopener
        // is merged in alongside it.
        $this->assertStringContainsString(
            '<a href="https://example.com/withrel" rel="nofollow noopener" target="_blank">',
            $body
        );
    }

    public function testIndexDoesNotEmitIframeWithoutActiveBanner(): void
    {
        $this->removeBanner();

        $body = (string) $this->newGuestClient()->get('/')->getBody();

        $this->assertStringNotContainsString('id="site-banner"', $body);
    }

    public function testIndexEmitsIframeWithActiveBanner(): void
    {
        $this->writeBanner('An active announcement.');

        $body = (string) $this->newGuestClient()->get('/')->getBody();

        $this->assertStringContainsString('id="site-banner"', $body);
        $this->assertStringContainsString('src="banner.php"', $body);
    }

    /**
     * Smoke test of the render path using the tracked template, not a
     * guarantee about future live content -- content/banner.md is
     * gitignored, so CI never sees the real file. This only proves the
     * mechanism itself works end to end against known-good input.
     */
    public function testExampleTemplateRendersWithoutPhpWarningsOrErrors(): void
    {
        $template = file_get_contents(self::EXAMPLE_TEMPLATE_PATH);
        $this->assertIsString($template, 'Could not read banner.md.example');

        $this->writeBanner($template);

        $resp = $this->newGuestClient()->get('/banner.php');
        $body = (string) $resp->getBody();

        $this->assertSame(200, $resp->getStatusCode());
        // This app does surface PHP diagnostics inline into response
        // bodies elsewhere (confirmed against submit.php), so this is a
        // real assertion against a real failure mode, not a formality.
        foreach (['Warning', 'Fatal error', 'Deprecated', 'Notice', 'Parse error'] as $marker) {
            $this->assertStringNotContainsString("<b>{$marker}</b>", $body);
        }
    }
}
