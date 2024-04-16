<!-- Card.vue -->
<template>

  <!-- Card -->
  <va-card>

    <!-- Title -->
    <va-card-title>
      <slot name="title"></slot>
    </va-card-title>

    <!-- Content -->
    <va-card-content>
      <slot name="content"></slot>
    </va-card-content>

    <!-- Actions -->
    <va-card-actions>
      <slot name="actions"></slot>
    </va-card-actions>

  </va-card>
</template>