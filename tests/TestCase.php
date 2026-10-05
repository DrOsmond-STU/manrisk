<?php

namespace Tests;

use App\Models\Organization;
use App\Models\OrgUnit;
use App\Models\User;
use Database\Seeders\CoreSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    use RefreshDatabase;

    protected Organization $org;
    protected OrgUnit $unitA;
    protected OrgUnit $unitB;
    /** @var array<string, User> */
    protected array $users = [];

    protected function setUp(): void
    {
        parent::setUp();
        \App\Support\MailSettings::flush();
        $this->org = Organization::create(['name' => 'Org Uji', 'code' => 'UJI']);
        CoreSeeder::seedOrganization($this->org);
        $this->unitA = OrgUnit::withoutGlobalScopes()->create(['organization_id' => $this->org->id, 'name' => 'Unit A', 'code' => 'A']);
        $this->unitA->refreshPath();
        $this->unitB = OrgUnit::withoutGlobalScopes()->create(['organization_id' => $this->org->id, 'name' => 'Unit B', 'code' => 'B']);
        $this->unitB->refreshPath();
        foreach (array_keys(User::ROLES) as $role) {
            $this->users[$role] = User::withoutGlobalScopes()->create(['organization_id' => $this->org->id, 'name' => ucfirst($role), 'email' => "$role@uji.test", 'password' => 'Secret#Pass123', 'role' => $role,
                'unit_id' => in_array($role, ['risk_officer', 'risk_owner'], true) ? $this->unitA->id : null, 'active' => true, 'password_changed_at' => now()]);
        }
    }

    protected function as(string $role): static
    {
        return $this->actingAs($this->users[$role]);
    }

    protected function makeRisk(array $attrs = [], ?OrgUnit $unit = null): \App\Models\Risk
    {
        $cat = \App\Models\RiskCategory::withoutGlobalScopes()->where('organization_id', $this->org->id)->first();
        $risk = new \App\Models\Risk(array_merge(['code' => 'R-2026-' . str_pad((string) (\App\Models\Risk::withoutGlobalScopes()->count() + 1), 3, '0', STR_PAD_LEFT), 'name' => 'Risiko uji', 'unit_id' => ($unit ?? $this->unitA)->id, 'category_id' => $cat->id, 'owner_id' => $this->users['risk_owner']->id,
            'cause' => 'sebab', 'event' => 'peristiwa', 'impact' => 'dampak', 'inherent_l' => 4, 'inherent_i' => 4, 'residual_l' => 3, 'residual_i' => 3, 'target_l' => 2, 'target_i' => 2, 'status' => 'monitoring'], $attrs));
        $risk->organization_id = $this->org->id;
        (new \App\Support\Scoring())->apply($risk, $cat);
        $risk->save();
        return $risk;
    }

    protected function riskPayload(array $over = []): array
    {
        $cat = \App\Models\RiskCategory::withoutGlobalScopes()->where('organization_id', $this->org->id)->first();
        return array_merge(['name' => 'Risiko baru', 'unit_id' => $this->unitA->id, 'category_id' => $cat->id, 'owner_id' => $this->users['risk_owner']->id, 'cause' => 'karena', 'event' => 'terjadi', 'impact' => 'berdampak',
            'source_type' => 'internal', 'source_kind' => 'process', 'treatment' => 'reduce', 'inherent_l' => 4, 'inherent_i' => 5, 'residual_l' => 3, 'residual_i' => 4, 'target_l' => 2, 'target_i' => 3], $over);
    }
}
