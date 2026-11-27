<?php

namespace App\Policies;

/** Penilaian RMI disusun fungsi manajemen risiko (lini kedua); peran lain dapat melihat. */
class RmiAssessmentPolicy extends BasePolicy
{
    protected array $writers = ['super_admin', 'risk_admin', 'risk_manager'];
    protected array $deleters = ['super_admin', 'risk_admin'];
    protected ?string $unitColumn = null;
}
