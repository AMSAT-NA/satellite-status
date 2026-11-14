<?php
declare(strict_types=1);

namespace AmsatStatus\Tests;

/**
 * Issue #67: frontend-v1's public pages read through the API, not MariaDB.
 * StatusGridTest covers the grid's counting rules end to end; these cover
 * the rest of the page and the footer.
 */
final class StatusPageViaApiTest extends TestCase
{
    public function testPublicPagesDoNotQueryTheDatabase(): void
    {
        foreach (['index.php', 'submit.php'] as $file) {
            $source = (string) file_get_contents(__DIR__ . '/../frontend/v1/' . $file);
            $this->assertStringNotContainsString('mysqli', $source, "$file still talks to MariaDB");
            $this->assertStringNotContainsString('$mysqlHost', $source, "$file still talks to MariaDB");
        }
    }

    public function testSatelliteDropdownComesFromTheCatalog(): void
    {
        $body = (string) $this->newGuestClient()->get('/')->getBody();

        $this->assertMatchesRegularExpression('#<option value="AO-91">[^<]+</option>#', $body);
        $this->assertMatchesRegularExpression('#<option value="ISS">[^<]+</option>#', $body);
    }

    public function testReportsFromIsCaseInsensitiveAndSkipsTestCallsigns(): void
    {
        $this->insertIssReport('w5low', '10:05');
        $this->insertIssReport('W5LOW', '11:05');
        $this->insertIssReport('test', '12:05');
        $this->insertIssReport('', '13:05');

        $body = (string) $this->newGuestClient()->get('/')->getBody();

        $this->assertSame(1, substr_count($body, '<b>W5LOW</b>'));
        $this->assertStringNotContainsString('<b>TEST</b>', $body);
        $this->assertStringNotContainsString('<b></b>', $body);
    }

    public function testFooterLinksAndCredits(): void
    {
        $body = (string) $this->newGuestClient()->get('/')->getBody();

        $this->assertStringContainsString('blob/main/CHANGELOG"', $body);
        $this->assertStringContainsString('href="/api/v1/overview.php"', $body);
        $this->assertStringContainsString('blob/main/ACKNOWLEDGEMENTS.md"', $body);
        $this->assertStringContainsString("Satellite Corporation (AMSAT)\n", $body);
        $this->assertStringNotContainsString('original idea', $body);
    }

    public function testAcknowledgementsFileHasEveryCredit(): void
    {
        $text = (string) file_get_contents(__DIR__ . '/../ACKNOWLEDGEMENTS.md');

        foreach (['David Carr, KD5QGR', 'Bob Bruninga, WB4APR', 'Ben Welsh, KFØIA', 'Dominic Hord, AD8AK'] as $name) {
            $this->assertStringContainsString($name, $text);
        }
    }

    private function insertIssReport(string $callsign, string $time): void
    {
        $observationTime = gmdate('Y-m-d', time() - 86400) . ' ' . $time . ':00';
        $stmt = $this->db->prepare(
            'INSERT INTO satellite (name, longname, callsign, report, grid_square, observation_time) '
            . "VALUES ('ISS', 'ISS', ?, 'Heard', '', ?)"
        );
        $stmt->bind_param('ss', $callsign, $observationTime);
        $stmt->execute();
        $stmt->close();
    }
}
