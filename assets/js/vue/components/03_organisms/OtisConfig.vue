<template>
  <Card>
    <template #title>
      OTIS Credentials
    </template>
    <template #content>

      <OtisFieldset>
        <va-input v-model="username" type="text" placeholder="Your OTIS Username" label="Username" aria-describedby="otis-dashboard__description" :readonly="hasCredentials" :disabled="hasCredentials" />
        <va-input v-model="password" type="password" placeholder="Your OTIS Password" label="Password" aria-describedby="otis-dashboard__description" :readonly="hasCredentials" :disabled="hasCredentials" />
        <p id="otis-dashboard__description" class="otis-dashboard__description">Please enter your credentials for <a href="https://otis.traveloregon.com/">https://otis.traveloregon.com/</a>.</p>
      </OtisFieldset>

    </template>
    <template #actions>
      <VaButton :disabled="importStarting || importActive || syncAllActive || countsLoading || hasCredentials" color="info" @click="emitCredentials">
        <span>Save Credentials</span>
      </VaButton>
      <VaButton v-if="hasCredentials && !editCredentials" :disabled="!hasCredentials" @click="toggleEditCredentials">
        <span>Edit Credentials</span>
      </VaButton>
      <VaButton v-if="!hasCredentials && editCredentials" color="warning" @click="toggleEditCredentials">
        <span>Cancel</span>
      </VaButton>
    </template>
  </Card>
</template>

<script setup>
  import { ref, onMounted, computed } from "vue";
  import Card from '../02_molecules/Card.vue';
  import OtisFieldset from '../02_molecules/Fieldset.vue';

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
  const editCredentials = ref(false);

  // Computed.
  const hasCredentials = computed(() => {
    return props.storedCredentials.username && props.storedCredentials.password && !editCredentials.value ? true : false;
  });

  // Methods.
  const toggleEditCredentials = () => {
    editCredentials.value = !editCredentials.value;
  };

  // Emit.
  const emit = defineEmits(['credentials-updated']);
  const emitCredentials = () => {
    emit('credentials-updated', { username: username.value, password: password.value });
  };

  // On Mounted.
  onMounted(() => {
    username.value = props.storedCredentials.username;
    password.value = props.storedCredentials.password;
  });
</script>