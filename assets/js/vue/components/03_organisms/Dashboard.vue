<template>
  <div class="otis-dashboard">
    <h1>OTIS Dashboard</h1>

    <!-- Initial import -->
    <div v-if="displayInitialImport" class="otis-dashboard__banner">
      <Card>
        <!-- Title -->
        <template #title>
          <h2>Initial POI Import</h2>
        </template>
        <template #content>
          <LoadingIndicator v-if="importStarting" />
          <p>Start here if this is your first time running the plugin. This interface will let you store your OTIS credentials and start your initial import.</p>
          <p><em>Note: The importer will run based on the wp_otis_listings filter if it is set in your theme or a different plugin.</em></p>
        </template>
        <template #actions>
          <p v-if="importStarting">POI import starting, please wait this usually takes a few minutes...</p>
          <button class="button button-primary" :disabled="importStarting || credentialsNeeded" @click="triggerInitialImport">
            <span v-if="importStarting">
              Import Starting Please Wait...
            </span>
            <span v-else>Start Importing POIs</span>
          </button>
        </template>
      </Card>
    </div>

    <!-- POI import and update, reset and status -->
    <div v-else class="otis-dashboard__settings">
      <div class="otis-dashboard__setting-group">

        <!-- POI import and update -->
        <div class="otis-dashboard__setting">
          <Card>

            <!-- Title -->
            <template #title>
              POI Import & Update
            </template>

            <!-- Content -->
            <template #content>

              <p>Start an import of POIs that have been modified since a given date. POIs that already exist on the site will be updated or trashed if the have been updated or deleted on or after that date.</p>
              <p><em>Note: This will run the importer based on the wp_otis_listings filter if it is set in your theme or a different plugin.</em></p>
              <label for="modified-date">Date To Import From</label>
              <Datepicker
                v-model="modifiedDate"
                :enable-time-picker="false"
                :max-date="maxDate"
                format="MM/dd/yyyy"
              />
            </template>

            <!-- Actions -->
            <template #actions>
              <button class="button button-primary" 
                :disabled="!dateIsValid || importStarting || importActive || syncAllActive" @click="toggleImportConfirm"
              >
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
            </template>

          </Card>
        </div>

        <!-- Statuses and reset -->
        <div class="otis-dashboard__statuses">

          <!-- Last Import -->
          <div class="otis-dashboard__status">
            <Card>
                
                <!-- Title -->
                <template #title>
                  Last Import
                </template>
  
                <!-- Content -->
                <template #content>
                  <div v-if="countsLoading">
                    <LoadingIndicator />
                  </div>
                  <div v-else>
                    {{ lastImport }}
                  </div>
                </template>
            </Card>
          </div>
          
          <!-- Next import -->
          <div class="otis-dashboard__status">
            <Card>
              <template #title>
                Next Import
              </template>
              <template #content>
                <div v-if="countsLoading">
                  <LoadingIndicator />
                </div>
                <div v-else>
                  {{ nextImport }}
                </div>
              </template>
            </Card>
          </div>

          <!-- Status -->
          <div class="otis-dashboard__status">
            <Card>
              <template #title>
                Importer Status
              </template>
              <template #content>
                <div v-if="countsLoading">
                  <LoadingIndicator />
                </div>
                <div v-else>
                  {{ importerStatus }}
                </div>
              </template>
            </Card>
          </div>

          <!-- Reset Importer Processes -->
          <div class="otis-dashboard__status otis-dashboard__status--full-width">
            <Card>
              <template #title>
                Reset Importer Processes
              </template>
              <template #content>
                <div v-if="countsLoading">
                  <LoadingIndicator />
                </div>
                <div v-else>
                  <p>Use this to restart standard automatic imports, useful if automatic imports seem stuck.</p>
                </div>
              </template>
              <template #actions>
                <button class="button button-primary" :disabled="importActive || syncAllActive" @click="toggleStopAllConfirm">
                  Reset Importer Processes
                </button>
              </template>
            </Card>
          </div>
        </div>
      </div>

      <!-- Sync all POIs -->
      <div class="otis-dashboard__setting otis-dashboard__setting--full-width">
        <Card>
          <template #title>
            Sync All POIs
          </template>
          <template #content>
            <p><i><strong>Note:</strong> This is a lengthy and resource intensive process</i></p>
            <p>This will sync all relevant POIs that are active in OTIS with WordPress using the Otis filters you have set. This is useful if you find there are POIs that are stale/should have been imported/deleted.</p>
            <p>This process is split into several actions and each action is split into pages. The process will run until all pages have been processed. You can cancel the process at any time but it will need to be started from the beginning if canceled.</p>
            <p><strong>This process will trash POI posts if they've been removed from OTIS.</strong></p>
          </template>
          <template #actions>
            <button class="button button-primary" :disabled="importStarting || importActive || syncAllActive" @click="toggleSyncConfirm">
              <span v-if="importStarting || importActive || syncAllActive">Sync Running Please Wait...</span>
              <span v-else>Sync POIs</span>
            </button>
            <button v-if="syncAllActive" class="button button-primary" @click="toggleCancelConfirm">
              Cancel Sync All
            </button>
          </template>
        </Card>
      </div>

      <!-- OTIS Config -->
      <div class="otis-dashboard__setting otis-dashboard__setting--full-width">
        <Card>
          <template #title>
            OTIS Config
          </template>
          <template #content>

            <!-- Form grid -->
            <div class="otis-dashboard__form-grid">

              <!-- Login -->
              <fieldset class="otis-dashboard__fieldset">
                <legend class="va-h6">Login</legend>
                <va-input v-model="value" placeholder="1234" label="Current Value" disabled />
                <va-input v-model="value" placeholder="Enter value" label="New Value" />
              </fieldset>
  
              <!-- Login -->
              <fieldset class="otis-dashboard__fieldset">
                <legend class="va-h6">Password</legend>
                <va-input v-model="value" placeholder="a1b2c3" label="Current Value" disabled />
                <va-input v-model="value" placeholder="Enter value" label="New Value" />
              </fieldset>

              <!-- Login -->
              <fieldset class="otis-dashboard__fieldset">
                <legend class="va-h6">Sint cupidatat</legend>
                <va-input v-model="value" placeholder="1KML2" label="Current Value" disabled />
                <va-input v-model="value" placeholder="Enter value" label="New Value" />
              </fieldset>
            </div>

          </template>
          <template #actions>
            <button class="button button-primary" :disabled="importStarting || importActive || syncAllActive" @click="toggleConfigSyncConfirm">
              <span>Sync Config</span>
            </button>
          </template>
        </Card>
      </div>

      <!-- Import log preview -->
      <div class="otis-dashboard__setting">
        <Card>
          <template #title>
            Import Log Preview
          </template>
          <template #content>
            <div v-if="countsLoading">
              <LoadingIndicator />
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
          </template>
          <template #actions>
            <a :href="importLogUrl" role="button" class="button">View Full Import Log</a>
          </template>
        </Card>
      </div>

      <!-- POI counts -->
      <div class="otis-dashboard__setting">
        <Card>
          <template #title>
            POI Counts
          </template>
          <template #content>
            <div v-if="countsLoading">
              <LoadingIndicator />
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
          </template>
        </Card>
      </div>
      
    </div>

    <!-- Notifications -->
    <div v-if="importStarted" class="otis-dashboard__notifications">
      <Alert v-model="importStarted" color="success">
        <template #message>
          OTIS Importer Started.
        </template>
      </Alert>
    </div>

    <!-- Modal - Confirm Sync -->
    <Modal v-model="showSyncModal" title="Confirm Sync All POIs" cancel-text="No, do not start the sync." ok-text="Yes, start the sync process." @ok="triggerSyncPois">
      <p><strong>Are you sure you want to sync all POIs?</strong></p>
      <p>This action could take several hours to complete. You may close this browser window while the sync is running.</p>
    </Modal>

    <!-- Modal - Confirm Cancel -->
    <Modal v-model="showCancelModal" title="Confirm Cancellation" cancel-text="No, continue the process." ok-text="Yes, cancel the process." @ok="cancelImporter">
      <p>Are you sure you want to cancel?</p>
    </Modal>

    <!-- Modal - Confirm import -->
    <Modal v-model="showImportModal" title="Confirm POI Import" cancel-text="No, do not start the import." ok-text="Yes, start the import process." @ok="triggerModifiedImport">
      <p>Are you sure you want to start the importer using the date: {{modifiedDateString}}?</p>
    </Modal>

    <!-- Modal - Confirm Stop All -->
    <Modal v-model="showStopAllModal" title="Confirm Reset" cancel-text="No, do not reset importer." ok-text="Yes, reset importer." @ok="triggerStopAll">
      <p>Are you sure you want to restart automatic imports?</p>
    </Modal>

    <!-- Modal - Confirm OTIS sync -->
    <Modal v-model="showOtisSyncModal" title="Confirm OTIS config sync" cancel-text="No, do not sync." ok-text="Yes, sync config." @ok="triggerSyncOtisConfig">
      <p>Are you sure you want to sync OTIS configuration?</p>
    </Modal>
  </div>
</template>

<style scoped src="vuestic-ui/dist/vuestic-ui.css">
</style>

<script setup>
  // Dashboard uses https://vuestic.dev/ UI Framework
  import { ref, computed, onMounted } from "vue";
  import LoadingIndicator from "../01_atoms/LoadingIndicator.vue";
  import Card from "../02_molecules/Card.vue";
  import Alert from "../02_molecules/Alert.vue";
  import Modal from "../02_molecules/Modal.vue";
  import useApi from "../../composables/useApi";

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
  const credentials = ref({});
  const storedCredentials = ref({});
  const showSyncModal = ref(false);
  const showCancelModal = ref(false);
  const showImportModal = ref(false);
  const showStopAllModal = ref(false);
  const showOtisSyncModal = ref(false);
  const { triggerAction } = useApi();

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
  const credentialsNeeded = computed(() => {
    return credentials.value.username ? false : true;
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
  const notifyImportStarted = () => {
    importStarted.value = true;
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
          importerActive.value = data[key] === '1';
          break;
        case "poiCount":
          poiCount.value = data[key];
          break;
        case "activeFilters":
          activeFilters.value = data[key];
          break;
        case "credentials":
          storedCredentials.value = data[key];
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
  const triggerSyncOtisConfig = () => {
    console.log("Syncing config");
  };
  const toggleSyncConfirm = () => {
    showSyncModal.value = !showSyncModal.value;
  };
  const toggleConfigSyncConfirm = () => {
    showOtisSyncModal.value = !showOtisSyncModal.value;
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
</script>