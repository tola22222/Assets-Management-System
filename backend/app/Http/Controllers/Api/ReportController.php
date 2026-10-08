<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Mail\ScheduledAssetReportMail;
use App\Models\ActivityLog;
use App\Models\Asset;
use App\Models\AssetAssignment;
use App\Models\AssetReturn;
use App\Models\AssetScan;
use App\Models\AssetTransfer;
use App\Models\AssetVerification;
use App\Models\Location;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class ReportController extends Controller
{
    /**
     * Manual "email this report" action from the Reports page — sends the
     * same summary the automated `app:send-scheduled-asset-report` command
     * sends, immediately, to whatever address the user provides, regardless
     * of that command's own once-per-interval throttle.
     */
    public function email(Request $request)
    {
        $request->validate(['email' => 'required|email']);

        $summary = ScheduledAssetReportMail::buildSummary();
        $periodLabel = now()->format('F Y');

        try {
            Mail::to($request->email)->send(new ScheduledAssetReportMail($summary, $periodLabel, \App\Services\InventoryListXlsx::build(), \App\Services\InventoryListXlsx::fileName()));
        } catch (\Throwable $e) {
            Log::error('Manual report email failed for '.$request->email.': '.$e->getMessage());

            return response()->json(['message' => 'Could not send the email — check the mail server configuration.'], 422);
        }

        ActivityLog::create([
            'user_id' => $request->user()->id,
            'action' => 'Email Report',
            'description' => $request->user()->name." emailed the asset report to {$request->email}.",
        ]);

        return response()->json(['message' => 'Report emailed to '.$request->email.'.']);
    }

    /**
     * Grouped "count by model" view: each Asset row stays an individually
     * tracked unit with its own tag, but this rolls same-name units up into
     * one line per model/category — a read-only summary, not a change to how
     * the data is stored.
     *
     * total counts every unit still on the register; lost_broken is how many of
     * those a verification marked lost or broken; available is the rest.
     */
    /**
     * Staff read reports for their own sites only (their program's schools —
     * User::siteLocationIds(); none set means nothing, fail closed). Everyone
     * else sees every site. Each report below goes through one of these.
     */
    private function onSites($query, Request $request, string $relation = 'asset')
    {
        $user = $request->user();

        return $user->isSiteScoped() ? $query->whereHas($relation, fn ($a) => $a->visibleTo($user)) : $query;
    }

    public function byModel(Request $request)
    {
        $unavailable = "'".implode("','", Asset::UNAVAILABLE_CONDITIONS)."'";

        $rows = Asset::select('name', 'category_id', DB::raw('count(*) as total'), DB::raw("sum(case when `condition` in ($unavailable) then 1 else 0 end) as lost_broken"))
            ->onRegister()
            ->visibleTo($request->user())
            ->groupBy('name', 'category_id')
            ->with('category:id,name,short_name')
            ->get()
            ->map(function ($row) {
                $row->total = (int) $row->total;
                $row->lost_broken = (int) $row->lost_broken;
                $row->available = $row->total - $row->lost_broken;

                return $row;
            })
            ->sortByDesc('available')
            ->values();

        return response()->json($rows);
    }

    public function inventory(Request $request)
    {
        $query = Asset::visibleTo($request->user())->with([
            'category',
            'stocks.location',
            'location:id,name,code',
            // Who holds it now — the same assigned/active pair AssetAssignmentController
            // treats as "already assigned" when refusing a second assignment.
            'assignments' => fn ($q) => $q->whereIn('status', ['assigned', 'active'])->latest(),
        ]);

        if ($request->filled('category_id')) {
            $query->where('category_id', $request->category_id);
        }
        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }
        if ($request->filled('condition')) {
            $query->where('condition', $request->condition);
        }

        $assets = $query->latest()->get()->each(function ($asset) {
            $asset->current_user = $asset->assignments->first()?->recipient_name;
            $asset->unsetRelation('assignments');
        });

        return response()->json($assets);
    }

    public function assignments(Request $request)
    {
        $query = $this->onSites(AssetAssignment::with(['asset', 'location']), $request);

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }
        if ($request->filled('assigned_to_type')) {
            $query->where('assigned_to_type', $request->assigned_to_type);
        }

        return response()->json($query->latest()->get());
    }

    public function transfers(Request $request)
    {
        $query = AssetTransfer::with(['asset', 'fromLocation', 'toLocation', 'requester']);

        // A transfer touches two sites: staff see the ones leaving or reaching theirs.
        $user = $request->user();
        if ($user->isSiteScoped()) {
            $sites = $user->siteLocationIds();
            $query->where(fn ($q) => $q->whereIn('from_location_id', $sites)->orWhereIn('to_location_id', $sites));
        }

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        return response()->json($query->latest()->get());
    }

    public function verifications(Request $request)
    {
        $query = $this->onSites(AssetVerification::with(['asset', 'location', 'verifiedBy']), $request);

        if ($request->filled('condition')) {
            $query->where('condition', $request->condition);
        }

        return response()->json($query->latest()->get());
    }

    public function returns(Request $request)
    {
        $query = $this->onSites(AssetReturn::with(['asset', 'assignment', 'returnedBy']), $request);

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        return response()->json($query->latest()->get());
    }

    public function disposed(Request $request)
    {
        return response()->json(Asset::visibleTo($request->user())->where('status', 'disposed')->with('category')->latest()->get());
    }

    public function lost(Request $request)
    {
        return response()->json(Asset::visibleTo($request->user())->where('condition', 'lost')->with('category')->latest()->get());
    }

    public function locations(Request $request)
    {
        $user = $request->user();

        // Assets still on the register, as on the dashboard.
        return response()->json(
            Location::withCount(['assets' => fn ($q) => $q->where('status', '!=', 'disposed')])
                ->when($user->isSiteScoped(), fn ($q) => $q->whereIn('id', $user->siteLocationIds()))
                ->latest()->latest('id')->get()
        );
    }

    public function qrScans(Request $request)
    {
        return response()->json(
            $this->onSites(AssetScan::with(['user:id,name,role', 'asset:id,asset_code,name', 'location:id,name', 'previousLocation:id,name']), $request)
                ->latest()
                ->get()
                // The Reports page renders this report as two columns, `message`
                // and `created_at` — the shape it had when scans were notification
                // rows. The sentence carries who/what/where so that table needs no
                // change; the structured fields ride along for anything that wants them.
                ->each(fn (AssetScan $scan) => $scan->setAttribute('message', $scan->summary()))
        );
    }

    public function dataCompleteness(Request $request)
    {
        $assets = Asset::visibleTo($request->user())->with('category')
            ->where('status', '!=', 'disposed')
            ->where(function ($q) {
                $q->whereNull('purchase_price')
                    ->orWhereNull('purchase_date')
                    ->orWhereNull('serial_number');
            })
            ->latest()
            ->get()
            ->map(function ($asset) {
                $missing = [];
                if (is_null($asset->purchase_price)) {
                    $missing[] = 'Purchase Price';
                }
                if (is_null($asset->purchase_date)) {
                    $missing[] = 'Purchase Date';
                }
                if (blank($asset->serial_number)) {
                    $missing[] = 'Serial Number';
                }
                $asset->missing_fields = implode(', ', $missing);

                return $asset;
            });

        return response()->json($assets);
    }
}
