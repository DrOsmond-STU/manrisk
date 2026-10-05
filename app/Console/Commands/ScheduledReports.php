<?php

namespace App\Console\Commands;

use App\Mail\ScheduledReportMail;
use App\Models\ReportJob;
use App\Models\ReportSchedule;
use App\Services\ReportService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Mail;

class ScheduledReports extends Command
{
    protected $signature = 'manrisk:scheduled-reports {--force : Kirim semua jadwal aktif sekarang}';

    protected $description = 'Membuat dan mengirim laporan terjadwal lewat email';

    public function handle(ReportService $svc): int
    {
        $now = now();
        $n = 0;
        foreach (ReportSchedule::withoutGlobalScopes()->where('active', true)->with('user')->get() as $s) {
            if (!$this->option('force') && !$s->isDue($now)) {
                continue;
            }
            if (!$s->user || !$s->user->active) {
                $s->update(['active' => false, 'last_error' => 'Pembuat jadwal tidak aktif']);
                continue;
            }
            Auth::setUser($s->user); // laporan dibuat dengan hak akses & cakupan unit pembuatnya
            try {
                $out = $svc->render($s->type, $s->format, $s->user, $s->params ?? []);
                Mail::to($s->recipients)->send(new ScheduledReportMail(ReportService::TYPES[$s->type] ?? $s->type, $out['content'], $out['file'], $out['mime']));
                ReportJob::withoutGlobalScopes()->create(['organization_id' => $s->organization_id, 'user_id' => $s->user_id, 'type' => $s->type, 'format' => $s->format, 'params' => ['schedule' => $s->id], 'status' => 'done', 'path' => $out['file'], 'finished_at' => now()]);
                $s->update(['last_sent_at' => $now, 'last_error' => null]);
                $n++;
            } catch (\Throwable $e) {
                report($e);
                $s->update(['last_error' => mb_substr($e->getMessage(), 0, 500)]);
            }
            Auth::forgetUser();
        }
        $this->info("{$n} laporan terjadwal dikirim");
        return self::SUCCESS;
    }
}
