<script setup>
import { nextTick, ref, watch } from 'vue'
import SendIcon from './icons/SendIcon.vue'

const props = defineProps({ modelValue: { type: String, default: '' }, disabled: Boolean,
  canSend: Boolean, maxLength: { type: Number, default: null } })
const emit = defineEmits(['update:modelValue', 'send'])
const input = ref(null)
const MAX_INPUT_HEIGHT = 144

function resizeInput() {
  if (!input.value) return
  input.value.style.height = 'auto'
  input.value.style.height = `${Math.min(input.value.scrollHeight, MAX_INPUT_HEIGHT)}px`
}

function onInput(event) {
  emit('update:modelValue', event.target.value)
  resizeInput()
}

function handleKeydown(event) {
  if (event.key === 'Enter' && !event.shiftKey && !event.isComposing) {
    event.preventDefault()
    if (props.canSend) emit('send')
  }
}

watch(() => props.modelValue, async () => {
  await nextTick()
  resizeInput()
  if (!props.disabled) input.value?.focus()
})
watch(() => props.disabled, async disabled => {
  if (!disabled) {
    await nextTick()
    resizeInput()
    input.value?.focus()
  }
})
</script>

<template>
  <form class="composer" @submit.prevent="$emit('send')">
    <div class="composer-field">
      <label for="travel-message" class="sr-only">Descrivi il tragitto, il mezzo e il peso trasportato</label>
      <textarea id="travel-message" ref="input" :value="modelValue" :disabled="disabled" :maxlength="maxLength"
        rows="2" placeholder="Da dove partiamo? Raccontami il tuo viaggio…" aria-describedby="composer-help"
        @keydown="handleKeydown" @input="onInput"></textarea>
      <button class="send-button" type="submit" :disabled="!canSend" aria-label="Invia messaggio" title="Invia messaggio"><SendIcon /></button>
    </div>
    <div class="composer-help" id="composer-help"><span>Invio per inviare · Maiusc + Invio per andare a capo</span><span>{{ modelValue.length }}/{{ maxLength ?? '…' }}</span></div>
  </form>
</template>
