<script setup>
import { computed, nextTick, ref, watch } from 'vue'
import BaseButton from '../base/BaseButton.vue'
import { formatReais, formatKwh } from '../../utils/formatters'

const props = defineProps({
  aberto: { type: Boolean, default: false },
  competenciaLabel: { type: String, default: '' },
  // { geracaoKwh, consumoKwh, faturaReais, adicionalReais, valorFinalReais }
  atual: { type: Object, default: null },
  novo: { type: Object, default: null },
})

const emit = defineEmits(['confirmar', 'cancelar'])

const cardRef = ref(null)
const cancelarRef = ref(null)

const CAMPOS = [
  { key: 'geracaoKwh', label: 'Geração', fmt: (v) => formatKwh(v) },
  { key: 'consumoKwh', label: 'Consumo', fmt: (v) => formatKwh(v) },
  { key: 'faturaReais', label: 'Fatura', fmt: (v) => formatReais(v) },
  { key: 'adicionalReais', label: 'Adicional', fmt: (v) => formatReais(v) },
  { key: 'valorFinalReais', label: 'Valor final', fmt: (v) => formatReais(v) },
]

const linhasDiff = computed(() =>
  CAMPOS.filter(
    (campo) => props.atual?.[campo.key] != null || props.novo?.[campo.key] != null
  ).map((campo) => {
    const valorAtual = props.atual?.[campo.key]
    const valorNovo = props.novo?.[campo.key]
    return {
      key: campo.key,
      label: campo.label,
      atual: valorAtual != null ? campo.fmt(valorAtual) : '—',
      novo: valorNovo != null ? campo.fmt(valorNovo) : '—',
      mudou:
        valorAtual != null &&
        valorNovo != null &&
        Number(valorAtual) !== Number(valorNovo),
    }
  })
)

watch(
  () => props.aberto,
  async (aberto) => {
    if (aberto) {
      await nextTick()
      cancelarRef.value?.$el?.focus()
    }
  }
)

function onKeydown(evento) {
  if (evento.key === 'Escape') {
    evento.stopPropagation()
    emit('cancelar')
    return
  }
  if (evento.key !== 'Tab') return
  // Focus-trap simples: mantém o Tab dentro do card
  const focaveis = cardRef.value?.querySelectorAll(
    'button:not([disabled]), [href], input, select, textarea, [tabindex]:not([tabindex="-1"])'
  )
  if (!focaveis || focaveis.length === 0) return
  const primeiro = focaveis[0]
  const ultimo = focaveis[focaveis.length - 1]
  if (evento.shiftKey && document.activeElement === primeiro) {
    evento.preventDefault()
    ultimo.focus()
  } else if (!evento.shiftKey && document.activeElement === ultimo) {
    evento.preventDefault()
    primeiro.focus()
  }
}
</script>

<template>
  <Teleport to="body">
    <div
      v-if="aberto"
      class="refaturar__overlay"
      @click.self="$emit('cancelar')"
      @keydown="onKeydown"
    >
      <div
        ref="cardRef"
        class="refaturar__card"
        role="dialog"
        aria-modal="true"
        aria-labelledby="refaturar-titulo"
      >
        <h2 id="refaturar-titulo" class="refaturar__titulo">
          Refaturar {{ competenciaLabel }}?
        </h2>

        <p class="refaturar__aviso">
          Este mês já foi faturado. Ao confirmar, o lançamento existente será
          substituído pelos novos valores abaixo. Essa ação fica registrada na
          trilha de auditoria.
        </p>

        <table class="refaturar__diff">
          <thead>
            <tr>
              <th scope="col">Campo</th>
              <th scope="col" class="refaturar__num">Lançado</th>
              <th scope="col" class="refaturar__num">Novo</th>
            </tr>
          </thead>
          <tbody>
            <tr
              v-for="linha in linhasDiff"
              :key="linha.key"
              :class="{ 'refaturar__linha--mudou': linha.mudou }"
            >
              <th scope="row">{{ linha.label }}</th>
              <td class="refaturar__num">{{ linha.atual }}</td>
              <td class="refaturar__num">{{ linha.novo }}</td>
            </tr>
            <tr v-if="linhasDiff.length === 0">
              <td colspan="3" class="refaturar__vazio">
                Sem valores para comparar.
              </td>
            </tr>
          </tbody>
        </table>

        <footer class="refaturar__acoes">
          <BaseButton ref="cancelarRef" variant="secondary" @click="$emit('cancelar')">
            Cancelar
          </BaseButton>
          <BaseButton variant="danger" @click="$emit('confirmar')">
            Sim, refaturar
          </BaseButton>
        </footer>
      </div>
    </div>
  </Teleport>
</template>

<style scoped>
.refaturar__overlay {
  position: fixed;
  inset: 0;
  z-index: 1080;
  background: var(--color-ink-overlay);
  display: flex;
  align-items: center;
  justify-content: center;
  padding: var(--space-5);
}

.refaturar__card {
  width: 100%;
  max-width: 520px;
  max-height: calc(100vh - var(--space-8));
  overflow-y: auto;
  background: var(--color-paper);
  border-radius: var(--radius-lg);
  box-shadow: var(--shadow-lg);
  padding: var(--space-6);
  display: flex;
  flex-direction: column;
  gap: var(--space-4);
}

.refaturar__titulo {
  margin: 0;
  font-family: var(--font-display);
  font-size: var(--fs-h4);
  font-weight: var(--fw-extra);
  color: var(--color-ink);
}

.refaturar__aviso {
  margin: 0;
  padding: var(--space-3) var(--space-4);
  background: var(--color-danger-soft);
  color: var(--color-danger);
  border-radius: var(--radius-md);
  font-size: var(--fs-sm);
  line-height: var(--lh-normal);
}

.refaturar__diff {
  width: 100%;
  border-collapse: collapse;
  font-size: var(--fs-sm);
}

.refaturar__diff thead th {
  padding: var(--space-2) var(--space-3);
  font-size: var(--fs-xs);
  font-weight: var(--fw-bold);
  text-transform: uppercase;
  letter-spacing: 0.1em;
  color: var(--color-slate);
  text-align: left;
  border-bottom: 1px solid var(--color-mist);
}

.refaturar__diff tbody th {
  padding: var(--space-2) var(--space-3);
  text-align: left;
  font-weight: var(--fw-medium);
  color: var(--color-graphite);
  border-bottom: 1px solid var(--color-mist);
}

.refaturar__diff tbody td {
  padding: var(--space-2) var(--space-3);
  border-bottom: 1px solid var(--color-mist);
}

.refaturar__diff tbody tr:last-child th,
.refaturar__diff tbody tr:last-child td {
  border-bottom: none;
}

.refaturar__num {
  text-align: right;
  font-family: var(--font-mono);
  font-variant-numeric: tabular-nums;
  white-space: nowrap;
}

.refaturar__linha--mudou th,
.refaturar__linha--mudou td {
  background: var(--color-warning-soft);
  font-weight: var(--fw-bold);
  color: var(--color-ink);
}

.refaturar__vazio {
  text-align: center;
  color: var(--color-slate);
  padding: var(--space-4);
}

.refaturar__acoes {
  display: flex;
  justify-content: flex-end;
  gap: var(--space-3);
  padding-top: var(--space-3);
  border-top: 1px solid var(--color-mist);
}
</style>
