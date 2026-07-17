import Echo from 'laravel-echo';
import Pusher from 'pusher-js';

window.Pusher = Pusher;

const reverbKey = import.meta.env.VITE_REVERB_APP_KEY;

// Read the Bearer token the same way api.js does
function readToken() {
    const token = localStorage.getItem('auth_token');
    if (token) return token;
    try {
        return JSON.parse(localStorage.getItem('sportgo_auth') || 'null')?.token || null;
    } catch {
        return null;
    }
}

const echo = reverbKey ? new Echo({
    broadcaster: 'reverb',
    key: reverbKey,
    wsHost: import.meta.env.VITE_REVERB_HOST ?? 'localhost',
    wsPort: import.meta.env.VITE_REVERB_PORT ?? 8080,
    wssPort: import.meta.env.VITE_REVERB_PORT ?? 8080,
    forceTLS: (import.meta.env.VITE_REVERB_SCHEME ?? 'http') === 'https',
    enabledTransports: ['ws', 'wss'],
    authEndpoint: '/api/broadcasting/auth',
    auth: {
        headers: {
            Authorization: `Bearer ${readToken()}`,
            Accept: 'application/json',
        },
    },
}) : null;

export default echo;
