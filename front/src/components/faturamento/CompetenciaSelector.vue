<script setup>
import { computed } from 'vue'

const props = defineProps({
  ano: { type: Number, required: true },
  mes: { type: String, default: '' },
  estadosPorMes: { type: Object, default: () => ({}) },
  minAno: { type: Number, default: 2024 },
})

const emit = defineEmits(['update:ano', 'update:mes'])

// Mesmas chaves usadas pela API/página atual ('janeiro'...'dezembro').
const MESES = [
  { chave: 'janeiro', abrev: 'Jan', nome: 'Janeiro' },
  { chave: 'fevereiro', abrev: 'Fev', nome: 'Fevereiro' },
  { chave: 'marco', abrev: 'Mar', nome: 'Março' },
  { chave: 'abril', abrev: 'Abr', nome: 'Abril' },
  { chave: 'maio', abrev: 'Mai', nome: 'Maio' },
  { chave: 'junho', abrev: 'Jun', nome: 'Junho' },
  { chave: 'julho', abrev: 'Jul', nome: 'Julho' },
  { chave: 'agosto', abrev: 'Ago', nome: 'Agosto' },
  { chave: 'setembro', abrev: 'Set', nome: 'Setembro' },
  { chave: 'outubro', abrev: 'Out', nome: 'Outubro' },
  { chave: 'novembro', abrev: 'Nov', nome: 'Novembro' },
  { chave: 'dezembro', abrev: 'Dez', nome: 'Dezembro' },
]

const maxAno = new Date().getFullYear() + 1

const podeVoltar = computed(() => props.ano > props.minAno)
const podeAvancar = computed(() => props.ano < maxAno)

function mudarAno(delta) {
  const novo = props.ano + delta
  if (novo < props.minAno || novo > maxAno) return
  emit('update:ano', novo)
}

const faturado = (chave) => props.estadosPorMes?.[chave] === 'faturado'

function rotuloMes(mesItem) {
  return faturado(mesItem.chave)
    ? `${mesItem.nome} de ${props.ano} — já faturado`
    : `${mesItem.nome} de ${props.ano} — pendente`
}
</script>

<template>
  <div class="competencia-selector">
    <div class="competencia-selector__ano" role="group" aria-label="Ano da competência">
      <button
        class="competencia-selector__step"
        type="button"
        :disabled="!podeVoltar"
        aria-label="Ano anterior"
        @click="mudarAno(-1)"
      >
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
          <path d="m15 18-6-6 6-6" />
        </svg>
      </button>
      <span class="competencia-selector__ano-valor" aria-live="polite">{{ ano }}</span>
      <button
        class="competencia-selector__step"
        type="button"
        :disabled="!podeAvancar"
        aria-label="Próximo ano"
        @click="mudarAno(1)"
      >
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
          <path d="m9 18 6-6-6-6" />
        </svg>
      </button>
    </div>

    <div class="competencia-selector__meses" role="group" aria-label="Mês da competência">
      <button
        v-for="mesItem in MESES"
        :key="mesItem.chave"
        class="competencia-selector__chip"
        :class="{
          'competencia-selector__chip--selecionado': mesItem.chave === mes,
          'competencia-selector__chip--faturado': faturado(mesItem.chave),
        }"
        type="button"
        :aria-pressed="mesItem.chave === mes ? 'true' : 'false'"
        :aria-label="rotuloMes(mesItem)"
        :title="rotuloMes(mesItem)"
        @click="emit('update:mes', mesItem.chave)"
      >
        <span
          v-if="faturado(mesItem.chave)"
          class="competencia-selector__dot"
          aria-hidden="true"
        ></span>
        {{ mesItem.abrev }}
      </button>
    </div>
  </div>
</template>

<style scoped>
.competencia-selector {
  display: flex;
  align-items: center;
  flex-wrap: wrap;
  gap: var(--space-3) var(--space-5);
  font-family: var(--font-body);
}

/* Stepper de ano */
.competencia-selector__ano {
  display: inline-flex;
  align-items: center;
  gap: var(--space-1);
  padding: var(--space-1);
  background: var(--color-paper);
  border: 1px solid var(--color-mist);
  border-radius: var(--radius-pill);
}

.competencia-selector__ano-valor {
  font-family: var(--font-mono);
  font-size: var(--fs-body);
  font-weight: var(--fw-bold);
  color: var(--color-ink);
  min-width: 56px;
  text-align: center;
  font-variant-numeric: tabular-nums;
}

.competencia-selector__step {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  width: 32px;
  height: 32px;
  padding: 0;
  background: transparent;
  border: none;
  border-radius: var(--radius-pill);
  color: var(--color-graphite);
  cursor: pointer;
  transition:
    background-color var(--dur-hover) var(--ease-standard),
    color var(--dur-hover) var(--ease-standard);
}

.competencia-selector__step:hover:not(:disabled) {
  background: rgba(243, 147, 37, 0.12);
  color: var(--color-primary-deep);
}

.competencia-selector__step:focus-visible {
  outline: none;
  box-shadow: var(--shadow-focus);
}

.competencia-selector__step:disabled {
  opacity: 0.35;
  cursor: not-allowed;
}

/* Chips de mês */
.competencia-selector__meses {
  display: flex;
  flex-wrap: wrap;
  gap: var(--space-2);
}

.competencia-selector__chip {
  display: inline-flex;
  align-items: center;
  gap: var(--space-2);
  padding: var(--space-2) var(--space-4);
  background: var(--color-paper);
  border: 1px solid var(--color-mist);
  border-radius: var(--radius-pill);
  font-family: var(--font-body);
  font-size: var(--fs-sm);
  font-weight: var(--fw-bold);
  color: var(--color-graphite);
  line-height: 1;
  cursor: pointer;
  transition:
    background-color var(--dur-hover) var(--ease-standard),
    border-color var(--dur-hover) var(--ease-standard),
    color var(--dur-hover) var(--ease-standard);
}

.competencia-selector__chip:hover {
  border-color: var(--color-primary);
  color: var(--color-primary-deep);
}

.competencia-selector__chip:focus-visible {
  outline: none;
  box-shadow: var(--shadow-focus);
}

.competencia-selector__chip--faturado {
  background: var(--color-success-soft);
  border-color: var(--color-success-soft);
  color: var(--color-ink);
}

.competencia-selector__dot {
  width: 7px;
  height: 7px;
  flex: none;
  border-radius: 50%;
  background: var(--color-success);
}

.competencia-selector__chip--selecionado,
.competencia-selector__chip--selecionado:hover {
  background: var(--color-primary);
  border-color: var(--color-primary);
  color: var(--color-paper);
}

.competencia-selector__chip--selecionado .competencia-selector__dot {
  background: var(--color-paper);
}

@media (prefers-reduced-motion: reduce) {
  .competencia-selector__step,
  .competencia-selector__chip {
    transition: none;
  }
}
</style>
