<template>
  
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
</template>

<script setup>
  import Card from '../02_molecules/Card.vue';

  const props = defineProps({
    greeting: {
      type: String,
      default: 'Default greeting',
    },
    testbool: {
      type: Boolean,
      default: false,
    },
    importStarting: {
      type: Boolean,
      default: false,
    },
    importActive: {
      type: Boolean,
      default: false,
    },
    syncAllActive: {
      type: Boolean,
      default: false,
    },
    toggleConfigSyncConfirm: {
      type: Function,
      default: () => {},
    },
  });
</script>