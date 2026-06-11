<script setup>
defineProps({
  variant: {
    type: String,
    default: 'neutral',
    validator: (v) => ['success', 'warning', 'danger', 'neutral', 'info'].includes(v),
  },
  dot: { type: Boolean, default: false },
})
</script>

<template>
  <span class="base-badge" :class="`base-badge--${variant}`">
    <span v-if="dot" class="base-badge__dot" aria-hidden="true"></span>
    <slot></slot>
  </span>
</template>

<style scoped>
.base-badge {
  display: inline-flex;
  align-items: center;
  gap: var(--space-2);
  padding: var(--space-1) var(--space-3);
  border-radius: var(--radius-pill);
  font-family: var(--font-body);
  font-size: var(--fs-xs);
  font-weight: var(--fw-bold);
  line-height: var(--lh-normal);
  white-space: nowrap;
}

.base-badge__dot {
  width: 7px;
  height: 7px;
  flex: none;
  border-radius: 50%;
  background: currentColor;
}

.base-badge--success {
  background: var(--color-success-soft);
  color: var(--color-success);
}

.base-badge--warning {
  background: var(--color-warning-soft);
  color: var(--color-warning);
}

.base-badge--danger {
  background: var(--color-danger-soft);
  color: var(--color-danger);
}

.base-badge--neutral {
  background: var(--color-mist);
  color: var(--color-graphite);
}

.base-badge--info {
  background: var(--color-info-soft);
  color: var(--color-info);
}
</style>
