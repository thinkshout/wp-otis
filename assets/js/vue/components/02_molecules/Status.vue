<script setup>
  defineProps({
    isFullWidth: Boolean,
  });
</script>

<template>
  <div :class="['otis-dashboard__status', {'otis-dashboard__status--full-width': isFullWidth}]">
    <slot/>
  </div>
</template>