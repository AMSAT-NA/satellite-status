#!/usr/bin/env php
<?php

declare(strict_types=1);

/**
 * CI guard against new raw DB connections missing the UTC time_zone pin
 * (Issue #24 follow-up).
 *
 * ── Why this exists ──────────────────────────────────────────────────────
 * `observed_at` / `submitted_at` (and `satellite_name.date_changed`) are
 * TIMESTAMP columns, which implicitly convert based on the connection's
 * session `time_zone`. Issue #24 made this app's UTC-everywhere assumption
 * explicit by running `SET time_zone = '+00:00'` immediately after every
 * raw DB connection, with a runtime test (tests/TimezonePinningTest.php)
 * proving the pin works on the connections that have it TODAY.
 *
 * That runtime test does nothing to stop a NEW connection -- added later,
 * by someone with no knowledge of this design decision -- from being
 * added without the pin. That mistake would not error and would not be
 * visible in normal testing; it would only surface as silently wrong
 * timestamps if the DB server's session/global default time_zone were
 * ever not UTC. This script is a static-analysis safety net against that
 * specific class of future regression -- it does NOT re-verify that
 * today's connections are correctly pinned (the runtime test does that).
 *
 * ── Scope: diff-only, not a full-codebase scan ──────────────────────────
 * This only looks at lines ADDED since the merge-base with the target
 * branch (see determineBaseRef() below) -- not the whole codebase. This
 * was a deliberate choice, not an oversight: a full-codebase scan would
 * also flag pre-existing connections that predate this check (several
 * exist in frontend/v1/admin/*.php as of this writing -- see the PR
 * description for the follow-up that added this script for the specific
 * list). Retroactively fixing or suppressing those in the same change as
 * adding this check would be exactly the "silently patch it in the same
 * PR" this was told not to do. Diff-only scoping also directly matches
 * the actual goal: catching NEW connections as they're introduced, not
 * auditing existing ones.
 *
 * ── Proximity heuristic (deliberately imperfect -- read this) ───────────
 * For each new connection call found, this looks for a UTC pin (or an
 * explicit suppression comment) within PIN_PROXIMITY_LINES lines AFTER
 * the connection line, in the same file. This is NOT real control-flow
 * analysis -- it does not know about function/method boundaries, does
 * not verify the pin actually executes on the SAME connection variable
 * (vs. some unrelated one nearby), and does not verify the pin runs
 * before any TIMESTAMP-touching query. It is a blunt, line-proximity
 * static check, not a guarantee. PIN_PROXIMITY_LINES=15 was picked by
 * measuring the widest existing gap between a connect call and its pin
 * in this codebase (api/v1/lib/bootstrap.php: 12 lines, due to an
 * intervening connect_errno check and set_charset() call) plus margin.
 * If a legitimate pattern needs a wider gap, raise this constant rather
 * than restructuring code just to satisfy the checker.
 *
 * ── Suppressing a false positive ─────────────────────────────────────────
 * If a specific connection genuinely never touches a TIMESTAMP column
 * (e.g. it only ever queries a table with no TIMESTAMP columns), add a
 * comment within the same proximity window:
 *
 *   // timezone-pin-not-required: <reason>
 *
 * The reason is required and is echoed in this script's output, so
 * suppressions stay visible in code review and in CI logs -- never add
 * an exclusion for a file or pattern in this script or in the workflow
 * YAML instead; every suppression must be a visible, reasoned comment at
 * the connection site itself.
 *
 * Usage: php tools/check-timezone-pins.php [base-ref]
 *   base-ref defaults to auto-detection -- see determineBaseRef().
 */

const CONNECTION_PATTERNS = [
    '/\bnew\s+mysqli\s*\(/i' => 'new mysqli(...)',
    '/\bmysqli_connect\s*\(/i' => 'mysqli_connect(...)',
    '/\bmysqli_init\s*\(/i' => 'mysqli_init(...)',
    // Not used anywhere in this codebase as of writing, but explicitly
    // in scope per the Issue #24 follow-up request -- guard against it
    // being introduced in the future too.
    '/\bnew\s+PDO\s*\(\s*[\'"]mysql:/i' => "new PDO('mysql:...')",
];

const PIN_PATTERN = '/set\s+time_zone\s*=/i';
const SUPPRESSION_PATTERN = '/timezone-pin-not-required\s*:\s*(.+)/i';
const PIN_PROXIMITY_LINES = 15;

// This script's own source necessarily contains the literal connection
// patterns it searches for (in CONNECTION_PATTERNS' regex strings and in
// this file's own header-comment examples), so it always self-matches on
// its own initial add/edits. That's a false positive, not a real
// connection -- this script never opens a DB connection itself -- so it
// is excluded by path rather than scattering `// timezone-pin-not-
// required:` comments through regex literals and prose, which would be
// misleading (there's no connection at those specific lines to annotate,
// unlike a genuine suppression).
const SELF_PATH = 'tools/check-timezone-pins.php';

function determineBaseRef(): string
{
    $baseRefEnv = getenv('GITHUB_BASE_REF');
    if ($baseRefEnv !== false && $baseRefEnv !== '') {
        // Pull request event -- diff against the PR's actual target branch.
        return 'origin/' . $baseRefEnv;
    }

    // Push event (or local run): compare against origin/main if it
    // exists and isn't where we already are.
    exec('git rev-parse --verify origin/main 2>/dev/null', $out, $exit);
    if ($exit === 0) {
        return 'origin/main';
    }

    // Fallback (e.g. running locally without an 'origin' remote fetched,
    // or already on main itself): compare against the immediate parent
    // commit so a direct push to main still gets checked against
    // *something* rather than silently checking nothing.
    return 'HEAD~1';
}

/**
 * @return array<string, array<int, int>> file path => added line numbers
 */
function parseAddedLines(string $diff): array
{
    $result = [];
    $file = null;
    $newLineNo = null;

    foreach (explode("\n", $diff) as $line) {
        if (str_starts_with($line, '+++ ')) {
            $path = substr($line, 4);
            $file = $path === '/dev/null' ? null : preg_replace('#^b/#', '', $path);
            continue;
        }

        if (str_starts_with($line, '@@')) {
            if (preg_match('/\+(\d+)/', $line, $m)) {
                $newLineNo = (int) $m[1];
            }
            continue;
        }

        if ($file === null || $newLineNo === null) {
            continue;
        }

        if (str_starts_with($line, '+') && !str_starts_with($line, '+++')) {
            $result[$file][] = $newLineNo;
            $newLineNo++;
        }
        // '-unified=0' diffs contain no context lines, and '-' (removed)
        // lines don't consume a line number in the NEW file, so nothing
        // to do for those here.
    }

    return $result;
}

/**
 * @param array<int, string> $fileLines 0-indexed lines of the file (with newlines)
 * @return array{status: string, reason?: string}
 */
function checkNearbyPin(array $fileLines, int $connectionLineNo): array
{
    $start = $connectionLineNo; // 1-indexed connection line, 0-indexed array -> this is "one past" the connect line
    $end = min(count($fileLines), $connectionLineNo + PIN_PROXIMITY_LINES);

    for ($i = $start; $i < $end; $i++) {
        $text = $fileLines[$i] ?? '';

        if (preg_match(SUPPRESSION_PATTERN, $text, $m)) {
            return ['status' => 'suppressed', 'reason' => trim($m[1])];
        }

        if (preg_match(PIN_PATTERN, $text)) {
            return ['status' => 'pinned'];
        }
    }

    return ['status' => 'missing'];
}

function main(array $argv): int
{
    $baseRef = $argv[1] ?? determineBaseRef();

    $diffCmd = sprintf(
        'git diff --unified=0 %s...HEAD -- \'*.php\' 2>&1',
        escapeshellarg($baseRef)
    );
    exec($diffCmd, $diffLines, $exitCode);

    if ($exitCode !== 0) {
        fwrite(STDERR, "check-timezone-pins: could not diff against '{$baseRef}':\n" . implode("\n", $diffLines) . "\n");
        fwrite(STDERR, "This is a diff-only check (see this script's header comment) -- if there's truly nothing to diff against, that's a CI/checkout configuration problem, not a code problem. Failing loudly rather than silently skipping.\n");
        return 1;
    }

    $diff = implode("\n", $diffLines);
    $addedLinesByFile = parseAddedLines($diff);

    $violations = [];
    $suppressions = [];

    foreach ($addedLinesByFile as $file => $lineNumbers) {
        if (!str_ends_with($file, '.php') || !is_file($file) || $file === SELF_PATH) {
            continue;
        }

        $fileLines = file($file);
        if ($fileLines === false) {
            continue;
        }

        foreach ($lineNumbers as $lineNo) {
            $lineText = $fileLines[$lineNo - 1] ?? '';

            foreach (CONNECTION_PATTERNS as $pattern => $label) {
                if (!preg_match($pattern, $lineText)) {
                    continue;
                }

                $result = checkNearbyPin($fileLines, $lineNo);

                if ($result['status'] === 'missing') {
                    $violations[] = sprintf('%s:%d -- %s', $file, $lineNo, $label);
                } elseif ($result['status'] === 'suppressed') {
                    $suppressions[] = sprintf('%s:%d -- %s (reason: %s)', $file, $lineNo, $label, $result['reason']);
                }
            }
        }
    }

    if ($suppressions !== []) {
        echo "check-timezone-pins: honored " . count($suppressions) . " explicit suppression(s):\n";
        foreach ($suppressions as $s) {
            echo "  - {$s}\n";
        }
        echo "\n";
    }

    if ($violations !== []) {
        fwrite(STDERR, "check-timezone-pins: FAILED\n\n");
        fwrite(STDERR, "New raw DB connection(s) found without a nearby UTC time_zone pin:\n\n");
        foreach ($violations as $v) {
            fwrite(STDERR, "  - {$v}\n");
        }
        fwrite(STDERR, "\n");
        fwrite(STDERR,
            "Why this matters: observed_at/submitted_at (and satellite_name.date_changed)\n"
            . "are TIMESTAMP columns, which implicitly convert based on this connection's\n"
            . "session time_zone. Without an explicit pin, this connection will silently use\n"
            . "whatever timezone the DB server's session/global default happens to be -- which\n"
            . "may not be UTC, and this app assumes UTC everywhere (gmdate()/gmmktime()\n"
            . "throughout). This would not error; it would just silently produce wrong\n"
            . "timestamps.\n\n"
            . "What to do: add this immediately after the connection is opened:\n\n"
            . "    \$conn->query(\"SET time_zone = '+00:00'\");\n\n"
            . "(or the mysqli_query()/PDO equivalent for how this connection is used).\n\n"
            . "If this specific connection genuinely never touches a TIMESTAMP column, add\n"
            . "a comment within " . PIN_PROXIMITY_LINES . " lines of the connection line instead:\n\n"
            . "    // timezone-pin-not-required: <reason>\n\n"
            . "See this script's header comment for the full rationale and the proximity\n"
            . "heuristic this check uses.\n"
        );
        return 1;
    }

    echo "check-timezone-pins: OK -- no new unpinned raw DB connections found.\n";
    return 0;
}

exit(main($argv));
