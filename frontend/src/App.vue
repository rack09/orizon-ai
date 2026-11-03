<script setup>
import ChatPanel from './components/ChatPanel.vue'
import BrandMark from './components/icons/BrandMark.vue'
import JourneyPath from './components/icons/JourneyPath.vue'
import LeafIcon from './components/icons/LeafIcon.vue'
import { useChat } from './composables/useChat'

const chat = useChat()
</script>

<template>
  <div class="page-shell">
    <header class="site-header">
      <a class="brand" href="./" aria-label="Orizon, pagina iniziale">
        <BrandMark />
        <span>orizon<span class="brand-dot">.</span></span>
      </a>
      <p class="header-caption">NUOVI ORIZZONTI. SCELTE CONSAPEVOLI.</p>
      <span class="preview-badge" role="status"><span aria-hidden="true"></span> {{ chat.connectionLabel }}</span>
    </header>

    <main class="main-layout">
      <section class="intro" aria-labelledby="page-title">
        <p class="eyebrow"><span></span> IL VIAGGIO COMINCIA DA QUI</p>
        <h1 id="page-title">Esplora di più.<br />Lascia<br /><em>meno tracce.</em></h1>
        <p class="intro-description">Ogni viaggio ha un impatto. Comprenderlo è il primo passo per partire con più consapevolezza.</p>

        <div class="journey-illustration" aria-hidden="true">
          <JourneyPath />
          <span>Una scelta alla volta, verso nuovi orizzonti.</span>
        </div>

        <aside class="scope-note" aria-label="Come leggere le stime">
          <LeafIcon />
          <div><h2>Un dato, con il giusto contesto.</h2><p>Le stime riguardano il trasporto del peso indicato, secondo i fattori EcoFreight. Non rappresentano l’intera impronta del passeggero.</p></div>
        </aside>
      </section>

      <ChatPanel :chat="chat" />
    </main>

    <footer class="site-footer"><span>ORIZON · VIAGGIARE CON CONSAPEVOLEZZA</span><span>Un progetto di Melvin Rosales</span></footer>
  </div>
</template>
