<script setup lang="ts">
import { onMounted, onUnmounted } from 'vue'
import { RouterView } from 'vue-router'
import { useAuthStore } from './stores/auth'
import { useToastStore } from './stores/toasts'
import { router } from './router'
import ToastRegion from './components/ToastRegion.vue'

const auth = useAuthStore()
const toasts = useToastStore()
function expired() {
  if (auth.user) toasts.show('Sua sessão expirou. Entre novamente para continuar.', 'error')
  auth.clear()
  void router.replace('/login')
}
onMounted(() => window.addEventListener('auth:expired', expired))
onUnmounted(() => window.removeEventListener('auth:expired', expired))
</script>

<template>
  <RouterView />
  <ToastRegion />
</template>
