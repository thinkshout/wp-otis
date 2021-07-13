(function () {
	new Vue({
		el: document.getElementById("otis-dashboard-mount"),
		template: `
      <div class="otis-dashboard">
        <h1>OTIS Dashboard</h1>
        <div class="otis-dashboard__statuses">
          <div class="otis-dashboard__status">
            <p>Last Import</p>
            <p>{{ lastImport }}</p>
          </div>
          <div class="otis-dashboard__status">
            <p>Bulk Importer Active</p>
            <p>{{ importerActive }}</p>
          </div>
          <div class="otis-dashboard__status">
            <p>Bulk History Importer Active</p>
            <p>{{ historyImporterActive }}</p>
          </div>
        </div>
        <div class="otis-dashboard__settings">
          <div class="input-text-wrap">
            <label for="modified-date">Import Modified POIs Since Date</label>
            <input id="modified-date" type="text" name="otis-modified-date" placeholder="YYYY-MM-DD" v-model="fromDate" />
          </div>
        </div>
        <div class="otis-dashboard__buttons">
          <button class="button button-primary" @click="triggerImport">
            Start
            <span v-if="fromDate">Modified POIs Import</span>
            <span v-else>Bulk Import</span>
          </button>
        </div>
      </div>
    `,
		data: {
			fromDate: "",
			lastImportDate: "",
			bulkImportActive: "",
			bulkHistoryImportActive: "",
		},
		computed: {
			lastImport() {
				if (!this.lastImportDate) return "N/A";
				const lastImportDate = new Date(this.lastImportDate);
				return `${lastImportDate.getMonth()}/${lastImportDate.getDate()}/${lastImportDate.getFullYear()} @ ${lastImportDate.getHours()}:${lastImportDate.getMinutes()}`;
			},
			importerActive() {
				return this.bulkImportActive ? "Active" : "Inactive";
			},
			historyImporterActive() {
				return this.bulkHistoryImportActive ? "Active" : "Inactive";
			},
		},
		methods: {
			async otisStatus() {
				const payload = new FormData();
				payload.append("action", "otis_status");
				const { data } = await axios.post(otisDash.ajax_url, payload);
				Object.keys(data).forEach((key) => {
					this[key] = data[key];
				});
			},
			triggerImport() {
				if (
					this.fromDate?.match(
						/^\d{4}\-(0[1-9]|1[012])\-(0[1-9]|[12][0-9]|3[01])$/g
					)
				) {
					console.log(this.fromDate);
				} else {
					console.log("No match");
				}
			},
		},
		async mounted() {
			await this.otisStatus();
		},
	});
})();
