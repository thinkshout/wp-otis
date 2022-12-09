import { createApp } from "vue";
import Datepicker from '@vuepic/vue-datepicker';
import '@vuepic/vue-datepicker/dist/main.css'
import OtisDashboard from "./vue/Dashboard.vue";

export default function dashboardVue() {
	const app = createApp(OtisDashboard);
	app.component('Datepicker', Datepicker);
	app.mount("#otis-dashboard-mount");
};
