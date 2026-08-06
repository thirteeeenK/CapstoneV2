import ApexCharts from 'apexcharts';

function baseOptions() {
    return {
        chart: {
            fontFamily: '"DM Sans", sans-serif',
            toolbar: { show: false },
            animations: { speed: 500 },
        },
    };
}

function mount(selector, options) {
    const el = document.querySelector(selector);
    if (!el) return null;
    const chart = new ApexCharts(el, options);
    chart.render();
    return chart;
}

function renderSentimentDonut(sentiment) {
    const labels = ['Positive', 'Neutral', 'Negative'];
    const colors = ['#10b981', '#fbbf24', '#f43f5e'];
    const series = [sentiment.positive, sentiment.neutral, sentiment.negative];
    const total = series.reduce((a, b) => a + b, 0) || 1;
    mount('#chart-sentiment-donut', {
        ...baseOptions(),
        chart: { ...baseOptions().chart, type: 'donut' },
        series,
        labels,
        colors,
        dataLabels: {
            formatter(val) {
                return Math.round(val) + '%';
            },
        },
        legend: {
            position: 'bottom',
            labels: {
                colors: '#64748b',
                fontWeight: 700,
            },
            formatter(seriesName, opts) {
                const count = series[opts.seriesIndex];
                return `${seriesName}  ·  ${count}  (${Math.round((count / total) * 100)}%)`;
            },
        },
        plotOptions: {
            pie: {
                donut: {
                    labels: {
                        show: true,
                        name: { fontSize: '11px', color: '#64748b' },
                        value: {
                            fontSize: '20px',
                            fontWeight: 800,
                            color: '#0f172a',
                            formatter: (val) => `${Math.round((val / total) * 100)}%`,
                        },
                        total: {
                            show: true,
                            label: 'Reviews',
                            color: '#64748b',
                            formatter: () => `${total}`,
                        },
                    },
                },
            },
        },
        stroke: { width: 2, colors: ['#ffffff'] },
    });
}

function renderRatingDistribution(dist) {
    mount('#chart-rating-dist', {
        ...baseOptions(),
        chart: { ...baseOptions().chart, type: 'bar' },
        series: [
            {
                name: 'Reviews',
                data: [1, 2, 3, 4, 5].map((star) => dist[star] || 0),
            },
        ],
        colors: ['#0a78a8'],
        plotOptions: {
            bar: {
                borderRadius: 4,
                columnWidth: '45%',
            },
        },
        xaxis: {
            categories: ['1★', '2★', '3★', '4★', '5★'],
            labels: { style: { colors: '#64748b', fontWeight: 700 } },
        },
        yaxis: {
            labels: { style: { colors: '#94a3b8' } },
        },
        grid: { borderColor: '#f1f5f9' },
        dataLabels: { enabled: true, style: { colors: ['#fff'], fontWeight: 700, fontSize: '11px' } },
    });
}

function renderLeaderboard(type, rows) {
    if (!rows.length) return;
    mount(`#chart-leaderboard-${type}`, {
        ...baseOptions(),
        chart: { ...baseOptions().chart, type: 'bar' },
        series: [
            {
                name: 'Avg rating',
                data: rows.map((r) => r.average_rating),
            },
        ],
        colors: ['#1294c8'],
        plotOptions: {
            bar: {
                horizontal: true,
                borderRadius: 4,
                barHeight: '55%',
            },
        },
        dataLabels: {
            enabled: true,
            formatter: (val) => `${Number(val).toFixed(1)} ★`,
            style: { colors: ['#085e85'], fontWeight: 700, fontSize: '10px' },
        },
        xaxis: {
            categories: rows.map((r) => r.label),
            min: 0,
            max: 5,
            labels: { show: false },
        },
        yaxis: {
            labels: {
                style: { colors: '#475569', fontWeight: 700, fontSize: '10px' },
                maxWidth: 175,
            },
        },
        grid: { borderColor: '#f1f5f9' },
        tooltip: {
            y: {
                formatter: (val, o) => {
                    const row = rows[o.dataPointIndex];
                    return `${Number(val).toFixed(2)} ★ · ${row.review_count} reviews`;
                },
            },
        },
    });
}

function renderNeedsImprovement(alerts) {
    if (!alerts.length) return;
    mount('#chart-needs-improvement', {
        ...baseOptions(),
        chart: { ...baseOptions().chart, type: 'bar' },
        series: [
            {
                name: 'Negative %',
                data: alerts.map((a) => a.negative_percentage),
            },
        ],
        colors: ['#f43f5e'],
        plotOptions: {
            bar: {
                horizontal: true,
                borderRadius: 4,
                barHeight: '50%',
            },
        },
        dataLabels: {
            enabled: true,
            formatter: (val) => `${Math.round(val)}%`,
            style: { colors: ['#fff'], fontWeight: 700, fontSize: '10px' },
        },
        xaxis: {
            categories: alerts.map((a) => a.label),
            max: 100,
            labels: { show: false },
        },
        yaxis: {
            labels: { style: { colors: '#475569', fontWeight: 700, fontSize: '10px' }, maxWidth: 160 },
        },
        grid: { borderColor: '#f1f5f9' },
        tooltip: {
            y: {
                formatter: (val, o) => {
                    const a = alerts[o.dataPointIndex];
                    return `${Math.round(val)}% negative · ${a.total_reviews} reviews · ${Number(a.average_rating).toFixed(1)} ★ avg`;
                },
            },
        },
    });
}

function renderKeywordTreemap(keywords) {
    if (!Object.keys(keywords).length) return;
    mount('#chart-keyword-treemap', {
        ...baseOptions(),
        chart: { ...baseOptions().chart, type: 'treemap' },
        series: [
            {
                data: Object.entries(keywords).map(([label, value]) => ({ x: label, y: value })),
            },
        ],
        colors: ['#0a78a8', '#1294c8', '#38b0e3', '#0f766e', '#14b8a6'],
        plotOptions: {
            treemap: {
                distributed: true,
                enableShades: true,
            },
        },
        legend: { show: false },
        dataLabels: {
            enabled: true,
            style: { fontSize: '11px', fontWeight: 700, colors: ['#fff'] },
        },
        tooltip: {
            y: { formatter: (val) => `${val} mentions` },
        },
    });
}

document.addEventListener('DOMContentLoaded', () => {
    const dataEl = document.getElementById('reviews-chart-data');
    if (!dataEl) return;

    let data;
    try {
        data = JSON.parse(dataEl.textContent);
    } catch (e) {
        console.error('Failed to parse review chart data:', e);
        return;
    }

    renderSentimentDonut(data.sentiment || {});
    renderRatingDistribution(data.ratingDist || {});
    (data.leaderboards || {}) && ['hotels', 'rooms', 'activities'].forEach((type) => {
        renderLeaderboard(type, (data.leaderboards && data.leaderboards[type]) || []);
    });
    renderNeedsImprovement(data.needsImprovement || []);
    renderKeywordTreemap(data.keywords || {});
});
