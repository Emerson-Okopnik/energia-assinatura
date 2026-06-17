<script setup>
import { ref, watch, computed } from 'vue'
import { obterAuditoriaUsina } from '../../services/auditoriaApi'
import { formatReais } from '../../utils/formatters'
import { rotuloDiferenca, linhaConta } from '../../utils/auditoria'
import StatValue from '../base/StatValue.vue'
import DataTable from '../base/DataTable.vue'
import BaseBadge from '../base/BaseBadge.vue'
import BaseButton from '../base/BaseButton.vue'

const props = defineProps({
  aberto: { type: Boolean, default: false },
  usiId: { type: Number, default: null },
})
const emit = defineEmits(['fechar'])

const carregando = ref(false)
const erro = ref('')
const dados = ref(null)
const contaAberta = ref(new Set())

const colunas = [
  { key: 'competencia', label: 'Mês' },
  { key: 'antes', label: 'Antes', numeric: true },
  { key: 'pago', label: 'Efetivamente pago', numeric: true },
  { key: 'atual', label: 'Atual', numeric: true },
  { key: 'diferenca', label: 'Diferença', numeric: true },
]

function rotuloTexto(m) {
  if (m.status === 'inconclusivo' || m.diferenca == null) return 'inconclusivo (sem fatura)'
  const r = rotuloDiferenca(m.pago, m.atual)
  if (r.tipo === 'igual') return 'ok'
  return (r.tipo === 'a_menos' ? 'pagamos a menos ' : 'pagamos a mais ') + formatReais(r.valor)
}
function rotuloTipo(m) {
  if (m.status === 'inconclusivo' || m.diferenca == null) return 'inconclusivo'
  return rotuloDiferenca(m.pago, m.atual).tipo
}
function conta(m) { return linhaConta(m.termos) }
function toggleConta(comp) {
  const s = new Set(contaAberta.value)
  s.has(comp) ? s.delete(comp) : s.add(comp)
  contaAberta.value = s
}

// Injeta _detalhes nos rows para que DataTable exiba a linha de detalhe
const linhas = computed(() => {
  if (!dados.value?.meses) return []
  return dados.value.meses.map((m) => ({
    ...m,
    _detalhes: contaAberta.value.has(m.competencia),
  }))
})

async function carregar() {
  if (!props.usiId) return
  carregando.value = true; erro.value = ''; dados.value = null; contaAberta.value = new Set()
  try {
    dados.value = await obterAuditoriaUsina(props.usiId)
  } catch (e) {
    erro.value = 'Não foi possível carregar o detalhe da usina.'
  } finally {
    carregando.value = false
  }
}

watch(() => [props.aberto, props.usiId], () => { if (props.aberto) carregar() })
</script>

<template>
  <Teleport to="body">
    <Transition name="auditoria-modal">
      <div
        v-if="aberto"
        class="aud-modal__overlay"
        @click.self="emit('fechar')"
      >
        <div
          class="aud-modal__painel"
          role="dialog"
          aria-modal="true"
          aria-labelledby="aud-modal-titulo"
        >
          <header class="aud-modal__cabecalho">
            <div>
              <span class="aud-modal__eyebrow">Auditoria</span>
              <h2 id="aud-modal-titulo" class="aud-modal__titulo">
                {{ dados?.usina?.cliente || 'Usina' }}
                <span v-if="dados?.usina?.uc" class="aud-modal__uc">UC {{ dados.usina.uc }}</span>
              </h2>
            </div>
            <button
              type="button"
              class="aud-modal__fechar"
              aria-label="Fechar"
              @click="emit('fechar')"
            >
              <svg
                width="20" height="20" viewBox="0 0 24 24" fill="none"
                stroke="currentColor" stroke-width="1.75" stroke-linecap="round"
                stroke-linejoin="round" aria-hidden="true"
              >
                <path d="M18 6 6 18" />
                <path d="m6 6 12 12" />
              </svg>
            </button>
          </header>

          <div class="aud-modal__corpo">
            <!-- Estado de carregamento -->
            <div v-if="carregando" class="aud-modal__estado">
              <span class="aud-modal__spinner" aria-label="Carregando..."></span>
              <span>Carregando detalhe...</span>
            </div>

            <!-- Estado de erro -->
            <div v-else-if="erro" class="aud-modal__estado aud-modal__estado--erro">
              <p>{{ erro }}</p>
              <BaseButton variant="secondary" size="sm" @click="carregar">Tentar de novo</BaseButton>
            </div>

            <!-- Conteúdo -->
            <template v-else-if="dados">
              <!-- Cards de resumo -->
              <div class="aud-modal__resumo">
                <StatValue
                  label="Pago total"
                  :value="formatReais(dados.resumo.pago_total)"
                  :loading="carregando"
                />
                <StatValue
                  label="Atual (correto)"
                  :value="formatReais(dados.resumo.atual_total)"
                  :loading="carregando"
                />
                <StatValue
                  label="Saldo"
                  :value="formatReais(Math.abs(dados.resumo.saldo))"
                  :tone="dados.resumo.saldo > 0.01 ? 'danger' : dados.resumo.saldo < -0.01 ? 'success' : 'default'"
                  :hint="dados.resumo.saldo > 0.01 ? 'pagamos a mais' : dados.resumo.saldo < -0.01 ? 'pagamos a menos' : ''"
                  :loading="carregando"
                />
              </div>

              <!-- Tabela de meses -->
              <DataTable :columns="colunas" :rows="linhas">
                <!-- Mês com badge de inconclusivo -->
                <template #cell-competencia="{ row }">
                  <span class="aud-modal__competencia">{{ row.competencia }}</span>
                  <BaseBadge v-if="row.status === 'inconclusivo'" variant="warning" class="aud-modal__badge">
                    inconclusivo
                  </BaseBadge>
                </template>

                <!-- Antes -->
                <template #cell-antes="{ row }">
                  {{ row.antes != null ? formatReais(row.antes) : '—' }}
                </template>

                <!-- Efetivamente pago -->
                <template #cell-pago="{ row }">
                  {{ row.pago != null ? formatReais(row.pago) : '—' }}
                </template>

                <!-- Atual -->
                <template #cell-atual="{ row }">
                  {{ row.atual != null ? formatReais(row.atual) : '—' }}
                  <BaseButton
                    v-if="row.termos"
                    variant="ghost"
                    size="sm"
                    class="aud-modal__btn-conta"
                    @click="toggleConta(row.competencia)"
                  >
                    {{ contaAberta.has(row.competencia) ? 'ocultar conta' : 'ver conta' }}
                  </BaseButton>
                </template>

                <!-- Diferença -->
                <template #cell-diferenca="{ row }">
                  <span
                    class="aud-modal__diferenca"
                    :class="{
                      'aud-modal__diferenca--a-mais': rotuloTipo(row) === 'a_mais',
                      'aud-modal__diferenca--a-menos': rotuloTipo(row) === 'a_menos',
                      'aud-modal__diferenca--igual': rotuloTipo(row) === 'igual',
                      'aud-modal__diferenca--inconclusivo': rotuloTipo(row) === 'inconclusivo',
                    }"
                  >
                    {{ rotuloTexto(row) }}
                  </span>
                </template>

                <!-- Linha de detalhe da conta -->
                <template #row-details="{ row }">
                  <div class="aud-modal__linha-conta">
                    <span class="aud-modal__linha-conta-label">Cálculo atual:</span>
                    <code class="aud-modal__linha-conta-formula">{{ conta(row) }}</code>
                  </div>
                </template>
              </DataTable>
            </template>
          </div>

          <footer class="aud-modal__rodape">
            <BaseButton variant="ghost" @click="emit('fechar')">Fechar</BaseButton>
          </footer>
        </div>
      </div>
    </Transition>
  </Teleport>
</template>

<style scoped>
.aud-modal__overlay {
  position: fixed;
  inset: 0;
  z-index: 1070;
  background: var(--color-ink-overlay);
  display: flex;
  align-items: flex-start;
  justify-content: center;
  padding: var(--space-7) var(--space-5);
  overflow-y: auto;
}

.aud-modal__painel {
  width: 100%;
  max-width: 860px;
  margin: auto;
  background: var(--color-paper);
  border-radius: var(--radius-xl);
  box-shadow: var(--shadow-lg);
  display: flex;
  flex-direction: column;
  max-height: calc(100vh - var(--space-9));
}

/* ---------- cabeçalho ---------- */
.aud-modal__cabecalho {
  display: flex;
  align-items: flex-start;
  justify-content: space-between;
  gap: var(--space-4);
  padding: var(--space-6) var(--space-6) var(--space-4);
  border-bottom: 1px solid var(--color-mist);
}

.aud-modal__eyebrow {
  font-family: var(--font-body);
  font-size: var(--fs-eyebrow);
  font-weight: var(--fw-bold);
  text-transform: uppercase;
  letter-spacing: 0.14em;
  color: var(--color-primary-deep);
}

.aud-modal__titulo {
  margin: var(--space-1) 0 0;
  font-family: var(--font-display);
  font-size: var(--fs-h4);
  font-weight: var(--fw-extra);
  line-height: var(--lh-snug);
  letter-spacing: -0.01em;
  color: var(--color-ink);
}

.aud-modal__uc {
  font-size: var(--fs-sm);
  font-weight: var(--fw-medium);
  color: var(--color-slate);
  margin-left: var(--space-2);
}

.aud-modal__fechar {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  width: 32px;
  height: 32px;
  flex: none;
  border: none;
  background: transparent;
  border-radius: var(--radius-md);
  color: var(--color-slate);
  cursor: pointer;
  transition: background-color var(--dur-hover) var(--ease-standard);
}

.aud-modal__fechar:hover {
  background: rgba(61, 61, 61, 0.06);
  color: var(--color-ink);
}

.aud-modal__fechar:focus-visible {
  outline: none;
  box-shadow: var(--shadow-focus);
}

/* ---------- corpo ---------- */
.aud-modal__corpo {
  padding: var(--space-5) var(--space-6);
  overflow-y: auto;
  display: flex;
  flex-direction: column;
  gap: var(--space-5);
}

/* ---------- estado (carregando / erro) ---------- */
.aud-modal__estado {
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: var(--space-4);
  padding: var(--space-8) 0;
  color: var(--color-slate);
  font-size: var(--fs-sm);
}

.aud-modal__estado--erro {
  color: var(--color-danger);
}

.aud-modal__spinner {
  display: inline-block;
  width: 24px;
  height: 24px;
  border: 3px solid var(--color-mist);
  border-top-color: var(--color-primary);
  border-radius: 50%;
  animation: aud-spin 0.8s linear infinite;
}

@keyframes aud-spin {
  to { transform: rotate(360deg); }
}

/* ---------- resumo ---------- */
.aud-modal__resumo {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(160px, 1fr));
  gap: var(--space-5);
  padding: var(--space-4) var(--space-5);
  background: var(--color-linen);
  border-radius: var(--radius-md);
}

/* ---------- competencia + badge ---------- */
.aud-modal__competencia {
  display: inline;
  margin-right: var(--space-2);
}

.aud-modal__badge {
  vertical-align: middle;
}

/* ---------- botao ver conta ---------- */
.aud-modal__btn-conta {
  display: block;
  margin-top: var(--space-1);
  font-size: var(--fs-xs);
  padding: var(--space-1) var(--space-2);
}

/* ---------- diferenca ---------- */
.aud-modal__diferenca {
  font-family: var(--font-mono);
  font-size: var(--fs-sm);
  font-variant-numeric: tabular-nums;
}

.aud-modal__diferenca--a-mais {
  color: var(--color-danger);
}

.aud-modal__diferenca--a-menos {
  color: var(--color-success);
}

.aud-modal__diferenca--igual {
  color: var(--color-slate);
}

.aud-modal__diferenca--inconclusivo {
  color: var(--color-warning);
}

/* ---------- linha da conta ---------- */
.aud-modal__linha-conta {
  display: flex;
  flex-direction: column;
  gap: var(--space-1);
  padding: var(--space-2) var(--space-3);
  background: var(--color-linen);
  border-radius: var(--radius-sm);
  border-left: 3px solid var(--color-primary-soft);
}

.aud-modal__linha-conta-label {
  font-size: var(--fs-xs);
  font-weight: var(--fw-bold);
  text-transform: uppercase;
  letter-spacing: 0.1em;
  color: var(--color-slate);
}

.aud-modal__linha-conta-formula {
  font-family: var(--font-mono);
  font-size: var(--fs-sm);
  color: var(--color-ink);
  word-break: break-word;
}

/* ---------- rodapé ---------- */
.aud-modal__rodape {
  display: flex;
  align-items: center;
  justify-content: flex-end;
  padding: var(--space-4) var(--space-6);
  border-top: 1px solid var(--color-mist);
}

/* ---------- transição ---------- */
.auditoria-modal-enter-active,
.auditoria-modal-leave-active {
  transition: opacity var(--dur-enter) var(--ease-out-quart);
}

.auditoria-modal-enter-active .aud-modal__painel,
.auditoria-modal-leave-active .aud-modal__painel {
  transition: transform var(--dur-enter) var(--ease-out-quart),
    opacity var(--dur-enter) var(--ease-out-quart);
}

.auditoria-modal-enter-from,
.auditoria-modal-leave-to {
  opacity: 0;
}

.auditoria-modal-enter-from .aud-modal__painel,
.auditoria-modal-leave-to .aud-modal__painel {
  transform: translateY(8px);
  opacity: 0;
}

@media (prefers-reduced-motion: reduce) {
  .auditoria-modal-enter-active,
  .auditoria-modal-leave-active,
  .auditoria-modal-enter-active .aud-modal__painel,
  .auditoria-modal-leave-active .aud-modal__painel {
    transition: none;
  }

  .auditoria-modal-enter-from .aud-modal__painel,
  .auditoria-modal-leave-to .aud-modal__painel {
    transform: none;
  }
}
</style>
