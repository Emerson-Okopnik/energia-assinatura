<script setup>
import { computed, inject, ref, watch } from 'vue'
import { parseDecimalPtBr, formatDecimalPtBr } from '../../utils/numberFormat.js'

const props = defineProps({
  modelValue: { type: Number, default: null },
  prefix: { type: String, default: '' },
  suffix: { type: String, default: '' },
  min: { type: Number, default: null },
  placeholder: { type: String, default: '' },
  disabled: { type: Boolean, default: false },
})

const emit = defineEmits(['update:modelValue'])

// Contexto opcional do BaseField (id, aria-describedby, required, erro)
const field = inject('base-field', null)
const inputId = computed(() => (field ? field.id : undefined))
const describedBy = computed(() => (field ? field.describedBy.value : undefined))
const ariaRequired = computed(() => (field && field.required.value ? 'true' : undefined))
const ariaInvalid = computed(() => (field && field.invalid.value ? 'true' : undefined))

const texto = ref(formatDecimalPtBr(props.modelValue))
const focado = ref(false)

watch(
  () => props.modelValue,
  (novo) => {
    // Não reescreve enquanto o usuário digita
    if (focado.value) return
    texto.value = formatDecimalPtBr(novo)
  }
)

function aoDigitar(evento) {
  // Mantém apenas caracteres válidos para número pt-BR
  const bruto = evento.target.value
  const limpo = bruto.replace(/[^\d.,-]/g, '')
  if (limpo !== bruto) evento.target.value = limpo
  texto.value = limpo
  emit('update:modelValue', parseDecimalPtBr(limpo))
}

function aoFocar() {
  focado.value = true
}

function aoSairDoCampo() {
  focado.value = false
  let numero = parseDecimalPtBr(texto.value)
  if (numero !== null && props.min !== null && numero < props.min) {
    numero = props.min
  }
  texto.value = formatDecimalPtBr(numero)
  emit('update:modelValue', numero)
}
</script>

<template>
  <div
    class="number-input"
    :class="{
      'number-input--disabled': disabled,
      'number-input--invalid': ariaInvalid === 'true',
    }"
  >
    <span v-if="prefix" class="number-input__adorno" aria-hidden="true">{{ prefix }}</span>
    <input
      :id="inputId"
      class="number-input__campo"
      type="text"
      inputmode="decimal"
      autocomplete="off"
      :value="texto"
      :placeholder="placeholder"
      :disabled="disabled"
      :aria-describedby="describedBy"
      :aria-required="ariaRequired"
      :aria-invalid="ariaInvalid"
      @input="aoDigitar"
      @focus="aoFocar"
      @blur="aoSairDoCampo"
    />
    <span v-if="suffix" class="number-input__adorno" aria-hidden="true">{{ suffix }}</span>
  </div>
</template>

<style scoped>
.number-input {
  display: inline-flex;
  align-items: center;
  width: 100%;
  background: var(--color-paper);
  border: 1px solid var(--color-smoke);
  border-radius: var(--radius-md);
  transition:
    border-color var(--dur-hover) var(--ease-standard),
    box-shadow var(--dur-hover) var(--ease-standard);
}

.number-input:focus-within {
  border-color: var(--color-primary);
  box-shadow: var(--shadow-focus);
}

.number-input--invalid {
  border-color: var(--color-danger);
}

.number-input--disabled {
  opacity: 0.4;
  cursor: not-allowed;
}

.number-input__campo {
  flex: 1;
  min-width: 0;
  border: none;
  background: transparent;
  padding: var(--space-3) var(--space-4);
  font-family: var(--font-mono);
  font-size: var(--fs-body);
  color: var(--color-ink);
  text-align: right;
}

.number-input__campo:focus,
.number-input__campo:focus-visible {
  outline: none;
  box-shadow: none;
}

.number-input__campo:disabled {
  cursor: not-allowed;
}

.number-input__campo::placeholder {
  color: var(--color-smoke);
}

.number-input__adorno {
  flex: none;
  padding: 0 var(--space-3);
  font-family: var(--font-body);
  font-size: var(--fs-sm);
  font-weight: var(--fw-semibold);
  color: var(--color-slate);
  user-select: none;
}

.number-input__adorno:first-child {
  padding-right: 0;
}

.number-input__adorno:last-child {
  padding-left: 0;
}
</style>
