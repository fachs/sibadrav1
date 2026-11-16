<?php

require dirname(__DIR__).'/vendor/autoload.php';
require __DIR__.'/lib/LegacyRenbutSql.php';
require __DIR__.'/lib/RenbutImportPlan.php';

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

$app = require dirname(__DIR__).'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
$path = $argv[1] ?? throw new RuntimeException('Supply the SQL backup path.');
$source = LegacyRenbutSql::read($path);
$connection = DB::connection();
$database = $connection->getDatabaseName();
$accounts = DB::table('akuns')->orderBy('id')->get()->map(fn ($row) => (array) $row)->all();
$existing = DB::table('rencana_kebutuhans')->orderBy('id')->get()->mapWithKeys(fn ($row) => [(int) $row->id => (array) $row])->all();
$normalizeCode = function (string $code): string {
    return implode('.', array_map(fn ($part) => ctype_digit($part) ? (ltrim($part, '0') ?: '0') : $part, explode('.', trim($code))));
};
$exact = $normalized = [];
foreach ($accounts as $account) {
    $exact[trim($account['kode_rek'])][] = $account;
    $normalized[$normalizeCode($account['kode_rek'])][] = $account;
}
$usedIds = array_unique(array_filter(array_column($source['rencana_kebutuhans'], 'akun_id'), fn ($id) => $id !== null));
$mapping = $issues = [];
foreach ($usedIds as $id) {
    $old = $source['akuns'][(int) $id] ?? null;
    $candidates = $old ? ($exact[trim($old['kode_rek'])] ?? $normalized[$normalizeCode($old['kode_rek'])] ?? []) : [];
    if (count($candidates) === 1) {
        $mapping[$id] = ['old_id' => (int) $id, 'old_code' => $old['kode_rek'], 'old_label' => $old['uraian'],
            'new_id' => (int) $candidates[0]['id'], 'new_code' => $candidates[0]['kode_rek'], 'new_label' => $candidates[0]['uraian']];
    } else {
        $issues[$id] = ['old' => $old, 'candidates' => $candidates];
    }
}
$comparison = ['new' => 0, 'same_source_fields_except_account' => 0, 'different' => 0, 'existing_only' => count(array_diff_key($existing, $source['rencana_kebutuhans']))];
$differences = [];
foreach ($source['rencana_kebutuhans'] as $id => $row) {
    if (!isset($existing[$id])) {
        $comparison['new']++;
        continue;
    }
    $diff = [];
    foreach ($row as $column => $value) {
        if ($column !== 'akun_id' && $value !== null && is_numeric($value) && is_numeric($existing[$id][$column] ?? null)) {
            $equal = \Brick\Math\BigDecimal::of($value)->isEqualTo((string) $existing[$id][$column]);
        } else {
            $equal = $value === null ? ($existing[$id][$column] ?? null) === null : (string) $value === (string) ($existing[$id][$column] ?? '');
        }
        if ($column !== 'akun_id' && !$equal) {
            $diff[$column] = ['current' => $existing[$id][$column] ?? null, 'source' => $value];
        }
    }
    $comparison[$diff ? 'different' : 'same_source_fields_except_account']++;
    if ($diff) {
        $differences[$id] = $diff;
    }
}
$folder = storage_path('app/private/renbut-import-'.now('Asia/Jakarta')->format('Ymd-His'));
if (!is_dir($folder) && !mkdir($folder, 0700, true)) {
    throw new RuntimeException('Cannot create audit folder.');
}
$report = [
    'database' => $database, 'source_sha256' => hash_file('sha256', $path),
    'source_counts' => array_map('count', $source), 'current_counts' => ['akuns' => count($accounts), 'rencana_kebutuhans' => count($existing)],
    'source_unassigned_accounts' => count(array_filter($source['rencana_kebutuhans'], fn ($row) => $row['akun_id'] === null)),
    'mapping' => $mapping, 'mapping_issues' => $issues, 'comparison' => $comparison, 'differences' => $differences,
    'current_columns' => Schema::getColumns('rencana_kebutuhans'),
    'foreign_keys' => Schema::getForeignKeys('rencana_kebutuhans'),
    'incoming_foreign_keys' => DB::select('SELECT TABLE_NAME, COLUMN_NAME FROM information_schema.KEY_COLUMN_USAGE WHERE REFERENCED_TABLE_SCHEMA = ? AND REFERENCED_TABLE_NAME = ?', [$database, 'rencana_kebutuhans']),
];
$report['identity_conflicts'] = [];
$report['account_differences'] = [];
$report['current_extra_values'] = [];
$sourceColumns = array_keys(reset($source['rencana_kebutuhans']));
foreach ($existing as $id => $row) {
    $old = $source['rencana_kebutuhans'][$id] ?? null;
    if ($old && $row['created_at'] !== $old['created_at']) {
        $report['identity_conflicts'][$id] = ['current_blud' => $row['nama_blud'], 'source_blud' => $old['nama_blud'],
            'current_created' => $row['created_at'], 'source_created' => $old['created_at']];
    }
    if ($old && (string) $row['akun_id'] !== (string) $old['akun_id']) {
        $report['account_differences'][$id] = ['current_id' => $row['akun_id'], 'source_id' => $old['akun_id'],
            'current_updated' => $row['updated_at'], 'source_updated' => $old['updated_at']];
    }
    foreach (array_diff(array_keys($row), $sourceColumns, ['status', 'is_hasil_perubahan', 'nama_barjas']) as $field) {
        if ($row[$field] !== null && $row[$field] !== '') {
            $report['current_extra_values'][$field] = ($report['current_extra_values'][$field] ?? 0) + 1;
        }
    }
}
$report['source_max_lengths'] = [];
$accountsById = array_column($accounts, null, 'id');
$report['account_states'] = [];
$report['account_conflict_samples'] = [];
foreach ($source['rencana_kebutuhans'] as $id => $row) {
    if (!isset($existing[$id]) || isset($report['identity_conflicts'][$id])) {
        continue;
    }
    $currentId = $existing[$id]['akun_id'];
    $expectedId = $mapping[$row['akun_id']]['new_id'] ?? null;
    $state = $row['akun_id'] === null
        ? ($currentId === null ? 'both_unassigned' : 'current_assigned_source_null')
        : ($expectedId !== null && (string) $currentId === (string) $expectedId ? 'already_correct'
            : ((string) $currentId === (string) $row['akun_id'] ? 'still_legacy_id' : 'different_account'));
    $report['account_states'][$state] = ($report['account_states'][$state] ?? 0) + 1;
    if ($state === 'different_account' && count($report['account_conflict_samples']) < 12) {
        $report['account_conflict_samples'][] = ['record_id' => $id, 'current_akun_id' => $currentId,
            'current_code' => $accountsById[$currentId]['kode_rek'] ?? null,
            'source_akun_id' => $row['akun_id'], 'expected' => $mapping[$row['akun_id']] ?? $issues[$row['akun_id']] ?? null];
    }
}
foreach ($source['rencana_kebutuhans'] as $row) {
    foreach ($row as $column => $value) {
        $report['source_max_lengths'][$column] = max($report['source_max_lengths'][$column] ?? 0, mb_strlen((string) $value));
    }
}
$plan = RenbutImportPlan::build($source, $accounts, $existing);
$masterColumns = array_flip(Schema::getColumnListing('akuns'));
$plan['ignored_master_columns'] = [];
foreach ($plan['missing_accounts'] as $code => $row) {
    $plan['ignored_master_columns'] = array_values(array_unique(array_merge($plan['ignored_master_columns'], array_keys(array_diff_key($row, $masterColumns)))));
    $plan['missing_accounts'][$code] = array_intersect_key($row, $masterColumns);
}
$report['plan_counts'] = $plan['counts'];
$report['missing_reference_count'] = count($plan['missing_accounts']);
$report['preflight_issues'] = [];
foreach (['akuns' => array_values($plan['missing_accounts']), 'rencana_kebutuhans' => array_values($source['rencana_kebutuhans'])] as $table => $rows) {
    $columns = array_column(Schema::getColumns($table), null, 'name');
    foreach ($rows as $row) {
        foreach ($row as $column => $value) {
            if (!isset($columns[$column])) {
                $report['preflight_issues'][] = $table.': unknown column '.$column;
            } elseif ($value !== null && preg_match('/^varchar\((\d+)\)/', $columns[$column]['type'], $size) && mb_strlen((string) $value) > (int) $size[1]) {
                $report['preflight_issues'][] = $table.': value exceeds '.$column.' limit';
            } elseif ($value !== null && $columns[$column]['type'] === 'int' && (\Brick\Math\BigDecimal::of((string) $value)->isGreaterThan('2147483647') || \Brick\Math\BigDecimal::of((string) $value)->isLessThan('-2147483648'))) {
                $report['preflight_issues'][] = $table.': value exceeds '.$column.' integer range';
            }
        }
    }
}
file_put_contents($folder.'/analysis.json', json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR));
file_put_contents($folder.'/plan.json', json_encode($plan, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR));
echo json_encode(array_intersect_key($report, array_flip(['database', 'source_sha256', 'source_counts', 'current_counts', 'source_unassigned_accounts', 'identity_conflicts', 'current_extra_values', 'plan_counts', 'missing_reference_count', 'preflight_issues'])), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR).PHP_EOL;
echo 'Analysis: '.$folder.'/analysis.json'.PHP_EOL;
if (in_array('--apply', $argv, true)) {
    if (!in_array('--source-sha256='.$report['source_sha256'], $argv, true)) {
        throw new RuntimeException('Applying requires an explicit --source-sha256 matching the reviewed backup.');
    }
    if ($report['preflight_issues']) {
        throw new RuntimeException('Resolve preflight issues before importing.');
    }
    require __DIR__.'/lib/ApplyRenbutImport.php';
    $result = ApplyRenbutImport::run($source, $accounts, $existing, $plan, $folder, $report['source_sha256']);
    echo json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR).PHP_EOL;
}
