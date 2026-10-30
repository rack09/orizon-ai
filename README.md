# 🌿 Orizon AI

### Nuovi orizzonti. Scelte consapevoli.

Un assistente in italiano che raccoglie i dati di un viaggio e stima le emissioni
del trasporto di bagagli o carico attraverso un tool collegato a EcoFreight.

**Vue 3 · Vite · PHP · MySQL · Gemini · EcoFreight**

🔗 [Demo online](https://orizon-ai.melvinrosales.com/)
· [Repository](https://github.com/rack09/orizon-ai)

Il repository contiene la **versione senza login, da eseguire in locale**.
La demo online utilizza una versione con accesso riservato per limitare l'uso
delle API pubblicamente. Le credenziali della demo vengono consegnate al docente
nel PDF privato del corso e non sono pubblicate qui.

Per avviare la copia locale servono un database MySQL e le proprie chiavi
Gemini/EcoFreight; non occorrono le credenziali di accesso della demo online.

## ✨ Cosa fa

- Raccoglie origine, destinazione, mezzo di trasporto e peso tramite la chat.
- Chiede i dati mancanti e conserva il contesto quando cambia un dettaglio.
- Usa Gemini per richiedere il tool `calcola_emissioni`; PHP valida gli argomenti
  e interroga EcoFreight.
- Mostra una scheda con i dati del tragitto, la distanza disponibile e la stima
  in kg di CO₂ equivalente, secondo il risultato del servizio.
- Salva messaggi, risultati e contesto in MySQL e ripristina la conversazione
  dopo un aggiornamento della pagina nello stesso browser.

> **Ambito della stima:** EcoFreight applica fattori di trasporto merci.
> Una richiesta per 20 kg riguarda il trasporto di quel peso secondo le
> assunzioni del servizio; non rappresenta l'intera impronta del passeggero o
> del veicolo. Questa distinzione è visibile anche nell'interfaccia.

## 🧩 Come funziona

Vue invia le richieste a `backend/public/chat.php`, l'unico endpoint della chat.
PHP verifica origine, formato e contenuto delle richieste prima di elaborare
i messaggi. Un token identifica la conversazione da ripristinare o aggiornare.
Il backend ricostruisce la cronologia da MySQL e la invia a Gemini. Quando il
modello richiede il calcolo, PHP chiama EcoFreight con i parametri validati e
restituisce il risultato al modello e all'interfaccia.

Il browser non riceve le chiavi dei provider. Il tool ha nome e destinazione
fissi: il modello non può scegliere URL esterni o eseguire query SQL.

| Percorso | Responsabilità |
|---|---|
| `frontend/src/App.vue` | Chat e schede dei risultati. |
| `frontend/src/services/chatApi.js` | Richieste HTTP e ripristino della conversazione. |
| `backend/public/chat.php` | Endpoint JSON, validazione e controllo delle origini. |
| `backend/src/Agent.php` | Conversazione con il modello e invocazione del tool. |
| `backend/src/GeminiClient.php` | Client Gemini Interactions API. |
| `backend/src/EcoFreightClient.php` | Client EcoFreight e normalizzazione dei risultati. |
| `backend/src/ConversationRepository.php` | Persistenza MySQL e query preparate. |
| `backend/config/system-prompt.txt` | Istruzioni dell'assistente. |
| `backend/database/schema.sql` | Schema delle cinque tabelle applicative. |

## 🚀 Avvio locale con WAMP

Servono PHP 8.1 o successivo, le estensioni `curl` e `pdo_mysql`, MySQL/MariaDB e
una versione di Node compatibile con `frontend/package.json`:
`^20.19.0 || >=22.12.0`. Il progetto non richiede Composer.

1. Clonare il repository direttamente dentro `C:\wamp64\www`:

   ```powershell
   cd C:\wamp64\www
   git clone https://github.com/rack09/orizon-ai.git
   cd orizon-ai
   ```

2. Avviare Apache e MySQL. Creare il database `orizon_ai` e importare
   `backend/database/schema.sql` tramite phpMyAdmin.

3. Creare la configurazione privata e inserire le credenziali MySQL e le proprie
   chiavi Gemini/EcoFreight. Se `config.php` esiste già, conservarlo:

   ```powershell
   Copy-Item backend/config/config.example.php backend/config/config.php
   ```

4. Installare le dipendenze e avviare Vue:

   ```powershell
   cd frontend
   npm.cmd ci
   npm.cmd run dev
   ```

5. Aprire `http://localhost:5173/`: la chat locale è disponibile senza login.
   Il proxy di Vite ricava il nome della cartella del progetto, collocata
   direttamente dentro `www`, e inoltra le richieste ad Apache sulla porta 80.

Per verificare PHP, estensioni, chiavi presenti, connessione e schema MySQL,
aprire `http://localhost/orizon-ai/backend/public/chat.php?action=status`.
La risposta deve indicare `ready: true`. Questo controllo verifica la presenza
delle chiavi, mentre la loro validità si conferma con una richiesta reale.

## 🔑 Configurazione: file di esempio e file privati

| File | Su GitHub | Sul PC e sul server |
|---|---|---|
| `backend/config/config.example.php` | Sì, con chiavi e password vuote. | Necessario: contiene i valori di base. |
| `backend/config/config.php` | No. | Contiene le proprie chiavi e credenziali MySQL. |
| `frontend/.env.example` | Sì. | Documenta gli URL pubblici del frontend. |
| `frontend/.env.production` | No. | Configurazione locale usata durante la build. |

`Config::load()` carica **sempre** `config.example.php`. Se esiste `config.php`,
i valori presenti nella copia privata sostituiscono quelli di base. Infine le
variabili d'ambiente del server hanno precedenza su entrambi.

Se manca `config.php`, restano i valori dell'esempio, che non contengono le
chiavi necessarie alla chat. Se manca `config.example.php`, il caricamento della
configurazione fallisce anche quando `config.php` esiste.

Variabili del backend supportate: `DB_HOST`, `DB_PORT`, `DB_NAME`, `DB_USER`,
`DB_PASSWORD`, `GEMINI_API_KEY`, `GEMINI_MODEL`, `ECOFREIGHT_API_KEY`,
`ALLOWED_ORIGINS`, `REQUESTS_PER_HOUR` e `MAX_TURNS`.
`ALLOWED_ORIGINS` accetta origini separate da virgole,
complete di protocollo e senza barra finale.

## 🌍 Demo pubblica e copia locale

| Ambiente | Accesso e configurazione |
|---|---|
| Repository GitHub | Codice senza login per l'esecuzione locale; chiavi API e credenziali MySQL configurate da chi lo esegue. |
| Demo online | Copia pubblicata con login e credenziali fornite privatamente al docente. |

Il login è un'aggiunta della versione live e non è richiesto per usare questo
repository in locale. La cartella pubblica della demo espone `backend/public`;
le configurazioni private restano fuori dalla radice pubblica. La versione
locale del repository e quella pubblicata hanno quindi un diverso controllo
di accesso. Le istruzioni di installazione qui riportate riguardano la copia
locale senza login.

## 🛡️ Controlli e limiti

- Chiavi dei provider conservate nel backend e file privati esclusi da Git.
- Richieste JSON con formato, lunghezza e parametri del tool validati.
- Query preparate, token di conversazione conservato come hash nel database e
  identificativi degli invii per evitare di ripetere lo stesso turno.
- Limiti predefiniti: 40 turni per conversazione, tre chiamate al calcolatore
  per messaggio e 60 operazioni di scrittura per IP/ora.

Le quote dei provider e un eventuale budget globale di consumo si gestiscono
separatamente; il progetto non implementa un limite globale giornaliero.

## 🧪 Verifica

Con PHP disponibile nella shell:

```powershell
php backend/tests/run.php
```

La suite verifica il ciclo agente/tool con provider simulati: contesto,
contratti delle richieste, argomenti validi e non validi, errori dei servizi e
conservazione dei risultati. I test non usano le chiavi reali dei provider.
Le integrazioni MySQL opzionali richiedono un database di test dedicato;
l'integrazione con le API reali si verifica nell'ambiente configurato.

Esempio manuale in italiano:

1. «Vorrei stimare le emissioni per 20 kg di bagagli da Milano a Roma in treno.»
2. «E se il peso fosse 25 kg?»
3. Aggiornare la pagina e verificare il ripristino dei messaggi nello stesso
   browser.

Le azioni dell'endpoint locale sono `status`, `start`, `history` e `send`.
Le ultime due usano il token della conversazione nell'header `Authorization`;
questo token non è una chiave API né un account utente.

## 📚 Riferimenti

- [Vue](https://vuejs.org/guide/introduction.html)
- [Vite](https://vite.dev/guide/)
- [PHP: query preparate con PDO](https://www.php.net/manual/en/pdo.prepared-statements.php)
- [Gemini Interactions API](https://ai.google.dev/gemini-api/docs/interactions)
- [EcoFreight OpenAPI](https://ecofreight.co/openapi.json)

**Melvin Rosales** · Progetto formativo Start2Impact — Agenti AI per Sviluppo.
