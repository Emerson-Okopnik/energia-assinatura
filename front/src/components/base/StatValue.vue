<script setup>
defineProps({
  label: { type: String, required: true },
  value: { type: String, default: '' },
  tone: {
    type: String,
    default: 'default',
    validator: (v) => ['default', 'success', 'danger', 'warning'].includes(v),
  },
  loading: { type: Boolean, default: false },
  hint: { type: String, default: '' },
})
</script>

<template>
  <div class="stat-value" :class="`stat-value--${tone}`">
    <span class="stat-value__label">{{ label }}</span>
    <span v-if="loading" class="stat-value__skeleton" aria-hidden="true"></span>
    <span v-else class="stat-value__valor">{{ value }}</span>
    <span v-if="hint && !loading" class="stat-value__hint">{{ hint }}</span>
  </div>
</template>

<style scoped>
.stat-value {
  display: flex;
  flex-direction: column;
  gap: var(--space-1);
  min-width: 0;
}

.stat-value__label {
  font-family: var(--font-body);
  font-size: var(--fs-xs);
  font-weight: var(--fw-bold);
  text-transform: uppercase;
  letter-spacing: 0.12em;
  color: var(--color-slate);
}

.stat-value__valor {
  font-family: var(--font-mono);
  font-size: var(--fs-h4);
  font-weight: var(--fw-bold);
  line-height: var(--lh-snug);
  color: var(--color-ink);
  font-variant-numeric: tabular-nums;
}

.stat-value--success .stat-value__valor {
  color: var(--color-success);
}

.stat-value--danger .stat-value__valor {
  color: var(--color-danger);
}

.stat-value--warning .stat-value__valor {
  color: var(--color-warning);
}

.stat-value__hint {
  font-size: var(--fs-xs);
  line-height: var(--lh-normal);
  color: var(--color-slate);
}

.stat-value__skeleton {
  display: inline-block;
  width: 96px;
  height: 24px;
  border-radius: var(--radius-sm);
  background: var(--color-mist);
  animation: stat-value-pulse 1.2s var(--ease-standard) infinite;
}

@keyframes stat-value-pulse {
  0%,
  100% {
    opacity: 1;
  }
  50% {
    opacity: 0.45;
  }
}

@media (prefers-reduced-motion: reduce) {
  .stat-value__skeleton {
    animation: none;
  }
}
</style>
