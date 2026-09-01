<?php

namespace Database\Seeders\Uat;

use App\Models\Asset;
use App\Models\AssetAssignment;
use App\Models\AssetDisposal;
use App\Models\AssetReturn;
use App\Models\AssetTransfer;
use App\Models\AssetVerification;
use App\Models\Location;
use App\Models\Program;
use App\Models\Staff;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;

/**
 * Assignments, returns, transfers, the February and August counts, and
 * disposals — all built on assets that exist in the register source. No asset
 * is created here.
 *
 * Where a workflow changes an asset (a count recording damage, an approved
 * disposal retiring an item), this seeder makes exactly the same change the
 * matching controller makes, so the register ends in a state the application
 * itself could have produced. Those are the only condition/status values in the
 * dataset that differ from the schema default.
 *
 * Counting dates follow the manual: full count and reconciliation in February
 * and August.
 */
class UatWorkflowSeeder extends Seeder
{
    public array $stats = [
        'assignments' => 0, 'returns' => 0, 'transfers' => 0,
        'verifications' => 0, 'disposals' => 0, 'assets_touched' => 0,
    ];

    /** @var array<int,string> */
    public array $notes = [];

    private array $users = [];

    private array $sites = [];

    /** asset_code => [recipient type, recipient name, site code, status, assigned days ago, due days from assigned] */
    private const ASSIGNMENTS = [
        'PEY-SR-MOV-0085' => ['program', 'Office', 'SR', 'active', 420, null],
        'PEY-SR-COM-0326' => ['staff', 'Meas Sokvoeun', 'SR', 'active', 120, 365],
        'PEY-SR-EQU-0161' => ['staff', 'Ariel Sophea', 'SR', 'active', 300, null],
        'PEY-SR-COM-0107' => ['program', 'LC_ICT', 'SR', 'active', 500, null],
        'PEY-KL-MOV-0044' => ['staff', 'Nhem Sievlong', 'KL', 'overdue', 400, 90],
        'PEY-SR-EQU-0121' => ['staff', 'Ariel Sophea', 'SR', 'returned', 600, 365],
        'PEY-SR-COM-0204' => ['program', 'Office Storage', 'SR', 'active', 200, null],
        'PEY-VR-EQU-0087' => ['staff', 'Pich Savoeng', 'VR', 'active', 260, null],
        'PEY-SR-FAF-0619' => ['program', 'LC_YE', 'SR', 'assigned', 60, 400],
        'PEY-SR-EQU-0192' => ['program', 'LC_YE', 'SR', 'active', 45, null],
        'PEY-SR-COM-0161' => ['program', 'Scholarship Program', 'SR', 'active', 340, 180],
        'PEY-SR-COM-0195' => ['program', 'Bright Future Labs', 'SR', 'active', 380, null],
    ];

    /** assignment asset_code => [returned_by email, condition, status, approver email|null, notes] */
    private const RETURNS = [
        'PEY-SR-EQU-0121' => ['staff.sr@pepy.test', 'good', 'approved', 'opm@pepy.test', 'Camera returned in working order after the Dream Program shoot.'],
        'PEY-SR-COM-0204' => ['staff.sr@pepy.test', 'fair', 'pending', null, 'Desktop returned from office storage — casing scratched, powers on normally.'],
        'PEY-SR-COM-0161' => ['staff.kl@pepy.test', 'broken', 'rejected', 'opm@pepy.test', 'Scholarship laptop returned with a cracked screen; return rejected pending a damage report.'],
    ];

    /** asset_code => [from site, to site, requester email, status, approver email|null, days ago, reason] */
    private const TRANSFERS = [
        'PEY-ST-FAF-0536' => ['ST', 'SP', 'opm2@pepy.test', 'pending', null, 6,
            'Fan is physically at Spean Thnort but tagged PEY-ST-. Move the register record onto the Spean Thnort site.'],
        'PEY-SR-COM-0284' => ['SR', 'SS', 'finance@pepy.test', 'pending', null, 3,
            'Dream Program laptops needed at Sen Sok HS for the new ICT cohort.'],
        'PEY-SR-EQU-0189' => ['SR', 'KD', 'opm2@pepy.test', 'approved', 'opm@pepy.test', 40,
            'JBL speaker reassigned to Kork Dong HS for Dream Program sessions.'],
        'PEY-SR-EQU-0190' => ['SR', 'RO', 'finance@pepy.test', 'rejected', 'opm@pepy.test', 25,
            'Second JBL speaker requested for Roeul HS — rejected, office needs it for the annual event.'],
        'PEY-SS-FAF-0512' => ['SS', 'BS', 'opm2@pepy.test', 'pending', null, 1,
            'Wall fan surplus at Sen Sok HS after the classroom refit.'],
    ];

    /**
     * The February and August counts. Each row is:
     *   asset_code => [count month, outcome, quantity found, condition, site found at, remark]
     *
     * Outcomes map onto the manual's reconciliation categories: confirmed,
     * relocated, missing, damaged, discrepancy.
     */
    private const COUNTS = [
        // ---- February 2026 count ----
        ['PEY-SR-MOV-0085', 'feb', 'confirmed', 1, 'good', null, 'Matched to the register. Odometer and tag both checked.'],
        ['PEY-SR-EQU-0001', 'feb', 'confirmed', 1, 'good', null, 'Safe box present in the finance room, tag legible.'],
        ['PEY-SR-FAF-0589', 'feb', 'confirmed', 1, 'good', null, 'Wall fan present and working.'],
        ['PEY-KL-FAF-0290', 'feb', 'damaged', 1, 'broken', null, 'Ceiling fan present but not running — motor seized. Recommended for repair or disposal.'],
        ['PEY-SR-FAF-0657', 'feb', 'discrepancy', 3, 'good', null, 'Three identical arm chairs found under one tag. Register records a single unit — needs re-tagging.'],
        ['PEY-VR-FAF-0472', 'feb', 'relocated', 1, 'good', 'BS', 'Tagged for Varin HS, physically found at Banteay Srei HS. Relocation to be confirmed with the site focal point.'],
        ['PEY-SR-COM-0022', 'feb', 'confirmed', 1, 'fair', null, 'Old desktop still in service, showing wear.'],
        ['PEY-SR-EQU-0008', 'feb', 'damaged', 1, 'broken', null, 'Air-conditioner in the ICT room not cooling; compressor fault reported by the trainer.'],

        // ---- August 2026 count ----
        ['PEY-SR-MOV-0085', 'aug', 'confirmed', 1, 'good', null, 'Second count of the year. Vehicle present, tag intact.'],
        ['PEY-SR-MOV-0086', 'aug', 'confirmed', 1, 'good', null, 'Honda CR-V present. Purchase price still missing from the register.'],
        ['PEY-SR-EQU-0161', 'aug', 'confirmed', 1, 'good', null, 'Camera checked out to the Comms team, produced on request.'],
        ['PEY-SR-MOV-0007', 'aug', 'missing', 0, 'lost', null, 'Black motor helmet not produced at the count and not accounted for by any staff member.'],
        ['PEY-KL-FAF-0078', 'aug', 'damaged', 1, 'broken', null, 'Plastic chair group — one unit with a broken leg pulled from service.'],
        ['PEY-SS-FAF-0294', 'aug', 'relocated', 1, 'good', 'KL', 'Standing fan tagged to Sen Sok HS found in the Kralanh HS Dream classroom.'],
        ['PEY-SR-FAF-0525', 'aug', 'discrepancy', 2, 'fair', null, 'Two units found against one register line. Second unit untagged.'],
        ['PEY-SR-COM-0043', 'aug', 'missing', 0, 'lost', null, 'Laminator not found in the office storage room at the August count.'],
        ['PEY-SR-EQU-0171', 'aug', 'confirmed', 1, 'good', 'SR', 'Boom Bass speaker located at the office. Register row is incomplete in the source workbook — site recorded at the count.'],
        ['PEY-SR-FAF-0016', 'aug', 'confirmed', 1, 'good', null, 'Arm chair present in the English learning centre.'],
    ];

    /** asset_code => [action, status, requester email, reviewer email|null, days ago, reason, review notes|null] */
    private const DISPOSALS = [
        'PEY-KL-FAF-0290' => ['disposal', 'pending', 'opm@pepy.test', null, 4,
            'Ceiling fan failed at the February count — motor seized, repair quote exceeds replacement cost.', null],
        'PEY-SR-EQU-0008' => ['repair', 'pending', 'opm@pepy.test', null, 3,
            'ICT room air-conditioner not cooling. Requesting approval for a compressor repair.', null],
        'PEY-SR-COM-0059' => ['replacement', 'pending', 'finance@pepy.test', null, 2,
            'Donated laptop group is past useful life and cannot run current coursework software.', null],
        'PEY-SR-MOV-0005' => ['disposal', 'approved', 'opm@pepy.test', 'ed@pepy.test', 70,
            'White motor helmet, foam perished and strap torn — unsafe to issue.',
            'Approved. Remove from the register and dispose of locally.'],
        'PEY-SR-COM-0323' => ['disposal', 'rejected', 'opm@pepy.test', 'ed@pepy.test', 55,
            'Amazon Kindle no longer used by the office.',
            'Not approved — reassign to the English learning centre for student reading instead of writing it off.'],
    ];

    public function run(): void
    {
        $this->users = User::pluck('id', 'email')->all();
        $this->sites = Location::whereNotNull('code')->pluck('id', 'code')->all();

        $this->seedAssignments();
        $this->seedReturns();
        $this->seedTransfers();
        $this->seedCounts();
        $this->seedDisposals();
    }

    // -----------------------------------------------------------------

    private function seedAssignments(): void
    {
        foreach (self::ASSIGNMENTS as $code => [$type, $recipient, $siteCode, $status, $daysAgo, $dueIn]) {
            $asset = $this->asset($code);
            if (! $asset) {
                continue;
            }

            $recipientId = $type === 'staff'
                ? Staff::where('full_name', $recipient)->value('id')
                : Program::where('name', $recipient)->value('id');

            if (! $recipientId) {
                $this->notes[] = "Assignment skipped for {$code}: no {$type} named \"{$recipient}\".";

                continue;
            }

            $assignedDate = now()->subDays($daysAgo);

            AssetAssignment::updateOrCreate(
                ['asset_id' => $asset->id, 'assigned_to_type' => $type, 'assigned_to_id' => $recipientId],
                [
                    'location_id' => $this->sites[$siteCode] ?? $asset->location_id,
                    'quantity' => 1,
                    'assigned_date' => $assignedDate->toDateString(),
                    'due_date' => $dueIn ? $assignedDate->copy()->addDays($dueIn)->toDateString() : null,
                    'status' => $status,
                ]
            );

            $this->stats['assignments']++;
        }
    }

    /**
     * AssetReturn rows are seeded even though no screen renders them: the API
     * routes are live, and the staff dashboard's "Pending returns" tile counts
     * this table, so without rows that tile can only ever read zero.
     */
    private function seedReturns(): void
    {
        foreach (self::RETURNS as $code => [$email, $condition, $status, $approverEmail, $notes]) {
            $asset = $this->asset($code);
            if (! $asset) {
                continue;
            }

            $assignment = AssetAssignment::where('asset_id', $asset->id)->first();
            if (! $assignment) {
                $this->notes[] = "Return skipped for {$code}: no assignment to return against.";

                continue;
            }

            AssetReturn::updateOrCreate(
                ['assignment_id' => $assignment->id, 'asset_id' => $asset->id],
                [
                    'returned_by' => $this->users[$email] ?? null,
                    'condition' => $condition,
                    'damage_notes' => $condition === 'good' ? null : $notes,
                    'status' => $status,
                    'approved_by' => $approverEmail ? ($this->users[$approverEmail] ?? null) : null,
                    'admin_notes' => $status === 'pending' ? null : $notes,
                    'return_date' => now()->subDays(20)->toDateString(),
                ]
            );

            $this->stats['returns']++;
        }
    }

    private function seedTransfers(): void
    {
        foreach (self::TRANSFERS as $code => [$from, $to, $requester, $status, $approver, $daysAgo, $reason]) {
            $asset = $this->asset($code);
            if (! $asset) {
                continue;
            }

            if (! isset($this->sites[$from], $this->sites[$to])) {
                $this->notes[] = "Transfer skipped for {$code}: site code {$from} or {$to} not found.";

                continue;
            }

            $transfer = AssetTransfer::updateOrCreate(
                ['asset_id' => $asset->id, 'from_location_id' => $this->sites[$from], 'to_location_id' => $this->sites[$to]],
                [
                    'requested_by' => $this->users[$requester] ?? null,
                    'reason' => $reason,
                    'status' => $status,
                    'approved_by' => $approver ? ($this->users[$approver] ?? null) : null,
                    'transfer_date' => now()->subDays($daysAgo)->toDateString(),
                ]
            );

            // AssetTransferController::processTransfer relocates the asset on
            // approval — mirror that so the register agrees with the workflow.
            if ($status === 'approved' && $asset->location_id !== $this->sites[$to]) {
                $asset->update(['location_id' => $this->sites[$to]]);
                $this->stats['assets_touched']++;
            }

            $transfer->created_at = now()->subDays($daysAgo);
            $transfer->save();

            $this->stats['transfers']++;
        }
    }

    /**
     * February and August counts. Verification rows carry the outcome in the
     * remark; the asset itself is updated for damaged/missing exactly as
     * AssetVerificationController::store does (broken and lost propagate to the
     * asset, good and fair do not).
     */
    private function seedCounts(): void
    {
        $countDates = [
            'feb' => Carbon::create(2026, 2, 3),
            'aug' => Carbon::create(2026, 8, 5),
        ];

        $counters = [
            'feb' => 'finance@pepy.test',   // manual: Finance schedules and verifies the count
            'aug' => 'opm@pepy.test',
        ];

        foreach (self::COUNTS as [$code, $season, $outcome, $quantity, $condition, $foundAtSite, $remark]) {
            $asset = $this->asset($code);
            if (! $asset) {
                continue;
            }

            $locationId = $foundAtSite ? ($this->sites[$foundAtSite] ?? null) : $asset->location_id;

            if (! $locationId) {
                $this->notes[] = "Count row skipped for {$code}: asset has no location and no count site was given.";

                continue;
            }

            $verifiedAt = $countDates[$season];

            AssetVerification::updateOrCreate(
                ['asset_id' => $asset->id, 'verified_at' => $verifiedAt->toDateString()],
                [
                    'location_id' => $locationId,
                    'verified_by' => (string) ($this->users[$counters[$season]] ?? ''),
                    'quantity_verified' => $quantity,
                    'condition' => $condition,
                    'remark' => strtoupper($outcome).' — '.$remark,
                ]
            );

            if (in_array($condition, ['broken', 'lost'], true) && $asset->condition !== $condition) {
                $asset->update(['condition' => $condition]);
                $this->stats['assets_touched']++;
            }

            $this->stats['verifications']++;
        }
    }

    private function seedDisposals(): void
    {
        foreach (self::DISPOSALS as $code => [$action, $status, $requester, $reviewer, $daysAgo, $reason, $reviewNotes]) {
            $asset = $this->asset($code);
            if (! $asset) {
                continue;
            }

            $disposal = AssetDisposal::updateOrCreate(
                ['asset_id' => $asset->id, 'recommended_action' => $action],
                [
                    'requested_by' => $this->users[$requester] ?? null,
                    'reason' => $reason,
                    'status' => $status,
                    'reviewed_by' => $reviewer ? ($this->users[$reviewer] ?? null) : null,
                    'reviewed_at' => $reviewer ? now()->subDays(max($daysAgo - 5, 1)) : null,
                    'review_notes' => $reviewNotes,
                ]
            );

            // AssetDisposalController::approve retires the asset only when the
            // approved action is an actual disposal.
            if ($status === 'approved' && $action === 'disposal' && $asset->status !== 'disposed') {
                $asset->update(['status' => 'disposed']);
                $this->stats['assets_touched']++;
            }

            $disposal->created_at = now()->subDays($daysAgo);
            $disposal->save();

            $this->stats['disposals']++;
        }
    }

    private function asset(string $code): ?Asset
    {
        $asset = Asset::where('asset_code', $code)->first();

        if (! $asset) {
            $this->notes[] = "Asset {$code} is not in the register — workflow row skipped rather than creating an undocumented asset.";
        }

        return $asset;
    }
}
