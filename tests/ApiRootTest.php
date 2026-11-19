<?php
declare(strict_types=1);

namespace AmsatStatus\Tests;

/**
 * /api (the versions index, #38) is api-v2's since it was deployed
 * (api/v2/src/routes/versions.ts, tested in api/v2/test/versions.test.ts).
 * What's left for v1: it doesn't answer /api itself, and unversioned data
 * paths still don't alias any version's endpoints.
 *
 * The Apache side (the v1 image 404s /api and /api/) is checked against
 * the real image in ci.yml's docker-compose-smoke job.
 */
final class ApiRootTest extends TestCase
{
    public function testV1NoLongerServesTheVersionsIndex(): void
    {
        $client = $this->newGuestClient();

        foreach (['/api', '/api/'] as $path) {
            $this->assertSame(404, $client->get($path)->getStatusCode(), $path);
        }
    }

    /**
     * /api is a front door, not an alias: unversioned data paths must not
     * resolve to any version's endpoints.
     */
    public function testUnversionedDataPathsAreNotAliased(): void
    {
        $client = $this->newGuestClient();

        foreach (['/api/reports.php', '/api/catalog.php', '/api/health.php'] as $path) {
            $this->assertSame(404, $client->get($path)->getStatusCode(), $path);
        }
    }

    public function testVersionOneRootStillAnswers(): void
    {
        $resp = $this->newGuestClient()->get('/api/v1/');

        $this->assertSame(200, $resp->getStatusCode());
    }
}
