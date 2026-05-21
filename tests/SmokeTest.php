<?php
declare(strict_types=1);

namespace AmsatStatus\Tests;

/**
 * Coarse "the app is responding" tests. These are cheap insurance
 * against the kind of regression where a syntactically valid change
 * breaks the page in an obvious way.
 */
final class SmokeTest extends TestCase
{
    public function testPublicStatusPageReturns200(): void
    {
        $resp = $this->newGuestClient()->get('/');
        $this->assertSame(200, $resp->getStatusCode());
        $this->assertStringContainsString('AO-91', (string) $resp->getBody());
    }

    public function testApiSatInfoReturnsJsonArray(): void
    {
        $resp = $this->newGuestClient()->get('/api/v1/sat_info.php', [
            'query' => ['name' => 'AO-91', 'hours' => 72],
        ]);

        $this->assertSame(200, $resp->getStatusCode());
        $this->assertSame('application/json', $resp->getHeaderLine('Content-Type'));

        $data = json_decode((string) $resp->getBody(), true);
        $this->assertIsArray($data);
        $this->assertNotEmpty($data, 'Expected at least one seeded AO-91 report');
        $this->assertArrayHasKey('callsign', $data[0]);
    }

    public function testApiSatInfoMissingNameReturnsEmptyArray(): void
    {
        $resp = $this->newGuestClient()->get('/api/v1/sat_info.php');
        $this->assertSame('[]', trim((string) $resp->getBody()));
    }

    public function testApiSatInfoClampsHoursToMax(): void
    {
        // hours=999 should be clamped to 96; we can't directly observe the
        // clamping, but we can confirm the request succeeds and doesn't
        // bomb out with an error.
        $resp = $this->newGuestClient()->get('/api/v1/sat_info.php', [
            'query' => ['name' => 'AO-91', 'hours' => 9999],
        ]);
        $this->assertSame(200, $resp->getStatusCode());
        $this->assertIsArray(json_decode((string) $resp->getBody(), true));
    }
}
