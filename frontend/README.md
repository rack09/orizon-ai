# 🌿 Frontend Orizon

Interfaccia in italiano realizzata con Vue 3 e Vite: chat, cronologia e schede
delle stime EcoFreight. La versione del repository si esegue in locale senza
login; la demo online ha un accesso riservato.

La guida completa di installazione, configurazione e pubblicazione è nel
[README principale](../README.md).

Da questa cartella:

```powershell
npm.cmd ci
npm.cmd run dev
```

Per eseguire i test automatici e verificare la build:

```powershell
npm.cmd run test:run
npm.cmd run build
```

`useChat` gestisce lo stato; `ChatPanel`, `ChatMessage`, `CalculationCard`,
`Composer` ed `ExampleCards` compongono l'interfaccia. Le icone SVG sono separate.
Il benvenuto e il limite dei messaggi provengono dal backend.

Il backend PHP e MySQL devono essere configurati prima di utilizzare la chat.
Le credenziali e le chiavi API restano nel backend.
