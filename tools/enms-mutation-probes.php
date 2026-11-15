<?php

/**
 * Mutation probes for the native shared layer. Run with: php tools/enms-mutation-probes.php
 * Every probe preloads one mutated class from a temporary file in a separate Pest process.
 * Application sources and tests are never overwritten. PHPUnit uses the isolated SQLite DB.
 */
$root = dirname(__DIR__);
$directory = sys_get_temp_dir().DIRECTORY_SEPARATOR.'enms-probes-'.bin2hex(random_bytes(8));
mkdir($directory, 0700, true);
$run = function (?string $prepend, string $filter, string $name) use ($root, $directory): array {
    $command = [PHP_BINARY];
    if ($prepend) {
        array_push($command, '-d', 'auto_prepend_file='.$prepend);
    }
    array_push($command, $root.'/vendor/bin/pest', '--filter='.$filter, '--colors=never', '--compact', '--log-junit='.$directory.'/'.$name.'.xml');
    $log = $directory.'/'.$name.'.log';
    $process = proc_open($command, [0 => ['pipe', 'r'], 1 => ['file', $log, 'w'], 2 => ['file', $log, 'a']], $pipes, $root);
    if (! is_resource($process)) {
        throw new RuntimeException('Cannot start Pest');
    }
    fclose($pipes[0]);
    $exit = proc_close($process);
    $xml = is_file($directory.'/'.$name.'.xml') ? simplexml_load_file($directory.'/'.$name.'.xml') : false;
    $suite = $xml ? $xml->testsuite : null;

    return ['exit' => $exit, 'tests' => (int) ($suite['tests'] ?? 0), 'failures' => (int) ($suite['failures'] ?? 0), 'errors' => (int) ($suite['errors'] ?? 0), 'log' => $log];
};
$auth = 'app/Services/Enms/Authorization.php';
$workspace = 'app/Services/Enms/Workspace.php';
$actions = 'app/Services/Enms/AuthorizedActions.php';
$probes = [
    ['evidence-version', $auth, '$verification[\'evidence_version\'] === $evidence[\'version\']', 'true', 'enms evidence status'],
    ['authority-minimum', $auth, '($ranks[$status] ?? -1) < $ranks[$minimum]', 'false', 'enms minimum authority'],
    ['revocation', $auth, '$revocation && $date->gte(', 'false && $date->gte(', 'enms rejects expired'],
    ['scope', $auth, '($grant[\'scope_version\'] ?? null) !== $context[\'scope_version\']', 'false', 'enms rejects expired'],
    ['financial-limit', $auth, '$used + $context[\'amount_cents\'] > $grant[\'limit_cents\']', 'false', 'enms financial authorization'],
    ['independence', $auth, 'return $conflicts;', 'return [];', 'enms independent assessment'],
    ['tenant-read', $workspace, "->where('r.workspace_id', \$workspace->id)", "->whereRaw('1 = 1')", 'enms workspace denies foreign'],
    ['revision', $workspace, '(int) ($workspace->revision ?? 0) === $expectedRevision', 'true', 'enms workspace keeps historical'],
    ['historical-read', $workspace, "\$query->where('v.workspace_revision', '<=', \$atRevision);", "\$query->whereColumn('v.version', 'r.current_version');", 'enms workspace keeps historical'],
    ['journal-atomicity', $workspace, 'return DB::transaction(function () use ($expectedRevision', 'return (function () use ($expectedRevision', 'enms rolls back'],
    ['action-grant', $actions, "if (! \$check['allowed'])", 'if (false)', 'enms task execution and acceptance'],
    ['multistep', $actions, 'if (array_diff(range(1, $required), array_keys($steps)))', 'if (false)', 'enms multistep authority'],
    ['client-submission', 'app/Services/Enms/LeadershipWorkflow.php', "in_array(\$r['id'], \$selectedEvidence, true)", "! empty(\$r['payload']['requirements'])", 'enms consultant evidence'],
];
$report = ['directory' => $directory, 'baseline' => $run(null, 'enms', 'baseline'), 'probes' => []];
if ($report['baseline']['exit'] !== 0 || $report['baseline']['tests'] === 0) {
    fwrite(STDERR, 'Baseline failed: '.$report['baseline']['log'].PHP_EOL);
    exit(2);
}
echo 'Baseline passed: '.$report['baseline']['tests'].' tests'.PHP_EOL;
$passed = 0;
foreach ($probes as [$name, $file, $needle, $replacement, $filter]) {
    $source = file_get_contents($root.'/'.$file);
    if (substr_count($source, $needle) !== 1) {
        throw new RuntimeException('Mutation anchor changed: '.$name);
    }
    $source = str_replace($needle, $replacement, $source);
    if ($name === 'journal-atomicity') {
        // The closure still runs, but outside its inner transaction; the rollback test must catch it.
        $anchor = "return \$this->record(\$id, \$kind);\n        });";
        $source = str_replace("\r\n", "\n", $source);
        if (substr_count($source, $anchor) !== 1) {
            throw new RuntimeException('Transaction closing anchor changed');
        }
        $source = str_replace($anchor, "return \$this->record(\$id, \$kind);\n        })();", $source);
    }
    $mutant = $directory.'/'.$name.'.php';
    $prepend = $directory.'/'.$name.'-prepend.php';
    file_put_contents($mutant, $source);
    file_put_contents($prepend, '<?php require '.var_export($root.'/vendor/autoload.php', true).'; require '.var_export($mutant, true).';');
    $result = $run($prepend, $filter, $name);
    // A crash, syntax error or empty selection does not count as a detected semantic mutation.
    $result['detected'] = $result['exit'] === 1 && $result['tests'] > 0 && $result['failures'] > 0 && $result['errors'] === 0;
    $report['probes'][$name] = $result;
    $passed += $result['detected'] ? 1 : 0;
    echo ($result['detected'] ? 'DETECTED ' : 'FAILED ').$name.PHP_EOL;
}
$report['passed'] = $passed;
$report['total'] = count($probes);
file_put_contents($directory.'/report.json', json_encode($report, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR));
echo $passed.'/'.count($probes).' mutations detected; report: '.$directory.'/report.json'.PHP_EOL;
exit($passed === count($probes) ? 0 : 1);
