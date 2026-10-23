<script setup lang="ts">
import { ref } from 'vue'
import { useRouter } from 'vue-router'
import {
  ArrowRight,
  Check,
  Eye,
  EyeOff,
  LockKeyhole,
  Mail,
  MapPin,
  Plane,
  ShieldCheck,
} from '@lucide/vue'
import AppLogo from '../components/AppLogo.vue'
import LoadingSpinner from '../components/LoadingSpinner.vue'
import { useAuthStore } from '../stores/auth'
import { errorMessage } from '../services/api'

const auth = useAuthStore()
const router = useRouter()
const email = ref('')
const password = ref('')
const visible = ref(false)
const busy = ref(false)
const error = ref('')
function demo(role: 'traveler' | 'admin') {
  email.value = role === 'admin' ? 'admin@onfly.test' : 'rafael@onfly.test'
  password.value = 'Onfly@123'
  error.value = ''
}
async function submit() {
  if (busy.value) return
  busy.value = true
  error.value = ''
  try {
    await auth.login(email.value.trim(), password.value)
    await router.replace('/')
  } catch (err) {
    error.value = errorMessage(err)
  } finally {
    busy.value = false
  }
}
</script>
<template>
  <main class="login-layout">
    <section class="login-story" aria-label="Onfly Travel, gestão de viagens corporativas">
      <AppLogo light /><span class="login-tag"
        ><span></span>VIAGENS CORPORATIVAS, SEM COMPLICAÇÃO</span
      >
      <h1>O trabalho leva<br />você mais longe.<br /><span>A gente também.</span></h1>
      <p class="login-story-copy">
        Da primeira solicitação ao próximo destino.<br />Sua jornada profissional, mais simples.
      </p>
      <div class="travel-art" aria-hidden="true">
        <span class="art-orbit orbit-one"></span><span class="art-orbit orbit-two"></span
        ><span class="art-orbit orbit-three"></span
        ><span class="art-plane"><Plane :size="67" :stroke-width="1.2" /></span>
        <div class="art-location location-start">
          <span><MapPin :size="18" /></span
          ><strong>Belo Horizonte<small>Onde as ideias decolam</small></strong>
        </div>
        <div class="art-ticket">
          <div class="ticket-top">
            <span>PRÓXIMO DESTINO</span
            ><span class="ticket-approved"><Check :size="11" />Aprovado</span>
          </div>
          <div class="ticket-route">
            <strong>BH<small>Seu ponto de partida</small></strong
            ><span><i></i><Plane :size="22" /><i></i></span
            ><strong>∞<small>Novas possibilidades</small></strong>
          </div>
          <div class="ticket-bottom">
            <span><span class="ticket-dot"></span>Mais tempo para o que importa</span
            ><span>ONFLY TRAVEL</span>
          </div>
        </div>
      </div>
      <div class="login-story-footer">
        <ShieldCheck :size="17" /><span>Uma experiência pensada para quem vai além.</span>
      </div>
    </section>
    <section class="login-panel">
      <div class="login-mobile-brand"><AppLogo /></div>
      <div class="login-form-container">
        <span class="welcome-icon"><Plane :size="25" /></span>
        <p class="eyebrow">BEM-VINDO À ONFLY TRAVEL</p>
        <h2>Bom ter você por aqui.</h2>
        <p class="login-subtitle">Entre para planejar sua próxima viagem.</p>
        <form class="login-form" @submit.prevent="submit">
          <div class="field">
            <label for="email">E-mail</label>
            <div class="input-icon">
              <Mail :size="18" /><input
                id="email"
                v-model="email"
                type="email"
                placeholder="voce@empresa.com.br"
                required
                autocomplete="username"
                :disabled="busy"
              />
            </div>
          </div>
          <div class="field">
            <label for="password">Senha</label>
            <div class="input-icon password-input">
              <LockKeyhole :size="18" /><input
                id="password"
                v-model="password"
                :type="visible ? 'text' : 'password'"
                placeholder="Sua senha de acesso"
                required
                autocomplete="current-password"
                :disabled="busy"
              /><button
                type="button"
                class="icon-button"
                :aria-label="visible ? 'Ocultar senha' : 'Mostrar senha'"
                :aria-pressed="visible"
                @click="visible = !visible"
              >
                <component :is="visible ? EyeOff : Eye" :size="18" />
              </button>
            </div>
          </div>
          <div v-if="error" class="form-error" role="alert">{{ error }}</div>
          <button type="submit" class="button button-primary login-submit" :disabled="busy">
            <LoadingSpinner v-if="busy" label="Entrando" />{{
              busy ? 'Entrando…' : 'Entrar na minha conta'
            }}<ArrowRight v-if="!busy" :size="18" />
          </button>
        </form>
        <div class="demo-box">
          <div>
            <span class="demo-label">AMBIENTE DE DEMONSTRAÇÃO</span>
            <p>Explore a experiência de cada perfil.</p>
          </div>
          <div class="demo-actions">
            <button type="button" :disabled="busy" @click="demo('traveler')">
              Sou viajante <ArrowRight :size="14" /></button
            ><button type="button" :disabled="busy" @click="demo('admin')">
              Sou administrador <ArrowRight :size="14" />
            </button>
          </div>
          <small>Os botões preenchem as credenciais. Clique em entrar para continuar.</small>
        </div>
      </div>
      <p class="login-footer">
        Desenvolvido para o case técnico Onfly <span>·</span> {{ new Date().getFullYear() }}
      </p>
    </section>
  </main>
</template>
