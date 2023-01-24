import { createApp } from "vue";
import Datepicker from '@vuepic/vue-datepicker';
import '@vuepic/vue-datepicker/dist/main.css'
import OtisDashboard from "./vue/Dashboard.vue";
import { createVuestic } from 'vuestic-ui';

export default function dashboardVue() {
	const app = createApp(OtisDashboard);
	app.component('Datepicker', Datepicker);
	app.use(createVuestic({
		config: {
			colors: {
				primary: '#23e066',
				secondary: '#002c85',
				success: '#40e583',
				info: '#2c82e0',
				danger: '#e34b4a',
				warning: '#ffc200',
				gray: '#babfc2',
				dark: '#34495e',
			}
		}
	}));
	app.mount("#otis-dashboard-mount");
};
