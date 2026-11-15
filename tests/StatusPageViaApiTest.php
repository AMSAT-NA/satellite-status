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

        // Alphabetical: Acknowledgments, API, Changelog.
        $this->assertMatchesRegularExpression(
            '#ACKNOWLEDGMENTS\.md"[^>]*>Acknowledgments</a> &middot; <a href="/api/v1/overview\.php"[^>]*>API</a> &middot; <a href="[^"]*blob/main/CHANGELOG"[^>]*>Changelog</a>#',
            $body
        );
        $this->assertStringContainsString("Satellite Corporation (AMSAT)\n", $body);

        // The Support AMSAT button sits in the footer, under the copyright.
        $copyright = strpos($body, 'Satellite Corporation (AMSAT)');
        $button = strpos($body, '>Support AMSAT</a>');
        $this->assertNotFalse($button);
        $this->assertGreaterThan($copyright, $button);
        $this->assertSame(1, substr_count($body, '>Support AMSAT</a>'));
        $this->assertStringNotContainsString('original idea', $body);
    }

    public function testAcknowledgmentsFileHasEveryCredit(): void
    {
        $text = (string) file_get_contents(__DIR__ . '/../ACKNOWLEDGMENTS.md');

        foreach (['David Carr, KD5QGR', 'Bob Bruninga, WB4APR', 'Ben Welsh, KFØIA', 'Dominic Hord, AD8AK'] as $name) {
            $this->assertStringContainsString($name, $text);
        }
    }

    /**
     * The submit pages (the "You Entered" review and the thank-you page)
     * share the status page's footer, and the old PayPal Donate form is
     * gone.
     */
    public function testSubmitPagesShareTheFooter(): void
    {
        [$year, $month, $day] = explode('-', gmdate('Y-m-d', strtotime('yesterday UTC')));
        $query = [
            'SatSubmit' => 'yes', 'SatName' => 'AO-91', 'SatYear' => $year, 'SatMonth' => $month,
            'SatDay' => $day, 'SatTime' => '12:00', 'SatCall' => 'W5FTR', 'SatReport' => 'Heard',
            'SatGridSquare' => 'EM48',
        ];

        foreach (['review' => $query, 'thank you' => $query + ['Confirm' => 'yes']] as $page => $q) {
            $body = (string) $this->newGuestClient()->get('/submit.php', ['query' => $q])->getBody();

            $this->assertStringContainsString('Satellite Corporation (AMSAT)', $body, $page);
            $this->assertStringContainsString('>Support AMSAT</a>', $body, $page);
            $this->assertStringContainsString('>Acknowledgments</a>', $body, $page);
            $this->assertStringNotContainsString('paypal.com', $body, $page);
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
