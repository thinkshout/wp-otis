import { createApp } from "vue";
import OtisDashboard from "./vue/Dashboard.vue";

export default function dashboardVue() {
	const app = createApp(OtisDashboard);
	app.mount("#otis-dashboard-mount");
};
