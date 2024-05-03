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
          <va-input v-model="username" placeholder="Enter value" label="Username" />
        </fieldset>

        <!-- Password -->
        <fieldset class="otis-dashboard__fieldset">
          <va-input v-model="password" placeholder="Enter value" label="Password" />
        </fieldset>
      </div>

    </template>
    <template #actions>
      <button class="button button-primary" :disabled="importStarting || importActive || syncAllActive" @click="emitCredentials">
        <span>Update Credentials</span>
      </button>
    </template>
  </Card>
</template>

<script setup>
  import { ref } from "vue";
  import Card from '../02_molecules/Card.vue';

  const username = ref('');
  const password = ref('');

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
      default: {},
    },
  });

  const emit = defineEmits(['credentials']);

  const emitCredentials = () => {
    emit('credentials', { username: username.value, password: password.value });
    props.toggleConfigSyncConfirm();
  };
</script>