<template>
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
    </div>
    <div class="otis-dashboard__settings">
      <div v-if="!displayInitialImport" class="otis-dashboard__setting">
        <div :class="['postbox', { success: importStarted }]">
          <div v-if="importStarting" class="otis-ellipsis"><div /><div /><div /><div /></div>
          <h2>Modified POI Import & Update</h2>
          <p>Start an import of POIs that have been modified since a given date. POIs that already exist on the site will be updated if they fall in the date range.</p>
          <p><em>Note: This will run the importer based on the wp_otis_listings filter if it is set in your theme or a different plugin.</em></p>
          <div class="otis-dashboard__action">
            <label for="modified-date">Date To Import From</label>
            <Datepicker
              v-model="modifiedDate"
              :enable-time-picker="false"
              :max-date="maxDate"
              format="yyyy-MM-dd"
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
          <p>Manually deactivates the bulk importer. If a large import is interrupted for some reason, the "bulk" flag can stay active on the server (see above). Use this button to turn the "bulk" flag off, and re-start hourly imports.</p>
          <div class="otis-dashboard__action">
            <button class="button button-primary" :disabled="!bulkImportActive" @click="stopBulkImporter">
              Stop Bulk Importer
            </button>
          </div>
        </div>
      </div>
      <div v-if="!displayInitialImport" class="otis-dashboard__setting otis-dashboard__setting--full-width">
        <div class="postbox">
          <h2>Sync Deleted POIs</h2>
          <p>This will sync all deleted POIs from the OTIS to the local database. This is useful if you find there are POIs that are stale/should have been deleted.</p>
          <div class="otis-dashboard__action">
            <div class="otis-dashboard__action-button">
              <label>This will fetch the list of deleted POIs, check them against the POIs still active in WordPress, and delete the POI post if relevant. <strong>This will delete POIs if they've been removed from OTIS.</strong></label>
              <button class="button button-primary" :disabled="importStarting || bulkImportActive != '' || bulkImportScheduled" @click="triggerSyncDeletes">
                <span v-if="bulkImportActive || bulkImportScheduled">Sync Running Please Wait...</span>
                <span v-else>Sync Deleted POIs</span>
              </button>
              <button v-if="bulkImportActive" class="button button-primary" @click="stopBulkImporter">
                Stop Importer and Syncing
              </button>
            </div>
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
</template>

<script>
  import { ref, computed, onMounted } from "vue";
  import axios from "axios";

  export default {
    name: "OtisDashboard",
    setup() {
      // Refs
      const modifiedDate = ref("");
      const lastImportDate = ref("");
      const bulkImportActive = ref("");
      const bulkImportScheduled = ref(false);
      const poiCount = ref({});
      const importLog = ref([]);
      const importStarting = ref(false);
      const importStarted = ref(false);
      const logLoading = ref(false);
      const countsLoading = ref(false);

      // Computed
      const lastImport = computed(() => {
        if (!lastImportDate.value) return "N/A";
        const lastImportDateObject = new Date(lastImportDate.value);
        const month = lastImportDateObject.getMonth() + 1; // months are zero indexed
        const day = lastImportDateObject.getDate();
        const year = lastImportDateObject.getFullYear();
        const hours = lastImportDateObject.getHours();
        const minutes = lastImportDateObject.getMinutes() > 9 ? lastImportDateObject.getMinutes() : "0" + lastImportDateObject.getMinutes();
        return `${month}/${day}/${year} @ ${hours}:${minutes}`;
      });
      const importerStatus = computed(() => {
        if (bulkImportActive.value) return "Active";
        if (bulkImportScheduled.value) return "Scheduled";
        return "Inactive";
      });
      const maxDate = computed(() => {
        return new Date();
      });
      const displayInitialImport = computed(() => {
        if (countsLoading.value) return false;
        let count = 0;
        for (const key in poiCount.value) {
          if (Object.hasOwnProperty.call(poiCount.value, key)) {
            const statusCount = poiCount.value[key];
            count += parseInt(statusCount);
          }
        }
        return count === 0;
      });
      const dateIsValid = computed(() => {
        return modifiedDate.value ? true : false;
      });
      const importLogUrl = computed(() => {
        return `${otisDash.admin_url}edit.php?post_type=poi&page=tror_poi_otis_log`;
      });

      // Methods
      const poiPostsUrl = (status = null) => {
        if (!status)
          return `${otisDash.admin_url}edit.php?post_type=poi`;
        return `${otisDash.admin_url}edit.php?post_status=${status}&post_type=poi`;
      };
      const makePayload = (payloadData = {}) => {
        const payload = new FormData();
        Object.keys(payloadData).forEach((key) => {
          payload.append(key, payloadData[key]);
        });
        return payload;
      };
      const notifyImportStarted = () => {
        importStarted.value = true;
        setTimeout(() => {
          importStarted.value = false;
        }, 1000);
      };
      const triggerAction = async (action, data = {}) => {
        const payload = makePayload({
          action,
          ...data,
        });
        return await axios.post(
          otisDash.ajax_url,
          payload,
          { timeout: 0 }
        );
      };
      const otisStatus = async () => {
        countsLoading.value = true;
        const { data } = await triggerAction("otis_status");
        Object.keys(data).forEach((key) => {
          this[key] = data[key];
        });
        countsLoading.value = false;
        await otisLogPreview();
      };
      const otisLogPreview = async () => {
        logLoading.value = true;
        const { data } = await triggerAction("otis_preview_log");
        logLoading.value = false;
        importLog.value = data;
      };
      const stopBulkImporter = async () => {
        await triggerAction('otis_stop_bulk');
        await otisStatus();
      };
      const triggerInitialImport = async () => {
        importStarting.value = true;
        await triggerAction("otis_import", {initial_import: true});
        await otisStatus();
        importStarting.value = false;
      };
      const triggerModifiedImport = async () => {
        if (!dateIsValid.value) return;
        importStarting.value = true;
        const importData = {from_date: modifiedDate.value};
        await triggerAction('otis_import', importData);
        await otisStatus();
        notifyImportStarted.value();
        importStarting.value = false;
      };
      const triggerSyncDeletes = async () => {
        importStarting.value = true;
        if ( !confirm('Are you sure you want to delete all POIs that have been deleted from OTIS?') ) {
          return;
        }
        await triggerAction("otis_sync_deleted_pois");
        await otisStatus();
        importStarting.value = false;
      };

      // On Mount
      onMounted(async () => {
        await otisStatus();
      });

      return {
        modifiedDate,
        lastImportDate,
        bulkImportActive,
        bulkImportScheduled,
        poiCount,
        importLog,
        importStarting,
        importStarted,
        logLoading,
        countsLoading,
        lastImport,
        importerStatus,
        maxDate,
        displayInitialImport,
        dateIsValid,
        importLogUrl,
        poiPostsUrl,
        triggerAction,
        otisStatus,
        otisLogPreview,
        stopBulkImporter,
        triggerInitialImport,
        triggerModifiedImport,
        triggerSyncDeletes,
      }
    },
  }
</script>