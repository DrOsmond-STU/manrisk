<?php

namespace Database\Seeders;

use App\Models\CriteriaVersion;
use App\Models\FrameworkItem;
use App\Models\Organization;
use App\Models\RiskCategory;
use App\Support\Scoring;
use Illuminate\Database\Seeder;

/** Data dasar yang wajib ada untuk setiap organisasi: kriteria, taksonomi, butir kerangka ISO 31000. */
class CoreSeeder extends Seeder
{
    public static function seedOrganization(Organization $org): void
    {
        $json = json_decode(file_get_contents(__DIR__ . '/data/demo.json'), true);
        if (!CriteriaVersion::withoutGlobalScopes()->where('organization_id', $org->id)->exists()) {
            CriteriaVersion::withoutGlobalScopes()->create([
                'organization_id' => $org->id, 'version' => 1, 'effective_from' => now()->startOfYear(),
                'likelihood' => array_map(fn ($l) => ['v' => $l['v'], 'label' => $l['id'], 'en' => $l['n'], 'desc' => $l['d']], $json['LIKELIHOOD']),
                'impact' => array_map(fn ($i) => ['v' => $i['v'], 'label' => $i['id'], 'en' => $i['n'], 'dims' => ['fin' => $i['fin'], 'ops' => $i['ops'], 'rep' => $i['rep'], 'law' => $i['law']]], $json['IMPACT']),
                'dimensions' => [['key' => 'fin', 'label' => 'Finansial'], ['key' => 'ops', 'label' => 'Operasional'], ['key' => 'rep', 'label' => 'Reputasi'], ['key' => 'law', 'label' => 'Hukum/Kepatuhan']],
                'matrix' => Scoring::defaultMatrix(), 'thresholds' => ['escalate' => 16, 'critical' => 20], 'active' => true,
            ]);
        }
        if (!RiskCategory::withoutGlobalScopes()->where('organization_id', $org->id)->exists()) {
            foreach ($json['TAXONOMY'] as $i => $t) {
                RiskCategory::withoutGlobalScopes()->create(['organization_id' => $org->id, 'name' => $t['k'], 'name_en' => $t['en'], 'description' => $t['d'], 'appetite' => $t['app'], 'tolerance' => $t['tol'], 'sort' => $i + 1]);
            }
        }
        if (!FrameworkItem::withoutGlobalScopes()->where('organization_id', $org->id)->exists()) {
            $items = [
                ['4.a', 'principle', 'Terintegrasi', 'Semua modul'], ['4.b', 'principle', 'Terstruktur dan komprehensif', 'Risk Register, Kriteria'], ['4.c', 'principle', 'Disesuaikan (customized)', 'Kriteria, Taksonomi'],
                ['4.d', 'principle', 'Inklusif', 'Konsultasi, Multi-peran'], ['4.e', 'principle', 'Dinamis', 'KRI, Early Warning, Review'], ['4.f', 'principle', 'Informasi terbaik yang tersedia', 'Dokumen, Insiden, Loss DB'],
                ['4.g', 'principle', 'Faktor manusia dan budaya', 'Lesson Learned, Pelatihan'], ['4.h', 'principle', 'Perbaikan berkelanjutan', 'Continual Improvement'],
                ['5.2', 'framework', 'Kepemimpinan dan komitmen', 'Executive Dashboard, Approval'], ['5.3', 'framework', 'Integrasi', 'Objective Mapping'], ['5.4', 'framework', 'Desain', 'Konteks, Kriteria, Organisasi'],
                ['5.5', 'framework', 'Implementasi', 'Risk Register, Treatment'], ['5.6', 'framework', 'Evaluasi', 'Review, Audit Trail'], ['5.7', 'framework', 'Perbaikan', 'Continual Improvement'],
                ['6.2', 'process', 'Komunikasi dan konsultasi', 'Konsultasi, Notifikasi'], ['6.3', 'process', 'Ruang lingkup, konteks, kriteria', 'Konteks & Kriteria'], ['6.4.2', 'process', 'Identifikasi risiko', 'Identifikasi'],
                ['6.4.3', 'process', 'Analisis risiko', 'Analisis, Matriks'], ['6.4.4', 'process', 'Evaluasi risiko', 'Evaluasi'], ['6.5', 'process', 'Perlakuan risiko', 'Treatment, Action Plan'],
                ['6.6', 'process', 'Pemantauan dan tinjauan', 'Monitoring, KRI, Review'], ['6.7', 'process', 'Pencatatan dan pelaporan', 'Dokumen, Laporan, Audit Trail'],
            ];
            foreach ($items as [$clause, $group, $title, $module]) {
                FrameworkItem::withoutGlobalScopes()->create(['organization_id' => $org->id, 'clause' => $clause, 'group' => $group, 'title' => $title, 'module' => $module, 'status' => 'partial', 'score' => 60]);
            }
        }
    }

    public function run(): void
    {
        $org = Organization::firstOrCreate(['code' => env('MR_ORG_CODE', 'BLDN')], ['name' => env('MR_ORG_NAME', 'Badan Layanan Digital Nusantara'), 'settings' => ['review_cycle' => 'quarterly', 'fiscal_year_start' => 1, 'currency' => 'IDR']]);
        self::seedOrganization($org);
    }
}
