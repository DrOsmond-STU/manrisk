<?php

namespace App\Console\Commands;

use App\Models\Risk;
use App\Models\RiskSnapshot;
use Illuminate\Console\Command;

class MonthlySnapshot extends Command
{
    protected $signature = 'manrisk:snapshot {--period=}';

    protected $description = 'Simpan snapshot skor seluruh risiko untuk periode (YYYY-MM) sebagai dasar tren';

    public function handle(): int
    {
        $period = $this->option('period') ?: now()->format('Y-m');
        $n = 0;
        Risk::withoutGlobalScopes()->chunkById(200, function ($risks) use ($period, &$n) {
            foreach ($risks as $r) {
                RiskSnapshot::withoutGlobalScopes()->updateOrCreate(['risk_id' => $r->id, 'period' => $period], ['organization_id' => $r->organization_id, 'inherent_score' => $r->inherent_score, 'residual_score' => $r->residual_score, 'level' => $r->residual_level, 'status' => $r->status, 'created_at' => now()]);
                $n++;
            }
        });
        $this->info("Snapshot $period: $n risiko");
        return self::SUCCESS;
    }
}
