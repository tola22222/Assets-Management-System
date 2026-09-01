<?php

namespace Database\Seeders;

use App\Models\ActivityLog;
use App\Models\Asset;
use App\Models\AssetAssignment;
use App\Models\AssetCategory;
use App\Models\AssetDisposal;
use App\Models\AssetReturn;
use App\Models\AssetTransfer;
use App\Models\AssetVerification;
use App\Models\Location;
use App\Models\Notification;
use App\Models\NotificationLog;
use App\Models\Program;
use App\Models\Staff;
use App\Models\StockItem;
use App\Models\StockTransaction;
use App\Models\Supplier;
use App\Models\User;
use Database\Seeders\Uat\UatAssetSeeder;
use Database\Seeders\Uat\UatNotificationSeeder;
use Database\Seeders\Uat\UatReferenceSeeder;
use Database\Seeders\Uat\UatStockSeeder;
use Database\Seeders\Uat\UatUserSeeder;
use Database\Seeders\Uat\UatWorkflowSeeder;
use Illuminate\Database\Seeder;

/**
 * Full user-testing dataset.
 *
 *   php artisan db:seed --class=UatSeeder
 *   UAT_QR=0 php artisan db:seed --class=UatSeeder     # skip QR PNG generation
 *
 * Separate from DatabaseSeeder on purpose — that one runs on every production
 * deploy and must stay minimal.
 *
 * Idempotent: every write is keyed on a natural key (email, asset_code,
 * stock_code, name), so re-running updates in place rather than duplicating.
 * Passwords are set only on first creation, and asset condition/status are
 * stamped only on first creation, so a re-run never undoes a tester's work.
 *
 * The fixed-asset register comes from `.claude/commands/PEPY_Asset_Inventory_Cleaned.md`,
 * parsed at seed time by Database\Seeders\Support\PepyInventory. No asset in
 * this dataset is invented.
 */
class UatSeeder extends Seeder
{
    public function run(): void
    {
        $withQr = env('UAT_QR', '1') !== '0';

        $this->command?->newLine();
        $this->command?->info('PEPY UAT dataset');
        $this->command?->line('  Register source: .claude/commands/PEPY_Asset_Inventory_Cleaned.md');
        $this->command?->line('  QR generation:   '.($withQr ? 'on (UAT_QR=0 to skip)' : 'off'));
        $this->command?->newLine();

        $this->command?->line('  → reference data (sites, categories, programs, suppliers, settings)');
        $this->call(UatReferenceSeeder::class);

        $this->command?->line('  → built-in roles and permissions');
        $this->call(SystemRoleSeeder::class);

        $this->command?->line('  → users and staff');
        $this->call(UatUserSeeder::class);

        $this->command?->line('  → asset register');
        $assetSeeder = new UatAssetSeeder($withQr);
        $assetSeeder->setContainer(app())->setCommand($this->command)->run();

        $this->command?->line('  → assignments, returns, transfers, counts, disposals');
        $workflowSeeder = new UatWorkflowSeeder;
        $workflowSeeder->setContainer(app())->setCommand($this->command)->run();

        $this->command?->line('  → stock and consumables');
        $stockSeeder = new UatStockSeeder;
        $stockSeeder->setContainer(app())->setCommand($this->command)->run();

        $this->command?->line('  → notifications, delivery log, activity log');
        $notificationSeeder = new UatNotificationSeeder;
        $notificationSeeder->setContainer(app())->setCommand($this->command)->run();

        $this->report($assetSeeder, $workflowSeeder, $stockSeeder, $notificationSeeder);
    }

    private function report(
        UatAssetSeeder $assets,
        UatWorkflowSeeder $workflow,
        UatStockSeeder $stock,
        UatNotificationSeeder $notifications
    ): void {
        $c = $this->command;
        if (! $c) {
            return;
        }

        $c->newLine();
        $c->info('── Accounts ───────────────────────────────────────────');
        $c->table(
            ['Email', 'Role', 'Site', 'State'],
            User::orderBy('role')->orderBy('email')->get()->map(fn (User $u) => [
                $u->email,
                $u->role,
                $u->staff?->location?->name ?? '—',
                $u->is_locked ? 'LOCKED' : ($u->is_active ? 'active' : 'INACTIVE'),
            ])->all()
        );
        $c->line('  Password for every pepy.test account: '.UatUserSeeder::PASSWORD);

        $c->newLine();
        $c->info('── Assets by category ─────────────────────────────────');
        $c->table(
            ['Category', 'Code', 'Assets'],
            AssetCategory::orderBy('short_name')->get()->map(fn ($cat) => [
                $cat->name,
                $cat->short_name,
                Asset::where('category_id', $cat->id)->count(),
            ])->all()
        );

        $c->newLine();
        $c->info('── Row counts ─────────────────────────────────────────');
        $c->table(['Table', 'Rows'], [
            ['users', User::count()],
            ['staff', Staff::count()],
            ['locations', Location::count()],
            ['asset_categories', AssetCategory::count()],
            ['programs', Program::count()],
            ['suppliers', Supplier::count()],
            ['assets', Asset::count()],
            ['asset_assignments', AssetAssignment::count()],
            ['asset_returns', AssetReturn::count()],
            ['asset_transfers', AssetTransfer::count()],
            ['asset_verifications', AssetVerification::count()],
            ['asset_disposals', AssetDisposal::count()],
            ['stock_items', StockItem::count()],
            ['stock_transactions', StockTransaction::count()],
            ['notifications', Notification::count()],
            ['notification_logs', NotificationLog::count()],
            ['activity_logs', ActivityLog::count()],
        ]);

        $c->newLine();
        $c->info('── Register import ────────────────────────────────────');
        $c->line('  itemised assets created ....... '.$assets->stats['detail']);
        $c->line('  range summaries created ....... '.$assets->stats['range'].'  (not expanded, per the source)');
        $c->line('  existing rows updated ......... '.$assets->stats['updated']);
        $c->line('  range groups not importable ... '.$assets->stats['skipped_no_id'].'  (source gives no asset ID)');
        $c->line('  QR codes generated ............ '.$assets->stats['qr_generated'].($assets->stats['qr_failed'] ? '  (failed: '.$assets->stats['qr_failed'].')' : ''));

        $c->newLine();
        $c->info('── Documented gaps preserved ──────────────────────────');
        $c->line('  assets with no purchase price . '.Asset::whereNull('purchase_price')->count());
        $c->line('  assets with no purchase date .. '.Asset::whereNull('purchase_date')->count());
        $c->line('  assets with no serial number .. '.Asset::whereNull('serial_number')->orWhere('serial_number', '')->count());
        $c->line('  assets with no location ....... '.Asset::whereNull('location_id')->count());

        $c->newLine();
        $c->info('── Register state after workflows ─────────────────────');
        foreach (['good', 'fair', 'broken', 'lost'] as $condition) {
            $c->line('  condition '.str_pad($condition, 8).' ........... '.Asset::where('condition', $condition)->count());
        }
        $c->line('  status    disposed ............ '.Asset::where('status', 'disposed')->count());
        $c->line('  assets changed by a workflow .. '.$workflow->stats['assets_touched']);

        $c->newLine();
        $c->info('── Counts (per the manual: February & August) ─────────');
        foreach (['2026-02-03' => 'February 2026', '2026-08-05' => 'August 2026'] as $date => $label) {
            $c->line('  '.str_pad($label, 16).' '.AssetVerification::whereDate('verified_at', $date)->count().' records');
        }

        $c->newLine();
        $c->info('── Stock ──────────────────────────────────────────────');
        $items = StockItem::all();
        foreach (['low', 'normal', 'high'] as $status) {
            $c->line('  '.str_pad($status, 8).' ..................... '.$items->where('status', $status)->count());
        }
        $c->line('  of which zero balance ......... '.$items->where('balance', '<=', 0)->count());
        $c->line('  items with no history ......... '.$items->filter(fn ($i) => $i->transactions()->doesntExist())->count().'  (deletable)');

        // Assets already in the database that this seeder did not import from
        // the register source. They are not deleted — that is the operator's
        // call — but they skew every count, report and chart, so they are
        // named on every run. Matched on asset_code against what was actually
        // imported, so it stays accurate regardless of what any description says.
        $foreign = Asset::whereNotIn('asset_code', $assets->importedCodes)->get(['asset_code', 'name']);

        if ($foreign->isNotEmpty()) {
            $c->newLine();
            $c->warn('── Assets NOT from the register source ('.$foreign->count().') ─────────');
            $c->line('  These pre-date this seeder and are not backed by PEPY_Asset_Inventory_Cleaned.md.');
            $c->line('  They inflate every register count, report and chart. Remove them for a clean UAT run:');
            foreach ($foreign as $a) {
                $c->line('  • '.$a->asset_code.'  '.$a->name);
            }
        }

        $allNotes = array_merge($assets->notes, $workflow->notes);

        if ($allNotes !== []) {
            $c->newLine();
            $c->warn('── Notes ('.count($allNotes).') ───────────────────────────────────');
            foreach ($allNotes as $note) {
                $c->line('  • '.$note);
            }
        }

        $c->newLine();
        $c->info('Done. Asset data came from PEPY_Asset_Inventory_Cleaned.md; nothing was invented.');
        $c->newLine();
    }
}
