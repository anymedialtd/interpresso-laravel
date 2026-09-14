# API Reference

All protected endpoints require request body:

```json
{
  "api_key": "your-shared-secret"
}
```

Auth middleware:

- `AnyMedia\Interpresso\Middleware\AuthApi`

## Version

### `GET /api/version`

Returns package version.

## Protected Endpoints

Route group prefix: `/api`

### `POST /api/cancelJobs`

Cancels/deletes language batches/jobs and releases only those batches' leases. A separate cron/artisan lock remains held.

Response:

- `204 No Content`

### `POST /api/interpresso-has-jobs-running`

Checks live process leases as well as language jobs/batches. `process_running` is true when either is active. Metadata describes the recorded lease, including an expired lease for diagnostics; absent metadata is null. An expired or legacy lease alone reports false. `queue_running` preserves queue/batch status independently of lease expiry.

Response:

```json
{
  "process_running": true,
  "queue_running": false,
  "process_owner": "cron-host:123 interpresso:import-translations [invocation-id]",
  "process_started_at": "2026-09-14T10:00:00+00:00",
  "process_expires_at": "2026-09-14T10:15:00+00:00"
}
```

### `POST /api/interpresso-get-languages`

Returns all languages as resource collection.

Fields:

- `id`, `name`, `native_name`, `code`, `created_at`, `updated_at`

### `POST /api/interpresso-get-paginated-translations`

Returns translations paginated at 500 items/page.

Supports standard Laravel `?page=N` query parameter.

### `POST /api/interpresso-force-export`

Requires a deferring queue connection, acquires a local process lease, dispatches force-export jobs for all languages to the queue, and returns a start message. A worker executes the export outside the HTTP request. It returns HTTP 409 with `message` and `lock` (`owner`, `started_at`, `expires_at`) if busy. It checks local work only: the initiating peer may still hold its own export lease. Empty exports and failures release the lease. Peer API-key authentication is unchanged.

If the configured driver cannot defer work (`sync`, `null`, `deferred`, missing configuration, or unsafe failover), it refuses before any lease, batch or export writes and returns **HTTP 503**:

```json
{
  "message": "No queue worker is configured, so this would run inside the web request and be cut off by PHP's time limit. Run: php artisan interpresso:export-translations-deployment"
}
```

Run the command on the receiving host or configure an asynchronous connection and worker there. Peer exports require this on every receiving host.

Accepted response:

```json
{
  "message": "Export on https://example.com started."
}
```

## Security Notes

- Keep `INTERPRESSO_API_SHARED_SECRET` secret and rotate on compromise.
- Use HTTPS for all inter-host API traffic.
- Restrict endpoint access at network/firewall layer when possible.
