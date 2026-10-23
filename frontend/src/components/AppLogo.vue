<script setup lang="ts">
import { Plane } from '@lucide/vue'
defineProps<{ light?: boolean }>()
</script>

<template>
  <span class="app-logo" :class="{ 'app-logo-light': light }" aria-label="Onfly Travel">
    <span class="logo-symbol"><Plane :size="21" :stroke-width="2.5" aria-hidden="true" /></span>
    <span>onfly<span class="logo-dot">.</span><small>TRAVEL</small></span>
  </span>
</template>
