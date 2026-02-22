import './bootstrap';

import Alpine from 'alpinejs';
import { Chart, LineController, LineElement, PointElement, LinearScale, CategoryScale, Filler, Tooltip, Legend } from 'chart.js';

Chart.register(LineController, LineElement, PointElement, LinearScale, CategoryScale, Filler, Tooltip, Legend);

window.Chart = Chart;

window.Alpine = Alpine;

Alpine.start();
