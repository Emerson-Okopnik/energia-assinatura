<script setup>
defineProps({
  variant: {
    type: String,
    default: 'primary',
    validator: (v) => ['primary', 'secondary', 'ghost', 'danger', 'danger-soft'].includes(v),
  },
  size: {
    type: String,
    default: 'md',
    validator: (v) => ['sm', 'md'].includes(v),
  },
  loading: { type: Boolean, default: false },
  disabled: { type: Boolean, default: false },
  glow: { type: Boolean, default: false },
  type: { type: String, default: 'button' },
})
</script>

<template>
  <button
    class="base-btn"
    :class="[`base-btn--${variant}`, `base-btn--${size}`, { 'base-btn--glow': glow }]"
    :type="type"
    :disabled="disabled || loading"
    :aria-busy="loading ? 'true' : undefined"
  >
    <span v-if="loading" class="base-btn__spinner" aria-hidden="true"></span>
    <span class="base-btn__label"><slot></slot></span>
  </button>
</template>

<style scoped>
.base-btn {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  gap: var(--space-2);
  border: 1px solid transparent;
  border-radius: var(--radius-pill);
  font-family: var(--font-body);
  font-weight: var(--fw-bold);
  line-height: 1;
  cursor: pointer;
  white-space: nowrap;
  transition:
    background-color var(--dur-hover) var(--ease-standard),
    border-color var(--dur-hover) var(--ease-standard),
    color var(--dur-hover) var(--ease-standard),
    box-shadow var(--dur-hover) var(--ease-standard),
    transform 80ms var(--ease-standard);
}

.base-btn:active:not(:disabled) {
  transform: scale(0.98);
}

.base-btn:focus-visible {
  outline: none;
  box-shadow: var(--shadow-focus);
}

.base-btn:disabled {
  opacity: 0.4;
  cursor: not-allowed;
}

/* Tamanhos */
.base-btn--md {
  font-size: var(--fs-body);
  padding: var(--space-3) var(--space-6);
}

.base-btn--sm {
  font-size: var(--fs-sm);
  padding: var(--space-2) var(--space-4);
}

/* Variantes */
.base-btn--primary {
  background: var(--color-primary);
  color: var(--color-paper);
}

.base-btn--primary:hover:not(:disabled) {
  background: var(--color-primary-deep);
}

.base-btn--primary.base-btn--glow:not(:disabled) {
  box-shadow: var(--shadow-glow);
}

.base-btn--secondary {
  background: var(--color-paper);
  border-color: var(--color-mist);
  color: var(--color-ink);
}

.base-btn--secondary:hover:not(:disabled) {
  background: rgba(243, 147, 37, 0.08);
  border-color: var(--color-primary);
}

.base-btn--ghost {
  background: transparent;
  color: var(--color-ink);
}

.base-btn--ghost:hover:not(:disabled) {
  background: rgba(61, 61, 61, 0.06);
}

.base-btn--danger {
  background: var(--color-danger);
  color: var(--color-paper);
}

.base-btn--danger:hover:not(:disabled) {
  background: #a93226;
}

.base-btn--danger-soft {
  background: var(--color-danger-soft);
  color: var(--color-danger);
}

.base-btn--danger-soft:hover:not(:disabled) {
  background: var(--color-danger);
  color: var(--color-paper);
}

/* Spinner de carregamento */
.base-btn__spinner {
  width: 14px;
  height: 14px;
  flex: none;
  border: 2px solid currentColor;
  border-right-color: transparent;
  border-radius: 50%;
  animation: base-btn-spin 0.7s linear infinite;
}

@keyframes base-btn-spin {
  to {
    transform: rotate(360deg);
  }
}

@media (prefers-reduced-motion: reduce) {
  .base-btn {
    transition: none;
  }
  .base-btn:active:not(:disabled) {
    transform: none;
  }
}
</style>
