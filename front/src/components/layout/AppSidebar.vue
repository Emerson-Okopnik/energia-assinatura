<script setup>
import { onBeforeUnmount, onMounted, ref } from 'vue'
import logo from '@/assets/brand/logo-color.png'

const props = defineProps({
  mobileOpen: { type: Boolean, default: false },
  operador: { type: Object, default: () => ({}) },
})

const emit = defineEmits(['close', 'update:mobileOpen'])

const grupos = [
  {
    titulo: 'FATURAMENTO',
    itens: [
      { rotulo: 'Faturar', to: '/faturar', icone: 'file-text' },
      { rotulo: 'Lista de usinas', to: '/usinas', icone: 'sun' },
    ],
  },
  {
    titulo: 'CADASTROS',
    itens: [
      { rotulo: 'Cadastrar usina', to: '/cadastro-usina', icone: 'plus-circle' },
      { rotulo: 'Consumidores', to: '/consumidores', icone: 'users' },
      { rotulo: 'Distribuir créditos', to: '/distribuicao', icone: 'share-2' },
      { rotulo: 'Relatórios', to: '/relatorio', icone: 'bar-chart' },
    ],
  },
]

function fecharDrawer() {
  emit('update:mobileOpen', false)
  emit('close')
}

function aoClicarItem() {
  fecharDrawer()
}

function onKeydown(event) {
  if (event.key === 'Escape' && props.mobileOpen) {
    fecharDrawer()
  }
}

onMounted(() => {
  document.addEventListener('keydown', onKeydown)
})

onBeforeUnmount(() => {
  document.removeEventListener('keydown', onKeydown)
})
</script>

<template>
  <div>
    <transition name="overlay-fade">
      <div
        v-if="mobileOpen"
        class="sidebar-overlay"
        @click="fecharDrawer"
        aria-hidden="true"
      ></div>
    </transition>

    <aside
      class="app-sidebar"
      :class="{ 'is-open': mobileOpen }"
      aria-label="Navegação principal"
    >
      <router-link to="/faturar" class="sidebar-brand" @click="aoClicarItem">
        <img :src="logo" alt="Líder Energy" height="36" />
      </router-link>

      <nav class="sidebar-nav" aria-label="Seções do sistema">
        <div v-for="grupo in grupos" :key="grupo.titulo" class="nav-group">
          <p class="nav-eyebrow">{{ grupo.titulo }}</p>
          <ul class="nav-list">
            <li v-for="item in grupo.itens" :key="item.to">
              <router-link
                :to="item.to"
                class="nav-item"
                @click="aoClicarItem"
              >
                <span class="nav-icon" aria-hidden="true">
                  <!-- file-text -->
                  <svg v-if="item.icone === 'file-text'" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/><line x1="10" y1="9" x2="8" y2="9"/></svg>
                  <!-- sun -->
                  <svg v-else-if="item.icone === 'sun'" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="4"/><path d="M12 2v2M12 20v2M4.93 4.93l1.41 1.41M17.66 17.66l1.41 1.41M2 12h2M20 12h2M6.34 17.66l-1.41 1.41M19.07 4.93l-1.41 1.41"/></svg>
                  <!-- plus-circle -->
                  <svg v-else-if="item.icone === 'plus-circle'" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="16"/><line x1="8" y1="12" x2="16" y2="12"/></svg>
                  <!-- users -->
                  <svg v-else-if="item.icone === 'users'" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87M16 3.13a4 4 0 0 1 0 7.75"/></svg>
                  <!-- share-2 -->
                  <svg v-else-if="item.icone === 'share-2'" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"><circle cx="18" cy="5" r="3"/><circle cx="6" cy="12" r="3"/><circle cx="18" cy="19" r="3"/><line x1="8.59" y1="13.51" x2="15.42" y2="17.49"/><line x1="15.41" y1="6.51" x2="8.59" y2="10.49"/></svg>
                  <!-- bar-chart -->
                  <svg v-else-if="item.icone === 'bar-chart'" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"><line x1="12" y1="20" x2="12" y2="10"/><line x1="18" y1="20" x2="18" y2="4"/><line x1="6" y1="20" x2="6" y2="16"/></svg>
                </span>
                <span class="nav-label">{{ item.rotulo }}</span>
              </router-link>
            </li>
          </ul>
        </div>
      </nav>

      <div class="sidebar-footer">
        <div class="help-card">
          <p class="help-title">Precisa de ajuda?</p>
          <p class="help-text">Fale com o suporte da Líder Energy.</p>
          <a class="help-cta" href="mailto:suporte@grupoarco.cc">Abrir chat</a>
        </div>

      </div>
    </aside>
  </div>
</template>

<style scoped>
.app-sidebar {
  display: flex;
  flex-direction: column;
  height: 100%;
  min-height: 100vh;
  padding: var(--space-6);
  background: var(--color-paper);
  border-right: 1px solid var(--color-mist);
}

.sidebar-brand {
  display: inline-flex;
  align-items: center;
  margin-bottom: var(--space-7);
  border-radius: var(--radius-md);
}

.sidebar-brand img {
  height: 36px;
  width: auto;
  display: block;
}

.sidebar-nav {
  display: flex;
  flex-direction: column;
  gap: var(--space-6);
}

.nav-eyebrow {
  margin: 0 0 var(--space-2);
  padding: 0 var(--space-3);
  font-family: var(--font-body);
  font-size: 11px;
  font-weight: var(--fw-bold);
  letter-spacing: 0.14em;
  text-transform: uppercase;
  color: var(--color-slate);
}

.nav-list {
  list-style: none;
  margin: 0;
  padding: 0;
  display: flex;
  flex-direction: column;
  gap: 2px;
}

.nav-item {
  display: flex;
  align-items: center;
  gap: 10px;
  padding: 10px 12px;
  border-radius: var(--radius-md);
  font-family: var(--font-body);
  font-size: var(--fs-sm);
  font-weight: var(--fw-semibold);
  color: var(--color-graphite);
  text-decoration: none;
  transition:
    background-color var(--dur-hover) var(--ease-standard),
    color var(--dur-hover) var(--ease-standard);
}

.nav-item:hover {
  background: rgba(243, 147, 37, 0.08);
  color: var(--color-ink);
}

.nav-item.router-link-active {
  background: rgba(243, 147, 37, 0.1);
  color: var(--color-primary-deep);
  font-weight: var(--fw-bold);
}

.nav-icon {
  display: inline-flex;
  flex: none;
}

.nav-label {
  min-width: 0;
}

/* ---------- Rodapé ---------- */
.sidebar-footer {
  margin-top: auto;
  padding-top: var(--space-6);
  display: flex;
  flex-direction: column;
  gap: var(--space-4);
}

.help-card {
  background: var(--color-linen);
  border-radius: var(--radius-md);
  padding: var(--space-4);
}

.help-title {
  margin: 0 0 var(--space-1);
  font-family: var(--font-display);
  font-weight: var(--fw-bold);
  font-size: var(--fs-sm);
  color: var(--color-ink);
}

.help-text {
  margin: 0 0 var(--space-3);
  font-size: var(--fs-xs);
  line-height: var(--lh-normal);
  color: var(--color-graphite);
}

.help-cta {
  display: inline-flex;
  align-items: center;
  padding: var(--space-2) var(--space-4);
  border-radius: var(--radius-pill);
  background: var(--color-primary);
  color: var(--color-paper);
  font-family: var(--font-body);
  font-size: var(--fs-xs);
  font-weight: var(--fw-bold);
  text-decoration: none;
  transition: background-color var(--dur-hover) var(--ease-standard);
}

.help-cta:hover {
  background: var(--color-primary-deep);
  color: var(--color-paper);
}

/* ---------- Overlay (mobile) ---------- */
.sidebar-overlay {
  display: none;
}

.overlay-fade-enter-active,
.overlay-fade-leave-active {
  transition: opacity var(--dur-enter) var(--ease-out-quart);
}

.overlay-fade-enter-from,
.overlay-fade-leave-to {
  opacity: 0;
}

/* ---------- Responsivo ---------- */
@media (max-width: 992px) {
  .sidebar-overlay {
    display: block;
    position: fixed;
    inset: 0;
    z-index: 1040;
    background: var(--color-ink-overlay);
    backdrop-filter: blur(16px);
    -webkit-backdrop-filter: blur(16px);
  }

  .app-sidebar {
    position: fixed;
    top: 0;
    left: 0;
    bottom: 0;
    z-index: 1050;
    width: 280px;
    max-width: 85vw;
    transform: translateX(-100%);
    transition: transform var(--dur-enter) var(--ease-out-quart);
    box-shadow: var(--shadow-lg);
  }

  .app-sidebar.is-open {
    transform: translateX(0);
  }
}

@media (prefers-reduced-motion: reduce) {
  .app-sidebar,
  .overlay-fade-enter-active,
  .overlay-fade-leave-active {
    transition: none;
  }
}
</style>
