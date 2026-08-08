/*
 * ThinkingOrb — dotted "thinking orb" loading indicator for AI UIs.
 * Adapted from the MIT-licensed "thinking-orbs" package by
 * Jakub Antalik & Alex Brinza (https://orbs.jakubantalik.com).
 * Geometry and timing constants ported from the original canvas source.
 */

import Alpine from 'alpinejs';

const YB = {
    working: { orbitN: 12, ghostN: 40, ghostR: 0.9, ghostA: 0.5, particles: 3, partR: 1.2, partRDepth: 1.6, rsPow: 0.6, rMin: 0.3 },
    searching: { latRings: 17, lonDensity: 44, rBase: 0.6, rDepth: 1.7, rBoost: 1, inkFar: 0.62, inkSpan: 0.54, rsPow: 0.6, rMin: 0.3 },
    composing: { lanes: 5, segs: 88, ghostN: 150, rBase: 1.1, rDepth: 1.7, rsPow: 0.6, rMin: 0.3 },
};

const WP = {
    working: { 64: { speed: 1.885, count: 1, size: 1 }, 20: { speed: 3.9, count: 0.238, size: 2.4 } },
    searching: {
        64: { speed: 2.015, count: 0.42, size: 1.15, extra: { scanMul: 4.08, dimBase: 0.45 } },
        20: { speed: 2.665, count: 0.105, size: 1.75, extra: { scanMul: 4.335, dimBase: 0.45 } },
    },
    composing: {
        64: { speed: 2.34, count: 0.25, size: 0.85, extra: { spin: 0, bandMul: 3.9, wobMul: 1 } },
        20: { speed: 3.12, count: 0.051, size: 1.073, extra: { spin: 0, bandMul: 4.94, wobMul: 1 } },
    },
};

const SIZE_FIELDS = ['rBase', 'rDepth', 'rActive', 'rDot', 'ghostR', 'partR', 'partRDepth', 'nodeR', 'nodeRDepth'];
const PAIR_FIELDS = [['latRings', 'lonDensity'], ['rings', 'lonDensity'], ['lanes', 'segs']];
const COUNT_FIELDS = ['orbitN', 'ghostN', 'nodeN', 'strandN', 'signals'];

const lerp = (a, b, t) => a + (b - a) * t;
const clamp = (v, lo, hi) => Math.min(hi, Math.max(lo, v));
const hash = (x, y) => { const v = Math.sin(x * 12.9898 + y * 78.233) * 43758.5453; return v - Math.floor(v); };
const dotScale = (size, p) => Math.pow(size / 300, p);
const angleDiff = (a, b) => Math.atan2(Math.sin(a - b), Math.cos(a - b));

function applyCount(o, t) {
    const n = { ...o };
    const done = new Set();
    const sq = Math.sqrt(t);
    for (const [x, y] of PAIR_FIELDS) {
        if (n[x] != null && n[y] != null && !done.has(x) && !done.has(y)) {
            n[x] = Math.max(2, Math.round(n[x] * sq));
            n[y] = Math.max(2, Math.round(n[y] * sq));
            done.add(x);
            done.add(y);
        }
    }
    for (const k of COUNT_FIELDS) {
        if (n[k] != null && n[k] !== 0 && !done.has(k)) n[k] = Math.max(1, Math.round(n[k] * t));
    }
    return n;
}

function applySize(o, t) {
    const n = { ...o };
    for (const k of SIZE_FIELDS) if (n[k] != null) n[k] *= t;
    n.rSizeMul = (n.rSizeMul ?? 1) * t;
    return n;
}

function resolveConfig(state, size) {
    const a = WP[state][20];
    const b = WP[state][64];
    const t = clamp((size - 20) / 44, 0, 1);
    let opts = { ...YB[state] };
    opts = applyCount(opts, lerp(a.count, b.count, t));
    opts = applySize(opts, lerp(a.size, b.size, t));
    const extra = {};
    for (const k in (b.extra ?? {})) {
        const av = a.extra?.[k] ?? b.extra[k];
        extra[k] = lerp(av, b.extra[k], t);
    }
    opts = { ...opts, ...extra };
    opts.speed = lerp(a.speed, b.speed, t);
    return opts;
}

function makeRotator(spin, tilt, cx, cy, radius) {
    const sn = Math.sin(tilt), cs = Math.cos(tilt), si = Math.sin(spin), ci = Math.cos(spin);
    return (x, y, z) => {
        const p = x * ci + z * si;
        const g = -x * si + z * ci;
        const yy = y * cs - g * sn;
        const zz = y * sn + g * cs;
        return [cx + p * radius, cy - yy * radius, zz];
    };
}

function paint(ctx, dots, dark, rMin) {
    dots.sort((a, b) => a.z - b.z);
    for (const d of dots) {
        const o = d.a ?? 1;
        if (o < 0.02) continue;
        const u = clamp(d.white ?? 1, 0, 1);
        const i = Math.round((dark ? 1 - u : u) * 255);
        ctx.fillStyle = `rgba(${i},${i},${i},${o})`;
        ctx.beginPath();
        ctx.arc(d.x, d.y, Math.max(rMin, d.r), 0, Math.PI * 2);
        ctx.fill();
    }
}

function drawOrbits(ctx, size, time, dark, o) {
    const cx = size / 2, cy = size / 2;
    const R = size / 2 * 0.82;
    const rot = makeRotator(time * 0.12, 0.3, cx, cy, 1);
    const c = dotScale(size, o.rsPow);
    const dots = [];
    for (let y = 0; y < o.orbitN; y++) {
        const k = hash(y, 1.7), z = hash(y, 5.2), d = hash(y, 8.9);
        const a = R * (0.45 + 0.52 * k);
        const f = k * 2 * Math.PI, v = Math.acos(2 * z - 1);
        const w = Math.sin(v) * Math.cos(f), S = Math.cos(v), x = Math.sin(v) * Math.sin(f);
        let E = -S, N = w;
        const C = 0;
        const m = Math.max(1e-6, Math.sqrt(E * E + N * N));
        E /= m; N /= m;
        const j = S * C - x * N, U = x * E - w * C, A = w * N - S * E;
        const Be = (0.25 + 0.55 * d) * (d > 0.5 ? 1 : -1);
        for (let oe = 0; oe < o.ghostN; oe++) {
            const H = oe / o.ghostN * 2 * Math.PI;
            const [px, py, pz] = rot(
                (E * Math.cos(H) + j * Math.sin(H)) * a,
                (N * Math.cos(H) + U * Math.sin(H)) * a,
                (C * Math.cos(H) + A * Math.sin(H)) * a
            );
            const I = (pz / a + 1) / 2;
            dots.push({ x: px, y: py, z: pz, r: o.ghostR * c, white: 0.72, a: o.ghostA * (0.4 + 0.6 * I) });
        }
        for (let oe = 0; oe < o.particles; oe++) {
            const H = time * Be + oe / o.particles * 2 * Math.PI + z * 6;
            const [px, py, pz] = rot(
                (E * Math.cos(H) + j * Math.sin(H)) * a,
                (N * Math.cos(H) + U * Math.sin(H)) * a,
                (C * Math.cos(H) + A * Math.sin(H)) * a
            );
            const I = (pz / a + 1) / 2;
            dots.push({ x: px, y: py, z: pz, r: (o.partR + o.partRDepth * I) * c, white: 0.3 - 0.22 * I, a: 1 });
        }
    }
    paint(ctx, dots, dark, o.rMin);
}

function drawGlobe(ctx, size, time, dark, o) {
    const cx = size / 2, cy = size / 2;
    const wob = 0.4 + 0.06 * Math.sin(time * 0.35);
    const rot = makeRotator(time * 0.5, wob, cx, cy, size / 2);
    const scan = time * (0.5 + (1.7 - 0.5) * (o.scanMul ?? 1));
    const p = dotScale(size, o.rsPow);
    const g = o.dimBase ?? 1;
    const dots = [];
    for (let d = 0; d <= o.latRings; d++) {
        const lat = -Math.PI / 2 + d / o.latRings * Math.PI;
        const f = Math.cos(lat), v = Math.sin(lat);
        const w = Math.max(1, Math.round(Math.abs(f) * o.lonDensity));
        for (let S = 0; S < w; S++) {
            const x = S / w * 2 * Math.PI;
            const [E, N, C] = rot(f * Math.cos(x), v, f * Math.sin(x));
            const Rz = (C + 1) / 2;
            const j = angleDiff(x + time * 0.5, scan);
            const U = Math.exp(-(j * j) / 0.18) * Math.max(0, C);
            dots.push({
                x: E, y: N, z: C,
                r: (o.rBase + o.rDepth * Rz + o.rBoost * U) * p,
                white: o.inkFar - o.inkSpan * Rz,
                a: g + (1 - g) * Math.min(1, U),
            });
        }
    }
    paint(ctx, dots, dark, o.rMin);
}

function drawRibbon(ctx, size, time, dark, o) {
    const cx = size / 2, cy = size / 2;
    const R = size / 2 * 0.78;
    const spin = o.spin ?? 1;
    const tilt = 0.3;
    const rot = makeRotator(time * 0.1 * spin, tilt, cx, cy, 1);
    const c = dotScale(size, o.rsPow);
    const dots = [];
    const ghostN = o.ghostN ?? 0;
    for (let i = 0; i < ghostN; i++) {
        const u1 = hash(i, 1.7), u2 = hash(i, 3.3);
        const zz = 2 * u1 - 1;
        const rr = Math.sqrt(Math.max(0, 1 - zz * zz));
        const th = u2 * 2 * Math.PI;
        const [x, y, zh] = rot(rr * Math.cos(th) * R, zz * R, rr * Math.sin(th) * R);
        const L = (zh / R + 1) / 2;
        dots.push({ x, y, z: zh, r: 0.8 * c, white: 0.78, a: 0.1 + 0.22 * L });
    }
    const yy = time * 0.24 * spin;
    const k = o.faceOn ? -tilt : 0.55 + 0.3 * Math.sin(time * 0.18) * spin;
    const zz = Math.cos(yy), d = 0, a = Math.sin(yy);
    const f = -a * Math.sin(k), v = Math.cos(k), w = zz * Math.sin(k);
    const S = d * w - a * v, x = a * f - zz * w, E = zz * v - d * f;
    const N = 0.23 * (o.wobMul ?? 1);
    const C = o.faceOn ? R / (1 + 0.85 * N) : R;
    const lanes = o.lanes ?? 5, segs = o.segs ?? 88;
    const U = Math.max(1, Math.round(lanes * (o.bandMul ?? 1)));
    for (let A = 0; A < U; A++) {
        const Be = (A - (U - 1) / 2) * 0.075;
        const oe = Math.abs(A - (U - 1) / 2) / Math.max(1, (U - 1) / 2);
        for (let H = 0; H < segs; H++) {
            const _ = H / segs * 2 * Math.PI;
            const L = (0.16 * Math.sin(_ * 3 - time * 1.7 + A * 0.22) + 0.07 * Math.sin(_ * 5 + time * 1.1)) * (o.wobMul ?? 1);
            const T = o.faceOn ? 1 + L : 1;
            const I = o.faceOn ? Be : Be + L;
            const X = zz * Math.cos(_) + f * Math.sin(_) + S * I;
            const rt = d * Math.cos(_) + v * Math.sin(_) + x * I;
            const Re = a * Math.cos(_) + w * Math.sin(_) + E * I;
            const _t = Math.sqrt(X * X + rt * rt + Re * Re);
            const De = C * T;
            const [px, py, pz] = rot(X / _t * De, rt / _t * De, Re / _t * De);
            const Ml = (pz / R + 1) / 2;
            dots.push({
                x: px, y: py, z: pz,
                r: (o.rBase + o.rDepth * Ml) * (1 - 0.25 * oe) * c,
                white: 0.52 - 0.44 * Ml + 0.18 * oe,
                a: 0.4 + 0.6 * Ml,
            });
        }
    }
    paint(ctx, dots, dark, o.rMin);
}

const DRAW = { working: drawOrbits, searching: drawGlobe, composing: drawRibbon };

function detectDark(el) {
    let n = el;
    while (n) {
        const dt = n.getAttribute && n.getAttribute('data-theme');
        if (dt === 'dark') return true;
        if (dt === 'light') return false;
        if (n.classList) {
            if (n.classList.contains('dark')) return true;
            if (n.classList.contains('light')) return false;
        }
        n = n.parentElement;
    }
    return !!window.matchMedia && matchMedia('(prefers-color-scheme: dark)').matches;
}

Alpine.data('thinkingOrb', (opts = {}) => ({
    running: false,
    rafId: 0,
    visible: true,
    observer: null,
    init() {
        const canvas = this.$el;
        const size = Number(opts.size) || 40;
        const state = Object.prototype.hasOwnProperty.call(DRAW, opts.state) ? opts.state : 'working';
        const dpr = Math.min(2, window.devicePixelRatio || 1);
        canvas.width = Math.round(size * dpr);
        canvas.height = Math.round(size * dpr);
        const ctx = canvas.getContext('2d');
        if (!ctx) return;
        const dark = opts.light ? false : detectDark(canvas);
        const cfg = resolveConfig(state, size);
        const draw = (t) => {
            ctx.setTransform(dpr, 0, 0, dpr, 0, 0);
            ctx.clearRect(0, 0, size, size);
            DRAW[state](ctx, size, t, dark, cfg);
        };
        draw(0.6);
        if (window.matchMedia && matchMedia('(prefers-reduced-motion: reduce)').matches) return;
        const start = () => {
            if (this.running || opts.paused || !this.visible || document.visibilityState === 'hidden') return;
            this.running = true;
            const tick = () => {
                if (!this.running) return;
                draw((performance.now() / 1000) * cfg.speed * (Number(opts.speed) || 1));
                this.rafId = requestAnimationFrame(tick);
            };
            this.rafId = requestAnimationFrame(tick);
        };
        const stop = () => {
            this.running = false;
            if (this.rafId) {
                cancelAnimationFrame(this.rafId);
                this.rafId = 0;
            }
        };
        this.observer = new IntersectionObserver((entries) => {
            this.visible = entries[0].isIntersecting;
            this.visible ? start() : stop();
        });
        this.observer.observe(canvas);
        const vis = () => { document.visibilityState === 'hidden' ? stop() : this.visible && start(); };
        document.addEventListener('visibilitychange', vis);
        start();
    },
    destroy() {
        this.running = false;
        if (this.rafId) cancelAnimationFrame(this.rafId);
        if (this.observer) this.observer.disconnect();
    },
}));
