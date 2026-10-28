import './bootstrap';
import { createApp } from 'vue';

import ShowInfoComponent from './components/ShowInfoComponent.vue';
import TimeComponent from './components/TimeComponent.vue';

const app = createApp({});

app.component('show-info-component', ShowInfoComponent);
app.component('time-component', TimeComponent);

if (document.getElementById('app')) {
    app.mount('#app');
}
