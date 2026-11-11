import Echo from 'laravel-echo';
import Pusher from 'pusher-js';

window.Pusher = Pusher;

// Rendered by the server so one build works with any deployment.
// The static time page also works when broadcasting is disabled.
const client = window.__REVERB_CLIENT__;

if (client?.key) {
    window.Echo = new Echo({
        broadcaster: 'reverb',
        key: client.key,
        wsHost: client.host,
        wsPort: client.port,
        wssPort: client.port,
        forceTLS: client.scheme === 'https',
        enabledTransports: ['ws', 'wss'],
    });
}
