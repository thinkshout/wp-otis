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
              <button class="button button-primary" :disabled="!dateIsValid || importStarting || importActive || syncAllActive" @click="toggleImportConfirm">
                <span v-if="importStarting">
                  Import Starting Please Wait...
                </span>
                <span v-else-if="importActive">Import Running Please Wait...</span>
                <span v-else-if="syncAllActive">Sync Running Please Wait...</span>
                <span v-else>Start Importing Modified POIs</span>
              </button>
              <button v-if="importActive" class="button button-primary" @click="toggleCancelConfirm">
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
              <va-card-title>Next Import</va-card-title>
              <va-card-content>
                <div v-if="countsLoading">
                  <OtisLoader />
                </div>
                <div v-else>
                  {{ nextImport }}
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
          <div class="otis-dashboard__status">
            <va-card>
              <va-card-title>Stop All Importer Processes</va-card-title>
              <va-card-content>
                <div v-if="countsLoading">
                  <OtisLoader />
                </div>
                <div v-else>
                  <p>Use this to stop all Importer processes and restart standard automatic imports.</p>
                </div>
              </va-card-content>
              <va-card-actions>
                <button class="button button-primary" :disabled="!importActive && !syncAllActive" @click="toggleStopAllConfirm">
                  Stop All Importer Processes
                </button>
              </va-card-actions>
            </va-card>
          </div>
        </div>
      </div>
      <div v-if="!displayInitialImport" class="otis-dashboard__setting otis-dashboard__setting--full-width">
        <va-card>
          <va-card-title>Sync All POIs</va-card-title>
          <va-card-content>
            <p><i><strong>Note:</strong> This is a lengthy and resource intensive process</i></p>
            <p>This will sync all relevant POIs that are active in OTIS with WordPress using the Otis filters you have set. This is useful if you find there are POIs that are stale/should have been imported/deleted.</p>
            <p>This process is split into several actions and each action is split into pages. The process will run until all pages have been processed. You can cancel the process at any time but it will need to be started from the beginning if canceled.</p>
            <p><strong>This process will trash POI posts if they've been removed from OTIS.</strong></p>
          </va-card-content>
          <va-card-actions>
            <button class="button button-primary" :disabled="importStarting || importActive || syncAllActive" @click="toggleSyncConfirm">
              <span v-if="importStarting || importActive || syncAllActive">Sync Running Please Wait...</span>
              <span v-else>Sync POIs</span>
            </button>
            <button v-if="syncAllActive" class="button button-primary" @click="toggleCancelConfirm">
              Cancel Sync All
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
    <va-modal v-model="showSyncModal" title="Confirm Sync All POIs" cancel-text="No, do not start the sync." ok-text="Yes, start the sync process." @ok="triggerSyncPois">
      <p><strong>Are you sure you want to sync all POIs?</strong></p>
      <p>This action could take several hours to complete. You may close this browser window while the sync is running.</p>
    </va-modal>
    <va-modal v-model="showCancelModal" title="Confirm Cancellation" cancel-text="No, continue the process." ok-text="Yes, cancel the process." @ok="cancelImporter">
      <p>Are you sure you want to cancel?</p>
    </va-modal>
    <va-modal v-model="showImportModal" title="Confirm POI Import" cancel-text="No, do not start the import." ok-text="Yes, start the import process." @ok="triggerModifiedImport">
      <p>Are you sure you want to start the importer using the date: {{modifiedDateString}}?</p>
    </va-modal>
    <va-modal v-model="showStopAllModal" title="Confirm Stop All" cancel-text="No, do not stop all processes." ok-text="Yes, stop all import processes." @ok="triggerStopAll">
      <p>Are you sure you want to stop all importer processes and restart automatic imports?</p>
    </va-modal>
  </div>
</template>

<style scoped src="vuestic-ui/dist/vuestic-ui.css">
</style>

<script>
  // Dashboard uses https://vuestic.dev/ UI Framework
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
      const importerActive = ref(false);
      const logLoading = ref(false);
      const countsLoading = ref(false);
      const activeFilters = ref([]);
      const showSyncModal = ref(false);
      const showCancelModal = ref(false);
      const showImportModal = ref(false);
      const showStopAllModal = ref(false);

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
      const nextImport = computed(() => {
        if (!importSchedule.value) return "N/A";
        const { nextScheduledImport } = importSchedule.value;
        if (!nextScheduledImport) return "N/A";
        const nextScheduledImportTimestamp = parseInt(nextScheduledImport) * 1000;
        const nextImportDateObject = new Date(nextScheduledImportTimestamp);
        const month = nextImportDateObject.getMonth() + 1; // months are zero indexed
        const day = nextImportDateObject.getDate();
        const year = nextImportDateObject.getFullYear();
        const hours = nextImportDateObject.getHours();
        const minutes = nextImportDateObject.getMinutes() > 9 ? nextImportDateObject.getMinutes() : "0" + nextImportDateObject.getMinutes();
        return `${month}/${day}/${year} @ ${hours}:${minutes}`;
      });
      const importerStatus = computed(() => {
        const { fetchListings, processListings, deleteListings, syncAllPoisFetch, syncAllPoisProcess, syncAllPoisImport, syncAllPoisTransient } = importSchedule.value;
        if ( fetchListings ) return "Fetching Listings";
        if ( processListings ) return "Processing Listings";
        if ( deleteListings ) return "Deleting Listings";
        if ( syncAllPoisFetch ) return "Fetching POIs for Sync All";
        if ( syncAllPoisProcess ) return "Processing POIs for Sync All";
        if ( syncAllPoisImport ) return "Importing POIs for Sync All";
        if ( syncAllPoisTransient ) return "Creating Transient for Sync All";
        if ( importerActive.value ) return "Active";
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
        return fetchListings || processListings || deleteListings || importerActive.value;
      });
      const syncAllActive = computed(() => {
        const { syncAllPoisFetch, syncAllPoisProcess, syncAllPoisImport, syncAllPoisTransient } = importSchedule.value;
        return syncAllPoisFetch || syncAllPoisProcess || syncAllPoisImport || syncAllPoisTransient;
      });
      const modifiedDateString = computed(() => {
        if (!modifiedDate.value) return "N/A";
        // Return the date in the format of MM/DD/YYYY
        const month = modifiedDate.value.getMonth() + 1; // months are zero indexed
        const day = modifiedDate.value.getDate();
        const year = modifiedDate.value.getFullYear();
        return `${month}/${day}/${year}`;
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
            case "importerActive":
              importerActive.value = data[key];
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
        await triggerAction("otis_sync_all_pois");
        await otisStatus();
        notifyImportStarted();
        importStarting.value = false;
      };
      const triggerStopAll = async () => {
        await triggerAction("otis_stop_all");
        await otisStatus();
      };
      const toggleSyncConfirm = () => {
        showSyncModal.value = !showSyncModal.value;
      };
      const toggleCancelConfirm = () => {
        showCancelModal.value = !showCancelModal.value;
      };
      const toggleImportConfirm = () => {
        showImportModal.value = !showImportModal.value;
      };
      const toggleStopAllConfirm = () => {
        showStopAllModal.value = !showStopAllModal.value;
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
        syncAllActive,
        showSyncModal,
        showCancelModal,
        showImportModal,
        showStopAllModal,
        modifiedDateString,
        importSchedule,
        nextImport,
        importerActive,
        poiPostsUrl,
        triggerAction,
        otisStatus,
        otisLogPreview,
        cancelImporter,
        triggerInitialImport,
        triggerModifiedImport,
        triggerSyncPois,
        triggerStopAll,
        toggleSyncConfirm,
        toggleCancelConfirm,
        toggleImportConfirm,
        toggleStopAllConfirm,
      }
    },
  }
</script>