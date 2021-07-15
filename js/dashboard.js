(function () {
	new Vue({
		el: document.getElementById("otis-dashboard-mount"),
		template: `
      <div class="otis-dashboard">
        <h1>OTIS Dashboard</h1>
        <div v-if="displayInitialImport" class="otis-dashboard__banner">
          <div class="otis-dashboard__initial-import">
            <div class="postbox">
              <div v-if="importStarting" class="otis-ellipsis"><div /><div /><div /><div /></div>
              <h2>Initial POI Import</h2>
              <p>Start here if this is your first time running the plugin. This button will trigger the initial import of POI data from OTIS.</p>
              <p><em>Note: This will run the importer based on the wp_otis_listings filter if it is set in your theme or a different plugin.</em></p>
              <div class="otis-dashboard__action">
                <p v-if="importStarting">POI import starting, please wait this usually takes a few minutes...</p>
                <button class="button button-primary" :disabled="importStarting" @click="triggerInitialImport">
                  <span v-if="importStarting">
                    Import Starting Please Wait...
                  </span>
                  <span v-else>Start Importing POIs</span>
                </button>
              </div>
            </div>
          </div>
        </div>
        <div class="otis-dashboard__statuses">
          <div class="otis-dashboard__status">
            <div class="postbox">
              <h2>Last Import</h2>
              <p>{{ lastImport }}</p>
            </div>
          </div>
          <div class="otis-dashboard__status">
            <div :class="['postbox', { success: importStarted }]">
              <div v-if="importStarting" class="otis-ellipsis"><div /><div /><div /><div /></div>
              <h2>Bulk Importer Status</h2>
              <p>{{ importerActive }}</p>
            </div>
          </div>
          <div class="otis-dashboard__status">
            <div class="postbox">
              <h2>Bulk History Importer Status</h2>
              <p>{{ historyImporterActive }}</p>
            </div>
          </div>
        </div>
        <div class="otis-dashboard__settings">
          <div v-if="!displayInitialImport" class="otis-dashboard__setting">
            <div :class="['postbox', { success: importStarted }]">
              <div v-if="importStarting" class="otis-ellipsis"><div /><div /><div /><div /></div>
              <h2>Modified POI Import & Update</h2>
              <p>Start an import of POIs that have been modified since a given date. POIs that already exist on the site will be updated if they fall in the date range.</p>
              <p><em>Note: This will run the importer based on the wp_otis_listings filter if it is set in your theme or a different plugin.</em></p>
              <div class="otis-dashboard__action">
                <label for="modified-date">Start Date</label>
                <input id="modified-date" type="text" name="otis-modified-date" placeholder="YYYY-MM-DD" :readonly="importStarting" v-model="fromDate" />
                <p v-if="importStarting">POI import starting, please wait this usually takes a few minutes...</p>
                <button class="button button-primary" :disabled="!dateIsValid || importStarting" @click="triggerModifiedImport">
                  <span v-if="importStarting">
                    Import Starting Please Wait...
                  </span>
                  <span v-else>Start Importing Modified POIs</span>
                </button>
              </div>
            </div>
          </div>
          <div v-if="!displayInitialImport" class="otis-dashboard__setting">
            <div class="postbox">
              <h2>Stop Bulk Importer</h2>
              <p>Manually deactivates the bulk importer. If a large import is interrupted for some reason, the "bulk" flag can stay active on the server (see above). Use this button to turn the "bulk" flag off, and re-start hourly imports.</p>
              <div class="otis-dashboard__action">
                <button class="button button-primary" :disabled="!bulkImportActive" @click="stopBulkImporter">
                  Stop Bulk Importer
                </button>
              </div>
            </div>
          </div>
          <div v-if="!displayInitialImport" class="otis-dashboard__setting">
            <div class="postbox">
              <div v-if="logLoading" class="otis-ellipsis"><div /><div /><div /><div /></div>
              <h2>Import Log Preview</h2>
              <p>The last 15 entries in the import log. The full import log is available under <a :href="importLogUrl">POI > Import Log</a>.</p>
              <table class="otis-dashboard__import-log">
                <thead>
                  <tr>
                    <th>Log Entry</th>
                  </tr>
                </thead>
                <tbody>
                  <tr v-for="({ post_content }, index) of importLog" :key="index">
                    <td>{{ post_content }}</td>
                  </tr>
                </tbody>
              </table>
              <a :href="importLogUrl" role="button" class="button">View Full Import Log</a>
            </div>
          </div>
          <div v-if="!displayInitialImport" class="otis-dashboard__setting">
            <div class="postbox">
              <div v-if="countsLoading" class="otis-ellipsis"><div /><div /><div /><div /></div>
              <h2>POI Counts</h2>
              <table class="otis-dashboard__poi-counts">
                <thead>
                  <tr>
                    <th>Status</th>
                    <th>Count</th>
                  </tr>
                </thead>
                <tbody>
                  <tr v-for="(count, status) of poiCount" :key="status">
                    <td>{{ status }}</td>
                    <td><a :href="poiPostsUrl(status)">{{ count }}</a></td>
                  </tr>
                </tbody>
              </table>
            </div>
          </div>
        </div>
      </div>
    `,
		data: {
			fromDate: "",
			lastImportDate: "",
			bulkImportActive: "",
			bulkHistoryImportActive: "",
			poiCount: {},
			importLog: [],
			importStarting: false,
			importStarted: false,
			logLoading: false,
			countsLoading: false,
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
			displayInitialImport() {
				if (this.countsLoading) return false;
				let count = 0;
				for (const key in this.poiCount) {
					if (Object.hasOwnProperty.call(this.poiCount, key)) {
						const statusCount = this.poiCount[key];
						count += parseInt(statusCount);
					}
				}
				return count === 0;
			},
			dateIsValid() {
				const formatCorrect = this.fromDate?.match(
					/^\d{4}\-(0[1-9]|1[012])\-(0[1-9]|[12][0-9]|3[01])$/g
				);
				if (!formatCorrect) return false;
				const date = new Date(this.fromDate);
				const now = new Date();
				return date.getTime() < now.getTime();
			},
			otisDashObject() {
				return otisDash;
			},
			importLogUrl() {
				return `${this.otisDashObject.admin_url}edit.php?post_type=poi&page=tror_poi_otis_log`;
			},
		},
		methods: {
			poiPostsUrl(status = null) {
				if (!status)
					return `${this.otisDashObject.admin_url}edit.php?post_type=poi`;
				return `${this.otisDashObject.admin_url}edit.php?post_status=${status}&post_type=poi`;
			},
			makePayload(payloadData = {}) {
				const payload = new FormData();
				Object.keys(payloadData).forEach((key) => {
					payload.append(key, payloadData[key]);
				});
				return payload;
			},
			notifyImportStarted() {
				this.importStarted = true;
				setTimeout(() => {
					this.importStarted = false;
				}, 1000);
			},
			async otisStatus() {
				this.countsLoading = true;
				const payload = this.makePayload({ action: "otis_status" });
				const { data } = await axios.post(
					this.otisDashObject.ajax_url,
					payload,
					{ timeout: 0 }
				);
				Object.keys(data).forEach((key) => {
					this[key] = data[key];
				});
				this.countsLoading = false;
				await this.otisLogPreview();
			},
			async otisLogPreview() {
				this.logLoading = true;
				const payload = this.makePayload({
					action: "otis_preview_log",
				});
				const { data } = await axios.post(
					this.otisDashObject.ajax_url,
					payload,
					{ timeout: 0 }
				);
				this.logLoading = false;
				this.importLog = data;
			},
			async stopBulkImporter() {
				const payload = this.makePayload({
					action: "otis_stop_bulk",
				});
				const { data } = await axios.post(
					this.otisDashObject.ajax_url,
					payload,
					{ timeout: 0 }
				);
				await this.otisStatus();
			},
			async triggerInitialImport() {
				this.importStarting = true;
				const payload = this.makePayload({
					action: "otis_import",
				});
				const { data } = await axios.post(
					this.otisDashObject.ajax_url,
					payload,
					{ timeout: 0 }
				);
				await this.otisStatus();
				this.importStarting = false;
			},
			async triggerModifiedImport() {
				if (!this.dateIsValid) return;
				this.importStarting = true;
				const payload = this.makePayload({
					action: "otis_import",
					modified_date: this.fromDate,
				});
				const { data } = await axios.post(
					this.otisDashObject.ajax_url,
					payload,
					{ timeout: 0 }
				);
				console.log({ data });
				await this.otisStatus();
				this.notifyImportStarted();
				this.importStarting = false;
			},
		},
		async mounted() {
			await this.otisStatus();
		},
	});
})();
