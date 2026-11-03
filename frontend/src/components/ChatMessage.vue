<script setup>
import CalculationCard from './CalculationCard.vue'
defineProps({ message: { type: Object, required: true } })
</script>

<template>
  <div class="message-row" :class="`message-row--${message.role}`">
    <span v-if="message.role === 'assistant'" class="message-avatar" aria-hidden="true">o.</span>
    <div class="message-content">
      <span class="message-author">{{ message.role === 'user' ? 'Tu' : message.response_kind === 'fallback' ? 'Orizon · riepilogo automatico' : 'Orizon' }}</span>
      <p class="message-bubble">{{ message.content }}</p>
      <CalculationCard v-for="(calculation, index) in message.calculations || []"
        :key="`${message.id}-calculation-${index}`" :calculation="calculation" />
    </div>
  </div>
</template>
