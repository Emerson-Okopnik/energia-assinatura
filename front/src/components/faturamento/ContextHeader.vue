<script setup>
import { computed } from 'vue'
import { formatKwh, formatNumero, formatReais } from '../../utils/formatters'
import BaseButton from '../base/BaseButton.vue'

const props = defineProps({
  usina: { type: Object, default: null },
  fioB: { type: [Number, String], default: null },
  percentualLei: { type: [Number, String], default: null },
  reservaTotal: { type: [Number, String], default: null },
})

defineEmits(['trocar-usina'])

const nomeCliente = computed(() => props.usina?.cliente?.nome ?? 'Sem usina selecionada')
const idUsina = computed(() => (props.usina ? `Usina #${props.usina.usi_id}` : ''))

const fioBFormatado = computed(() => (props.fioB == null ? '—' : formatReais(props.fioB)))
const leiFormatada = computed(() =>
  props.percentualLei == null ? '—' : `${formatNumero(props.percentualLei, 0)}%`
)
const reservaFormatada = computed(() =>
  props.reservaTotal == null ? '—' : formatKwh(props.reservaTotal)
)
const toneReserva = computed(() => {
  if (props.reservaTotal == null) return ''
  return Number(props.reservaTotal) < 0
    ? 'context-header__chip-valor--negativo'
    : 'context-header__chip-valor--positivo'
})
</script>

<template>
  <header class="context-header">
    <div class="context-header__inner">
      <div class="context-header__identidade">
        <div class="context-header__textos">
          <h4 class="context-header__cliente">{{ nomeCliente }}</h4>
          <span v-if="idUsina" class="context-header__usina">{{ idUsina }}</span>
        </div>
        <BaseButton variant="ghost" size="sm" @click="$emit('trocar-usina')">
          <svg
            width="18"
            height="18"
            viewBox="0 0 24 24"
            fill="none"
            stroke="currentColor"
            stroke-width="1.75"
            stroke-linecap="round"
            stroke-linejoin="round"
            aria-hidden="true"
          >
            <path d="M16 3h5v5" />
            <path d="M21 3 9 15" />
            <path d="M21 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h6" />
          </svg>
          Trocar usina
        </BaseButton>
      </div>

      <dl class="context-header__chips">
        <div class="context-header__chip">
          <dt class="context-header__chip-label">Fio B</dt>
          <dd class="context-header__chip-valor">{{ fioBFormatado }}</dd>
        </div>
        <div class="context-header__chip">
          <dt class="context-header__chip-label">Lei 14.300</dt>
          <dd class="context-header__chip-valor">{{ leiFormatada }}</dd>
        </div>
        <div class="context-header__chip">
          <dt class="context-header__chip-label">Reserva acumulada</dt>
          <dd class="context-header__chip-valor" :class="toneReserva">
            {{ reservaFormatada }}
          </dd>
        </div>
      </dl>
    </div>
  </header>
</template>

<style scoped>
.context-header {
  position: sticky;
  top: var(--navbar-height, 64px);
  z-index: 20;
  background: rgba(255, 255, 255, 0.82);
  -webkit-backdrop-filter: blur(16px);
  backdrop-filter: blur(16px);
  border-bottom: 1px solid var(--color-mist);
}

.context-header__inner {
  display: flex;
  align-items: center;
  justify-content: space-between;
  flex-wrap: wrap;
  gap: var(--space-3) var(--space-6);
  padding: var(--space-3) var(--space-6);
}

.context-header__identidade {
  display: flex;
  align-items: center;
  gap: var(--space-4);
  min-width: 0;
}

.context-header__textos {
  display: flex;
  flex-direction: column;
  min-width: 0;
}

.context-header__cliente {
  margin: 0;
  font-family: var(--font-display);
  font-size: var(--fs-h4);
  font-weight: var(--fw-extra);
  line-height: var(--lh-snug);
  color: var(--color-ink);
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.context-header__usina {
  font-family: var(--font-mono);
  font-size: var(--fs-xs);
  color: var(--color-slate);
}

.context-header__chips {
  display: flex;
  align-items: stretch;
  flex-wrap: wrap;
  gap: var(--space-2);
  margin: 0;
}

.context-header__chip {
  display: flex;
  flex-direction: column;
  gap: 2px;
  padding: var(--space-1) var(--space-3);
  background: var(--color-linen);
  border: 1px solid var(--color-mist);
  border-radius: var(--radius-md);
  min-width: 96px;
}

.context-header__chip-label {
  font-family: var(--font-body);
  font-size: 11px;
  font-weight: var(--fw-bold);
  text-transform: uppercase;
  letter-spacing: 0.1em;
  color: var(--color-slate);
  white-space: nowrap;
}

.context-header__chip-valor {
  margin: 0;
  font-family: var(--font-mono);
  font-size: var(--fs-sm);
  font-weight: var(--fw-bold);
  color: var(--color-ink);
  font-variant-numeric: tabular-nums;
  white-space: nowrap;
}

.context-header__chip-valor--positivo {
  color: var(--color-success);
}

.context-header__chip-valor--negativo {
  color: var(--color-danger);
}
</style>
