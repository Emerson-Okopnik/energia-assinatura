<script setup>
defineProps({
  // columns: [{ key, label, align: 'left'|'right'|'center', numeric: Boolean }]
  columns: { type: Array, required: true },
  rows: { type: Array, default: () => [] },
})

function classesDaColuna(coluna) {
  return {
    num: Boolean(coluna.numeric),
    'data-table--right': coluna.align === 'right' || coluna.numeric,
    'data-table--center': coluna.align === 'center',
  }
}
</script>

<template>
  <div class="data-table__wrapper">
    <table class="data-table">
      <thead>
        <tr>
          <th
            v-for="coluna in columns"
            :key="coluna.key"
            scope="col"
            :class="classesDaColuna(coluna)"
          >
            {{ coluna.label }}
          </th>
        </tr>
      </thead>
      <tbody>
        <tr v-if="rows.length === 0">
          <td class="data-table__empty" :colspan="columns.length">
            <slot name="empty">Nenhum registro para exibir.</slot>
          </td>
        </tr>
        <template v-for="(linha, indice) in rows" :key="indice">
          <tr>
            <td
              v-for="coluna in columns"
              :key="coluna.key"
              :class="classesDaColuna(coluna)"
            >
              <slot :name="`cell-${coluna.key}`" :row="linha" :value="linha[coluna.key]" :index="indice">
                {{ linha[coluna.key] }}
              </slot>
            </td>
          </tr>
          <!-- Linha de detalhes opcional: renderizada quando o slot `row-details`
               existe e a linha está marcada com `_detalhes: true`. -->
          <tr v-if="$slots['row-details'] && linha._detalhes" class="data-table__details-row">
            <td class="data-table__details-cell" :colspan="columns.length">
              <slot name="row-details" :row="linha" :index="indice"></slot>
            </td>
          </tr>
        </template>
      </tbody>
    </table>
  </div>
</template>

<style scoped>
.data-table__wrapper {
  width: 100%;
  overflow-x: auto;
}

.data-table {
  width: 100%;
  border-collapse: collapse;
  font-family: var(--font-body);
  font-size: var(--fs-sm);
  color: var(--color-ink);
}

.data-table thead th {
  padding: var(--space-2) var(--space-3);
  font-size: var(--fs-xs);
  font-weight: var(--fw-bold);
  text-transform: uppercase;
  letter-spacing: 0.1em;
  color: var(--color-slate);
  text-align: left; /* default; colunas numéricas/à direita sobrescrevem abaixo */
  border-bottom: 1px solid var(--color-mist);
  white-space: nowrap;
}

/* O cabeçalho segue o alinhamento da coluna (números à direita ficam sobre o valor). */
.data-table thead th.data-table--right {
  text-align: right;
}

.data-table thead th.data-table--center {
  text-align: center;
}

.data-table tbody td {
  padding: var(--space-3);
  border-bottom: 1px solid var(--color-mist);
  vertical-align: middle;
}

.data-table tbody tr:last-child td {
  border-bottom: none;
}

.data-table tbody tr:hover td {
  background: rgba(243, 147, 37, 0.04);
}

.data-table .num {
  font-family: var(--font-mono);
  font-variant-numeric: tabular-nums;
}

.data-table--right {
  text-align: right;
}

.data-table--center {
  text-align: center;
}

.data-table__empty {
  padding: var(--space-6);
  text-align: center;
  color: var(--color-slate);
}

.data-table tbody tr:hover td.data-table__empty {
  background: transparent;
}

.data-table__details-cell {
  padding: 0 var(--space-3) var(--space-3);
}

.data-table tbody tr.data-table__details-row:hover td {
  background: transparent;
}
</style>
