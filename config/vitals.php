<?php

return [

    /*
     * The dashboard lives at this path, e.g. https://example.com/vitals.
     */
    'path' => env('VITALS_PATH', 'vitals'),

    'domain' => env('VITALS_DOMAIN'),

    /*
     * Middleware for the dashboard routes. Access is checked afterwards with Vitals::auth(), the
     * viewVitals gate, or (without either) the local environment only.
     */
    'middleware' => ['web'],

    /*
     * The central database connection for vitals_results and vitals_states. Null resolves at boot to
     * stancl's tenancy.database.central_connection when it exists, otherwise database.default.
     */
    'connection' => env('VITALS_DB_CONNECTION'),

    /*
     * Where the check jobs go. Give Vitals its own queue so a busy default queue does not delay it.
     */
    'queue' => env('VITALS_QUEUE'),

    'queue_connection' => env('VITALS_QUEUE_CONNECTION'),

    /*
     * The cache store for the dispatcher heartbeat. Null is the default store.
     */
    'cache_store' => env('VITALS_CACHE_STORE'),

    /*
     * Register vitals:dispatch (every minute, on one server) and vitals:prune (daily) in the scheduler.
     */
    'schedule' => true,

    'retention_days' => 14,

    /*
     * Default cron expression per scope, for checks without their own frequency.
     */
    'frequencies' => [
        'central' => '*/5 * * * *',
        'tenant' => '*/15 * * * *',
    ],

    'tenancy' => [
        /*
         * A NetOs\Vitals\Tenancy\TenancyAdapter class. Null detects stancl/tenancy and otherwise
         * runs without tenants.
         */
        'adapter' => null,

        'name_attribute' => 'name',

        /*
         * Connect to the tenant database right after initializing, so an unreachable database marks
         * every check of that tenant as crashed with "Tenant could not be initialized".
         */
        'verify_database_connection' => true,
    ],

    /*
     * An external monitor can read /vitals/status.json with the X-Vitals-Token header set to this value.
     */
    'status_token' => env('VITALS_STATUS_TOKEN'),

    'dashboard' => [
        /*
         * Shown above the overview headline. Null uses the application environment.
         */
        'environment_label' => env('VITALS_ENVIRONMENT_LABEL'),

        /*
         * Times on the dashboard are shown in this timezone. Null uses app.timezone.
         */
        'timezone' => env('VITALS_TIMEZONE'),

        'refresh_seconds' => 30,
    ],

];
