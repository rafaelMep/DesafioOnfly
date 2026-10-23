<script setup lang="ts">
withDefaults(defineProps<{ label?: string }>(), { label: 'Carregando' })
</script>
<template>
  <span class="spinner" role="status"
    ><span class="sr-only">{{ label }}</span></span
  >
</template>
