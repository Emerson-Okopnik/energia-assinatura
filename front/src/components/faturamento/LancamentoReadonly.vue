<script setup>
import { computed } from 'vue'
import BaseBadge from '../base/BaseBadge.vue'
import BaseButton from '../base/BaseButton.vue'
import { formatReais, formatKwh } from '../../utils/formatters'

const props = defineProps({
  // { geracaoKwh, consumoKwh, faturaReais, adicionalReais, valorFinalReais, lancadoEm, lancadoPor }
  dados: { type: Object, required: true },
  competenciaLabel: { type: String, default: '' },
  revertivel: { type: Boolean, default: false },
})

defineEmits(['refaturar', 'reverter', 'pdf'])

const CAMPOS = [
  { key: 'geracaoKwh', label: 'Geração', fmt: (v) => formatKwh(v) },
  { key: 'consumoKwh', label: 'Consumo', fmt: (v) => formatKwh(v) },
  { key: 'faturaReais', label: 'Fatura', fmt: (v) => formatReais(v) },
  { key: 'adicionalReais', label: 'Adicional', fmt: (v) => formatReais(v) },
]

const pares = computed(() =>
  CAMPOS.filter((campo) => props.dados?.[campo.key] != null).map((campo) => ({
    key: campo.key,
    label: campo.label,
    valor: campo.fmt(props.dados[campo.key]),
  }))
)

const lancadoEmFormatado = computed(() => {
  const bruto = props.dados?.lancadoEm
  if (!bruto) return ''
  const data = new Date(bruto)
  if (Number.isNaN(data.getTime())) return String(bruto)
  return data.toLocaleDateString('pt-BR')
})

const carimbo = computed(() => {
  const partes = []
  if (lancadoEmFormatado.value) partes.push(`em ${lancadoEmFormatado.value}`)
  if (props.dados?.lancadoPor) partes.push(`por ${props.dados.lancadoPor}`)
  return partes.join(' ')
})
</script>

<template>
  <section class="lancamento">
    <header class="lancamento__topo">
      <BaseBadge variant="success" dot>Faturado</BaseBadge>
      <span v-if="carimbo" class="lancamento__carimbo">{{ carimbo }}</span>
    </header>

    <dl class="lancamento__pares">
      <div v-for="par in pares" :key="par.key" class="lancamento__par">
        <dt>{{ par.label }}</dt>
        <dd>{{ par.valor }}</dd>
      </div>
    </dl>

    <div v-if="dados.valorFinalReais != null" class="lancamento__final">
      <span class="lancamento__final-label">Valor final faturado</span>
      <span class="lancamento__final-valor">{{ formatReais(dados.valorFinalReais) }}</span>
    </div>

    <footer class="lancamento__acoes">
      <BaseButton variant="danger-soft" @click="$emit('refaturar')">
        Refaturar
      </BaseButton>
      <span
        v-if="!revertivel"
        class="lancamento__reverter-wrap"
        title="Apenas o último lançamento pode ser revertido"
      >
        <BaseButton variant="ghost" class="lancamento__reverter" disabled>
          Reverter lançamento
        </BaseButton>
      </span>
      <BaseButton
        v-else
        variant="ghost"
        class="lancamento__reverter"
        @click="$emit('reverter')"
      >
        Reverter lançamento
      </BaseButton>
      <BaseButton variant="secondary" @click="$emit('pdf')">
        Baixar PDF
      </BaseButton>
    </footer>
  </section>
</template>

<style scoped>
.lancamento {
  display: flex;
  flex-direction: column;
  gap: var(--space-5);
}

.lancamento__topo {
  display: flex;
  align-items: center;
  gap: var(--space-3);
  flex-wrap: wrap;
}

.lancamento__carimbo {
  font-size: var(--fs-sm);
  color: var(--color-slate);
}

.lancamento__pares {
  margin: 0;
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(160px, 1fr));
  gap: var(--space-4);
}

.lancamento__par {
  display: flex;
  flex-direction: column;
  gap: var(--space-1);
}

.lancamento__par dt {
  font-size: var(--fs-xs);
  font-weight: var(--fw-bold);
  text-transform: uppercase;
  letter-spacing: 0.12em;
  color: var(--color-slate);
}

.lancamento__par dd {
  margin: 0;
  font-family: var(--font-mono);
  font-variant-numeric: tabular-nums;
  font-size: var(--fs-body);
  font-weight: var(--fw-bold);
  color: var(--color-ink);
}

.lancamento__final {
  display: flex;
  flex-direction: column;
  gap: var(--space-1);
  padding-top: var(--space-3);
  border-top: 1px solid var(--color-mist);
}

.lancamento__final-label {
  font-size: var(--fs-xs);
  font-weight: var(--fw-bold);
  text-transform: uppercase;
  letter-spacing: 0.12em;
  color: var(--color-graphite);
}

.lancamento__final-valor {
  font-family: var(--font-mono);
  font-variant-numeric: tabular-nums;
  font-size: var(--fs-h4);
  font-weight: var(--fw-bold);
  color: var(--color-success);
}

.lancamento__acoes {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  gap: var(--space-3);
  padding-top: var(--space-4);
  border-top: 1px solid var(--color-mist);
}

.lancamento__reverter-wrap {
  display: inline-flex;
}

.lancamento__reverter {
  color: var(--color-danger);
}
</style>
