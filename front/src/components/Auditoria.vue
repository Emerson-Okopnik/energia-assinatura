<script setup>
import { ref, computed, onMounted } from 'vue'
import { obterAuditoriaUsinas } from '../services/auditoriaApi'
import { formatReais } from '../utils/formatters'
import StatValue from './base/StatValue.vue'
import AuditoriaUsinaModal from './faturamento/AuditoriaUsinaModal.vue'

const carregando = ref(true)
const erro = ref('')
const totais = ref(null)
const usinas = ref([])
const busca = ref('')
const usiSelecionada = ref(null)
const modalAberto = ref(false)

const filtradas = computed(() => {
  const q = busca.value.trim().toLowerCase()
  if (!q) return usinas.value
  return usinas.value.filter((u) =>
    String(u.uc).toLowerCase().includes(q) || String(u.cliente).toLowerCase().includes(q))
})

function abrir(u) { usiSelecionada.value = u.usi_id; modalAberto.value = true }

onMounted(async () => {
  try {
    const d = await obterAuditoriaUsinas()
    totais.value = d.totais; usinas.value = d.usinas
  } catch (e) {
    erro.value = 'Não foi possível carregar a auditoria.'
  } finally {
    carregando.value = false
  }
})
</script>

<template>
  <div class="auditoria">
    <header class="auditoria__cabecalho">
      <span class="auditoria__eyebrow">Conferencia financeira</span>
      <h1 class="auditoria__titulo">Auditoria de pagamentos</h1>
    </header>

    <!-- Estado de erro -->
    <div v-if="erro" class="auditoria__estado-erro">
      <p>{{ erro }}</p>
    </div>

    <template v-else>
      <!-- Cards de totais -->
      <div class="auditoria__totais">
        <div class="auditoria__stat-card">
          <StatValue
            label="Pago a mais"
            :value="totais ? formatReais(totais.pago_a_mais) : '—'"
            tone="danger"
            :loading="carregando"
          />
        </div>
        <div class="auditoria__stat-card">
          <StatValue
            label="Pago a menos"
            :value="totais ? formatReais(totais.pago_a_menos) : '—'"
            tone="success"
            :loading="carregando"
          />
        </div>
        <div class="auditoria__stat-card">
          <StatValue
            label="Saldo"
            :value="totais ? formatReais(Math.abs(totais.saldo)) : '—'"
            :tone="totais && totais.saldo > 0.01 ? 'danger' : totais && totais.saldo < -0.01 ? 'success' : 'default'"
            :hint="totais && totais.saldo > 0.01 ? 'pagamos a mais' : totais && totais.saldo < -0.01 ? 'pagamos a menos' : ''"
            :loading="carregando"
          />
        </div>
        <div class="auditoria__stat-card">
          <StatValue
            label="Meses inconclusivos"
            :value="totais ? String(totais.total_inconclusivos) : '—'"
            :tone="totais && totais.total_inconclusivos > 0 ? 'warning' : 'default'"
            :loading="carregando"
          />
        </div>
      </div>

      <!-- Campo de busca -->
      <div class="auditoria__busca-wrapper">
        <label for="auditoria-busca" class="auditoria__busca-label">Buscar usina</label>
        <input
          id="auditoria-busca"
          v-model="busca"
          type="search"
          class="auditoria__busca"
          placeholder="Filtrar por UC ou nome do cliente..."
          autocomplete="off"
        />
      </div>

      <!-- Lista de usinas -->
      <div class="auditoria__lista-wrapper">
        <div v-if="carregando" class="auditoria__estado">
          <span class="auditoria__spinner" aria-label="Carregando..."></span>
          <span>Carregando usinas...</span>
        </div>

        <div v-else-if="filtradas.length === 0" class="auditoria__estado">
          Nenhuma usina encontrada.
        </div>

        <ul v-else class="auditoria__lista" role="list">
          <li
            v-for="u in filtradas"
            :key="u.usi_id"
            class="auditoria__item"
            role="button"
            tabindex="0"
            @click="abrir(u)"
            @keydown.enter.space.prevent="abrir(u)"
          >
            <div class="auditoria__item-identidade">
              <span class="auditoria__item-cliente">{{ u.cliente }}</span>
              <span class="auditoria__item-uc">UC {{ u.uc }}</span>
            </div>

            <div class="auditoria__item-numeros">
              <span class="auditoria__item-num">
                <span class="auditoria__item-num-label">Meses divergentes</span>
                <span class="auditoria__item-num-valor">{{ u.meses_divergentes }}</span>
              </span>

              <span class="auditoria__item-num" v-if="u.inconclusivos > 0">
                <span class="auditoria__item-num-label">Inconclusivos</span>
                <span class="auditoria__item-num-valor auditoria__item-num-valor--warning">{{ u.inconclusivos }}</span>
              </span>

              <span class="auditoria__item-saldo" :class="{
                'auditoria__item-saldo--a-mais': u.saldo > 0.01,
                'auditoria__item-saldo--a-menos': u.saldo < -0.01,
                'auditoria__item-saldo--ok': Math.abs(u.saldo) <= 0.01,
              }">
                <template v-if="u.saldo > 0.01">a mais {{ formatReais(u.saldo) }}</template>
                <template v-else-if="u.saldo < -0.01">a menos {{ formatReais(Math.abs(u.saldo)) }}</template>
                <template v-else>ok</template>
              </span>
            </div>

            <svg
              class="auditoria__item-seta"
              width="16" height="16" viewBox="0 0 24 24"
              fill="none" stroke="currentColor" stroke-width="1.75"
              stroke-linecap="round" stroke-linejoin="round"
              aria-hidden="true"
            >
              <polyline points="9 18 15 12 9 6" />
            </svg>
          </li>
        </ul>
      </div>
    </template>

    <AuditoriaUsinaModal
      :aberto="modalAberto"
      :usi-id="usiSelecionada"
      @fechar="modalAberto = false"
    />
  </div>
</template>

<style scoped>
.auditoria {
  display: flex;
  flex-direction: column;
  gap: var(--space-6);
}

/* ---------- cabecalho ---------- */
.auditoria__cabecalho {
  display: flex;
  flex-direction: column;
  gap: var(--space-1);
}

.auditoria__eyebrow {
  font-family: var(--font-body);
  font-size: var(--fs-eyebrow);
  font-weight: var(--fw-bold);
  text-transform: uppercase;
  letter-spacing: 0.14em;
  color: var(--color-primary-deep);
}

.auditoria__titulo {
  margin: 0;
  font-family: var(--font-display);
  font-size: var(--fs-h2);
  font-weight: var(--fw-extra);
  line-height: var(--lh-snug);
  letter-spacing: -0.02em;
  color: var(--color-ink);
}

/* ---------- totais ---------- */
.auditoria__totais {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
  gap: var(--space-4);
}

.auditoria__stat-card {
  padding: var(--space-5);
  background: var(--color-paper);
  border-radius: var(--radius-md);
  border: 1px solid var(--color-mist);
  box-shadow: var(--shadow-xs);
}

/* ---------- busca ---------- */
.auditoria__busca-wrapper {
  display: flex;
  flex-direction: column;
  gap: var(--space-2);
}

.auditoria__busca-label {
  font-family: var(--font-body);
  font-size: var(--fs-xs);
  font-weight: var(--fw-bold);
  text-transform: uppercase;
  letter-spacing: 0.1em;
  color: var(--color-slate);
}

.auditoria__busca {
  width: 100%;
  max-width: 480px;
  padding: var(--space-3) var(--space-4);
  font-family: var(--font-body);
  font-size: var(--fs-body);
  color: var(--color-ink);
  background: var(--color-paper);
  border: 1px solid var(--color-mist);
  border-radius: var(--radius-md);
  transition: border-color var(--dur-hover) var(--ease-standard),
    box-shadow var(--dur-hover) var(--ease-standard);
}

.auditoria__busca:focus {
  outline: none;
  border-color: var(--color-primary);
  box-shadow: var(--shadow-focus);
}

/* ---------- lista ---------- */
.auditoria__lista-wrapper {
  background: var(--color-paper);
  border-radius: var(--radius-md);
  border: 1px solid var(--color-mist);
  box-shadow: var(--shadow-xs);
  overflow: hidden;
}

.auditoria__lista {
  margin: 0;
  padding: 0;
  list-style: none;
}

.auditoria__item {
  display: flex;
  align-items: center;
  gap: var(--space-4);
  padding: var(--space-4) var(--space-5);
  border-bottom: 1px solid var(--color-mist);
  cursor: pointer;
  transition: background-color var(--dur-hover) var(--ease-standard);
}

.auditoria__item:last-child {
  border-bottom: none;
}

.auditoria__item:hover {
  background: rgba(243, 147, 37, 0.04);
}

.auditoria__item:focus-visible {
  outline: none;
  box-shadow: inset 0 0 0 2px var(--color-primary);
}

.auditoria__item-identidade {
  display: flex;
  flex-direction: column;
  gap: var(--space-1);
  min-width: 0;
  flex: 1 1 auto;
}

.auditoria__item-cliente {
  font-family: var(--font-body);
  font-size: var(--fs-body);
  font-weight: var(--fw-semibold);
  color: var(--color-ink);
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}

.auditoria__item-uc {
  font-family: var(--font-mono);
  font-size: var(--fs-xs);
  color: var(--color-slate);
}

.auditoria__item-numeros {
  display: flex;
  align-items: center;
  gap: var(--space-5);
  flex: none;
}

.auditoria__item-num {
  display: flex;
  flex-direction: column;
  gap: var(--space-1);
  align-items: center;
}

.auditoria__item-num-label {
  font-size: var(--fs-xs);
  color: var(--color-slate);
  white-space: nowrap;
}

.auditoria__item-num-valor {
  font-family: var(--font-mono);
  font-size: var(--fs-sm);
  font-weight: var(--fw-bold);
  color: var(--color-ink);
}

.auditoria__item-num-valor--warning {
  color: var(--color-warning);
}

.auditoria__item-saldo {
  font-family: var(--font-mono);
  font-size: var(--fs-sm);
  font-variant-numeric: tabular-nums;
  white-space: nowrap;
  flex: none;
}

.auditoria__item-saldo--a-mais {
  color: var(--color-danger);
}

.auditoria__item-saldo--a-menos {
  color: var(--color-success);
}

.auditoria__item-saldo--ok {
  color: var(--color-slate);
}

.auditoria__item-seta {
  flex: none;
  color: var(--color-smoke);
  transition: color var(--dur-hover) var(--ease-standard);
}

.auditoria__item:hover .auditoria__item-seta {
  color: var(--color-primary);
}

/* ---------- estado (carregando / vazio) ---------- */
.auditoria__estado {
  display: flex;
  align-items: center;
  justify-content: center;
  gap: var(--space-3);
  padding: var(--space-8);
  color: var(--color-slate);
  font-size: var(--fs-sm);
}

.auditoria__estado-erro {
  padding: var(--space-5);
  background: var(--color-danger-soft);
  border-radius: var(--radius-md);
  color: var(--color-danger);
  font-size: var(--fs-sm);
}

.auditoria__spinner {
  display: inline-block;
  width: 20px;
  height: 20px;
  border: 3px solid var(--color-mist);
  border-top-color: var(--color-primary);
  border-radius: 50%;
  animation: auditoria-spin 0.8s linear infinite;
}

@keyframes auditoria-spin {
  to { transform: rotate(360deg); }
}

@media (max-width: 600px) {
  .auditoria__item-numeros {
    display: none;
  }
}
</style>
