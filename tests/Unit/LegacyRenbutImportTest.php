<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

require_once dirname(__DIR__, 2).'/scripts/lib/LegacyRenbutSql.php';
require_once dirname(__DIR__, 2).'/scripts/lib/RenbutImportPlan.php';

class LegacyRenbutImportTest extends TestCase
{
    private function parse(string $sql): array
    {
        $path = tempnam(sys_get_temp_dir(), 'renbut-sql-');
        try {
            file_put_contents($path, $sql);
            return \LegacyRenbutSql::read($path);
        } finally {
            unlink($path);
        }
    }

    public function test_parser_preserves_escaped_strings_and_null_without_executing_other_tables(): void
    {
        $sql = <<<'SQL'
DROP TABLE `users`;
INSERT INTO `akuns` (`id`, `kode_rek`, `uraian`) VALUES
(5, '5.1.02', 'Barang \'A\', (unit);\nBaris baru');
INSERT INTO `users` (`id`, `name`) VALUES (9, 'ignored');
INSERT INTO `rencana_kebutuhans` (`id`, `akun_id`, `uraian`, `jumlah_harga`) VALUES
(10, 5, 'Kertas ''A4''', 2500.50),
(11, NULL, 'Folder \\ berkas', 0);
SQL;
        $rows = $this->parse($sql);
        self::assertSame(['akuns', 'rencana_kebutuhans'], array_keys($rows));
        self::assertSame("Barang 'A', (unit);\nBaris baru", $rows['akuns'][5]['uraian']);
        self::assertSame("Kertas 'A4'", $rows['rencana_kebutuhans'][10]['uraian']);
        self::assertSame('2500.50', $rows['rencana_kebutuhans'][10]['jumlah_harga']);
        self::assertNull($rows['rencana_kebutuhans'][11]['akun_id']);
        self::assertSame('Folder \\ berkas', $rows['rencana_kebutuhans'][11]['uraian']);
    }

    public function test_parser_rejects_truncated_insert(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->parse("INSERT INTO `akuns` (`id`, `uraian`) VALUES (1, 'unfinished");
    }

    public function test_parser_rejects_duplicate_source_ids(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->parse("INSERT INTO `akuns` (`id`) VALUES (1), (1);");
    }

    private function fixture(): array
    {
        $old = ['id' => '5', 'kode_rek' => '5.1.02.01.001.00024', 'uraian' => 'Alat tulis'];
        $row = ['id' => '10', 'akun_id' => '5', 'created_at' => '2026-01-01 00:00:00',
            'nama_blud' => 'BLUD A', 'nama_unit' => 'Klaster 2', 'uraian' => 'Kertas', 'jumlah_harga' => '1000'];
        return ['akuns' => [5 => $old], 'rencana_kebutuhans' => [10 => $row]];
    }

    public function test_plan_restores_missing_account_and_preserves_local_values_and_valid_account_edits(): void
    {
        $source = $this->fixture();
        $accounts = [['id' => 100, 'kode_rek' => '5.1.02.01.001.00024', 'uraian' => 'Alat tulis'],
            ['id' => 200, 'kode_rek' => '5.2.02', 'uraian' => 'Modal']];
        $existing = [10 => array_replace($source['rencana_kebutuhans'][10], ['akun_id' => null, 'jumlah_harga' => '2000'])];
        $plan = \RenbutImportPlan::build($source, $accounts, $existing);
        self::assertSame(100, $plan['rows'][10]['expected_account_id']);
        self::assertSame('restore_account', $plan['rows'][10]['action']);
        self::assertSame('2000', $existing[10]['jumlah_harga']);
        $existing[10]['akun_id'] = 200;
        self::assertSame('preserve_local_account', \RenbutImportPlan::build($source, $accounts, $existing)['rows'][10]['action']);
    }

    public function test_plan_keeps_conflicting_existing_id_and_reuses_identity_on_repeat_import(): void
    {
        $source = $this->fixture();
        $existing = [10 => array_replace($source['rencana_kebutuhans'][10], ['created_at' => '2026-02-01 00:00:00', 'uraian' => 'Data lokal'])];
        $accounts = [['id' => 100, 'kode_rek' => '5.1.02.01.001.00024', 'uraian' => 'Alat tulis']];
        $plan = \RenbutImportPlan::build($source, $accounts, $existing);
        self::assertSame(11, $plan['rows'][10]['destination_id']);
        self::assertSame(1, $plan['counts']['reassigned_source_ids']);
        $existing[11] = array_replace($source['rencana_kebutuhans'][10], ['id' => 11, 'akun_id' => 100]);
        $repeat = \RenbutImportPlan::build($source, $accounts, $existing);
        self::assertSame(11, $repeat['rows'][10]['destination_id']);
        self::assertSame('unchanged', $repeat['rows'][10]['action']);
        self::assertSame(0, $repeat['counts']['insert']);
    }

    public function test_plan_deduplicates_missing_reference_codes_and_rejects_ambiguous_current_codes(): void
    {
        $source = $this->fixture();
        $source['akuns'][6] = array_replace($source['akuns'][5], ['id' => '6']);
        $source['rencana_kebutuhans'][12] = array_replace($source['rencana_kebutuhans'][10], ['id' => '12', 'akun_id' => '6']);
        $plan = \RenbutImportPlan::build($source, [], []);
        self::assertCount(1, $plan['missing_accounts']);
        self::assertCount(2, $plan['mapping']);
        $this->expectException(\RuntimeException::class);
        \RenbutImportPlan::build($source, [
            ['id' => 100, 'kode_rek' => '5.1.02.01.001.00024', 'uraian' => 'Alat tulis'],
            ['id' => 101, 'kode_rek' => '5.1.02.01.001.00024', 'uraian' => 'Alat tulis'],
        ], []);
    }
}
