<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Quota Metric Types
    |--------------------------------------------------------------------------
    |
    | snapshot : Fixed quota. The usage value reflects a current state
    |            (e.g. number of users, storage space used). It is NOT
    |            reset on a billing cycle — it goes up and down as records
    |            are created/deleted.
    |
    | counter  : Accumulating quota that is reset every billing period
    |            (e.g. number of transactions per month). Usage only
    |            increments and is zeroed when the period rolls over.
    |
    */
    'types' => [
        'snapshot' => 'snapshot',
        'counter'  => 'counter',
    ],

    /*
    |--------------------------------------------------------------------------
    | Database Tables
    |--------------------------------------------------------------------------
    |
    | Customize the table names used by the package if they collide with
    | existing tables in the host application.
    |
    */
    'tables' => [
        'quota_metrics'    => 'quota_metrics',
        'app_plan_quotas'  => 'app_plan_quotas',
        'quota_usages'     => 'quota_usages',
        'quota_usage_logs' => 'quota_usage_logs',
    ],

    /*
    |--------------------------------------------------------------------------
    | Models
    |--------------------------------------------------------------------------
    |
    | Override these with your own model classes if you need to extend the
    | default package models (e.g. to point app_id / organization_id to your
    | own App and Organization models).
    |
    */
    'models' => [
        'quota_metric'   => \Keloola\Quota\Models\QuotaMetric::class,
        'app_plan_quota' => \Keloola\Quota\Models\AppPlanQuota::class,
        'quota_usage'    => \Keloola\Quota\Models\QuotaUsage::class,
        'quota_usage_log'=> \Keloola\Quota\Models\QuotaUsageLog::class,
    ],

    /*
    |--------------------------------------------------------------------------
    | Foreign Key References
    |--------------------------------------------------------------------------
    |
    | The package does not own the apps / organizations / app_plans tables.
    | Define here how the package should reference them. Set 'constrained' to
    | false if those tables live in a different connection / are not present
    | during migration (common in multi-service / SSO setups).
    |
    */
    'references' => [
        'app' => [
            'table'  => 'apps',
            'column' => 'id',
            'type'   => 'unsignedBigInteger', // or 'uuid'
        ],
        'app_plan' => [
            'table'  => 'app_plans',
            'column' => 'id',
            'type'   => 'unsignedBigInteger',
        ],
        'organization' => [
            'table'  => 'organizations',
            'column' => 'id',
            'type'   => 'uuid',
        ],
        // Whether to add real DB foreign key constraints. In an SSO /
        // distributed setup the referenced tables may not exist locally.
        'constrained' => false,
    ],

    /*
    |--------------------------------------------------------------------------
    | Period Resolution
    |--------------------------------------------------------------------------
    |
    | How a "counter" period key is generated. Counters reset when this key
    | changes. Default is monthly: Y-m (e.g. "2026-05").
    |
    */
    'period' => [
        'format' => 'Y-m',
    ],

    /*
    |--------------------------------------------------------------------------
    | Enforcement
    |--------------------------------------------------------------------------
    |
    | When strict is true, incrementing usage beyond the plan limit throws a
    | QuotaExceededException. When false, it allows overage but flags it.
    |
    */
    'strict' => true,

    /*
    |--------------------------------------------------------------------------
    | Provisioning (SSO push)
    |--------------------------------------------------------------------------
    |
    | The SSO/billing service pushes plan quota definitions to this app on
    | install and whenever a plan changes. These routes receive that push.
    |
    | route_prefix : URL prefix for the provisioning endpoints.
    | middleware   : middleware stack applied to the provisioning routes.
    | register_routes : set false to define the routes yourself instead.
    |
    */
    'provisioning' => [
        'route_prefix'    => 'api/quota',
        'app_id'          => env('KELOOLA_AUTH_APP_ID', null),
        'middleware'      => ['keloola.quota.context'],
        'register_routes' => true,
    ],

    /*
    |--------------------------------------------------------------------------
    | SSO Client
    |--------------------------------------------------------------------------
    |
    | Configuration for connecting to the SSO service to decode JWT tokens
    | and fetch user profile data.
    |
    */
    'sso' => [
        'base_url'           => env('KELOOLA_AUTH_SSO_HOST', 'http://localhost:8000'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Storage Quota
    |--------------------------------------------------------------------------
    |
    | Configuration for cross-app storage quota checking using QuotaCheckService.
    |
    */
    'storage' => [
        'api_url'     => env('KELOOLA_FILE_API_URL', 'https://file.keloola.in'),
        'metric_code' => env('KELOOLA_FILE_METRIC_CODE', 'storage_space'),
    ],
];
