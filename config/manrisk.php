<?php

/*
 * Konfigurasi aplikasi ManRisk ERM. Nilai yang bersifat kebijakan dapat ditimpa lewat .env.
 */
return [
    'app_name' => 'ManRisk ERM',

    // Keamanan sesi & kata sandi (spesifikasi §16.1)
    'password_min' => (int) env('MR_PASSWORD_MIN', 10),
    'password_history' => (int) env('MR_PASSWORD_HISTORY', 5),
    'login_max_attempts' => (int) env('MR_LOGIN_MAX_ATTEMPTS', 5),
    'login_lock_minutes' => (int) env('MR_LOGIN_LOCK_MINUTES', 15),
    'session_idle_minutes' => (int) env('MR_SESSION_IDLE_MINUTES', 30),
    'session_absolute_hours' => (int) env('MR_SESSION_ABSOLUTE_HOURS', 8),

    // MFA via email (OTP)
    'mfa' => [
        'code_ttl_minutes' => (int) env('MR_MFA_CODE_TTL', 10),
    ],

    // Penyelesaian action plan wajib bukti & verifikasi Risk Owner (F-TRT-07)
    'plan_completion_verification' => (bool) env('MR_PLAN_VERIFICATION', true),

    // Alur persetujuan
    'approval_sla_days' => (int) env('MR_APPROVAL_SLA_DAYS', 3),

    // Pengingat
    'action_reminder_days' => [7, 1],
    'action_escalation_days' => 14,
    'document_expiry_days' => [60, 30, 7],

    // Unggahan (spesifikasi §4.13, §16.3)
    'upload_max_kb' => (int) env('MR_UPLOAD_MAX_KB', 25600),
    'upload_mimes' => ['application/pdf', 'application/msword', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'application/vnd.ms-excel', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', 'image/jpeg', 'image/png'],
    'upload_extensions' => ['pdf', 'doc', 'docx', 'xls', 'xlsx', 'jpg', 'jpeg', 'png'],
    // Pemindaian antivirus opsional (spesifikasi F-DOC-05): path ke clamscan/clamdscan; kosong = nonaktif
    'clamav_path' => env('MR_CLAMAV_PATH'),

    // AI (spesifikasi §14)
    'ai' => [
        'enabled' => (bool) env('MR_AI_ENABLED', true),
        'provider' => env('MR_AI_PROVIDER', 'anthropic'),
        'api_key' => env('ANTHROPIC_API_KEY'),
        'model' => env('MR_AI_MODEL', 'claude-sonnet-5-5'),
        'daily_limit' => (int) env('MR_AI_DAILY_LIMIT', 50),
        'timeout' => 60,
    ],

    'document_types' => ['sop' => 'SOP', 'policy' => 'Kebijakan', 'minutes' => 'Berita Acara', 'photo' => 'Foto', 'report' => 'Laporan',
        'audit' => 'Hasil Audit', 'evidence' => 'Bukti Pelaksanaan', 'contract' => 'Kontrak', 'screenshot' => 'Screenshot', 'certificate' => 'Sertifikat', 'test' => 'Hasil Pengujian'],
    'source_kinds' => ['people' => 'People', 'process' => 'Process', 'technology' => 'Technology', 'infrastructure' => 'Infrastructure', 'regulation' => 'Regulation', 'financial' => 'Financial', 'third_party' => 'Third Party'],
    'treatments' => ['avoid' => 'Hindari', 'reduce' => 'Kurangi', 'share' => 'Bagikan', 'retain' => 'Terima'],
    'risk_statuses' => ['draft' => 'Draft', 'pending' => 'Menunggu Persetujuan', 'treating' => 'Dalam Penanganan', 'monitoring' => 'Dipantau', 'closed' => 'Ditutup'],
    'incident_statuses' => ['reported' => 'Dilaporkan', 'investigating' => 'Investigasi', 'corrective' => 'Tindakan Korektif', 'closed' => 'Ditutup'],
    'effectiveness' => [1 => 'Tidak Efektif', 2 => 'Sebagian Efektif', 3 => 'Efektif', 4 => 'Sangat Efektif'],
    'priorities' => ['low' => 'Rendah', 'medium' => 'Sedang', 'high' => 'Tinggi', 'critical' => 'Kritis'],
    'unit_types' => ['organization' => 'Organisasi', 'deputy' => 'Deputi/Eselon I', 'directorate' => 'Direktorat', 'bureau' => 'Biro', 'division' => 'Bagian', 'subdivision' => 'Subbagian', 'unit' => 'Unit'],
];
