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
              <p>{{ importerStatus }}</p>
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
                <button class="button button-primary" :disabled="!dateIsValid || importStarting || bulkImportActive || bulkImportScheduled" @click="triggerModifiedImport">
                  <span v-if="importStarting">
                    Import Starting Please Wait...
                  </span>
									<span v-else-if="bulkImportActive">Import Running Please Wait...</span>
									<span v-else-if="bulkImportScheduled">Import Scheduled Please Wait...</span>
                  <span v-else>Start Importing Modified POIs</span>
                </button>
              </div>
            </div>
          </div>
          <div v-if="!displayInitialImport" class="otis-dashboard__setting">
            <div class="postbox">
              <h2>Stop Bulk Importers</h2>
              <p>Manually deactivates the bulk importer or the bulk history importer. If a large import is interrupted for some reason, the "bulk" flag can stay active on the server (see above). Use this button to turn the "bulk" flag off, and re-start hourly imports.</p>
              <div class="otis-dashboard__action">
                <button class="button button-primary" :disabled="!bulkImportActive" @click="stopBulkImporter">
                  Stop Bulk Importer
                </button>
								<button class="button button-primary" :disabled="!bulkHistoryImportActive" @click="stopHistoryImporter">
                  Stop Bulk History Importer
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
			bulkImportScheduled: false,
			bulkHistoryImportScheduled: false,
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
			importerStatus() {
				if (this.bulkImportActive) return "Active";
				if (this.bulkImportScheduled) return "Scheduled";
				return "Inactive";
			},
			historyImporterActive() {
				if (this.bulkHistoryImportActive) return "Active";
				if (this.bulkHistoryImportScheduled) return "Scheduled";
				return "Inactive";
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
			async triggerAction(action, data = {}) {
				const payload = this.makePayload({
					action,
					...data,
				});
				return await axios.post(
					this.otisDashObject.ajax_url,
					payload,
					{ timeout: 0 }
				);
			},
			async otisStatus() {
				this.countsLoading = true;
				const { data } = await this.triggerAction("otis_status");
				Object.keys(data).forEach((key) => {
					this[key] = data[key];
				});
				this.countsLoading = false;
				await this.otisLogPreview();
			},
			async otisLogPreview() {
				this.logLoading = true;
				const { data } = await this.triggerAction("otis_preview_log");
				this.logLoading = false;
				this.importLog = data;
			},
			async stopBulkImporter() {
				await this.triggerAction('otis_stop_bulk');
				await this.otisStatus();
			},
			async stopHistoryImporter() {
				await this.triggerAction('otis_stop_bulk_history');
				await this.otisStatus();
			},
			async triggerInitialImport() {
				this.importStarting = true;
				await this.triggerAction("otis_import", {initial_import: true});
				await this.otisStatus();
				this.importStarting = false;
			},
			async triggerModifiedImport() {
				if (!this.dateIsValid) return;
				this.importStarting = true;
				await this.triggerAction('otis_import', {modified_date: this.fromDate});
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
