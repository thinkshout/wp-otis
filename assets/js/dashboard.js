import { createApp } from "vue";
import Datepicker from '@vuepic/vue-datepicker';
import '@vuepic/vue-datepicker/dist/main.css'
import OtisDashboard from "./vue/Dashboard.vue";
import { createVuestic } from 'vuestic-ui';
import 'vuestic-ui/dist/vuestic-ui.css';

export default function dashboardVue() {
	const app = createApp(OtisDashboard);
	app.component('Datepicker', Datepicker);
	app.use(createVuestic());
	app.mount("#otis-dashboard-mount");
};
