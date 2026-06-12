<script setup>
import { computed, provide, useId } from 'vue'

const props = defineProps({
  label: { type: String, required: true },
  required: { type: Boolean, default: false },
  optionalLabel: { type: Boolean, default: false },
  hint: { type: String, default: '' },
  error: { type: String, default: '' },
})

const id = useId()
const hintId = `${id}-hint`
const errorId = `${id}-erro`

const describedBy = computed(() => {
  const ids = []
  if (props.error) ids.push(errorId)
  if (props.hint) ids.push(hintId)
  return ids.length ? ids.join(' ') : undefined
})

const fieldContext = {
  id,
  describedBy,
  required: computed(() => props.required),
  invalid: computed(() => Boolean(props.error)),
}

// Controles internos (ex.: NumberInput) podem injetar 'base-field' para
// herdar id, aria-describedby, aria-required e estado de erro.
provide('base-field', fieldContext)
</script>

<template>
  <div class="base-field" :class="{ 'base-field--invalid': Boolean(error) }">
    <label class="base-field__label" :for="id">
      {{ label }}
      <span v-if="required" class="base-field__required" aria-hidden="true">*</span>
      <span v-else-if="optionalLabel" class="base-field__optional">(opcional)</span>
    </label>

    <slot
      :id="id"
      :described-by="describedBy"
      :required="required"
      :invalid="Boolean(error)"
    ></slot>

    <p v-if="error" :id="errorId" class="base-field__error" role="alert">{{ error }}</p>
    <p v-else-if="hint" :id="hintId" class="base-field__hint">{{ hint }}</p>
  </div>
</template>

<style scoped>
.base-field {
  display: flex;
  flex-direction: column;
  gap: var(--space-2);
}

.base-field__label {
  font-family: var(--font-body);
  font-size: var(--fs-sm);
  font-weight: var(--fw-bold);
  color: var(--color-ink);
  line-height: var(--lh-normal);
}

.base-field__required {
  color: var(--color-primary);
  font-weight: var(--fw-extra);
  margin-left: 2px;
}

.base-field__optional {
  color: var(--color-slate);
  font-weight: var(--fw-regular);
  font-size: var(--fs-xs);
  margin-left: var(--space-1);
}

.base-field__hint {
  margin: 0;
  font-size: var(--fs-xs);
  line-height: var(--lh-normal);
  color: var(--color-slate);
}

.base-field__error {
  margin: 0;
  font-size: var(--fs-xs);
  line-height: var(--lh-normal);
  font-weight: var(--fw-semibold);
  color: var(--color-danger);
}
</style>
