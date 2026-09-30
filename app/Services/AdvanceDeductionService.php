<?php

namespace App\Services;

use App\Models\Advance;
use App\Models\Salary;
use App\Models\SalaryComponentLog;
use Illuminate\Support\Facades\DB;

/**
 * Consumes advance installments - and ONLY when a salary is really disbursed.
 *
 * The financial rule enforced here:
 *
 *   remaining_amount = remaining_amount - installment_amount   (this month only)
 *   remaining_installments = remaining_installments - 1
 *   status = 'paid'  ONLY when remaining_amount <= 0
 *           = 'partially_paid' otherwise
 *
 * An advance must never be marked settled just because the installment counter
 * ran out, and a salary must never burn an installment while it is still a draft.
 *
 * Idempotency: `salaries.advances_deducted_at` is stamped on the first successful
 * deduction, so paying (or re-paying) the same salary can never consume a second
 * installment.
 */
class AdvanceDeductionService
{
    public function __construct(private SalaryBreakdownService $breakdownService) {}

    /**
     * @return array{deducted: int, advances: array<int, array<string, mixed>>}
     */
    public function deductForSalary(Salary $salary): array
    {
        return DB::transaction(function () use ($salary) {
            // Re-read under a row lock. Two concurrent "pay" requests serialise here,
            // so the second one observes the stamp written by the first and can never
            // burn a second installment. Checking the flag on the caller's (unlocked)
            // model outside the transaction was not sufficient.
            $locked = Salary::whereKey($salary->getKey())->lockForUpdate()->firstOrFail();

            if ($locked->advances_deducted_at !== null) {
                return ['deducted' => 0, 'advances' => []];
            }

            $locked->loadMissing('employee');

            $advanceIds = $this->resolveAdvanceIds($locked);

            if ($advanceIds->isEmpty()) {
                // nothing to deduct, but still stamp so a later advance log row can
                // never trigger a retroactive charge for an already-paid salary
                $locked->forceFill(['advances_deducted_at' => now()])->save();

                return ['deducted' => 0, 'advances' => []];
            }

            $touched = [];

            $advances = Advance::whereIn('id', $advanceIds)
                ->whereIn('status', ['active', 'partially_paid'])
                ->lockForUpdate()
                ->get();

            foreach ($advances as $advance) {
                $installment = round((float) $advance->installment_amount, 2);

                if ($installment <= 0 || $advance->remaining_installments <= 0) {
                    continue;
                }

                $remainingAmount = round((float) $advance->remaining_amount - $installment, 2);

                // never let a rounding tail push the balance below zero
                if ($remainingAmount < 0) {
                    $remainingAmount = 0.0;
                }

                $remainingInstallments = $advance->remaining_installments - 1;

                // settled only when there is genuinely nothing left to collect
                $status = $remainingAmount <= 0.005 ? 'paid' : 'partially_paid';

                $advance->update([
                    'paid_installments'      => $advance->paid_installments + 1,
                    'remaining_installments' => $remainingInstallments,
                    'remaining_amount'       => $remainingAmount,
                    'status'                 => $status,
                ]);

                $touched[] = [
                    'advance_id'            => $advance->id,
                    'installment'           => $installment,
                    'remaining_amount'      => $remainingAmount,
                    'remaining_installments' => $remainingInstallments,
                    'status'                => $status,
                ];
            }

            $locked->forceFill(['advances_deducted_at' => now()])->save();

            return ['deducted' => count($touched), 'advances' => $touched];
        });
    }

    /**
     * @return \Illuminate\Support\Collection<int, int>
     */
    private function resolveAdvanceIds(Salary $salary)
    {
        $loggedAdvanceIds = SalaryComponentLog::where('salary_id', $salary->id)
            ->where('component_type', 'advance')
            ->whereNotNull('component_id')
            ->pluck('component_id')
            ->unique()
            ->filter()
            ->values();

        // Legacy salaries can carry no 'advance' component rows at all: the old
        // calculate() re-created the salary on every run (and cascade-deleted the
        // previous log rows), and once an advance reached remaining_installments = 0
        // the `where('remaining_installments','>',0)` filter stopped logging it. So the
        // last saved salary of an exhausted advance has total_advances = 0 and no log
        // row. Falling back to the live active set prevents a silent under-deduction,
        // while preferring the log when it exists keeps us from charging an advance
        // that was created after this salary was calculated.
        return $loggedAdvanceIds->isNotEmpty()
            ? $loggedAdvanceIds
            : $this->breakdownService->build(
                $salary->employee,
                (int) $salary->month,
                (int) $salary->year
            )['active_advances']->pluck('id');
    }
}
