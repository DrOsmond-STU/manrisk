<?php

namespace App\Console\Commands;

use App\Models\Organization;
use App\Services\AlertService;
use Illuminate\Console\Command;

class DailySweep extends Command
{
    protected $signature = 'manrisk:daily-sweep';

    protected $description = 'Pengingat tenggat action plan, dokumen kedaluwarsa, kontrol jatuh tempo, SLA persetujuan';

    public function handle(AlertService $alerts): int
    {
        foreach (Organization::where('active', true)->get() as $org) {
            $r = $alerts->dailySweep($org->id);
            $this->info("{$org->code}: {$r['alerts']} peringatan baru");
        }
        return self::SUCCESS;
    }
}
