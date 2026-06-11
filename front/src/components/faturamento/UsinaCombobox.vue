<script setup>
import { computed, ref, useId, watch } from 'vue'
import { formatKwh } from '../../utils/formatters'
import BaseBadge from '../base/BaseBadge.vue'

const props = defineProps({
  usinas: { type: Array, default: () => [] },
  modelValue: { type: [Number, String], default: null },
  placeholder: { type: String, default: 'Buscar usina pelo nome do cliente' },
  // { [usi_id]: 'faturado' | 'pendente' } — estado da competência corrente.
  // Carregado em lote pela página (GET /usina não traz dados do mês).
  estadosMes: { type: Object, default: () => ({}) },
  // Nome do mês corrente para o texto do badge (ex.: "Junho").
  mesLabel: { type: String, default: '' },
})

const emit = defineEmits(['update:modelValue'])

const baseId = useId()
const listboxId = `${baseId}-listbox`

const aberto = ref(false)
const busca = ref('')
const indiceAtivo = ref(-1)
const inputRef = ref(null)
const listaRef = ref(null)

// Remove acentos para a busca acento-insensível.
function normalizar(texto) {
  return String(texto ?? '')
    .normalize('NFD')
    .replace(/[\u0300-\u036f]/g, "")
    .toLowerCase()
}

const usinaSelecionada = computed(() =>
  props.usinas.find((u) => u.usi_id === props.modelValue) ?? null
)

const filtradas = computed(() => {
  const termo = normalizar(busca.value.trim())
  if (!termo) return props.usinas
  return props.usinas.filter((u) => normalizar(u.cliente?.nome).includes(termo))
})

const idOpcao = (usina) => `${baseId}-opt-${usina.usi_id}`

const activeDescendant = computed(() => {
  if (!aberto.value || indiceAtivo.value < 0) return undefined
  const usina = filtradas.value[indiceAtivo.value]
  return usina ? idOpcao(usina) : undefined
})

// Texto exibido no input: busca enquanto aberto; nome selecionado quando fechado.
const textoInput = computed(() => {
  if (aberto.value) return busca.value
  return usinaSelecionada.value?.cliente?.nome ?? ''
})

watch(filtradas, (lista) => {
  if (indiceAtivo.value >= lista.length) indiceAtivo.value = lista.length ? 0 : -1
})

function abrir() {
  if (aberto.value) return
  aberto.value = true
  busca.value = ''
  // Posiciona o destaque na usina já selecionada, se visível.
  const idx = filtradas.value.findIndex((u) => u.usi_id === props.modelValue)
  indiceAtivo.value = idx >= 0 ? idx : filtradas.value.length ? 0 : -1
}

function fechar() {
  aberto.value = false
  busca.value = ''
  indiceAtivo.value = -1
}

function onInput(event) {
  if (!aberto.value) abrir()
  busca.value = event.target.value
  indiceAtivo.value = filtradas.value.length ? 0 : -1
}

function selecionar(usina) {
  emit('update:modelValue', usina.usi_id)
  fechar()
  inputRef.value?.focus()
}

function moverAtivo(delta) {
  if (!aberto.value) {
    abrir()
    return
  }
  const total = filtradas.value.length
  if (!total) return
  indiceAtivo.value = (indiceAtivo.value + delta + total) % total
  rolarParaAtivo()
}

function rolarParaAtivo() {
  const usina = filtradas.value[indiceAtivo.value]
  if (!usina) return
  const el = listaRef.value?.querySelector(`#${CSS.escape(idOpcao(usina))}`)
  el?.scrollIntoView({ block: 'nearest' })
}

function onKeydown(event) {
  switch (event.key) {
    case 'ArrowDown':
      event.preventDefault()
      moverAtivo(1)
      break
    case 'ArrowUp':
      event.preventDefault()
      moverAtivo(-1)
      break
    case 'Enter':
      if (aberto.value && indiceAtivo.value >= 0 && filtradas.value[indiceAtivo.value]) {
        event.preventDefault()
        selecionar(filtradas.value[indiceAtivo.value])
      }
      break
    case 'Escape':
      if (aberto.value) {
        event.preventDefault()
        fechar()
      }
      break
    default:
      break
  }
}

function onBlur(event) {
  // Mantém aberto se o foco foi para dentro da lista (clique numa opção).
  if (event.relatedTarget && listaRef.value?.contains(event.relatedTarget)) return
  fechar()
}
</script>

<template>
  <div class="usina-combobox">
    <div class="usina-combobox__campo">
      <svg
        class="usina-combobox__lupa"
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
        <circle cx="11" cy="11" r="8" />
        <path d="m21 21-4.3-4.3" />
      </svg>
      <input
        ref="inputRef"
        class="usina-combobox__input"
        type="text"
        role="combobox"
        :aria-expanded="aberto ? 'true' : 'false'"
        :aria-controls="listboxId"
        :aria-activedescendant="activeDescendant"
        aria-autocomplete="list"
        aria-label="Buscar e selecionar usina"
        autocomplete="off"
        :placeholder="placeholder"
        :value="textoInput"
        @input="onInput"
        @focus="abrir"
        @click="abrir"
        @keydown="onKeydown"
        @blur="onBlur"
      />
      <svg
        class="usina-combobox__seta"
        :class="{ 'usina-combobox__seta--aberta': aberto }"
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
        <path d="m6 9 6 6 6-6" />
      </svg>
    </div>

    <ul
      v-show="aberto"
      :id="listboxId"
      ref="listaRef"
      class="usina-combobox__lista"
      role="listbox"
      aria-label="Usinas"
      tabindex="-1"
    >
      <li
        v-for="(usina, index) in filtradas"
        :id="idOpcao(usina)"
        :key="usina.usi_id"
        class="usina-combobox__opcao"
        :class="{
          'usina-combobox__opcao--ativa': index === indiceAtivo,
          'usina-combobox__opcao--selecionada': usina.usi_id === modelValue,
        }"
        role="option"
        :aria-selected="usina.usi_id === modelValue ? 'true' : 'false'"
        @mousedown.prevent="selecionar(usina)"
        @mousemove="indiceAtivo = index"
      >
        <span class="usina-combobox__nome">{{ usina.cliente?.nome }}</span>
        <BaseBadge
          v-if="estadosMes[usina.usi_id]"
          class="usina-combobox__estado"
          :variant="estadosMes[usina.usi_id] === 'faturado' ? 'success' : 'warning'"
          dot
        >
          {{ mesLabel }} {{ estadosMes[usina.usi_id] === 'faturado' ? 'faturado' : 'pendente' }}
        </BaseBadge>
        <span class="usina-combobox__media">{{ formatKwh(usina.dado_geracao?.media ?? 0) }}</span>
      </li>
      <li v-if="!filtradas.length" class="usina-combobox__vazio" role="presentation">
        Nenhuma usina encontrada
      </li>
    </ul>
  </div>
</template>

<style scoped>
.usina-combobox {
  position: relative;
  width: 100%;
  font-family: var(--font-body);
}

.usina-combobox__campo {
  position: relative;
  display: flex;
  align-items: center;
}

.usina-combobox__lupa {
  position: absolute;
  left: var(--space-3);
  color: var(--color-slate);
  pointer-events: none;
}

.usina-combobox__seta {
  position: absolute;
  right: var(--space-3);
  color: var(--color-slate);
  pointer-events: none;
  transition: transform var(--dur-hover) var(--ease-standard);
}

.usina-combobox__seta--aberta {
  transform: rotate(180deg);
}

.usina-combobox__input {
  width: 100%;
  padding: var(--space-3) var(--space-8) var(--space-3) var(--space-8);
  padding-left: calc(var(--space-3) + 18px + var(--space-2));
  background: var(--color-paper);
  border: 1px solid var(--color-mist);
  border-radius: var(--radius-md);
  font-family: var(--font-body);
  font-size: var(--fs-body);
  color: var(--color-ink);
  line-height: var(--lh-normal);
  transition:
    border-color var(--dur-hover) var(--ease-standard),
    box-shadow var(--dur-hover) var(--ease-standard);
}

.usina-combobox__input::placeholder {
  color: var(--color-slate);
}

.usina-combobox__input:focus {
  outline: none;
  border-color: var(--color-primary);
  box-shadow: var(--shadow-focus);
}

.usina-combobox__lista {
  position: absolute;
  z-index: 30;
  top: calc(100% + var(--space-1));
  left: 0;
  right: 0;
  margin: 0;
  padding: var(--space-1);
  list-style: none;
  background: var(--color-paper);
  border: 1px solid var(--color-mist);
  border-radius: var(--radius-md);
  box-shadow: var(--shadow-md);
  max-height: 320px;
  overflow-y: auto;
}

.usina-combobox__opcao {
  display: flex;
  align-items: baseline;
  justify-content: space-between;
  gap: var(--space-3);
  padding: var(--space-2) var(--space-3);
  border-radius: var(--radius-sm);
  cursor: pointer;
}

.usina-combobox__opcao--ativa {
  background: rgba(243, 147, 37, 0.1);
}

.usina-combobox__opcao--selecionada .usina-combobox__nome {
  color: var(--color-primary-deep);
  font-weight: var(--fw-bold);
}

.usina-combobox__nome {
  flex: 1;
  font-size: var(--fs-body);
  font-weight: var(--fw-semibold);
  color: var(--color-ink);
  min-width: 0;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.usina-combobox__estado {
  flex: none;
}

.usina-combobox__media {
  flex: none;
  font-family: var(--font-mono);
  font-size: var(--fs-sm);
  color: var(--color-graphite);
  font-variant-numeric: tabular-nums;
}

.usina-combobox__vazio {
  padding: var(--space-4) var(--space-3);
  font-size: var(--fs-sm);
  color: var(--color-slate);
  text-align: center;
}

@media (prefers-reduced-motion: reduce) {
  .usina-combobox__input,
  .usina-combobox__seta {
    transition: none;
  }
}
</style>
