<?php

namespace App\Services;

use App\Models\IuranMember;
use App\Models\ProjectIuranAssignment;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class IuranTargetSynchronizer
{
    public function syncForTenant(int $tenantId, ?array $memberIds = null, bool $resetUnassigned = false): void
    {
        if ($tenantId <= 0) {
            return;
        }

        if (
            !Schema::hasTable('project_iuran_assignments')
            || !Schema::hasTable('projects')
            || !Schema::hasColumn('project_iuran_assignments', 'planned_amount')
        ) {
            return;
        }

        $filteredMemberIds = collect($memberIds ?? [])
            ->map(fn ($id) => (int) $id)
            ->filter(fn (int $id) => $id > 0)
            ->unique()
            ->values();

        $currentYear = (int) date('Y');

        $assignmentQuery = ProjectIuranAssignment::query()
            ->join('projects', 'projects.id', '=', 'project_iuran_assignments.project_id')
            ->where('projects.user_id', $tenantId)
            ->whereIn('projects.status', ['draft', 'ongoing']);

        if ($filteredMemberIds->isNotEmpty()) {
            $assignmentQuery->whereIn('project_iuran_assignments.iuran_member_id', $filteredMemberIds->all());
        }

        $aggregates = $assignmentQuery
            ->groupBy('project_iuran_assignments.iuran_member_id')
            ->get([
                'project_iuran_assignments.iuran_member_id',
                DB::raw('SUM(project_iuran_assignments.planned_amount) as total_target'),
                DB::raw('MIN(COALESCE(YEAR(projects.start_date), ' . $currentYear . ')) as min_year'),
                DB::raw('MAX(COALESCE(YEAR(projects.end_date), YEAR(projects.start_date), ' . $currentYear . ')) as max_year'),
            ])
            ->keyBy(fn ($row) => (int) $row->iuran_member_id);

        $targetMemberIds = $filteredMemberIds;
        if ($targetMemberIds->isEmpty()) {
            $targetMemberIds = $aggregates->keys()->map(fn ($id) => (int) $id)->values();
        }

        if ($targetMemberIds->isEmpty()) {
            return;
        }

        $members = IuranMember::query()
            ->withSum('installments as paid_amount', 'amount')
            ->where('user_id', $tenantId)
            ->whereIn('id', $targetMemberIds->all())
            ->get();

        foreach ($members as $member) {
            $aggregate = $aggregates->get((int) $member->id);
            if (!$aggregate && !$resetUnassigned) {
                continue;
            }

            $targetAmount = $aggregate
                ? max(0, round((float) $aggregate->total_target, 2))
                : 0.0;
            $startYear = $aggregate
                ? max(2000, (int) $aggregate->min_year)
                : $currentYear;
            $endYear = $aggregate
                ? max($startYear, (int) $aggregate->max_year)
                : $startYear;

            $paidAmount = (float) ($member->paid_amount ?? 0);
            $status = $paidAmount >= $targetAmount ? 'lunas' : 'aktif';

            $member->update([
                'target_amount' => $targetAmount,
                'target_start_year' => $startYear,
                'target_end_year' => $endYear,
                'status' => $status,
            ]);
        }
    }
}

