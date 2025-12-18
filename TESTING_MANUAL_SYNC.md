# Testing Manual Sync Feature

## Quick Test Guide

### 1. Start Queue Worker

Open a new terminal and run:

```bash
cd "d:\Kuliah\Magang\project\Internal Label\be-internal-label"
php artisan queue:work --verbose
```

Keep this terminal open to see the job processing in real-time.

### 2. Test via Frontend

1. Open the frontend application
2. Navigate to Label List page
3. Click "Sync Prod Label" button
4. You should see a toast message: "Sync job has been started! The process will run in the background (~5 minutes)."
5. Watch the queue worker terminal to see the job being processed

### 3. Test via API (Optional)

Using curl or Postman:

```bash
curl -X POST http://localhost:8000/api/sync/manual \
  -H "Authorization: Bearer YOUR_ACCESS_TOKEN" \
  -H "Content-Type: application/json" \
  -H "Accept: application/json"
```

Expected response:
```json
{
  "success": true,
  "message": "Sync job has been queued for period: 2512. The process will run in the background.",
  "prod_index": "2512"
}
```

### 4. Monitor Progress

#### Check Queue Worker Terminal
You should see output like:
```
[2024-12-18 07:30:00][1] Processing: App\Jobs\SyncProdData
[2024-12-18 07:30:00] Starting manual sync for prod_index: 2512
[2024-12-18 07:35:00] Manual sync completed for prod_index: 2512
[2024-12-18 07:35:00][1] Processed:  App\Jobs\SyncProdData
```

#### Check Database Logs
```sql
SELECT * FROM sync_logs 
WHERE sync_type IN ('prod_header', 'prod_label', 'manual_sync')
ORDER BY created_at DESC 
LIMIT 10;
```

#### Check Laravel Logs
```bash
tail -f storage/logs/laravel.log
```

### 5. Verify Data

After sync completes (~5 minutes):

```sql
-- Check prod_header records
SELECT COUNT(*) as total_headers FROM prod_headers;

-- Check prod_label records  
SELECT COUNT(*) as total_labels FROM prod_labels;

-- Check latest sync status
SELECT sync_type, records_synced, status, message, synced_at 
FROM sync_logs 
ORDER BY synced_at DESC 
LIMIT 5;
```

## Troubleshooting

### Queue Worker Not Processing Jobs

1. Check if queue connection is set to `database` in `.env`:
   ```
   QUEUE_CONNECTION=database
   ```

2. Make sure `jobs` table exists:
   ```bash
   php artisan migrate
   ```

3. Check if there are jobs in the queue:
   ```sql
   SELECT * FROM jobs;
   ```

### Job Failed

1. Check failed jobs:
   ```bash
   php artisan queue:failed
   ```

2. View failed job details:
   ```sql
   SELECT * FROM failed_jobs ORDER BY failed_at DESC;
   ```

3. Retry failed job:
   ```bash
   php artisan queue:retry <job-id>
   ```

### Timeout Issues

If the job still times out after 15 minutes, you can increase the timeout in `app/Jobs/SyncProdData.php`:

```php
public $timeout = 1800; // 30 minutes
```

## Production Deployment

For production, make sure to:

1. Set up Supervisor to keep queue worker running
2. Configure proper logging
3. Set up monitoring for failed jobs
4. Consider using Redis for better queue performance

See `QUEUE_SETUP.md` for detailed production setup instructions.
