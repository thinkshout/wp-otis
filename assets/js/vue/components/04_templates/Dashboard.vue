<template>
  <VaLayout
    :top="{ fixed: false,  order: 2 }"
    :left="{ fixed: false, order: 1 }"
    style="min-height: calc(100vh - 32px);"
  >
    <template #top>
      <VaNavbar
        color="primary"
        :shadowed="true"
      >
        <template #left>
          <VaNavbarItem>
            <h1>WP Otis Dashboard</h1>
          </VaNavbarItem>
        </template>
      </VaNavbar>
    </template>
    <template v-if="!displayInitialImport" #left>
      <VaSidebar
        style="background-color: var(--va-otis-light-blue);"
      >
        <VaSidebarItem :active="activeDashboardView == 'home'" @click="toggleView('home')">
          <VaSidebarItemContent>
            <VaIcon name="home"/>
            <VaSidebarItemTitle>
              Home
            </VaSidebarItemTitle>
          </VaSidebarItemContent>
        </VaSidebarItem>

        <VaSidebarItem :active="activeDashboardView == 'import'" @click="toggleView('import')">
          <VaSidebarItemContent>
            <VaIcon name="input"/>
            <VaSidebarItemTitle>
              Import
            </VaSidebarItemTitle>
          </VaSidebarItemContent>
        </VaSidebarItem>

        <VaSidebarItem :active="activeDashboardView == 'settings'" @click="toggleView('settings')">
          <VaSidebarItemContent>
            <VaIcon name="settings"/>
            <VaSidebarItemTitle>
              Settings
            </VaSidebarItemTitle>
          </VaSidebarItemContent>
        </VaSidebarItem>
      </VaSidebar>
    </template>
    <template #content>
      <div class="otis-dashboard">
        <!-- Initial import -->
        <div v-if="displayInitialImport" class="otis-dashboard__banner">

          <!-- Config Import -->
          <template v-if="displayInitialConfig">
            <OtisConfig
              :importStarting="importStarting"
              :importActive="importActive"
              :syncAllActive="syncAllActive"
              :storedCredentials="storedCredentials"
              :countsLoading="countsLoading"
              @credentials-updated="updateCredentials" 
            />
          </template>

          <!-- POI Import -->
          <template v-else>
            <InitialPOIImport 
              :importStarting="importStarting" :credentialsNeeded="credentialsNeeded" :triggerInitialImport="triggerInitialImport" 
            />
          </template>
        </div>

        <!-- POI import and update, reset and status -->
        <div v-else class="otis-dashboard__settings">
          <!-- Statuses -->
          <DashboardSettingGroup v-if="activeDashboardView == 'home'">
            <!-- Last Import -->
            <DashboardStatus>
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
            </DashboardStatus>

            <!-- Next import -->
            <DashboardStatus>
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
            </DashboardStatus>

            <!-- Status -->
            <DashboardStatus>
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
            </DashboardStatus>
          </DashboardSettingGroup>
          <!-- Log & Counts -->
          <DashboardSettingGroup v-if="activeDashboardView == 'home'">
            <!-- Import log preview -->
            <DashboardSetting>
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
            </DashboardSetting>

            <!-- POI counts -->
            <DashboardSetting>
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
            </DashboardSetting>
          </DashboardSettingGroup>
          <DashboardSettingGroup v-if="activeDashboardView == 'import'">
            <!-- POI import and update -->
            <DashboardSetting>
              <Card>
                <!-- Title -->
                <template #title>
                  POI Import & Update
                </template>

                <!-- Content -->
                <template #content>

                  <p>Start an import of POIs that have been modified since a given date. POIs that already exist on the site will be updated or trashed if the have been updated or deleted on or after that date.</p>
                  <p><em>Note: This will run the importer based on the wp_otis_listings filter if it is set in your theme or a different plugin.</em></p>
                  <OtisFieldset>
                    <VaDateInput v-model="modifiedDate" label="Date To Import From" :rules="dateValidationRules"/>
                  </OtisFieldset>
                </template>

                <!-- Actions -->
                <template #actions>
                  <VaButton color="info" :disabled="!dateIsValid || importStarting || importActive || syncAllActive" @click="toggleImportConfirm"
                  >
                    <span v-if="importStarting">
                      Import Starting Please Wait...
                    </span>
                    <span v-else-if="importActive">Import Running Please Wait...</span>
                    <span v-else-if="syncAllActive">Sync Running Please Wait...</span>
                    <span v-else>Start Importing Modified POIs</span>
                  </VaButton>
                  <VaButton v-if="importActive" color="info" @click="toggleCancelConfirm">
                    Cancel Import
                  </VaButton>
                </template>

              </Card>
            </DashboardSetting>
            <!-- Sync all POIs -->
            <DashboardSetting :isFullWidth="true">
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
                  <VaButton color="info" :disabled="importStarting || importActive || syncAllActive" @click="toggleSyncConfirm">
                    <span v-if="importStarting || importActive || syncAllActive">Sync Running Please Wait...</span>
                    <span v-else>Sync POIs</span>
                  </VaButton>
                  <VaButton v-if="syncAllActive" color="info" @click="toggleCancelConfirm">
                    Cancel Sync All
                  </VaButton>
                </template>
              </Card>
            </DashboardSetting>
          </DashboardSettingGroup>
          <DashboardSettingGroup v-if="activeDashboardView == 'settings'">
            <!-- Reset Importer Processes -->
            <DashboardSetting :isFullWidth="true">
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
                  <VaButton color="info" :disabled="importActive || syncAllActive" @click="toggleStopAllConfirm">
                    Reset Importer Processes
                  </VaButton>
                </template>
              </Card>
            </DashboardSetting>
            <!-- OTIS Credentials -->
            <DashboardSetting>
              <OtisConfig
                :importStarting="importStarting"
                :importActive="importActive"
                :syncAllActive="syncAllActive" 
                :storedCredentials="storedCredentials"
                :countsLoading="countsLoading"
                @credentials-updated="updateCredentials"
              />
            </DashboardSetting>
          </DashboardSettingGroup>    
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
        <Modal v-model="showOtisSyncModal" title="Confirm OTIS config sync" cancel-text="No, do not sync." ok-text="Yes, sync config." @ok="triggerSyncOtisConfig" @cancel="cancelSyncOtisConfig">
          <p>Are you sure you want to save your OTIS credentials?</p>
        </Modal>
      </div>
    </template>
  </VaLayout>
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
  import DashboardSettingGroup from "../02_molecules/SettingGroup.vue";
  import DashboardSetting from "../02_molecules/Setting.vue";
  import DashboardStatus from "../02_molecules/Status.vue";
  import OtisFieldset from '../02_molecules/Fieldset.vue';
  import useApi from "../../composables/useApi";
  import OtisConfig from "../03_organisms/OtisConfig.vue";
  import InitialPOIImport from "../03_organisms/InitialPOIImport.vue";

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
  const pendingCredentials = ref({});
  const showSyncModal = ref(false);
  const showCancelModal = ref(false);
  const showImportModal = ref(false);
  const showStopAllModal = ref(false);
  const showOtisSyncModal = ref(false);
  const { triggerAction } = useApi();
  const displayInitialConfig = ref(true);
  const activeDashboardView = ref("home");
  const dateValidationRules = ref([
    (val) => {
      if (!val) return true;
      return val <= new Date() ? true : "Date must be in the past";
    }
  ]);

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
  const triggerSyncOtisConfig = async () => {
    if (!pendingCredentials.value.username || !pendingCredentials.value.password) return;
    credentials.value = pendingCredentials.value;
    pendingCredentials.value = {};
    await triggerAction("otis_save_credentials", credentials.value );
    await otisStatus();
  };
  const cancelSyncOtisConfig = () => {
    pendingCredentials.value = {};
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
  const updateCredentials = (newCredentials) => {
    if (newCredentials.username && newCredentials.password) {
      pendingCredentials.value = newCredentials;
    }
    toggleConfigSyncConfirm();
  };
  const toggleView = (view) => {
    activeDashboardView.value = view;
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