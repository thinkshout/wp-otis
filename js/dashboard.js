(function () {
	new Vue({
		el: document.getElementById("otis-dashboard-mount"),
		components: {
			'datepicker' : vdprDatePicker.default,
			'calendar-dialog' : vdprDatePicker.CalendarDialog
		},
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
                <label for="modified-date">Date Range To Import</label>
								<datepicker
									format="MM/DD/YYYY"
									:show-helper-buttons="false"
									:date-input="{
										placeholder: 'Click to select a date range',
									}"
									:calendar-date-input="{
										format: 'MM/DD/YYYY'
									}"
									:same-date-format="{
										from: 'MM/DD/YYYY',
										to: 'MM/DD/YYYY',
									}"
									:time-input="{
										readonly: true,
									}"
									reset-button-label="Clear"
									:switch-button-initial="true"
									:disabled-dates="disabledDates"
									:disabled="importStarting"
									:readonly="importStarting"
									@date-applied="setDateRange"
								/>
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
			dateRange: {
				from: '',
				to: '',
			},
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
			disabledDates() {
				return {
					from: new Date(),
				}
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
				return this.dateRange.to && this.dateRange.from ? true : false;
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
			setDateRange(fromDate, toDate) {
				const formattedFromDate = new Date(fromDate).toISOString().substring(0, 10);
				const formattedToDate = new Date(toDate).toISOString().substring(0, 10);
				this.dateRange.from = formattedFromDate;
				this.dateRange.to = formattedToDate;
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
				await this.triggerAction('otis_import', {from_date: this.dateRange.from, to_date: this.dateRange.to});
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
