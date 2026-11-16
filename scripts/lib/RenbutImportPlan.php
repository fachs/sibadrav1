<?php

final class RenbutImportPlan
{
    public static function code(string $code): string
    {
        return implode('.', array_map(fn ($part) => ctype_digit($part) ? (ltrim($part, '0') ?: '0') : $part, explode('.', trim($code))));
    }

    private static function identity(array $row): string
    {
        return json_encode(array_map(fn ($field) => $row[$field] ?? null, ['created_at', 'nama_blud', 'nama_unit', 'uraian']), JSON_THROW_ON_ERROR);
    }

    public static function build(array $source, array $accounts, array $existing): array
    {
        $exact = $normalized = $accountsById = [];
        foreach ($accounts as $account) {
            $exact[trim($account['kode_rek'])][] = $account;
            $normalized[self::code($account['kode_rek'])][] = $account;
            $accountsById[(int) $account['id']] = $account;
        }
        $mapping = $missing = [];
        foreach ($source['rencana_kebutuhans'] as $row) {
            $oldId = $row['akun_id'];
            if ($oldId === null || isset($mapping[$oldId])) {
                continue;
            }
            $old = $source['akuns'][(int) $oldId] ?? throw new RuntimeException('Source account missing: '.$oldId);
            $code = trim($old['kode_rek']);
            if ($code === '') {
                throw new RuntimeException('Source account has no code: '.$oldId);
            }
            $matches = $exact[$code] ?? $normalized[self::code($code)] ?? [];
            if (count($matches) > 1) {
                throw new RuntimeException('Ambiguous destination account code: '.$code);
            }
            if ($matches) {
                $new = $matches[0];
                $mapping[$oldId] = ['new_id' => (int) $new['id'], 'code' => $code,
                    'new_code' => $new['kode_rek'], 'old_label' => $old['uraian'], 'new_label' => $new['uraian']];
            } else {
                if (isset($missing[$code]) && preg_replace('/\s+/u', ' ', trim($missing[$code]['uraian'])) !== preg_replace('/\s+/u', ' ', trim($old['uraian']))) {
                    throw new RuntimeException('Conflicting legacy labels for code: '.$code);
                }
                $missing[$code] = array_diff_key($old, ['id' => true]);
                $mapping[$oldId] = ['new_id' => null, 'code' => $code, 'new_code' => $code,
                    'old_label' => $old['uraian'], 'new_label' => $old['uraian']];
            }
        }
        $nextId = max(array_merge(array_keys($existing), array_keys($source['rencana_kebutuhans']), [0])) + 1;
        $byIdentity = [];
        foreach ($existing as $id => $row) {
            $byIdentity[self::identity($row)][] = $id;
        }
        $rows = [];
        $counts = ['insert' => 0, 'reassigned_source_ids' => 0, 'restore_account' => 0, 'preserve_local_account' => 0, 'unchanged' => 0];
        foreach ($source['rencana_kebutuhans'] as $sourceId => $row) {
            $id = $sourceId;
            $current = $existing[$id] ?? null;
            if ($current && $current['created_at'] !== $row['created_at']) {
                $matches = $byIdentity[self::identity($row)] ?? [];
                if (count($matches) > 1) {
                    throw new RuntimeException('Multiple existing records match backup row: '.$sourceId);
                }
                $id = $matches ? $matches[0] : $nextId++;
                $current = $existing[$id] ?? null;
            }
            $targetAccount = $row['akun_id'] === null ? null : $mapping[$row['akun_id']]['new_id'];
            $action = 'unchanged';
            if (!$current) {
                $action = 'insert';
                if ($id !== $sourceId) {
                    $counts['reassigned_source_ids']++;
                }
            } elseif ($row['akun_id'] !== null) {
                if ($targetAccount !== null && (string) $current['akun_id'] === (string) $targetAccount) {
                    $action = 'unchanged';
                } elseif ($current['akun_id'] === null || !isset($accountsById[(int) $current['akun_id']]) || (string) $current['akun_id'] === (string) $row['akun_id']) {
                    $action = 'restore_account';
                } else {
                    // A different valid account is a local edit, not an ID to blindly remap.
                    $action = 'preserve_local_account';
                }
            }
            $counts[$action]++;
            $rows[$sourceId] = ['source_id' => $sourceId, 'destination_id' => $id, 'action' => $action,
                'old_account_id' => $row['akun_id'], 'expected_account_id' => $targetAccount,
                'previous_account_id' => $current['akun_id'] ?? null];
        }
        return ['counts' => $counts, 'mapping' => $mapping, 'missing_accounts' => $missing, 'rows' => $rows];
    }
}
