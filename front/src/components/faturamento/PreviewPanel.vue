<script setup>
import { computed } from 'vue'
import BaseBadge from '../base/BaseBadge.vue'
import BaseButton from '../base/BaseButton.vue'
import StatValue from '../base/StatValue.vue'
import { formatReais, formatKwh, formatNumero } from '../../utils/formatters'
import { partesFormula } from '../../utils/detalhamentoFatura'

const props = defineProps({
  // shape: { termos: {...}, geracao: {...}, reserva: {...}, parametros: {...} }
  preview: { type: Object, default: null },
  loading: { type: Boolean, default: false },
  error: { type: String, default: '' },
  // true quando exibe um lançamento já persistido (ex.: detalhes do Histórico)
  salvo: { type: Boolean, default: false },
})

defineEmits(['retry'])

const termos = computed(() => props.preview?.termos ?? null)

const temExpiracao = computed(
  () => Number(termos.value?.receita_expiracao_reais) > 0
)

const formulaLinha = computed(() => {
  if (!termos.value) return ''
  const partes = partesFormula(termos.value)
  const corpo = partes
    .map((p, i) => {
      const sinal = i === 0 ? '' : p.valor < 0 ? '− ' : '+ '
      return `${sinal}${p.label} ${formatReais(Math.abs(p.valor))}`
    })
    .join(' ')
  return `${corpo} = ${formatReais(termos.value.valor_final_reais)}`
})

const guardadoKwh = computed(() => props.preview?.reserva?.guardado_kwh)
const co2Kg = computed(() => props.preview?.parametros?.co2_evitado_kg)
const arvores = computed(() => props.preview?.parametros?.arvores_equivalentes)
</script>

<template>
  <section class="preview-panel" aria-live="polite">
    <header class="preview-panel__topo">
      <BaseBadge v-if="salvo" variant="success" dot>Lançamento salvo</BaseBadge>
      <BaseBadge v-else variant="warning" dot>Simulação — valores não salvos</BaseBadge>
      <span v-if="loading" class="preview-panel__recalculando">Recalculando…</span>
    </header>

    <!-- Erro -->
    <div v-if="error && !loading" class="preview-panel__erro" role="alert">
      <p class="preview-panel__erro-msg">{{ error }}</p>
      <BaseButton variant="secondary" size="sm" @click="$emit('retry')">
        Tentar de novo
      </BaseButton>
    </div>

    <!-- Carregando -->
    <div v-else-if="loading" class="preview-panel__grid">
      <StatValue label="Fixo" loading />
      <StatValue label="Injetado" loading />
      <StatValue label="Crédito" loading />
      <StatValue label="CUO" loading />
    </div>

    <!-- Vazio -->
    <div v-else-if="!termos" class="preview-panel__vazio">
      <p>
        Preencha a geração e o consumo do mês para ver a simulação do valor a
        faturar.
      </p>
    </div>

    <!-- Conteúdo -->
    <template v-else>
      <div class="preview-panel__grid">
        <StatValue label="Fixo" :value="formatReais(termos.valor_fixo_reais)" />
        <StatValue label="Injetado" :value="formatReais(termos.valor_variavel_reais)" />
        <StatValue label="Crédito" :value="formatReais(termos.credito_reais)" />
        <StatValue label="CUO" tone="danger" :value="formatReais(termos.cuo_reais)" />
        <StatValue
          v-if="temExpiracao"
          label="Crédito expirado"
          :value="formatReais(termos.receita_expiracao_reais)"
        />
      </div>

      <div class="preview-panel__final">
        <span class="preview-panel__final-label">Valor final a receber</span>
        <span class="preview-panel__final-valor">{{ formatReais(termos.valor_final_reais) }}</span>
      </div>

      <p class="preview-panel__formula">{{ formulaLinha }}</p>

      <p class="preview-panel__secundaria">
        <span>
          Energia guardada no mês:
          <strong class="preview-panel__mono">{{ formatKwh(guardadoKwh) }}</strong>
        </span>
        <span v-if="co2Kg != null" class="preview-panel__ambiente">
          CO₂ evitado: {{ formatNumero(co2Kg) }} kg ·
          Árvores equivalentes: {{ formatNumero(arvores) }}
        </span>
      </p>
    </template>
  </section>
</template>

<style scoped>
.preview-panel {
  background: var(--color-linen);
  border-radius: var(--radius-lg);
  padding: var(--space-5);
  display: flex;
  flex-direction: column;
  gap: var(--space-4);
}

.preview-panel__topo {
  display: flex;
  align-items: center;
  gap: var(--space-3);
  flex-wrap: wrap;
}

.preview-panel__recalculando {
  font-size: var(--fs-sm);
  color: var(--color-slate);
}

.preview-panel__grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(140px, 1fr));
  gap: var(--space-4);
}

.preview-panel__final {
  display: flex;
  flex-direction: column;
  gap: var(--space-1);
  padding-top: var(--space-3);
  border-top: 1px solid var(--color-mist);
}

.preview-panel__final-label {
  font-size: var(--fs-xs);
  font-weight: var(--fw-bold);
  text-transform: uppercase;
  letter-spacing: 0.12em;
  color: var(--color-graphite);
}

.preview-panel__final-valor {
  font-family: var(--font-mono);
  font-variant-numeric: tabular-nums;
  font-size: var(--fs-h3);
  font-weight: var(--fw-bold);
  line-height: var(--lh-snug);
  color: var(--color-primary-deep);
}

.preview-panel__formula {
  margin: 0;
  font-family: var(--font-mono);
  font-variant-numeric: tabular-nums;
  font-size: var(--fs-sm);
  color: var(--color-graphite);
  overflow-x: auto;
  white-space: nowrap;
}

.preview-panel__secundaria {
  margin: 0;
  display: flex;
  flex-wrap: wrap;
  gap: var(--space-2) var(--space-5);
  font-size: var(--fs-sm);
  color: var(--color-graphite);
}

.preview-panel__mono {
  font-family: var(--font-mono);
  font-variant-numeric: tabular-nums;
  font-weight: var(--fw-bold);
  color: var(--color-ink);
}

.preview-panel__ambiente {
  color: var(--color-slate);
  font-size: var(--fs-xs);
  align-self: center;
}

.preview-panel__vazio p,
.preview-panel__erro-msg {
  margin: 0;
  font-size: var(--fs-sm);
  color: var(--color-graphite);
}

.preview-panel__erro {
  display: flex;
  flex-direction: column;
  align-items: flex-start;
  gap: var(--space-3);
}

.preview-panel__erro-msg {
  color: var(--color-danger);
}
</style>
