<script setup lang="ts">
import { LayoutDashboard, Plane, ShieldCheck, ArrowUpRight, X, MapPin } from '@lucide/vue'
import AppLogo from './AppLogo.vue'
defineProps<{ open: boolean; isAdmin: boolean }>()
defineEmits<{ close: [] }>()
</script>
<template>
  <div v-if="open" class="sidebar-backdrop" @click="$emit('close')"></div>
  <aside class="sidebar" :class="{ 'sidebar-open': open }" aria-label="Menu principal">
    <div class="sidebar-brand">
      <AppLogo light /><button
        class="icon-button sidebar-close"
        aria-label="Fechar menu"
        @click="$emit('close')"
      >
        <X :size="20" />
      </button>
    </div>
    <div class="workspace">
      <span class="workspace-icon"><Plane :size="19" /></span
      ><span><strong>Viagens corporativas</strong><small>Seu próximo destino, aqui</small></span
      ><span class="workspace-dot"></span>
    </div>
    <p class="nav-label">WORKSPACE</p>
    <nav>
      <RouterLink to="/" class="nav-item" aria-current="page" @click="$emit('close')"
        ><LayoutDashboard :size="19" />Pedidos de viagem<span class="nav-indicator"></span
      ></RouterLink>
    </nav>
    <div class="sidebar-bottom">
      <div class="sidebar-tip">
        <span class="tip-icon"><MapPin :size="21" /></span>
        <h3>Menos burocracia.<br />Mais possibilidades.</h3>
        <p>Todos os detalhes da sua próxima viagem, em um só lugar.</p>
        <span class="tip-route"><span></span><i></i><Plane :size="17" /><i></i><span></span></span>
      </div>
      <div class="sidebar-role">
        <ShieldCheck :size="17" /><span>{{
          isAdmin ? 'Ambiente administrativo' : 'Área do viajante'
        }}</span
        ><ArrowUpRight :size="14" />
      </div>
      <p class="sidebar-footer">Case técnico <span>·</span> Onfly</p>
    </div>
  </aside>
</template>
