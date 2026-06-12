<script setup>
import { computed } from 'vue'
import DataTable from '../base/DataTable.vue'
import { formatReais, formatKwh, formatNumero } from '../../utils/formatters'

const props = defineProps({
  // shape: { geracao: {...}, consumo_fifo: [...], expiracao: [...] }
  preview: { type: Object, default: null },
  // { tarifa, mediaKwh, menorGeracaoKwh, fioB, percentualLei, rede, descontoRedeKwh }
  parametros: { type: Object, default: null },
})

const linhasParametros = computed(() => {
  const p = props.parametros
  if (!p) return []
  const linhas = []
  if (p.tarifa != null) linhas.push({ label: 'Tarifa', valor: formatReais(p.tarifa, 4) })
  if (p.mediaKwh != null) linhas.push({ label: 'Média contratada', valor: formatKwh(p.mediaKwh) })
  if (p.menorGeracaoKwh != null) linhas.push({ label: 'Menor geração', valor: formatKwh(p.menorGeracaoKwh) })
  if (p.fioB != null) linhas.push({ label: 'Fio B', valor: formatReais(p.fioB, 4) })
  if (p.percentualLei != null) linhas.push({ label: 'Percentual da lei', valor: `${formatNumero(p.percentualLei)}%` })
  if (p.rede != null && p.rede !== '') linhas.push({ label: 'Rede', valor: String(p.rede) })
  if (p.descontoRedeKwh != null) linhas.push({ label: 'Desconto de rede', valor: formatKwh(p.descontoRedeKwh) })
  return linhas
})

const geracao = computed(() => props.preview?.geracao ?? null)

const consumoFifo = computed(() =>
  Array.isArray(props.preview?.consumo_fifo) ? props.preview.consumo_fifo : []
)

const expiracao = computed(() =>
  Array.isArray(props.preview?.expiracao) ? props.preview.expiracao : []
)

const colunasOrigem = [
  { key: 'origem', label: 'Mês de origem' },
  { key: 'kwh', label: 'Energia', numeric: true },
]
</script>

<template>
  <div class="auditoria">
    <details class="auditoria__bloco">
      <summary class="auditoria__summary">
        <svg
          class="auditoria__chevron"
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
          <polyline points="9 6 15 12 9 18" />
        </svg>
        Parâmetros usados neste cálculo
      </summary>
      <div class="auditoria__conteudo">
        <dl v-if="linhasParametros.length" class="auditoria__parametros">
          <div
            v-for="linha in linhasParametros"
            :key="linha.label"
            class="auditoria__parametro"
          >
            <dt>{{ linha.label }}</dt>
            <dd>{{ linha.valor }}</dd>
          </div>
        </dl>
        <p v-else class="auditoria__vazio">
          Nenhum parâmetro disponível para este cálculo.
        </p>
      </div>
    </details>

    <details class="auditoria__bloco">
      <summary class="auditoria__summary">
        <svg
          class="auditoria__chevron"
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
          <polyline points="9 6 15 12 9 18" />
        </svg>
        Detalhamento de auditoria
      </summary>
      <div class="auditoria__conteudo">
        <h4 class="auditoria__titulo">Geração líquida</h4>
        <table v-if="geracao" class="auditoria__tabela">
          <tbody>
            <tr>
              <th scope="row">Geração bruta</th>
              <td>{{ formatKwh(geracao.bruta_kwh) }}</td>
            </tr>
            <tr>
              <th scope="row">Desconto de rede{{ geracao.rede ? ` (${geracao.rede})` : '' }}</th>
              <td>− {{ formatKwh(geracao.desconto_rede_kwh) }}</td>
            </tr>
            <tr>
              <th scope="row">Consumo</th>
              <td>− {{ formatKwh(geracao.consumo_kwh) }}</td>
            </tr>
            <tr class="auditoria__total">
              <th scope="row">Geração líquida</th>
              <td>{{ formatKwh(geracao.liquida_kwh) }}</td>
            </tr>
          </tbody>
        </table>
        <p v-else class="auditoria__vazio">Sem dados de geração para este mês.</p>

        <h4 class="auditoria__titulo">Crédito resgatado por origem (FIFO)</h4>
        <DataTable :columns="colunasOrigem" :rows="consumoFifo">
          <template #cell-kwh="{ value }">{{ formatKwh(value) }}</template>
          <template #empty>Nenhum crédito resgatado neste mês.</template>
        </DataTable>

        <h4 class="auditoria__titulo">Crédito expirado</h4>
        <DataTable :columns="colunasOrigem" :rows="expiracao">
          <template #cell-kwh="{ value }">{{ formatKwh(value) }}</template>
          <template #empty>Nenhum crédito expirado neste mês.</template>
        </DataTable>
      </div>
    </details>
  </div>
</template>

<style scoped>
.auditoria {
  display: flex;
  flex-direction: column;
  gap: var(--space-3);
}

.auditoria__bloco {
  border: 1px solid var(--color-mist);
  border-radius: var(--radius-md);
  background: var(--color-paper);
}

.auditoria__summary {
  display: flex;
  align-items: center;
  gap: var(--space-2);
  padding: var(--space-3) var(--space-4);
  font-size: var(--fs-sm);
  font-weight: var(--fw-bold);
  color: var(--color-ink);
  cursor: pointer;
  list-style: none;
  border-radius: var(--radius-md);
}

.auditoria__summary::-webkit-details-marker {
  display: none;
}

.auditoria__summary:focus-visible {
  outline: none;
  box-shadow: var(--shadow-focus);
}

.auditoria__chevron {
  flex: none;
  color: var(--color-slate);
  transition: transform var(--dur-hover) var(--ease-standard);
}

details[open] > .auditoria__summary .auditoria__chevron {
  transform: rotate(90deg);
}

.auditoria__conteudo {
  padding: 0 var(--space-4) var(--space-4);
  display: flex;
  flex-direction: column;
  gap: var(--space-3);
}

.auditoria__parametros {
  margin: 0;
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(220px, 1fr));
  gap: var(--space-2) var(--space-5);
}

.auditoria__parametro {
  display: flex;
  align-items: baseline;
  justify-content: space-between;
  gap: var(--space-3);
  padding: var(--space-1) 0;
  border-bottom: 1px solid var(--color-mist);
}

.auditoria__parametro dt {
  font-size: var(--fs-sm);
  color: var(--color-graphite);
  font-weight: var(--fw-medium);
}

.auditoria__parametro dd {
  margin: 0;
  font-family: var(--font-mono);
  font-variant-numeric: tabular-nums;
  font-size: var(--fs-sm);
  font-weight: var(--fw-bold);
  color: var(--color-ink);
  white-space: nowrap;
}

.auditoria__titulo {
  margin: var(--space-2) 0 0;
  font-size: var(--fs-xs);
  font-weight: var(--fw-bold);
  text-transform: uppercase;
  letter-spacing: 0.12em;
  color: var(--color-slate);
}

.auditoria__tabela {
  width: 100%;
  border-collapse: collapse;
  font-size: var(--fs-sm);
}

.auditoria__tabela th {
  text-align: left;
  font-weight: var(--fw-medium);
  color: var(--color-graphite);
  padding: var(--space-2) var(--space-3);
  border-bottom: 1px solid var(--color-mist);
}

.auditoria__tabela td {
  text-align: right;
  font-family: var(--font-mono);
  font-variant-numeric: tabular-nums;
  color: var(--color-ink);
  padding: var(--space-2) var(--space-3);
  border-bottom: 1px solid var(--color-mist);
  white-space: nowrap;
}

.auditoria__tabela tr:last-child th,
.auditoria__tabela tr:last-child td {
  border-bottom: none;
}

.auditoria__total th,
.auditoria__total td {
  font-weight: var(--fw-bold);
  color: var(--color-ink);
  border-top: 1px solid var(--color-smoke);
}

.auditoria__vazio {
  margin: 0;
  font-size: var(--fs-sm);
  color: var(--color-slate);
}
</style>
