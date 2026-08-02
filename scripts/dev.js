/**
 * Dev server wrapper — runs artisan serve, queue listener, and Vite
 * concurrently while suppressing the EPIPE crash on Windows shutdown.
 */
const { concurrently } = require('concurrently');

// Swallow the EPIPE / errored-stream crash that concurrently throws on Ctrl+C (Windows)
process.on('uncaughtException', (err) => {
    if (err?.cause?.code === 'EPIPE' || err?.message?.includes('errored state')) {
        process.exit(0);
    }
    console.error(err);
    process.exit(1);
});

process.on('SIGINT', () => process.exit(0));
process.on('SIGTERM', () => process.exit(0));

const { result } = concurrently(
    [
        { command: 'php artisan serve --host=localhost', name: 'server' },
        { command: 'php artisan queue:listen --tries=1 --timeout=0', name: 'queue' },
        { command: 'npm run dev', name: 'vite' },
    ],
    {
        killOthers: ['failure', 'success'],
        prefixColors: ['#93c5fd', '#c4b5fd', '#fb7185'],
    }
);

result.catch(() => process.exit(0));
