<?php

namespace App\Http\Requests;

use App\Support\Scoring;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class RiskRequest extends FormRequest
{
    /** Otorisasi dijalankan sebelum validasi agar pihak tanpa hak tidak menerima pesan validasi. */
    public function authorize(): bool
    {
        $risk = $this->route('risk');
        return $risk ? $this->user()->can('update', $risk) : $this->user()->can('create', \App\Models\Risk::class);
    }

    public function rules(): array
    {
        $org = $this->user()->organization_id;
        $exists = fn (string $table) => Rule::exists($table, 'id')->where('organization_id', $org);
        return [
            'name' => ['required', 'string', 'max:255'],
            'unit_id' => ['required', $exists('org_units')],
            'objective_id' => ['nullable', $exists('objectives')],
            'process_id' => ['nullable', $exists('processes')],
            'category_id' => ['required', $exists('risk_categories')],
            'owner_id' => ['required', Rule::exists('users', 'id')->where('organization_id', $org)->where('active', true)],
            'cause' => ['required', 'string', 'max:2000'],
            'event' => ['required', 'string', 'max:2000'],
            'impact' => ['required', 'string', 'max:2000'],
            'source_type' => ['required', Rule::in(['internal', 'external'])],
            'source_kind' => ['required', Rule::in(array_keys(config('manrisk.source_kinds')))],
            'existing_controls' => ['nullable', 'string', 'max:4000'],
            'treatment' => ['required', Rule::in(array_keys(config('manrisk.treatments')))],
            'treatment_note' => ['nullable', 'string', 'max:4000'],
            'due_date' => ['nullable', 'date'],
            'inherent_l' => ['required', 'integer', 'between:1,5'],
            'inherent_i' => ['required', 'integer', 'between:1,5'],
            'inherent_dims' => ['nullable', 'array'],
            'inherent_dims.*' => ['nullable', 'integer', 'between:1,5'],
            'residual_l' => ['required', 'integer', 'between:1,5'],
            'residual_i' => ['required', 'integer', 'between:1,5'],
            'residual_dims' => ['nullable', 'array'],
            'residual_dims.*' => ['nullable', 'integer', 'between:1,5'],
            'target_l' => ['required', 'integer', 'between:1,5'],
            'target_i' => ['required', 'integer', 'between:1,5'],
            'control_ids' => ['nullable', 'array'],
            'control_ids.*' => ['integer', $exists('controls')],
            'note' => ['nullable', 'string', 'max:1000'],
            // Action plan awal opsional dari wizard (langkah perlakuan)
            'plan_title' => ['nullable', 'string', 'max:255'],
            'plan_due' => ['nullable', 'required_with:plan_title', 'date', 'after_or_equal:today'],
            'plan_pic_id' => ['nullable', Rule::exists('users', 'id')->where('organization_id', $org)->where('active', true)],
            'from_incident' => ['nullable', Rule::exists('incidents', 'id')->where('organization_id', $org)],
        ];
    }

    public function withValidator($v): void
    {
        $v->after(function ($v) {
            $s = new Scoring();
            if ($s->score((int) $this->residual_l, (int) $this->residual_i) > $s->score((int) $this->inherent_l, (int) $this->inherent_i)) {
                $v->errors()->add('residual_l', 'Skor residual tidak boleh lebih besar dari skor inheren.');
            }
            if ($s->score((int) $this->target_l, (int) $this->target_i) > $s->score((int) $this->residual_l, (int) $this->residual_i)) {
                $v->errors()->add('target_l', 'Skor target tidak boleh lebih besar dari skor residual.');
            }
            if ($this->user()->isUnitScoped() && !$this->user()->canAccessUnit((int) $this->unit_id)) {
                $v->errors()->add('unit_id', 'Unit di luar cakupan Anda.');
            }
        });
    }
}
