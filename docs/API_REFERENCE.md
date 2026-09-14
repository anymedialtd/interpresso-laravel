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

Acquires a local process lease, dispatches force-export jobs for all languages after the response, and returns a start message. It returns HTTP 409 with `message` and `lock` (`owner`, `started_at`, `expires_at`) if busy. It checks local work only: the initiating peer may still hold its own export lease. Empty exports and failures release the lease. Peer API-key authentication is unchanged.

Response:

```json
{
  "message": "Export on https://example.com started."
}
```

## Security Notes

- Keep `INTERPRESSO_API_SHARED_SECRET` secret and rotate on compromise.
- Use HTTPS for all inter-host API traffic.
- Restrict endpoint access at network/firewall layer when possible.
