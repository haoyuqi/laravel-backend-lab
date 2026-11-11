# Admin operation logs

The admin panel's **系统 → 操作日志** page records backend data changes and
successful backend login/logout in the same database transaction as the
mutation. Reads, searches, failed logins, unchanged saves, and direct CLI or job
writes are not recorded. Request bodies, cookies, credentials, and user emails
are excluded from snapshots.

Logs contain personal data (operator names and IPs) and are retained until
explicitly pruned. Preview the pruning first:

```bash
php artisan admin-operation-logs:prune --days=365
```

After reviewing the count and backing up history, add `--force` to delete old
logs in batches of 1,000. The minimum retention is 30 days.

## Invariants

- New Filament resource pages extend `App\Filament\Pages\CreateRecord`,
  `EditRecord`, or `ListRecords`.
- Writable business models declare a safe snapshot policy in
  `config/admin-operation-logs.php`; infrastructure models stay in `excluded`.
- Custom write services and bulk callbacks run inside
  `AdminOperationRecorder::run()` on the model's connection.
- Query-builder, pivot, and raw SQL writes use
  `AdminOperationRecorder::explicit()`.

See [architecture](../architecture.md#transactional-admin-operation-and-authentication-logs)
for the transaction and rollback behavior.
