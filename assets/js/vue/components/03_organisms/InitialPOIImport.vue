<template>
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
      <VaButton color="info" :disabled="importStarting" @click="triggerInitialImport">
        <span v-if="importStarting">
          Import Starting Please Wait...
        </span>
        <span v-else>Start Importing POIs</span>
      </VaButton>
    </template>
  </Card>
</template>

<script setup>
  import { ref } from "vue";
  import Card from '../02_molecules/Card.vue';
  import LoadingIndicator from "../01_atoms/LoadingIndicator.vue";

  const props = defineProps({
    importStarting: {
      type: Boolean,
      default: false,
    },
    triggerInitialImport: {
      type: Function,
      default: () => {},
    },
  });
</script>