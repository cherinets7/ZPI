import './bootstrap';
import 'bootstrap/dist/js/bootstrap.bundle.min.js';
import Chart from 'chart.js/auto';

document.querySelectorAll('canvas[data-chart]').forEach((canvas) => {
    const raw = canvas.getAttribute('data-chart');
    if (!raw) {
        return;
    }
    const config = JSON.parse(raw);
    new Chart(canvas, config);
});
