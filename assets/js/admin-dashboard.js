'use strict';
const chartRoot = document.querySelector('[data-dashboard-charts]');
if (chartRoot) {
    const data = JSON.parse(chartRoot.dataset.dashboardCharts);
    const palette = ['#197b66', '#e5b85c', '#b95d58', '#8a9690'];
    for (const [id, chart] of [['establishments-chart', data.establishments], ['traps-chart', data.traps], ['devices-chart', data.devices]]) {
        const canvas = document.getElementById(id);
        const context = canvas?.getContext('2d');
        if (!context) continue;
        const total = chart.values.reduce((sum, value) => sum + value, 0);
        const max = Math.max(...chart.values, 1);
        context.font = '13px Segoe UI, sans-serif';
        chart.values.forEach((value, index) => {
            const y = 22 + index * 48;
            const width = total === 0 ? 0 : (value / max) * 245;
            context.fillStyle = '#edf1ee'; context.fillRect(145, y, 245, 22);
            context.fillStyle = palette[index % palette.length]; context.fillRect(145, y, width, 22);
            context.fillStyle = '#425853'; context.fillText(chart.labels[index], 10, y + 16);
            context.fillStyle = '#203b38'; context.fillText(String(value), 405, y + 16);
        });
        const legend = document.querySelector(`[data-legend="${id}"]`);
        if (legend) legend.textContent = total === 0 ? 'No records yet.' : `${total} total records represented`;
    }
}
