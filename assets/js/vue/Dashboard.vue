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
    <div class="otis-dashboard__settings">
      <div class="otis-dashboard__setting-group">
        <div v-if="!displayInitialImport" class="otis-dashboard__setting">
          <va-card>
            <va-card-title>POI Import & Update</va-card-title>
            <va-card-content>
              <p>Start an import of POIs that have been modified since a given date. POIs that already exist on the site will be updated or trashed if the have been updated or deleted on or after that date.</p>
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
              <button class="button button-primary" :disabled="!dateIsValid || importStarting || importActive" @click="triggerModifiedImport">
                <span v-if="importStarting">
                  Import Starting Please Wait...
                </span>
                <span v-else-if="importActive">Import Running Please Wait...</span>
                <span v-else>Start Importing Modified POIs</span>
              </button>
              <button v-if="importActive" class="button button-primary" @click="cancelImporter">
                Cancel Import
              </button>
            </va-card-actions>
          </va-card>
        </div>
        <div class="otis-dashboard__statuses">
          <div class="otis-dashboard__status">
            <va-card>
              <va-card-title>Last Import</va-card-title>
              <va-card-content>
                <div v-if="countsLoading">
                  <OtisLoader />
                </div>
                <div v-else>
                  {{ lastImport }}
                </div>
              </va-card-content>
            </va-card>
          </div>
          <div class="otis-dashboard__status">
            <va-card>
              <va-card-title>Importer Status</va-card-title>
              <va-card-content>
                <div v-if="countsLoading">
                  <OtisLoader />
                </div>
                <div v-else>
                  {{ importerStatus }}
                </div>
              </va-card-content>
            </va-card>
          </div>
        </div>
      </div>
      <div v-if="!displayInitialImport" class="otis-dashboard__setting otis-dashboard__setting--full-width">
        <va-card>
          <va-card-title>Sync All POIs</va-card-title>
          <va-card-content>
            <p>This will sync all relevant POIs that are active in OTIS with WordPress using the Otis filters you have set. This is useful if you find there are POIs that are stale/should have been imported/deleted.</p>
            <p>This will fetch the list of active POIs, check them against the POIs still active in WordPress, and delete the POI post or add one if relevant.</p>
            <p><strong>This will delete POIs if they've been removed from OTIS.</strong></p>
          </va-card-content>
          <va-card-actions>
            <button class="button button-primary" :disabled="importStarting || importActive" @click="triggerSyncPois">
              <span v-if="importStarting || importActive">Sync Running Please Wait...</span>
              <span v-else>Sync POIs</span>
            </button>
          </va-card-actions>
        </va-card>
      </div>
      <div v-if="!displayInitialImport" class="otis-dashboard__setting">
        <va-card>
          <va-card-title>Import Log Preview</va-card-title>
          <va-card-content>
            <div v-if="countsLoading">
              <OtisLoader />
            </div>
            <div v-else>
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
            <div v-if="countsLoading">
              <OtisLoader />
            </div>
            <div v-else>
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
            </div>
          </va-card-content>
        </va-card>
      </div>
      
    </div>
    <div v-if="importStarted" class="otis-dashboard__notifications">
      <va-alert v-model="importStarted" color="success" icon="info" closeable>
        OTIS Importer Started.
      </va-alert>
    </div>
  </div>
</template>

<style scoped src="vuestic-ui/dist/vuestic-ui.css">
</style>

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
      const modifiedDate = ref(new Date());
      const lastImportDate = ref("");
      const importSchedule = ref({});
      const poiCount = ref({});
      const importLog = ref([]);
      const importStarting = ref(false);
      const importStarted = ref(false);
      const logLoading = ref(false);
      const countsLoading = ref(false);
      const activeFilters = ref([]);

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
        const { fetchListings, processListings, deleteListings } = importSchedule.value;
        if ( fetchListings ) return "Fetching Listings";
        if ( processListings ) return "Processing Listings";
        if ( deleteListings ) return "Deleting Listings";
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
      const importActive = computed(() => {
        const { fetchListings, processListings, deleteListings } = importSchedule.value;
        return fetchListings || processListings || deleteListings;
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
            case "importSchedule":
              importSchedule.value = data[key];
              break;
            case "poiCount":
              poiCount.value = data[key];
              break;
            case "activeFilters":
              activeFilters.value = data[key];
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
      const cancelImporter = async () => {
        await triggerAction('otis_cancel_importer');
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
        const modifiedFromDate = modifiedDate.value.toISOString().split('T')[0];
        const importData = {from_date: modifiedFromDate};
        await triggerAction('otis_import', importData);
        await otisStatus();
        notifyImportStarted();
        importStarting.value = false;
      };
      const triggerSyncPois = async () => {
        importStarting.value = true;
        if ( !confirm('Are you sure you want to sync all relevant POIs from OTIS?') ) {
          return;
        }
        await triggerAction("otis_sync_all_pois");
        await otisStatus();
        importStarting.value = false;
      };

      // On Mount
      onMounted(async () => {
        await otisStatus();
        // Setup interval to check for import status
        const interval = setInterval(async () => {
          await otisStatus();
        }, 30000);
      });

      return {
        modifiedDate,
        lastImportDate,
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
        activeFilters,
        importActive,
        poiPostsUrl,
        triggerAction,
        otisStatus,
        otisLogPreview,
        cancelImporter,
        triggerInitialImport,
        triggerModifiedImport,
        triggerSyncPois,
      }
    },
  }
</script>