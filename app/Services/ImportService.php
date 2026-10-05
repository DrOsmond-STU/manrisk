<?php

namespace App\Services;

use App\Models\CriteriaVersion;
use App\Models\Kri;
use App\Models\KriValue;
use App\Models\Objective;
use App\Models\OrgUnit;
use App\Models\Risk;
use App\Models\RiskCategory;
use App\Models\RiskVersion;
use App\Models\User;
use App\Support\Numbering;
use App\Support\Scoring;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

/**
 * Impor Excel dengan pratinjau kesalahan (spesifikasi F-REG-06, F-KRI-02, skenario uji #20):
 * berkas dibaca → tiap baris divalidasi → pengguna melihat baris valid/galat → hanya baris valid
 * yang disimpan setelah dikonfirmasi.
 */
class ImportService
{
    public const MAX_ROWS = 1000;

    public const RISK_COLUMNS = [
        'nama' => 'Nama Risiko', 'unit' => 'Kode Unit', 'kategori' => 'Kategori', 'pemilik' => 'Email Pemilik', 'sasaran' => 'Kode Sasaran',
        'penyebab' => 'Penyebab', 'peristiwa' => 'Peristiwa', 'dampak' => 'Dampak', 'sumber' => 'Sumber (internal/external)', 'jenis_sumber' => 'Jenis Sumber',
        'kontrol' => 'Kontrol Ada', 'l_inh' => 'L Inheren', 'i_inh' => 'I Inheren', 'l_res' => 'L Residual', 'i_res' => 'I Residual', 'l_tgt' => 'L Target', 'i_tgt' => 'I Target',
        'treatment' => 'Treatment (avoid/reduce/share/retain)',
    ];

    public const KRI_COLUMNS = ['kode' => 'Kode KRI', 'periode' => 'Periode (YYYY-MM)', 'nilai' => 'Nilai', 'referensi' => 'Referensi Sumber'];

    public function template(string $type): string
    {
        $cols = $type === 'kri' ? self::KRI_COLUMNS : self::RISK_COLUMNS;
        $ss = new Spreadsheet();
        $sheet = $ss->getActiveSheet();
        $sheet->setTitle($type === 'kri' ? 'Nilai KRI' : 'Risiko');
        $sheet->fromArray([array_values($cols)], null, 'A1');
        $example = $type === 'kri' ? ['KRI-001', now()->format('Y-m'), 2.5, 'Laporan bulanan'] : ['Contoh: Gangguan layanan aplikasi', 'TI-01', 'Teknologi Informasi', 'owner@manrisk.id', 'SS-1', 'server tunggal tanpa redundansi', 'layanan tidak tersedia', 'pengguna tidak dapat mengakses layanan', 'internal', 'technology', 'Backup harian', 4, 4, 3, 3, 2, 2, 'reduce'];
        $sheet->fromArray([$example], null, 'A2');
        $sheet->getStyle('1:1')->getFont()->setBold(true);
        foreach (range(1, count($cols)) as $c) {
            $sheet->getColumnDimensionByColumn($c)->setAutoSize(true);
        }
        $path = tempnam(sys_get_temp_dir(), 'tpl') . '.xlsx';
        (new Xlsx($ss))->save($path);
        return $path;
    }

    /** Baca berkas → baris (array asosiatif berdasarkan kunci kolom). */
    public function read(string $path, string $type): array
    {
        $cols = $type === 'kri' ? self::KRI_COLUMNS : self::RISK_COLUMNS;
        $reader = IOFactory::createReaderForFile($path);
        $reader->setReadDataOnly(true);
        $rows = $reader->load($path)->getActiveSheet()->toArray(null, true, false, false);
        $header = array_map(fn ($h) => mb_strtolower(trim((string) $h)), array_shift($rows) ?? []);
        $map = [];
        foreach ($cols as $key => $label) {
            $idx = array_search(mb_strtolower($label), $header, true);
            if ($idx === false) {
                throw new \RuntimeException("Kolom \"{$label}\" tidak ditemukan. Gunakan templat yang disediakan.");
            }
            $map[$key] = $idx;
        }
        $out = [];
        foreach ($rows as $i => $r) {
            if (count(array_filter($r, fn ($v) => $v !== null && trim((string) $v) !== '')) === 0) {
                continue;
            }
            $row = ['_line' => $i + 2];
            foreach ($map as $key => $idx) {
                $v = $r[$idx] ?? null;
                $row[$key] = is_string($v) ? trim($v) : $v;
            }
            $out[] = $row;
            if (count($out) > self::MAX_ROWS) {
                throw new \RuntimeException('Maksimal ' . self::MAX_ROWS . ' baris per impor.');
            }
        }
        return $out;
    }

    /** Validasi baris risiko → ['valid' => [...payload siap simpan], 'errors' => [...]] */
    public function validateRisks(array $rows, User $user): array
    {
        $units = OrgUnit::get(['id', 'code', 'name'])->keyBy(fn ($u) => mb_strtolower($u->code));
        $cats = RiskCategory::where('active', true)->get(['id', 'name'])->keyBy(fn ($c) => mb_strtolower($c->name));
        $owners = User::where('active', true)->get(['id', 'email'])->keyBy(fn ($u) => mb_strtolower($u->email));
        $objs = Objective::get(['id', 'code'])->keyBy(fn ($o) => mb_strtolower($o->code));
        $valid = [];
        $errors = [];
        $scoring = new Scoring();
        foreach ($rows as $r) {
            $msgs = [];
            $unit = $units[mb_strtolower((string) $r['unit'])] ?? null;
            $cat = $cats[mb_strtolower((string) $r['kategori'])] ?? null;
            $owner = $owners[mb_strtolower((string) $r['pemilik'])] ?? null;
            $obj = $r['sasaran'] ? ($objs[mb_strtolower((string) $r['sasaran'])] ?? null) : null;
            if (!$unit) $msgs[] = "Kode unit \"{$r['unit']}\" tidak dikenal";
            elseif (!$user->canAccessUnit($unit->id)) $msgs[] = 'Unit di luar cakupan Anda';
            if (!$cat) $msgs[] = "Kategori \"{$r['kategori']}\" tidak dikenal";
            if (!$owner) $msgs[] = "Pemilik \"{$r['pemilik']}\" tidak ditemukan/aktif";
            if ($r['sasaran'] && !$obj) $msgs[] = "Sasaran \"{$r['sasaran']}\" tidak dikenal";
            $v = Validator::make($r, [
                'nama' => ['required', 'string', 'max:255'], 'penyebab' => ['required', 'string', 'max:2000'], 'peristiwa' => ['required', 'string', 'max:2000'], 'dampak' => ['required', 'string', 'max:2000'],
                'sumber' => ['required', 'in:internal,external,eksternal'], 'jenis_sumber' => ['required', 'in:' . implode(',', array_keys(config('manrisk.source_kinds')))],
                'kontrol' => ['nullable', 'string', 'max:4000'], 'treatment' => ['required', 'in:' . implode(',', array_keys(config('manrisk.treatments')))],
                'l_inh' => ['required', 'integer', 'between:1,5'], 'i_inh' => ['required', 'integer', 'between:1,5'], 'l_res' => ['required', 'integer', 'between:1,5'],
                'i_res' => ['required', 'integer', 'between:1,5'], 'l_tgt' => ['required', 'integer', 'between:1,5'], 'i_tgt' => ['required', 'integer', 'between:1,5'],
            ], [], array_map(fn ($l) => $l, self::RISK_COLUMNS));
            if ($v->fails()) {
                $msgs = array_merge($msgs, $v->errors()->all());
            } else {
                $inh = $scoring->score((int) $r['l_inh'], (int) $r['i_inh']);
                $res = $scoring->score((int) $r['l_res'], (int) $r['i_res']);
                $tgt = $scoring->score((int) $r['l_tgt'], (int) $r['i_tgt']);
                if ($res > $inh) $msgs[] = 'Skor residual melebihi inheren';
                if ($tgt > $res) $msgs[] = 'Skor target melebihi residual';
            }
            if ($msgs) {
                $errors[] = ['line' => $r['_line'], 'name' => (string) $r['nama'], 'errors' => $msgs];
                continue;
            }
            $valid[] = ['line' => $r['_line'], 'data' => [
                'name' => $r['nama'], 'unit_id' => $unit->id, 'category_id' => $cat->id, 'owner_id' => $owner->id, 'objective_id' => $obj?->id,
                'cause' => $r['penyebab'], 'event' => $r['peristiwa'], 'impact' => $r['dampak'], 'source_type' => $r['sumber'] === 'internal' ? 'internal' : 'external',
                'source_kind' => $r['jenis_sumber'], 'existing_controls' => $r['kontrol'], 'treatment' => $r['treatment'],
                'inherent_l' => (int) $r['l_inh'], 'inherent_i' => (int) $r['i_inh'], 'residual_l' => (int) $r['l_res'], 'residual_i' => (int) $r['i_res'], 'target_l' => (int) $r['l_tgt'], 'target_i' => (int) $r['i_tgt'],
            ], 'preview' => ['unit' => $unit->name, 'category' => $cat->name, 'owner' => $owner->email, 'residual' => $scoring->score((int) $r['l_res'], (int) $r['i_res'])]];
        }
        return ['valid' => $valid, 'errors' => $errors];
    }

    public function commitRisks(array $valid, User $user): int
    {
        $criteria = CriteriaVersion::current();
        return DB::transaction(function () use ($valid, $user, $criteria) {
            $n = 0;
            foreach ($valid as $row) {
                $risk = new Risk($row['data']);
                $risk->code = Numbering::next(Risk::class, 'R');
                $risk->status = 'draft';
                $risk->version = 1;
                $risk->criteria_version_id = $criteria?->id;
                $risk->created_by = $user->id;
                $risk->updated_by = $user->id;
                app(Scoring::class)->apply($risk, RiskCategory::find($row['data']['category_id']));
                $risk->save();
                RiskVersion::create(['risk_id' => $risk->id, 'version' => 1, 'inherent_l' => $risk->inherent_l, 'inherent_i' => $risk->inherent_i, 'residual_l' => $risk->residual_l, 'residual_i' => $risk->residual_i,
                    'target_l' => $risk->target_l, 'target_i' => $risk->target_i, 'snapshot' => $risk->only('name', 'cause', 'event', 'impact'), 'note' => 'Impor Excel', 'created_by' => $user->id]);
                $n++;
            }
            return $n;
        });
    }

    public function validateKri(array $rows, User $user): array
    {
        $kris = Kri::with('risk:id,unit_id')->get()->keyBy(fn ($k) => mb_strtolower($k->code));
        $valid = [];
        $errors = [];
        foreach ($rows as $r) {
            $msgs = [];
            $kri = $kris[mb_strtolower((string) $r['kode'])] ?? null;
            if (!$kri) $msgs[] = "KRI \"{$r['kode']}\" tidak dikenal";
            elseif (!$user->can('update', $kri)) $msgs[] = 'Anda tidak berwenang mengubah KRI ini';
            $periode = is_numeric($r['periode']) ? \PhpOffice\PhpSpreadsheet\Shared\Date::excelToDateTimeObject($r['periode'])->format('Y-m') : (string) $r['periode'];
            if (!preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $periode)) $msgs[] = 'Periode harus berformat YYYY-MM';
            if (!is_numeric($r['nilai'])) $msgs[] = 'Nilai harus angka';
            if ($msgs) {
                $errors[] = ['line' => $r['_line'], 'name' => (string) $r['kode'], 'errors' => $msgs];
                continue;
            }
            $valid[] = ['line' => $r['_line'], 'data' => ['kri_id' => $kri->id, 'period' => $periode, 'value' => (float) $r['nilai'], 'source_ref' => mb_substr((string) $r['referensi'], 0, 120) ?: 'impor Excel'],
                'preview' => ['kri' => "{$kri->code} · {$kri->name}", 'period' => $periode, 'value' => (float) $r['nilai']]];
        }
        return ['valid' => $valid, 'errors' => $errors];
    }

    public function commitKri(array $valid, User $user, AlertService $alerts): int
    {
        return DB::transaction(function () use ($valid, $user, $alerts) {
            $touched = [];
            foreach ($valid as $row) {
                $d = $row['data'];
                $existing = KriValue::where('kri_id', $d['kri_id'])->whereDate('period', $d['period'] . '-01')->first();
                $attrs = ['value' => $d['value'], 'entered_by' => $user->id, 'source_ref' => $d['source_ref']];
                $existing ? $existing->update($attrs) : KriValue::create(['kri_id' => $d['kri_id'], 'period' => $d['period'] . '-01'] + $attrs);
                $touched[$d['kri_id']] = true;
            }
            foreach (array_keys($touched) as $id) {
                $kri = Kri::find($id);
                $latest = $kri->values()->orderByDesc('period')->first();
                $prev = $kri->status;
                $kri->update(['last_value' => $latest->value, 'status' => $kri->statusFor((float) $latest->value)]);
                $alerts->checkKri($kri->fresh(), $prev);
            }
            return count($valid);
        });
    }
}
