# Real-time updates

The `/time` page uses [Laravel Reverb](https://laravel.com/docs/13.x/reverb) and
Laravel Echo. Without a WebSocket server the page still renders its initial
value; live updates are optional.

`PushTimeEvent` implements `ShouldBroadcast` and is dispatched every minute on
the `push-time` queue. With an asynchronous queue connection, a worker must
consume `push-time` or no rows arrive.

## Local setup

Generate local Reverb credentials:

```bash
php artisan reverb:install
```

Set the server-side values:

```dotenv
BROADCAST_CONNECTION=reverb
REVERB_APP_ID=your-app-id
REVERB_APP_KEY=your-public-key
REVERB_APP_SECRET=your-secret
REVERB_HOST=localhost
REVERB_PORT=8080
REVERB_SCHEME=http
REVERB_SCALING_ENABLED=false
```

Run the processes in separate terminals:

```bash
php artisan reverb:start
php artisan schedule:work
# Only needed with an asynchronous QUEUE_CONNECTION:
php artisan queue:work --queue=push-time
```

Open `/time` and wait for the next minute.

## Server and browser endpoints

`REVERB_*` tells the application where to publish events. The browser endpoint
is rendered into the page at request time from:

```dotenv
REVERB_CLIENT_HOST=localhost
REVERB_CLIENT_PORT=8080
REVERB_CLIENT_SCHEME=http
```

`REVERB_CLIENT_*` defaults to the matching `REVERB_*` values. Set
`REVERB_CLIENT_HOST` when the browser reaches Reverb through a different host,
such as a container service name or a reverse proxy. Only the public key, host,
port, and scheme are rendered; `REVERB_APP_SECRET` never reaches the browser.

Changing these values does not require rebuilding frontend assets.

## Containers

- `REVERB_HOST` and `REVERB_PORT` are the internal address the application
  server uses to publish events.
- `REVERB_CLIENT_HOST` and `REVERB_CLIENT_PORT` are the address the browser
  uses. They often differ from the internal values.
- Expose the Reverb port and forward WebSocket upgrade headers when terminating
  TLS at a proxy.
- Keep `REVERB_SCALING_ENABLED=false` for a single Reverb instance. Scaling
  requires Redis and shared configuration.
- `config/reverb.php` currently allows all origins. Restrict `allowed_origins`
  for public deployments.

The container image starts Reverb with:

```bash
php artisan reverb:start --host=0.0.0.0 --port=8080
```

Reverb serves its own `/up` health endpoint.

## Verification

`npm run test:reverb` starts isolated application and Reverb processes on ports
8000 and 8080, builds test assets, and verifies real WebSocket delivery and
resubscription in Chromium. Stop other processes using those ports first. It
uses an in-memory database and ephemeral credentials; rebuild normal assets
afterwards with `npm run build`.

See [testing](testing.md) for the rest of the suite.
