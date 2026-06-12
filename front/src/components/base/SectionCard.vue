<script setup>
import { useSlots } from 'vue'

defineProps({
  eyebrow: { type: String, default: '' },
  title: { type: String, default: '' },
})

const slots = useSlots()
</script>

<template>
  <section class="section-card">
    <header v-if="eyebrow || title || slots.headerActions" class="section-card__header">
      <div class="section-card__heading">
        <span v-if="eyebrow" class="section-card__eyebrow">{{ eyebrow }}</span>
        <h2 v-if="title" class="section-card__title">{{ title }}</h2>
      </div>
      <div v-if="slots.headerActions" class="section-card__actions">
        <slot name="headerActions"></slot>
      </div>
    </header>

    <div class="section-card__body">
      <slot></slot>
    </div>

    <footer v-if="slots.footer" class="section-card__footer">
      <slot name="footer"></slot>
    </footer>
  </section>
</template>

<style scoped>
.section-card {
  background: var(--color-paper);
  border-radius: var(--radius-lg);
  box-shadow: var(--shadow-sm);
  padding: var(--space-6);
}

.section-card__header {
  display: flex;
  align-items: flex-start;
  justify-content: space-between;
  gap: var(--space-4);
  margin-bottom: var(--space-5);
}

.section-card__heading {
  display: flex;
  flex-direction: column;
  gap: var(--space-1);
  min-width: 0;
}

.section-card__eyebrow {
  font-family: var(--font-body);
  font-size: var(--fs-eyebrow);
  font-weight: var(--fw-bold);
  text-transform: uppercase;
  letter-spacing: 0.14em;
  color: var(--color-primary-deep);
}

.section-card__title {
  margin: 0;
  font-family: var(--font-display);
  font-size: var(--fs-h4);
  font-weight: var(--fw-bold);
  line-height: var(--lh-snug);
  color: var(--color-ink);
}

.section-card__actions {
  display: flex;
  align-items: center;
  gap: var(--space-2);
  flex: none;
}

.section-card__footer {
  display: flex;
  align-items: center;
  justify-content: flex-end;
  gap: var(--space-3);
  margin-top: var(--space-6);
  padding-top: var(--space-5);
  border-top: 1px solid var(--color-mist);
}
</style>
