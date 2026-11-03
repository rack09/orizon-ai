<script setup>
import { formatNumber, modeLabels } from '../utils/format'
defineProps({ calculation: { type: Object, required: true } })
</script>

<template>
  <section class="calculation-card" aria-label="Risultato del calcolo EcoFreight">
    <p class="calculation-source">STIMA ECOFREIGHT · SINGOLA TRATTA</p>
    <p class="calculation-total"><strong>{{ formatNumber(calculation.emissions_kg_co2e) }}</strong><span>kg CO₂e</span></p>
    <dl class="calculation-details">
      <div><dt>Origine</dt><dd>{{ calculation.origin }}</dd></div>
      <div><dt>Destinazione</dt><dd>{{ calculation.destination }}</dd></div>
      <div><dt>Mezzo</dt><dd>{{ modeLabels[calculation.transport_mode] }}</dd></div>
      <div><dt>Peso trasportato</dt><dd>{{ formatNumber(calculation.weight_kg) }} kg</dd></div>
      <div v-if="calculation.distance_km != null"><dt>Distanza stimata</dt><dd>{{ formatNumber(calculation.distance_km) }} km</dd></div>
      <div v-if="calculation.methodology"><dt>Metodo</dt><dd>{{ calculation.methodology }}</dd></div>
    </dl>
    <p class="calculation-scope">Emissioni del peso trasportato secondo fattori merci; non dell’intero passeggero. Fonte: EcoFreight.</p>
  </section>
</template>
