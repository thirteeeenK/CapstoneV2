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

function renderTrend(trend) {
    const labels = trend.labels || [];
    if (!labels.length) return;
    mount('#chart-trend', {
        ...baseOptions(),
        chart: { ...baseOptions().chart, type: 'line', height: 280 },
        series: [
            { name: 'Bookings', type: 'column', data: trend.bookings || [] },
            { name: 'Revenue (₱)', type: 'line', data: trend.revenue || [] },
        ],
        colors: ['#0ea5e9', '#10b981'],
        stroke: { width: [0, 3], curve: 'smooth' },
        plotOptions: { bar: { borderRadius: 4, columnWidth: '55%' } },
        xaxis: { categories: labels, labels: { style: { colors: '#64748b', fontSize: '10px' } }, tickAmount: 8 },
        yaxis: [
            { title: { text: 'Bookings', style: { color: '#64748b' } }, labels: { style: { colors: '#64748b' } } },
            { opposite: true, title: { text: 'Revenue (₱)', style: { color: '#64748b' } }, labels: { style: { colors: '#94a3b8' }, formatter: (v) => `₱${Math.round(v / 1000)}k` } },
        ],
        grid: { borderColor: '#f1f5f9' },
        legend: { position: 'top', horizontalAlign: 'right', labels: { colors: '#64748b', fontWeight: 700 } },
        dataLabels: { enabled: false },
        tooltip: { y: { formatter: (val, o) => (o.seriesIndex === 1 ? `₱${Number(val).toLocaleString()}` : `${val} booking(s)`) } },
    });
}

function renderFunnel(funnel) {
    const series = funnel.series || [];
    if (!series.length) return;
    mount('#chart-funnel', {
        ...baseOptions(),
        chart: { ...baseOptions().chart, type: 'donut', height: 280 },
        series,
        labels: funnel.labels || [],
        colors: ['#f59e0b', '#0ea5e9', '#10b981', '#14b8a6', '#94a3b8'],
        legend: { position: 'bottom', labels: { colors: '#64748b', fontWeight: 700 } },
        dataLabels: { formatter: (val) => `${Math.round(val)}%` },
        stroke: { width: 2, colors: ['#ffffff'] },
        tooltip: { y: { formatter: (val) => `${val} booking(s)` } },
    });
}

function renderTopSellers(top) {
    const labels = top.labels || [];
    if (!labels.length) {
        const el = document.querySelector('#chart-topsellers');
        if (el) el.innerHTML = '<p class="text-xs text-slate-400 font-medium px-1 py-6 text-center">No sales in the last 30 days.</p>';
        return;
    }
    mount('#chart-topsellers', {
        ...baseOptions(),
        chart: { ...baseOptions().chart, type: 'bar', height: 220 },
        series: [{ name: 'Bookings', data: top.data || [] }],
        colors: ['#1294c8'],
        plotOptions: { bar: { horizontal: true, borderRadius: 4, barHeight: '55%' } },
        dataLabels: { enabled: true, style: { colors: ['#fff'], fontWeight: 700, fontSize: '11px' } },
        xaxis: { categories: labels, labels: { show: false } },
        yaxis: { labels: { style: { colors: '#475569', fontWeight: 700, fontSize: '10px' }, maxWidth: 190 } },
        grid: { borderColor: '#f1f5f9' },
        tooltip: { y: { formatter: (val) => `${val} booking(s)` } },
    });
}

document.addEventListener('DOMContentLoaded', () => {
    const dataEl = document.getElementById('dashboard-chart-data');
    if (!dataEl) return;

    let data;
    try {
        data = JSON.parse(dataEl.textContent);
    } catch (e) {
        console.error('Failed to parse dashboard chart data:', e);
        return;
    }

    renderTrend(data.trend || {});
    renderFunnel(data.funnel || {});
    renderTopSellers(data.topSellers || {});
});
