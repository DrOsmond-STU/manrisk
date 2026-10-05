<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

/** Seeder lengkap (inti + akun + demo) harus berjalan tanpa galat dan mengisi semua modul. */
class SeederTest extends BaseTestCase
{
    use RefreshDatabase;

    public function test_full_seed_runs_and_populates_every_module(): void
    {
        $this->seed();
        foreach (['risks' => 127, 'controls' => 36, 'action_plans' => 200, 'kris' => 18, 'incidents' => 14, 'reviews' => 40, 'documents' => 26, 'improvements' => 11, 'approvals' => 10, 'users' => 30] as $t => $min) {
            $this->assertGreaterThanOrEqual($min, \DB::table($t)->count(), "tabel {$t}");
        }
        $this->assertSame(\DB::table('improvements')->count(), \DB::table('improvements')->distinct()->count('code'));
    }
}
