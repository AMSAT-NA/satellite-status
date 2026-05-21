<?php
declare(strict_types=1);

namespace AmsatStatus\Tests;

/**
 * Tests for GET /api/v1/satellites -- the satellite-catalog endpoint.
 */
final class SatellitesEndpointTest extends TestCase
{
    public function testReturnsJsonArray(): void
    {
        $resp = $this->newGuestClient()->get('/api/v1/satellites.php');

        $this->assertSame(200, $resp->getStatusCode());
        $this->assertSame('application/json', $resp->getHeaderLine('Content-Type'));

        $data = json_decode((string) $resp->getBody(), true);
        $this->assertIsArray($data);
    }

    public function testReturnsAllSeededSatellites(): void
    {
        // TestCase::resetDatabase() seeds 3 rows.
        $data = $this->fetchCatalog();
        $this->assertCount(3, $data);
    }

    public function testEntryShape(): void
    {
        $data  = $this->fetchCatalog();
        $entry = $data[0];

        $this->assertSame(
            ['id', 'name', 'html_element_name', 'website'],
            array_keys($entry)
        );
        $this->assertIsInt($entry['id']);
        $this->assertIsString($entry['name']);
        $this->assertIsString($entry['html_element_name']);
        $this->assertIsString($entry['website']);
    }

    public function testOrderedByNameAscending(): void
    {
        $names = array_column($this->fetchCatalog(), 'name');
        $sorted = $names;
        sort($sorted);
        $this->assertSame($sorted, $names);
    }

    public function testReflectsNewlyAddedSatellite(): void
    {
        $admin = $this->login();
        $admin->post('/admin/create_satellite.php', [
            'form_params' => [
                'satellite_name'      => 'ZZ-NEW-SAT',
                'html_satellite_name' => 'ZZ-1',
                'website'             => 'https://example.com',
            ],
        ]);

        $names = array_column($this->fetchCatalog(), 'name');
        $this->assertContains('ZZ-NEW-SAT', $names);
    }

    public function testEmptyCatalogReturnsEmptyArray(): void
    {
        $this->db->query('TRUNCATE TABLE satellite_name');

        $resp = $this->newGuestClient()->get('/api/v1/satellites.php');
        $this->assertSame('[]', trim((string) $resp->getBody()));
    }

    private function fetchCatalog(): array
    {
        $resp = $this->newGuestClient()->get('/api/v1/satellites.php');
        $data = json_decode((string) $resp->getBody(), true);
        $this->assertIsArray($data, 'Response was not a JSON array: ' . (string) $resp->getBody());
        return $data;
    }
}
