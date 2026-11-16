<?php

namespace Tests\Feature;

use App\Models\User;
use Tests\TestCase;

class TahunAnggaranSessionTest extends TestCase
{
    private function login(int $id = 7): void
    {
        $user = new User(['name' => 'Pengguna Uji', 'role' => 'Pejabat Keuangan', 'upt' => 'Puskesmas A']);
        $user->id = $id;
        $this->actingAs($user);
        $this->withoutVite();
    }

    public function test_year_selection_requires_login(): void
    {
        $this->post(route('tahun-anggaran.update'), ['tahun' => 2026])->assertRedirect(route('login'));
    }

    public function test_selection_is_saved_and_returns_to_page_without_stale_year_week_or_pagination(): void
    {
        $this->login();
        $this->post(route('tahun-anggaran.update'), ['tahun' => 2026,
            'kembali' => '/rba/ringkasan?tahun=2027&minggu=2027-01-04&page=3&puskesmas=Puskesmas+B&search=obat'])
            ->assertRedirect('/rba/ringkasan?puskesmas=Puskesmas+B&search=obat')
            ->assertSessionHas('tahun_anggaran.user_7', 2026);
        $response = $this->get(route('dokumen-rba.show', 'murni'))->assertOk()
            ->assertSee('data-global-year="2026"', false)->assertSee('value="2026" selected', false);
        $this->assertSame(1, substr_count($response->getContent(), 'id="rba-tahun"'));
        preg_match('/<select id="rba-tahun".*?<\/select>/s', $response->getContent(), $selector);
        preg_match_all('/<option value="(\d+)"/', $selector[0], $options);
        $this->assertSame(['2027', '2026'], $options[1]);
    }

    public function test_saved_year_overrides_old_bookmark_and_remains_active_across_pages(): void
    {
        $this->login();
        $this->post(route('tahun-anggaran.update'), ['tahun' => 2026])->assertRedirect('/');
        foreach (['murni', 'perubahan'] as $type) {
            $this->get(route('dokumen-rba.show', ['jenis' => $type, 'tahun' => 2027]))->assertOk()
                ->assertSee('data-global-year="2026"', false)->assertSee('value="2026" selected', false);
        }
        $this->assertSame(2026, session('tahun_anggaran.user_7'));
    }

    public function test_invalid_year_does_not_replace_existing_session_selection(): void
    {
        $this->login();
        $this->withSession(['tahun_anggaran.user_7' => 2026]);
        foreach (['abc', 1999, 2025, 2028, 2101, '2026.5', ['2026'], null] as $year) {
            $this->postJson(route('tahun-anggaran.update'), ['tahun' => $year])->assertUnprocessable()
                ->assertJsonValidationErrors('tahun')->assertSessionHas('tahun_anggaran.user_7', 2026);
        }
    }

    public function test_year_selection_is_separate_for_different_accounts_in_the_same_session(): void
    {
        $this->login();
        $this->post(route('tahun-anggaran.update'), ['tahun' => 2026])->assertSessionHas('tahun_anggaran.user_7', 2026);
        $this->login(8);
        $this->post(route('tahun-anggaran.update'), ['tahun' => 2027])->assertSessionHas('tahun_anggaran.user_8', 2027);
        $this->get(route('dokumen-rba.show', 'murni'))->assertOk()->assertSee('data-global-year="2027"', false);
        $this->login(7);
        $this->get(route('dokumen-rba.show', 'murni'))->assertOk()->assertSee('data-global-year="2026"', false);
    }

    public function test_external_or_ambiguous_return_urls_cannot_redirect_outside_the_app(): void
    {
        $this->login();
        foreach (['https://example.com/', '//example.com/', '/\\example.com/', 'javascript:alert(1)', '/'.chr(10).'bad'] as $url) {
            $this->post(route('tahun-anggaran.update'), ['tahun' => 2026, 'kembali' => $url])->assertRedirect('/');
        }
    }
}
