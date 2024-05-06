import { createApp } from "vue";
import Datepicker from '@vuepic/vue-datepicker';
import '@vuepic/vue-datepicker/dist/main.css'
import OtisDashboard from "./vue/components/04_templates/Dashboard.vue";
import { createVuestic } from 'vuestic-ui';

export default function dashboardVue() {
	const app = createApp(OtisDashboard);
	app.component('Datepicker', Datepicker);
	app.use(createVuestic({
		config: {
			colors: {
				variables: {
					primary: 'rgb(100, 154, 165)',
					secondary: 'rgb(53, 108, 118)',
					success: '#23e066',
					info: '#e67520',
					danger: '#e34b4a',
					warning: '#ffc200',
					gray: '#bdbdbd',
					dark: '#34495e',
				}
			}
		}
	}));
	app.mount("#otis-dashboard-mount");
};
