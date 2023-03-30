
// import Echo from 'laravel-echo';

// import Pusher from 'pusher-js';
// window.Pusher = Pusher;

// window.Echo = new Echo({
//     broadcaster: 'pusher',
//     key: import.meta.env.VITE_PUSHER_APP_KEY,
//     wsHost: import.meta.env.VITE_PUSHER_HOST ? import.meta.env.VITE_PUSHER_HOST : `ws-${import.meta.env.VITE_PUSHER_APP_CLUSTER}.pusher.com`,
//     wsPort: import.meta.env.VITE_PUSHER_PORT ?? 80,
//     wssPort: import.meta.env.VITE_PUSHER_PORT ?? 443,
//     forceTLS: (import.meta.env.VITE_PUSHER_SCHEME ?? 'https') === 'https',
//     enabledTransports: ['ws', 'wss'],
// });

Date.prototype.toLocaleWithoutToday = function (lang, attr)
{
    const d = new Date(this).setHours(0, 0, 0, 0);
    const t = new Date().setHours(0, 0, 0, 0);
    if (d === t)
    {
        return this.toLocaleTimeString(lang, attr);
    } else {
        return this.toLocaleString(lang, attr);
    }
}
