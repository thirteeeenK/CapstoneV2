{{--
    Loads the Cloudflare Turnstile SDK once per page and lets any number of
    widgets subscribe to it.

    The SDK honours a single `?onload=` callback, so a page carrying more than
    one widget cannot give each its own script tag — /register holds both the
    registration challenge and the Google sign-in challenge. This shim owns the
    one load and fans out to every subscriber.

    Surface:
      sunnyTurnstile.preload()        fetch the SDK now, render nothing (for a
                                     widget whose container is still hidden)
      sunnyTurnstile.ready(cb)        cb(turnstile) once the SDK is available
      sunnyTurnstile.retry(cb)        clear a previous load failure, then ready(cb)
      sunnyTurnstile.onError(cb)      cb() when the SDK cannot be loaded
--}}
@once
    <script>
        window.sunnyTurnstile = (function () {
            const src = 'https://challenges.cloudflare.com/turnstile/v0/api.js?onload=__sunnyTurnstileReady&render=explicit';
            const errorHandlers = [];
            let state = 'idle'; // idle | loading | loaded | failed
            let queue = [];

            window.__sunnyTurnstileReady = function () {
                state = 'loaded';
                const pending = queue;
                queue = [];
                pending.forEach(callback => callback(window.turnstile));
            };

            function fail() {
                state = 'failed';
                queue = [];
                errorHandlers.forEach(handler => handler());
            }

            function insert() {
                if (state === 'loading' || state === 'loaded') return;
                state = 'loading';
                const tag = document.createElement('script');
                tag.src = src;
                tag.async = true;
                tag.onerror = () => {
                    tag.remove();
                    fail();
                };
                document.head.appendChild(tag);
            }

            return {
                preload() {
                    if (window.turnstile) {
                        state = 'loaded';
                        return;
                    }
                    insert();
                },
                ready(callback) {
                    if (window.turnstile) {
                        state = 'loaded';
                        callback(window.turnstile);
                        return;
                    }
                    queue.push(callback);
                    insert();
                },
                retry(callback) {
                    if (state === 'failed') {
                        state = 'idle';
                    }
                    this.ready(callback);
                },
                onError(callback) {
                    errorHandlers.push(callback);
                },
            };
        })();
    </script>
@endonce
