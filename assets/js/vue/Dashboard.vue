<template>
  <div class="otis-dashboard">
    <h1>OTIS Dashboard</h1>
    <div v-if="displayInitialImport" class="otis-dashboard__banner">
      <div class="otis-dashboard__initial-import">
        <div class="postbox">
          <OtisLoader v-if="importStarting" />
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
        <va-card>
          <va-card-title>Last Import</va-card-title>
          <va-card-content>{{ lastImport }}</va-card-content>
        </va-card>
      </div>
      <div class="otis-dashboard__status">
        <va-card>
          <va-card-title>Importer Status</va-card-title>
          <va-card-content>
            {{ importerStatus }}
          </va-card-content>
        </va-card>
      </div>
    </div>
    <div class="otis-dashboard__settings">
      <div v-if="!displayInitialImport" class="otis-dashboard__setting otis-dashboard__setting--full-width">
        <va-card>
          <va-card-title>Modified POI Import & Update</va-card-title>
          <va-card-content>
            <p>Start an import of POIs that have been modified since a given date. POIs that already exist on the site will be updated if they fall in the date range.</p>
            <p><em>Note: This will run the importer based on the wp_otis_listings filter if it is set in your theme or a different plugin.</em></p>
            <label for="modified-date">Date To Import From</label>
            <Datepicker
              v-model="modifiedDate"
              :enable-time-picker="false"
              :max-date="maxDate"
              format="MM/dd/yyyy"
            />
          </va-card-content>
          <va-card-actions>
            <button class="button button-primary" :disabled="!dateIsValid || importStarting || bulkImportActive || bulkImportScheduled" @click="triggerModifiedImport">
              <span v-if="importStarting">
                Import Starting Please Wait...
              </span>
              <span v-else-if="bulkImportActive">Import Running Please Wait...</span>
              <span v-else-if="bulkImportScheduled">Import Scheduled Please Wait...</span>
              <span v-else>Start Importing Modified POIs</span>
            </button>
          </va-card-actions>
        </va-card>
      </div>
      <div v-if="!displayInitialImport" class="otis-dashboard__setting otis-dashboard__setting--full-width">
        <va-card>
          <va-card-title>Sync Deleted POIs</va-card-title>
          <va-card-content>
            <p>This will sync all deleted POIs from the OTIS to the local database. This is useful if you find there are POIs that are stale/should have been deleted.</p>
            <label>This will fetch the list of deleted POIs, check them against the POIs still active in WordPress, and delete the POI post if relevant. <strong>This will delete POIs if they've been removed from OTIS.</strong></label>
          </va-card-content>
          <va-card-actions>
            <button class="button button-primary" :disabled="importStarting || bulkImportActive != '' || bulkImportScheduled" @click="triggerSyncDeletes">
              <span v-if="bulkImportActive || bulkImportScheduled">Sync Running Please Wait...</span>
              <span v-else>Sync Deleted POIs</span>
            </button>
            <button v-if="bulkImportActive" class="button button-primary" @click="stopBulkImporter">
              Stop Importer and Syncing
            </button>
          </va-card-actions>
        </va-card>
      </div>
      <div v-if="!displayInitialImport" class="otis-dashboard__setting">
        <va-card>
          <va-card-title>Import Log Preview</va-card-title>
          <va-card-content>
            <p>The last 15 entries in the import log. The full import log is available under <a :href="importLogUrl">POI > Import Log</a>.</p>
            <div class="va-table-responsive">
              <table class="va-table va-table--striped">
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
            </div>
          </va-card-content>
          <va-card-actions>
            <a :href="importLogUrl" role="button" class="button">View Full Import Log</a>
          </va-card-actions>
        </va-card>
      </div>
      <div v-if="!displayInitialImport" class="otis-dashboard__setting">
        <va-card>
          <va-card-title>POI Counts</va-card-title>
          <va-card-content>
            <div class="va-table-responsive">
              <table class="va-table va-table--striped">
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
          </va-card-content>
        </va-card>
      </div>
    </div>
  </div>
</template>

<script>
  import { ref, computed, onMounted } from "vue";
  import axios from "axios";
  import OtisLoader from "./components/OtisLoader.vue";

  export default {
    name: "OtisDashboard",
    components: {
      OtisLoader,
    },
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
          switch (key) {
            case "lastImportDate":
              lastImportDate.value = data[key];
              break;
            case "bulkImportActive":
              bulkImportActive.value = data[key];
              break;
            case "bulkImportScheduled":
              bulkImportScheduled.value = data[key];
              break;
            case "poiCount":
              poiCount.value = data[key];
              break;
            default:
              break;
          }
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