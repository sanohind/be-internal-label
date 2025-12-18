# Queue Worker Setup

## Overview
The manual sync feature uses Laravel Queue Jobs to handle long-running sync operations in the background. This prevents timeout issues when syncing data from ERP.

## How It Works

1. User clicks "Sync Manual" button in the frontend
2. API endpoint `/api/sync/manual` receives the request
3. A `SyncProdData` job is dispatched to the queue
4. API returns immediately with success message
5. Queue worker processes the job in background (~5 minutes)
6. Sync logs are created to track the operation

## Running the Queue Worker

### Development
For development, you can run the queue worker using:

```bash
php artisan queue:work
```

Or with verbose output:

```bash
php artisan queue:work --verbose
```

### Production
For production, it's recommended to use a process manager like **Supervisor** to keep the queue worker running.

#### Supervisor Configuration Example

Create a file `/etc/supervisor/conf.d/laravel-worker.conf`:

```ini
[program:laravel-worker]
process_name=%(program_name)s_%(process_num)02d
command=php /path/to/your/project/artisan queue:work --sleep=3 --tries=1 --max-time=3600
autostart=true
autorestart=true
stopasgroup=true
killasgroup=true
user=www-data
numprocs=1
redirect_stderr=true
stdout_logfile=/path/to/your/project/storage/logs/worker.log
stopwaitsecs=3600
```

Then reload supervisor:

```bash
sudo supervisorctl reread
sudo supervisorctl update
sudo supervisorctl start laravel-worker:*
```

## Queue Configuration

The queue configuration is in `config/queue.php`. By default, Laravel uses the `sync` driver for development (executes immediately) and `database` driver for production.

To use database queue:

1. Make sure you have the `jobs` table:
   ```bash
   php artisan queue:table
   php artisan migrate
   ```

2. Update `.env`:
   ```
   QUEUE_CONNECTION=database
   ```

## Monitoring Queue Jobs

### View Failed Jobs
```bash
php artisan queue:failed
```

### Retry Failed Jobs
```bash
php artisan queue:retry all
```

### Clear Failed Jobs
```bash
php artisan queue:flush
```

## Job Details

- **Job Class**: `App\Jobs\SyncProdData`
- **Timeout**: 900 seconds (15 minutes)
- **Tries**: 1 (won't retry on failure)
- **Queue**: default

## Logging

All sync operations are logged in:
- `sync_logs` table in the database
- Laravel logs: `storage/logs/laravel.log`

## Testing Manual Sync

1. Start the queue worker:
   ```bash
   php artisan queue:work --verbose
   ```

2. In another terminal, trigger the sync via API:
   ```bash
   curl -X POST http://localhost:8000/api/sync/manual \
     -H "Authorization: Bearer YOUR_TOKEN" \
     -H "Content-Type: application/json"
   ```

3. Watch the queue worker terminal for processing logs

4. Check `sync_logs` table for results:
   ```sql
   SELECT * FROM sync_logs ORDER BY created_at DESC LIMIT 10;
   ```
