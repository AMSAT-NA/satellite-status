<?php
declare(strict_types=1);

namespace AmsatStatus\Tests;

/**
 * /api front door (api/index.php) -- see issue #38.
 *
 * Covers the PHP side through the dev-server router. The Apache side
 * (bare /api served without a mod_dir redirect, build files not in the
 * web root) can only be checked against the real image, so it lives in
 * the docker-compose-smoke job in ci.yml instead.
 */
final class ApiRootTest extends TestCase
{
    public function testApiRootListsVersions(): void
    {
        $resp = $this->newGuestClient()->get('/api/');

        $this->assertSame(200, $resp->getStatusCode());
        $this->assertSame('application/json; charset=utf-8', $resp->getHeaderLine('Content-Type'));
        $this->assertSame('*', $resp->getHeaderLine('Access-Control-Allow-Origin'));

        $data = json_decode((string) $resp->getBody(), true)['data'];

        $this->assertSame('AMSAT Satellite Status API', $data['name']);
        $this->assertStringEndsWith('/api/', $data['links']['self']);
        $this->assertNotEmpty($data['versions']);

        $current = array_values(array_filter(
            $data['versions'],
            static fn (array $v): bool => $v['status'] === 'current'
        ));
        $this->assertCount(1, $current, 'exactly one version is current');

        $v1 = array_values(array_filter(
            $data['versions'],
            static fn (array $v): bool => $v['version'] === 'v1'
        ))[0];
        $root = rtrim($data['links']['self'], '/');
        $this->assertSame($root . '/v1/', $v1['url']);
        $this->assertSame($root . '/v1/docs.php', $v1['docs']);
        $this->assertSame($root . '/v1/openapi.php', $v1['openapi']);
    }

    /**
     * Every advertised version link must actually resolve -- the index is
     * only useful if it never points somewhere dead.
     */
    public function testEveryAdvertisedVersionLinkResolves(): void
    {
        $client = $this->newGuestClient();
        $data = json_decode((string) $client->get('/api/')->getBody(), true)['data'];

        foreach ($data['versions'] as $v) {
            foreach (['url', 'docs', 'openapi'] as $rel) {
                $path = (string) parse_url($v[$rel], PHP_URL_PATH);
                $resp = $client->get($path);
                $this->assertSame(200, $resp->getStatusCode(), "{$v['version']}.{$rel} -> {$path}");
            }
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

    public function testApiRootRejectsNonGetMethods(): void
    {
        $resp = $this->newGuestClient()->post('/api/');

        $this->assertSame(405, $resp->getStatusCode());
        $this->assertSame('GET', $resp->getHeaderLine('Allow'));
    }
}
