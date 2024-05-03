<template>
  
  <Card>
    <template #title>
      OTIS Config
    </template>
    <template #content>

      <!-- Form grid -->
      <div class="otis-dashboard__form-grid">

        <!-- Username -->
        <fieldset class="otis-dashboard__fieldset">
          <va-input v-model="username" placeholder="Enter value" label="Username" aria-describedby="otis-dashboard__description" />
          <p id="otis-dashboard__description"  class="otis-dashboard__description">Current username: {{ storedCredentials.username }}</p>
        </fieldset>

        <!-- Password -->
        <fieldset class="otis-dashboard__fieldset">
          <va-input v-model="password" placeholder="Enter value" label="Password" aria-describedby="otis-dashboard__description" />
          <p id="otis-dashboard__description"  class="otis-dashboard__description">Current password: {{ storedCredentials.password }}</p>
        </fieldset>
      </div>

    </template>
    <template #actions>
      <button class="button button-primary" :disabled="importStarting || importActive || syncAllActive || countsLoading" @click="emitCredentials">
        <span>Update Credentials</span>
      </button>
    </template>
  </Card>
</template>

<script setup>
  import { ref } from "vue";
  import Card from '../02_molecules/Card.vue';

  // Props.
  const props = defineProps({
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
    storedCredentials: {
      type: Object,
      default: { username: '', password: '' },
    },
    countsLoading: {
      type: Boolean,
      default: false,
    },
  });

  // Refs.
  const username = ref('');
  const password = ref('');

  // Emit.
  const emit = defineEmits(['credentials']);
  const emitCredentials = () => {
    emit('credentials', { username: username.value, password: password.value });
    props.toggleConfigSyncConfirm();
  };
</script>