<?php

use Illuminate\Support\Facades\DB;

final class ApplyRenbutImport
{
    private static function rows(string $table, bool $lock = false): array
    {
        $query = DB::table($table)->orderBy('id');
        if ($lock) {
            $query->lockForUpdate();
        }
        return $query->get()->mapWithKeys(fn ($row) => [(int) $row->id => (array) $row])->all();
    }

    private static function save(string $file, string $content): void
    {
        if (file_put_contents($file, $content, LOCK_EX) !== strlen($content)) {
            throw new RuntimeException('Could not write complete audit/backup file: '.$file);
        }
    }

    public static function run(array $source, array $reviewedAccounts, array $reviewedRows, array $plan, string $folder, string $sourceHash): array
    {
        $result = DB::transaction(function () use ($source, $reviewedAccounts, $reviewedRows, $plan, $folder, $sourceHash) {
            $accounts = self::rows('akuns', true);
            $existing = self::rows('rencana_kebutuhans', true);
            if (array_values($accounts) !== $reviewedAccounts || $existing !== $reviewedRows) {
                throw new RuntimeException('Database changed since analysis; rerun the dry run.');
            }
            $related = self::rows('rincian_objeks', true);
            $before = ['akuns' => array_values($accounts), 'rencana_kebutuhans' => array_values($existing), 'rincian_objeks' => array_values($related)];
            foreach (array_keys($before) as $table) {
                $engine = DB::selectOne('SELECT ENGINE FROM information_schema.TABLES WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ?', [DB::connection()->getDatabaseName(), $table]);
                if (strtolower($engine->ENGINE ?? '') !== 'innodb') {
                    throw new RuntimeException('Transactional storage is required for '.$table);
                }
            }
            $snapshot = ['database' => DB::connection()->getDatabaseName(), 'source_sha256' => $sourceHash,
                'captured_at' => now('Asia/Jakarta')->toIso8601String(), 'tables' => $before];
            self::save($folder.'/before.json', json_encode($snapshot, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR));
            $sql = "-- Backup before merging the legacy renbut dump.\nSET FOREIGN_KEY_CHECKS=0;\n";
            foreach (array_reverse(array_keys($before)) as $table) {
                $sql .= 'DROP TABLE IF EXISTS `'.$table."`;\n";
            }
            $pdo = DB::connection()->getPdo();
            foreach ($before as $table => $rows) {
                $create = (array) DB::selectOne('SHOW CREATE TABLE `'.$table.'`');
                $sql .= array_values($create)[1].";\n";
                foreach (array_chunk($rows, 100) as $chunk) {
                    $columns = array_keys($chunk[0]);
                    $values = [];
                    foreach ($chunk as $row) {
                        $values[] = '('.implode(',', array_map(fn ($value) => $value === null ? 'NULL' : $pdo->quote((string) $value), array_values($row))).')';
                    }
                    $sql .= 'INSERT INTO `'.$table.'` (`'.implode('`,`', $columns)."`) VALUES\n".implode(",\n", $values).";\n";
                }
            }
            $sql .= "SET FOREIGN_KEY_CHECKS=1;\n";
            self::save($folder.'/before.sql', $sql);

            $newAccountIds = [];
            foreach ($plan['missing_accounts'] as $code => $row) {
                $newAccountIds[$code] = DB::table('akuns')->insertGetId($row);
            }
            foreach ($plan['mapping'] as &$mapping) {
                if ($mapping['new_id'] === null) {
                    $mapping['new_id'] = $newAccountIds[$mapping['code']];
                }
            }
            unset($mapping);
            $inserts = [];
            foreach ($plan['rows'] as $sourceId => &$operation) {
                $oldAccount = $operation['old_account_id'];
                $targetAccount = $oldAccount === null ? null : $plan['mapping'][$oldAccount]['new_id'];
                $operation['expected_account_id'] = $targetAccount;
                if ($operation['action'] === 'insert') {
                    $row = $source['rencana_kebutuhans'][$sourceId];
                    $row['id'] = $operation['destination_id'];
                    $row['akun_id'] = $targetAccount;
                    $inserts[] = $row;
                } elseif ($operation['action'] === 'restore_account') {
                    if (DB::table('rencana_kebutuhans')->where('id', $operation['destination_id'])->update(['akun_id' => $targetAccount]) !== 1) {
                        throw new RuntimeException('Failed to restore account for record '.$sourceId);
                    }
                }
            }
            unset($operation);
            foreach (array_chunk($inserts, 100) as $chunk) {
                DB::table('rencana_kebutuhans')->insert($chunk);
            }

            $after = self::rows('rencana_kebutuhans');
            foreach ($existing as $id => $row) {
                if (!isset($after[$id]) || array_diff_key($after[$id], ['akun_id' => true]) !== array_diff_key($row, ['akun_id' => true])) {
                    throw new RuntimeException('Local record fields were not preserved: '.$id);
                }
            }
            foreach ($plan['rows'] as $sourceId => $operation) {
                $actual = $after[$operation['destination_id']] ?? throw new RuntimeException('Missing imported row '.$sourceId);
                if (in_array($operation['action'], ['insert', 'restore_account'], true) && (string) $actual['akun_id'] !== (string) $operation['expected_account_id']) {
                    throw new RuntimeException('Account mapping verification failed: '.$sourceId);
                }
                if ($operation['action'] === 'insert') {
                    foreach ($source['rencana_kebutuhans'][$sourceId] as $column => $value) {
                        if (in_array($column, ['id', 'akun_id'], true)) {
                            continue;
                        }
                        if ($value === null ? $actual[$column] !== null : (string) $actual[$column] !== (string) $value) {
                            throw new RuntimeException('Imported field mismatch: '.$sourceId.' / '.$column);
                        }
                    }
                }
            }
            if (count($after) !== count($existing) + $plan['counts']['insert']) {
                throw new RuntimeException('Unexpected final record count.');
            }
            $afterAccounts = self::rows('akuns');
            foreach ($accounts as $id => $row) {
                if (($afterAccounts[$id] ?? null) !== $row) {
                    throw new RuntimeException('An existing master account changed: '.$id);
                }
            }
            if (self::rows('rincian_objeks') !== $related) {
                throw new RuntimeException('Related rincian_objeks records changed.');
            }
            $orphans = DB::table('rencana_kebutuhans as r')->leftJoin('akuns as a', 'r.akun_id', '=', 'a.id')
                ->whereNotNull('r.akun_id')->whereNull('a.id')->count();
            if ($orphans !== 0) {
                throw new RuntimeException('Account references without a master record remain: '.$orphans);
            }
            $result = ['database' => DB::connection()->getDatabaseName(), 'source_sha256' => $sourceHash,
                'counts' => $plan['counts'], 'new_reference_accounts' => $newAccountIds,
                'final_counts' => ['akuns' => count($afterAccounts), 'rencana_kebutuhans' => count($after),
                    'active_rencana_kebutuhans' => DB::table('rencana_kebutuhans')->whereNull('deleted_at')->count(),
                    'unassigned_accounts' => DB::table('rencana_kebutuhans')->whereNull('akun_id')->count(), 'orphan_accounts' => $orphans],
                'source_records_verified' => count($plan['rows']), 'local_records_preserved' => count($existing),
                'backup_sql' => $folder.'/before.sql', 'backup_json' => $folder.'/before.json'];
            self::save($folder.'/applied-plan.json', json_encode($plan, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR));
            self::save($folder.'/verification-before-commit.json', json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR));
            return $result;
        });
        $result['committed_at'] = now('Asia/Jakarta')->toIso8601String();
        self::save($folder.'/result.json', json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR));
        return $result;
    }
}
