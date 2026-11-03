<script setup>
import { examples } from '../data/examples'
import TrainIcon from './icons/TrainIcon.vue'
import PlaneIcon from './icons/PlaneIcon.vue'
import RoadIcon from './icons/RoadIcon.vue'
defineProps({ disabled: Boolean })
defineEmits(['select'])
const icons = { rail: TrainIcon, air: PlaneIcon, road: RoadIcon }
</script>

<template>
  <div class="examples-section">
    <p class="examples-label">NON SAI DA DOVE INIZIARE? PROVA UN ESEMPIO</p>
    <div class="example-grid">
      <button v-for="example in examples" :key="example.mode" class="example-card" type="button"
        :disabled="disabled" @click="$emit('select', example)">
        <component :is="icons[example.mode]" />
        <span class="example-route">{{ example.route }}</span><span class="example-label">{{ example.label }}</span>
      </button>
    </div>
  </div>
</template>
