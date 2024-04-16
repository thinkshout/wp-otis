<!-- CustomAlert.vue -->
<template>
  <va-alert :value="value" @input="updateValue" :color="color" icon="info" closeable>
    <!-- Named slot for the alert message -->
    <slot name="message"></slot>
  </va-alert>
</template>

<script>
export default {
  props: {
    value: {
      type: Boolean,
      default: false
    },
    color: {
      type: String,
      default: 'success'
    }
  },
  methods: {
    updateValue(newValue) {
      this.$emit('update:value', newValue);
    }
  }
}
</script>