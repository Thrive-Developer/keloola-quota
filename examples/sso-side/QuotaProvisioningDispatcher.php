<?php

/*
|--------------------------------------------------------------------------
| CONTOH KODE SISI SSO (keloola-sso) — BUKAN bagian dari package.
|--------------------------------------------------------------------------
|
| Letakkan kelas ini di project keloola-sso. Tugasnya: mengirim definisi
| quota sebuah plan ke app satelit saat organization meng-install app,
| atau saat plan/quota berubah. Endpoint app & secret HMAC harus sama
| dengan konfigurasi 'provisioning' di package quota pada app tersebut.
|
*/

namespace App\Services;

use App\Models\App as AppModel;
use App\Models\AppPlan;
use Illuminate\Support\Facades\Http;

class QuotaProvisioningDispatcher
{
    /**
     * Kirim definisi quota sebuah plan ke app yang bersangkutan.
     *
     * Dipanggil saat:
     *  - Organization meng-install app (provisioning awal)
     *  - Admin mengubah limit quota / membuat plan baru (re-sync)
     */
    public function pushPlan(AppPlan $plan): void
    {
        /** @var AppModel $app */
        $app = $plan->app; // asumsikan relasi AppPlan->app ada

        // Bangun payload dari app_plan_quotas + quota_metrics di SSO.
        $metrics = $plan->quotas()  // relasi ke AppPlanQuota
            ->with('metric')        // relasi ke QuotaMetric
            ->get()
            ->map(fn ($q) => [
                'name'         => $q->metric->name,
                'code'         => $q->metric->code,
                'type'         => $q->metric->type,   // snapshot | counter
                'unit'         => $q->metric->unit,
                'limit'        => $q->limit,
                'is_unlimited' => (bool) $q->is_unlimited,
                'is_active'    => (bool) $q->metric->is_active,
            ])
            ->values()
            ->all();

        $payload = [
            'app_id'      => $app->id,
            'app_plan_id' => $plan->id,
            'metrics'     => $metrics,
        ];

        $this->send($app, 'POST', '/api/quota/provision', $payload);
    }

    /**
     * Hapus definisi quota plan di app (uninstall / plan dihapus).
     */
    public function removePlan(AppModel $app, int|string $planId): void
    {
        $this->send($app, 'DELETE', "/api/quota/provision/{$planId}", []);
    }

    /**
     * Kirim request bertanda tangan HMAC ke app.
     * $app->base_url dan $app->quota_secret diasumsikan tersimpan di SSO
     * (mis. saat app didaftarkan ke katalog SSO).
     */
    protected function send(AppModel $app, string $method, string $path, array $payload): void
    {
        $body   = json_encode($payload, JSON_UNESCAPED_SLASHES);
        $sig    = 'sha256=' . hash_hmac('sha256', $body, $app->quota_secret);
        $url    = rtrim($app->base_url, '/') . $path;

        Http::withHeaders([
                'Content-Type'      => 'application/json',
                'Accept'            => 'application/json',
                'X-Quota-Signature' => $sig,
            ])
            ->withBody($body, 'application/json')
            ->send($method, $url)
            ->throw();
    }
}

/*
|--------------------------------------------------------------------------
| Contoh pemicu (trigger) di SSO
|--------------------------------------------------------------------------
|
| // Saat organization install app:
| app(QuotaProvisioningDispatcher::class)->pushPlan($appPlan);
|
| // Saat limit quota diubah lewat observer AppPlanQuota:
| class AppPlanQuotaObserver
| {
|     public function saved(AppPlanQuota $quota): void
|     {
|         app(QuotaProvisioningDispatcher::class)->pushPlan($quota->appPlan);
|     }
| }
|
| Sebaiknya bungkus pemanggilan ini dalam queued job agar tidak memblok
| request bila app satelit sedang lambat/down.
|
*/
