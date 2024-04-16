<!-- Modal.vue -->
<template>
  <va-modal :value="value" @input="updateValue" :title="title" :cancel-text="cancelText" :ok-text="okText" @ok="triggerAction">
    <!-- Named slot for the modal content -->
    <slot name="content"></slot>
  </va-modal>
</template>

<script>
export default {
  props: {
    value: {
      type: Boolean,
      default: false
    },
    title: {
      type: String,
      default: ''
    },
    cancelText: {
      type: String,
      default: ''
    },
    okText: {
      type: String,
      default: ''
    },
    action: {
      type: Function,
      default: () => {}
    }
  },
  methods: {
    updateValue(newValue) {
      this.$emit('update:value', newValue);
    },
    triggerAction() {
      this.$emit('update:value', false);
      this.action();
    }
  }
}
</script>