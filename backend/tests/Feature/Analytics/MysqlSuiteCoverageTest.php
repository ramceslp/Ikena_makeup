<?php

namespace Tests\Feature\Analytics;

use Tests\TestCase;

/**
 * MysqlSuiteCoverageTest
 *
 * Guards against exactly the mistake that let every Analytics test run
 * only under SQLite: phpunit.mysql.xml can declare a new <testsuite>
 * without .github/workflows/tests.yml's `--testsuite=` flag ever being
 * updated to invoke it. PHPUnit does not fail in that scenario — it
 * silently runs zero tests from the forgotten suite while the "Backend
 * PHPUnit (MySQL)" job stays green.
 *
 * Parses BOTH files' real content — suite names are never hardcoded here,
 * or this guard would pass by construction and defeat its own purpose.
 *
 * Lives in tests/Feature/Analytics so it is covered by phpunit.xml's
 * default `Feature` testsuite (the SQLite job) as well as this codebase's
 * own `Analytics` testsuite (the MySQL job, once this PR registers it) —
 * a future third testsuite that forgets the workflow is caught on BOTH CI
 * jobs, not only the MySQL one.
 */
class MysqlSuiteCoverageTest extends TestCase
{
    /** @return array<int, string> */
    private function declaredTestsuiteNames(): array
    {
        $xmlPath = base_path('phpunit.mysql.xml');
        $this->assertFileExists($xmlPath, 'phpunit.mysql.xml must exist.');

        $xml = simplexml_load_string((string) file_get_contents($xmlPath));
        $this->assertNotFalse($xml, 'phpunit.mysql.xml must be valid XML.');

        $names = [];
        foreach ($xml->testsuites->testsuite as $testsuite) {
            $names[] = (string) $testsuite['name'];
        }

        return $names;
    }

    /** @return array<int, string> */
    private function invokedTestsuiteNames(): array
    {
        $workflowPath = base_path('../.github/workflows/tests.yml');
        $this->assertFileExists($workflowPath, '.github/workflows/tests.yml must exist.');

        $contents = (string) file_get_contents($workflowPath);

        $this->assertMatchesRegularExpression(
            '/-c phpunit\.mysql\.xml --testsuite=([A-Za-z0-9,_-]+)/',
            $contents,
            'The MySQL job must run php artisan test with -c phpunit.mysql.xml and an explicit --testsuite= flag.'
        );

        preg_match('/-c phpunit\.mysql\.xml --testsuite=([A-Za-z0-9,_-]+)/', $contents, $matches);

        return explode(',', $matches[1]);
    }

    public function test_every_declared_mysql_testsuite_is_invoked_by_the_workflow(): void
    {
        $declared = $this->declaredTestsuiteNames();
        $invoked = $this->invokedTestsuiteNames();

        $missing = array_diff($declared, $invoked);

        $this->assertEmpty(
            $missing,
            'phpunit.mysql.xml declares testsuite(s) ['.implode(', ', $missing).'] that '.
            '.github/workflows/tests.yml never passes to --testsuite=. A declared-but-never-invoked '.
            'testsuite silently never runs against MySQL while the "Backend PHPUnit (MySQL)" job stays green.'
        );
    }

    public function test_at_least_one_testsuite_is_declared_and_invoked(): void
    {
        // Guards the guard: an empty declared list would make the diff
        // above vacuously pass even if both files were emptied out.
        $this->assertNotEmpty($this->declaredTestsuiteNames(), 'phpunit.mysql.xml must declare at least one testsuite.');
        $this->assertNotEmpty($this->invokedTestsuiteNames(), 'tests.yml must invoke at least one testsuite.');
    }
}
