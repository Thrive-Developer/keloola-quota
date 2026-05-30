<?php

namespace Keloola\Quota\Database\Seeders;

use Illuminate\Database\Seeder;
use Keloola\Quota\Models\AppPlanQuota;
use Keloola\Quota\Models\QuotaMetric;

/**
 * Seeds quota metrics and per-plan limits for the Keloola app suite.
 *
 * IMPORTANT: Adjust the $apps and $plans maps below to match the real
 * `id` values of your `apps` and `app_plans` records. The slugs here are
 * placeholders for readability.
 *
 * type:
 *   snapshot -> fixed/live count, never auto-reset (users, storage, hardware, ebooks)
 *   counter  -> accumulates and resets every billing month (transactions)
 */
class QuotaSeeder extends Seeder
{
    /**
     * Map app slug => app_id. Replace with your real IDs.
     */
    protected array $apps = [
        'accounting'    => 1,
        'cloud-storage' => 2,
        'pos'           => 3,
        'ebook'         => 4,
        'automate'      => 5,
    ];

    /**
     * Map "appSlug.planSlug" => app_plan_id. Replace with your real IDs.
     */
    protected array $plans = [
        // Accounting
        'accounting.basic'      => 101,
        'accounting.pro'        => 102,
        'accounting.enterprise' => 103,
        // Cloud Storage
        'cloud-storage.basic'      => 201,
        'cloud-storage.pro'        => 202,
        'cloud-storage.enterprise' => 203,
        // POS
        'pos.basic'      => 301,
        'pos.pro'        => 302,
        'pos.enterprise' => 303,
        // Ebook
        'ebook.basic'      => 401,
        'ebook.pro'        => 402,
        'ebook.enterprise' => 403,
        // Automate
        'automate.basic'      => 501,
        'automate.pro'        => 502,
        'automate.enterprise' => 503,
    ];

    public function run(): void
    {
        $this->seedAccounting();
        $this->seedCloudStorage();
        $this->seedPos();
        $this->seedEbook();
        $this->seedAutomate();
    }

    /* ----------------------------------------------------------------- */

    protected function seedAccounting(): void
    {
        $app = $this->apps['accounting'];

        $transactions = $this->metric($app, 'Jumlah Transaksi', 'transactions', 'counter', 'transactions/month');
        $users        = $this->metric($app, 'Jumlah User', 'users', 'snapshot', 'users');
        $invoices     = $this->metric($app, 'Jumlah Invoice', 'invoices', 'counter', 'invoices/month');
        $journals     = $this->metric($app, 'Jumlah Jurnal', 'journals', 'counter', 'journals/month');

        $this->assign('accounting.basic', [
            [$transactions, 500,   false],
            [$users,        3,     false],
            [$invoices,     100,   false],
            [$journals,     200,   false],
        ]);
        $this->assign('accounting.pro', [
            [$transactions, 5000,  false],
            [$users,        15,    false],
            [$invoices,     1000,  false],
            [$journals,     2000,  false],
        ]);
        $this->assign('accounting.enterprise', [
            [$transactions, 0, true],
            [$users,        0, true],
            [$invoices,     0, true],
            [$journals,     0, true],
        ]);
    }

    protected function seedCloudStorage(): void
    {
        $app = $this->apps['cloud-storage'];

        // Storage stored in MB for integer precision; unit shows GB.
        $storage = $this->metric($app, 'Storage Space', 'storage_mb', 'snapshot', 'MB');
        $users   = $this->metric($app, 'Jumlah User', 'users', 'snapshot', 'users');
        $files   = $this->metric($app, 'Jumlah File', 'files', 'snapshot', 'files');

        $this->assign('cloud-storage.basic', [
            [$storage, 5 * 1024,    false], // 5 GB
            [$users,   2,           false],
            [$files,   1000,        false],
        ]);
        $this->assign('cloud-storage.pro', [
            [$storage, 100 * 1024,  false], // 100 GB
            [$users,   10,          false],
            [$files,   50000,       false],
        ]);
        $this->assign('cloud-storage.enterprise', [
            [$storage, 1024 * 1024, false], // 1 TB
            [$users,   0,           true],
            [$files,   0,           true],
        ]);
    }

    protected function seedPos(): void
    {
        $app = $this->apps['pos'];

        $transactions = $this->metric($app, 'Jumlah Transaksi', 'transactions', 'counter', 'transactions/month');
        $outlets      = $this->metric($app, 'Jumlah Outlet', 'outlets', 'snapshot', 'outlets');
        $products     = $this->metric($app, 'Jumlah Produk', 'products', 'snapshot', 'products');

        $this->assign('pos.basic', [
            [$transactions, 1000,  false],
            [$outlets,      1,     false],
            [$products,     200,   false],
        ]);
        $this->assign('pos.pro', [
            [$transactions, 20000, false],
            [$outlets,      5,     false],
            [$products,     5000,  false],
        ]);
        $this->assign('pos.enterprise', [
            [$transactions, 0, true],
            [$outlets,      0, true],
            [$products,     0, true],
        ]);
    }

    protected function seedEbook(): void
    {
        $app = $this->apps['ebook'];

        $ebooks = $this->metric($app, 'Jumlah Ebook', 'ebooks', 'snapshot', 'ebooks');

        $this->assign('ebook.basic', [
            [$ebooks, 10,  false],
        ]);
        $this->assign('ebook.pro', [
            [$ebooks, 200, false],
        ]);
        $this->assign('ebook.enterprise', [
            [$ebooks, 0, true],
        ]);
    }

    protected function seedAutomate(): void
    {
        $app = $this->apps['automate'];

        $hardware = $this->metric($app, 'Jumlah Hardware', 'hardware', 'snapshot', 'devices');
        $users    = $this->metric($app, 'Jumlah User', 'users', 'snapshot', 'users');
        // Automation runs are typically metered monthly -> counter.
        $runs     = $this->metric($app, 'Jumlah Automation Run', 'automation_runs', 'counter', 'runs/month');

        $this->assign('automate.basic', [
            [$hardware, 2,     false],
            [$users,    2,     false],
            [$runs,     1000,  false],
        ]);
        $this->assign('automate.pro', [
            [$hardware, 10,    false],
            [$users,    10,    false],
            [$runs,     20000, false],
        ]);
        $this->assign('automate.enterprise', [
            [$hardware, 0, true],
            [$users,    0, true],
            [$runs,     0, true],
        ]);
    }

    /* ----------------------------------------------------------------- */

    protected function metric(int $appId, string $name, string $code, string $type, ?string $unit): QuotaMetric
    {
        return QuotaMetric::updateOrCreate(
            ['app_id' => $appId, 'code' => $code],
            ['name' => $name, 'type' => $type, 'unit' => $unit, 'is_active' => true]
        );
    }

    /**
     * @param array<int, array{0: QuotaMetric, 1: int, 2: bool}> $rows
     */
    protected function assign(string $planKey, array $rows): void
    {
        $planId = $this->plans[$planKey] ?? null;
        if (! $planId) {
            return;
        }

        foreach ($rows as [$metric, $limit, $unlimited]) {
            AppPlanQuota::updateOrCreate(
                ['app_plan_id' => $planId, 'quota_metric_id' => $metric->id],
                ['limit' => $limit, 'is_unlimited' => $unlimited]
            );
        }
    }
}
