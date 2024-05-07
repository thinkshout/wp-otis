<script setup>
  defineProps({
    isFullWidth: Boolean,
  });
</script>

<template>
  <div :class="['otis-dashboard__setting', {'otis-dashboard__setting--full-width': isFullWidth}]">
    <slot/>
  </div>
</template>