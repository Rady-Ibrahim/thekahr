<?php

namespace App\Console\Commands;

use App\Models\Advance;
use App\Models\Salary;
use App\Models\SalaryComponentLog;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Repairs `advances` rows that were corrupted by the old salary calculation flow.
 *
 * THE CORRUPTION
 * --------------
 * SalaryCalculationService::calculate() used to decrement every active advance by
 * one installment on each run of the "حساب الرواتب" button, regardless of whether
 * the salary was approved or paid. Calculating N months therefore consumed N
 * installments, so a 10-installment advance ended up with
 * remaining_amount = 0 and status = 'paid' ("مسدد") although at most a few
 * salaries were ever actually disbursed.
 *
 * THE TRUTH WE RECONSTRUCT FROM
 * -----------------------------
 * The damaged columns (paid_installments / remaining_installments /
 * remaining_amount) are the corrupted ones, so they are NEVER used as input.
 * The only trustworthy evidence of a real deduction is:
 *
 *   salary_components_log rows with component_type = 'advance'
 *   whose component_id = the advance id,
 *   joined to a salary whose status is 'paid'.
 *
 * One distinct paid salary == one installment actually collected.
 */
class RepairAdvancesCommand extends Command
{
    protected $signature = 'payroll:repair-advances
                            {--apply : Actually write the changes (default is a dry run)}
                            {--backup= : Directory to dump the current advances table into}';

    protected $description = 'Rebuild advances.paid_installments / remaining_* / status from actually-paid salaries';

    public function handle(): int
    {
        if (! Schema::hasColumn('advances', 'status')) {
            $this->error('advances table not found.');
            return 1;
        }

        $apply = (bool) $this->option('apply');

        $this->line('');
        $this->info('Reconciling advances against actually-paid salaries');
        $this->line($apply
            ? '  MODE: APPLY (values will be written)'
            : '  MODE: DRY RUN - pass --apply to write');
        $this->line('');

        $rows = [];
        $needsReview = 0;
        $changed = 0;
        $suspectRows = [];

        foreach (Advance::orderBy('id')->get() as $advance) {
            $installment     = round((float) $advance->installment_amount, 2);
            $totalAmount     = round((float) $advance->amount, 2);
            $installmentCount = (int) $advance->installments_count;

            // ── Evidence: distinct PAID salaries that charged this advance ──────
            $paidSalaryIds = SalaryComponentLog::where('component_type', 'advance')
                ->where('component_id', $advance->id)
                ->join('salaries', 'salaries.id', '=', 'salary_components_log.salary_id')
                ->whereIn('salaries.status', ['paid'])
                ->distinct()
                ->pluck('salary_components_log.salary_id');

            $truePaid = $paidSalaryIds->count();

            if ($truePaid === 0) {
                // No paid salary ever charged it. If the stored counter claims money
                // was collected, that claim came from the buggy calculation loop.
                $claimed = (int) $advance->paid_installments;
                if ($claimed > 0) {
                    $needsReview++;
                }
                $truePaid = 0;
            }

            $truePaid = min($truePaid, $installmentCount);

            $remainingInstallments = max(0, $installmentCount - $truePaid);
            $remainingAmount       = round(max(0, $totalAmount - ($truePaid * $installment)), 2);

            if ($remainingAmount <= 0.005) {
                $status = 'paid';
            } elseif ($advance->status === 'rejected') {
                $status = 'rejected';
            } elseif ($truePaid > 0) {
                $status = 'partially_paid';
            } else {
                $status = $advance->status === 'pending' ? 'pending' : 'active';
            }

            $dirty =
                (int) $advance->paid_installments !== $truePaid ||
                (int) $advance->remaining_installments !== $remainingInstallments ||
                abs((float) $advance->remaining_amount - $remainingAmount) > 0.005 ||
                $advance->status !== $status;

            // ── Ambiguity that CANNOT be resolved from the data ───────────────
            // The old AdvanceController::reject() wrote status = 'paid' on a
            // rejection, and employeeSummary() summed the FULL amount of every
            // 'paid' row into total_paid. A genuinely settled advance always has
            // remaining_installments = 0 (and, under the corrected rule,
            // remaining_amount = 0). So a 'paid' row that still has installments
            // left can only have come from reject() or a manual DB edit - and it is
            // impossible to tell which. Those are listed for manual review only.
            $settledByCount = $advance->status === 'paid' && $advance->remaining_installments > 0;
            $amountNotCleared = $advance->status === 'paid'
                && abs((float) $advance->remaining_amount) > 0.005;

            $suspectedRejected = $settledByCount || $amountNotCleared;

            if ($suspectedRejected) {
                $suspectRows[] = $advance->id;
            }

            $rows[] = [
                'id'                    => $advance->id,
                'employee_id'           => $advance->employee_id,
                'amount'                => $totalAmount,
                'installments'          => $installmentCount,
                'installment'           => $installment,
                'was_paid_inst'         => (int) $advance->paid_installments,
                'was_remaining_inst'    => (int) $advance->remaining_installments,
                'was_remaining_amount'  => round((float) $advance->remaining_amount, 2),
                'was_status'            => $advance->status,
                'now_paid_inst'         => $truePaid,
                'now_remaining_inst'    => $remainingInstallments,
                'now_remaining_amount'  => $remainingAmount,
                'now_status'            => $status,
                'paid_salary_evidence'  => $paidSalaryIds->implode(','),
                'suspected_rejected'    => $suspectedRejected,
                'changed'               => $dirty,
            ];

            if ($dirty) {
                $changed++;
            }
        }

        if ($rows === []) {
            $this->warn('No advances found.');

            return 0;
        }

        $this->table(
            ['id', 'emp', 'amount', 'inst', 'was paid/left/amount/status', 'now paid/left/amount/status', 'evidence', 'flag'],
            array_map(fn($r) => [
                $r['id'],
                $r['employee_id'],
                number_format($r['amount'], 2),
                $r['installments'],
                sprintf(
                    '%d / %d / %s / %s',
                    $r['was_paid_inst'],
                    $r['was_remaining_inst'],
                    number_format($r['was_remaining_amount'], 2),
                    $r['was_status']
                ),
                sprintf(
                    '%d / %d / %s / %s',
                    $r['now_paid_inst'],
                    $r['now_remaining_inst'],
                    number_format($r['now_remaining_amount'], 2),
                    $r['now_status']
                ),
                $r['paid_salary_evidence'] === '' ? '-' : $r['paid_salary_evidence'],
                $r['suspected_rejected'] ? 'REVIEW' : '',
            ], $rows)
        );

        if ($suspectRows !== []) {
            $this->line('');
            $this->error(sprintf(
                'MANUAL REVIEW REQUIRED - advance id(s): %s',
                implode(', ', $suspectRows)
            ));
            $this->error(
                'These rows are marked "paid" yet still have installments or a non-zero balance. ' .
                'The old reject() endpoint wrote status="paid" on a REJECTED advance, so these are ' .
                'either rejected advances masquerading as settled, or manual database edits. ' .
                'The data cannot tell them apart, so they are NOT auto-corrected.'
            );
            $this->error(
                'Check each one against the advance request records. If it was rejected, set ' .
                "status='rejected'. If it was genuinely collected, confirm total_advances = amount."
            );
        }

        if ($needsReview > 0) {
            $this->line('');
            $this->warn(sprintf(
                '%d advance(s) claimed collected installments but have NO paid salary as evidence. ' .
                'They are reset to 0 paid - this is the signature of the old calculation bug.',
                $needsReview
            ));
        }

        $this->line('');
        $this->info(sprintf('%d of %d advance(s) need correction.', $changed, count($rows)));

        if (! $apply) {
            $this->line('');
            $this->comment('Dry run. Re-run with --apply to write these values.');

            return 0;
        }

        if ($changed === 0) {
            $this->line('');
            $this->info('Nothing to write.');

            return 0;
        }

        if (! $this->confirm('Write these corrections now?', true)) {
            $this->line('Aborted.');

            return 0;
        }

        $this->backup($rows);

        $skipped = 0;

        DB::transaction(function () use ($rows, &$skipped) {
            foreach ($rows as $r) {
                if (! $r['changed']) {
                    continue;
                }

                // Never auto-touch a row whose intent is undecidable from the data.
                if ($r['suspected_rejected']) {
                    $skipped++;
                    continue;
                }

                Advance::where('id', $r['id'])->update([
                    'paid_installments'      => $r['now_paid_inst'],
                    'remaining_installments' => $r['now_remaining_inst'],
                    'remaining_amount'       => $r['now_remaining_amount'],
                    'status'                 => $r['now_status'],
                ]);
            }
        });

        $this->line('');
        $this->info(sprintf('Repaired %d advance(s).', $changed - $skipped));

        if ($skipped > 0) {
            $this->warn(sprintf(
                '%d advance(s) left untouched because they are flagged REVIEW (rejected vs genuinely settled ' .
                'cannot be told apart). Decide those manually.',
                $skipped
            ));
        }

        return 0;
    }

    private function backup(array $rows): void
    {
        $dir = $this->option('backup') ?: storage_path('backups');

        if (! is_dir($dir)) {
            mkdir($dir, 0775, true);
        }

        $path = rtrim($dir, '/\\') . DIRECTORY_SEPARATOR
            . 'advances_backup_' . now()->format('Ymd_His') . '.json';

        file_put_contents($path, json_encode($rows, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

        $this->info("Backup written to: {$path}");
    }
}
