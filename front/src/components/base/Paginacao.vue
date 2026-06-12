<script setup>
import { computed } from 'vue'
import BaseButton from './BaseButton.vue'
import { ELIPSE, calcularPaginas } from '../../utils/paginacao.js'

const props = defineProps({
  total: { type: Number, required: true },
  itensPorPagina: { type: Number, default: 12 },
  paginaAtual: { type: Number, default: 1 },
})

const emit = defineEmits(['update:paginaAtual'])

const info = computed(() =>
  calcularPaginas(props.total, props.itensPorPagina, props.paginaAtual),
)

const totalPaginas = computed(() => info.value.totalPaginas)
const paginaNormalizada = computed(() => info.value.paginaAtual)
const paginasVisiveis = computed(() => info.value.paginasVisiveis)

const temAnterior = computed(() => paginaNormalizada.value > 1)
const temProxima = computed(() => paginaNormalizada.value < totalPaginas.value)

function ehElipse(item) {
  return item === ELIPSE
}

function irPara(pagina) {
  const alvo = Math.min(Math.max(1, pagina), totalPaginas.value)
  if (alvo !== paginaNormalizada.value) emit('update:paginaAtual', alvo)
}

function anterior() {
  if (temAnterior.value) irPara(paginaNormalizada.value - 1)
}

function proxima() {
  if (temProxima.value) irPara(paginaNormalizada.value + 1)
}
</script>

<template>
  <nav class="paginacao" aria-label="Paginação de resultados">
    <BaseButton
      variant="ghost"
      size="sm"
      :disabled="!temAnterior"
      @click="anterior"
    >
      <svg
        class="paginacao__chevron"
        width="16"
        height="16"
        viewBox="0 0 24 24"
        fill="none"
        stroke="currentColor"
        stroke-width="1.75"
        stroke-linecap="round"
        stroke-linejoin="round"
        aria-hidden="true"
      >
        <path d="m15 18-6-6 6-6" />
      </svg>
      Anterior
    </BaseButton>

    <ul class="paginacao__paginas">
      <li v-for="(item, idx) in paginasVisiveis" :key="`${item}-${idx}`">
        <span v-if="ehElipse(item)" class="paginacao__elipse" aria-hidden="true">{{
          ELIPSE
        }}</span>
        <button
          v-else
          type="button"
          class="paginacao__numero"
          :class="{ 'paginacao__numero--ativo': item === paginaNormalizada }"
          :aria-current="item === paginaNormalizada ? 'page' : undefined"
          :aria-label="`Ir para a página ${item}`"
          @click="irPara(item)"
        >
          {{ item }}
        </button>
      </li>
    </ul>

    <BaseButton
      variant="ghost"
      size="sm"
      :disabled="!temProxima"
      @click="proxima"
    >
      Próxima
      <svg
        class="paginacao__chevron"
        width="16"
        height="16"
        viewBox="0 0 24 24"
        fill="none"
        stroke="currentColor"
        stroke-width="1.75"
        stroke-linecap="round"
        stroke-linejoin="round"
        aria-hidden="true"
      >
        <path d="m9 18 6-6-6-6" />
      </svg>
    </BaseButton>

    <p class="paginacao__contador" aria-live="polite">
      Página {{ paginaNormalizada }} de {{ totalPaginas }}
    </p>
  </nav>
</template>

<style scoped>
.paginacao {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  justify-content: center;
  gap: var(--space-3);
}

.paginacao__chevron {
  flex: none;
}

.paginacao__paginas {
  display: flex;
  align-items: center;
  gap: var(--space-1);
  margin: 0;
  padding: 0;
  list-style: none;
}

.paginacao__paginas > li {
  display: flex;
}

.paginacao__numero {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  min-width: 36px;
  height: 36px;
  padding: 0 var(--space-2);
  border: 1px solid transparent;
  border-radius: var(--radius-md);
  background: transparent;
  color: var(--color-ink);
  font-family: var(--font-mono);
  font-size: var(--fs-sm);
  font-variant-numeric: tabular-nums;
  font-weight: var(--fw-semibold);
  cursor: pointer;
  transition:
    background-color var(--dur-hover) var(--ease-standard),
    color var(--dur-hover) var(--ease-standard),
    box-shadow var(--dur-hover) var(--ease-standard);
}

.paginacao__numero:hover:not(.paginacao__numero--ativo) {
  background: rgba(243, 147, 37, 0.08);
}

.paginacao__numero:focus-visible {
  outline: none;
  box-shadow: var(--shadow-focus);
}

.paginacao__numero--ativo {
  background: var(--color-primary);
  color: var(--color-paper);
  cursor: default;
}

.paginacao__elipse {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  min-width: 24px;
  height: 36px;
  color: var(--color-graphite);
  font-family: var(--font-mono);
  font-size: var(--fs-sm);
  user-select: none;
}

.paginacao__contador {
  margin: 0;
  color: var(--color-graphite);
  font-family: var(--font-mono);
  font-size: var(--fs-sm);
  font-variant-numeric: tabular-nums;
  white-space: nowrap;
}

@media (prefers-reduced-motion: reduce) {
  .paginacao__numero {
    transition: none;
  }
}
</style>
