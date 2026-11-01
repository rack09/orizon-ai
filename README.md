<div align="center">

<img src="frontend/public/orizon.svg" alt="Orizon" width="220" />

# 🌿 Orizon AI

### Nuovi orizzonti. Scelte consapevoli.

Un assistente conversazionale che aiuta a comprendere l'impatto del trasporto di bagagli e carichi, un viaggio alla volta.

![Vue](https://img.shields.io/badge/Vue-3-4FC08D?style=for-the-badge&logo=vuedotjs&logoColor=white)
![Vite](https://img.shields.io/badge/Vite-8-646CFF?style=for-the-badge&logo=vite&logoColor=white)
![PHP](https://img.shields.io/badge/PHP-8.1%2B-777BB4?style=for-the-badge&logo=php&logoColor=white)
![MySQL](https://img.shields.io/badge/MySQL-Database-4479A1?style=for-the-badge&logo=mysql&logoColor=white)
![Gemini](https://img.shields.io/badge/Gemini-AI-4285F4?style=for-the-badge)
![EcoFreight](https://img.shields.io/badge/EcoFreight-Emissions-2F6B4F?style=for-the-badge)

[![Demo online](https://img.shields.io/badge/DEMO_LIVE-ACCESSO_RISERVATO-245444?style=for-the-badge)](https://orizona.melvinrosales.com/)
[![Repository](https://img.shields.io/badge/CODICE-GITHUB-181717?style=for-the-badge&logo=github&logoColor=white)](https://github.com/rack09/orizon-ai)

**Progetto formativo Start2Impact · Agenti AI per Sviluppo**  
Realizzato da **Melvin Rosales**

</div>

---

> 🔐 **Accesso alla demo:** la versione online richiede un nome utente e una password. Per provarla, richiedere le credenziali al creatore del repository, **Melvin Rosales**. Le credenziali non sono pubblicate su GitHub.
>
> 💻 **Versione del repository:** questa copia è **senza login**, pensata per l'esecuzione locale con le proprie chiavi API e il proprio database MySQL.

## 📑 Indice

- [🌍 Il progetto](#progetto)
- [✨ Funzionalità](#funzionalita)
- [🛠️ Tecnologie](#tecnologie)
- [🧩 Come funziona](#funzionamento)
- [🚀 Avvio locale](#avvio-locale)
- [🔑 Configurazione](#configurazione)
- [🔐 Demo online e versione locale](#demo-online)
- [📂 Struttura del progetto](#struttura)
- [🧪 Test e verifica](#verifiche)
- [🛡️ Controlli e limiti](#controlli-e-limiti)
- [👨‍💻 Autore e riferimenti](#autore)

<a id="progetto"></a>

## 🌍 Il progetto

**Orizon AI** nasce per rendere più comprensibili le stime delle emissioni attraverso una conversazione in italiano. L'utente descrive un viaggio; l'assistente raccoglie i dati necessari e usa un servizio esterno per calcolare le emissioni associate al peso trasportato.

Il progetto integra un'interfaccia **Vue**, un backend **PHP**, la persistenza su **MySQL** e un agente **Gemini** capace di richiedere il tool `calcola_emissioni`, collegato a **EcoFreight**.

> 🌱 **Cosa rappresenta la stima?** EcoFreight utilizza fattori di trasporto merci. Una richiesta per 20 kg riguarda il trasporto di quel peso secondo le assunzioni del servizio: **non rappresenta l'intera impronta del passeggero o del veicolo**. Questa distinzione è indicata anche nell'interfaccia.

<a id="funzionalita"></a>

## ✨ Funzionalità

| Funzionalità | Descrizione |
|---|---|
| 💬 Conversazione in italiano | L'utente descrive il viaggio con il linguaggio naturale. |
| 📍 Raccolta dei dati | L'assistente richiede origine, destinazione, mezzo di trasporto e peso, chiedendo ciò che manca. |
| 🧮 Calcolo delle emissioni | Il backend valida i parametri del tool e interroga EcoFreight. |
| 📊 Risultati leggibili | Una scheda mostra il tragitto, la distanza disponibile e la stima in kg di CO₂ equivalente. |
| 🔄 Modifica del viaggio | È possibile cambiare un dettaglio, come il peso, mantenendo il contesto della conversazione. |
| 💾 Persistenza | Messaggi e risultati sono salvati in MySQL e ripristinati dopo un aggiornamento della pagina nello stesso browser. |

<a id="tecnologie"></a>

## 🛠️ Tecnologie

| Tecnologia | Utilizzo |
|---|---|
| **Vue 3** | Interfaccia della chat e visualizzazione dei risultati. |
| **Vite 8** | Server di sviluppo, proxy verso PHP e build del frontend. |
| **PHP 8.1+** | Endpoint JSON, validazione e comunicazione con i provider. |
| **MySQL / MariaDB** | Conversazioni, messaggi, chiamate al tool e limiti delle richieste. |
| **Gemini Interactions API** | Gestione della conversazione e richiesta del tool di calcolo. |
| **EcoFreight API** | Stima delle emissioni del trasporto del peso indicato. |
| **PDO e cURL** | Accesso al database tramite query preparate e richieste HTTP dal backend. |

Il backend non richiede Composer.

<a id="funzionamento"></a>

## 🧩 Come funziona

1. L'utente invia un messaggio dalla chat Vue.
2. L'endpoint PHP controlla la richiesta e recupera la conversazione da MySQL.
3. Gemini riceve il contesto e, quando dispone dei dati necessari, richiede il tool `calcola_emissioni`.
4. PHP valida gli argomenti e chiama EcoFreight.
5. Il risultato viene salvato, restituito al modello e mostrato nell'interfaccia.

Le chiavi dei provider restano nel backend. Il browser comunica con l'endpoint dell'applicazione e non chiama direttamente Gemini o EcoFreight.

### Endpoint della versione locale

Tutte le azioni utilizzano `backend/public/chat.php`.

| Metodo | Azione | Funzione |
|---|---|---|
| `GET` | `status` | Controlla la disponibilità della configurazione e del database. |
| `POST` | `start` | Crea una nuova conversazione. |
| `GET` | `history` | Recupera i messaggi della conversazione. |
| `POST` | `send` | Invia un messaggio all'assistente. |

Le azioni `history` e `send` usano il token della conversazione nell'header `Authorization`. Questo token identifica la conversazione e non è una chiave dei provider.

<a id="avvio-locale"></a>

## 🚀 Avvio locale

### Requisiti

| Requisito | Indicazione |
|---|---|
| **WAMP o ambiente equivalente** | Apache e MySQL/MariaDB attivi. Le istruzioni seguenti usano WAMP su Windows. |
| **PHP** | Versione 8.1 o successiva, con estensioni `curl` e `pdo_mysql`. |
| **Node.js** | Versione compatibile con `frontend/package.json`: `^20.19.0 \|\| >=22.12.0`. |
| **Git** | Per clonare il repository. |
| **Chiavi API personali** | Gemini ed EcoFreight. |

### 1. Clonare il repository

Aprire PowerShell e collocare il progetto direttamente nella cartella `www` di WAMP:

```powershell
cd C:\wamp64\www
git clone https://github.com/rack09/orizon-ai.git
cd orizon-ai
```

### 2. Preparare il database

Avviare Apache e MySQL da WAMP. In phpMyAdmin, creare il database `orizon_ai` e importare il file:

```text
backend/database/schema.sql
```

### 3. Creare la configurazione privata

Dalla cartella principale del progetto, copiare il file di esempio soltanto se `config.php` non esiste già:

```powershell
if (!(Test-Path backend/config/config.php)) {
    Copy-Item backend/config/config.example.php backend/config/config.php
}
```

Aprire `backend/config/config.php` e inserire i dati del proprio database e le proprie chiavi Gemini ed EcoFreight. Conservare anche `config.example.php`: entrambi i file servono al caricamento della configurazione.

### 4. Avviare il frontend

```powershell
cd frontend
npm.cmd ci
npm.cmd run dev
```

Aprire **[http://localhost:5173/](http://localhost:5173/)**. La versione locale è disponibile **senza login**.

Il proxy di Vite ricava il nome della cartella del progetto e inoltra le richieste ad Apache sulla porta 80. La configurazione inclusa presuppone che il progetto si trovi direttamente dentro `www`; con porte o percorsi diversi occorre adattare `frontend/vite.config.js`.

### 5. Verificare il backend

Aprire:

```text
http://localhost/orizon-ai/backend/public/chat.php?action=status
```

La risposta deve indicare `ready: true`. Il controllo verifica PHP, estensioni, presenza delle chiavi, connessione e schema MySQL. La validità delle chiavi e il collegamento ai provider si confermano inviando una richiesta reale dalla chat.

### Build del frontend

Dalla cartella `frontend`:

```powershell
npm.cmd run build
```

I file generati si trovano in `frontend/dist`. Il backend PHP e il database rimangono necessari per utilizzare la chat.

<a id="configurazione"></a>

## 🔑 Configurazione

| File | Nel repository | Scopo |
|---|---|---|
| `backend/config/config.example.php` | ✅ Sì | Configurazione di base, con chiavi e password vuote. Deve essere conservato anche nell'ambiente di esecuzione. |
| `backend/config/config.php` | ❌ No | Configurazione privata con le proprie chiavi e credenziali MySQL. |
| `frontend/.env.example` | ✅ Sì | Esempio delle impostazioni pubbliche del frontend. |
| `frontend/.env.production` | ❌ No | Eventuale configurazione locale dell'URL del backend per la build. |

### Come vengono caricati i valori

`Config::load()` carica **sempre** `config.example.php`. Se esiste `config.php`, i valori della copia privata sostituiscono quelli di base. Le variabili d'ambiente del server hanno infine precedenza su entrambi.

- **Senza `config.php`:** restano i valori dell'esempio. Per avviare la chat, le chiavi e gli altri valori necessari devono essere forniti tramite le variabili d'ambiente.
- **Senza `config.example.php`:** il caricamento fallisce anche se il file privato esiste.

Il `.gitignore` esclude `config.php` e gli altri file privati non ancora tracciati. Chiavi API e password non devono essere inserite nei file pubblici o nelle variabili del frontend con prefisso `VITE_`.

### Variabili d'ambiente del backend

| Variabili | Impostazione |
|---|---|
| `DB_HOST`, `DB_PORT`, `DB_NAME`, `DB_USER`, `DB_PASSWORD` | Connessione al database. |
| `GEMINI_API_KEY`, `GEMINI_MODEL` | Chiave e modello Gemini. |
| `ECOFREIGHT_API_KEY` | Chiave EcoFreight. |
| `ALLOWED_ORIGINS` | Origini autorizzate, separate da virgole. |
| `REQUESTS_PER_HOUR`, `MAX_TURNS` | Limiti delle richieste e dei turni. |

Le origini devono includere il protocollo e non avere una barra finale, ad esempio `http://localhost:5173`.

<a id="demo-online"></a>

## 🔐 Demo online e versione locale

| Ambiente | Accesso | Configurazione |
|---|---|---|
| **Repository GitHub** | Senza login, per l'esecuzione locale. | Database e chiavi API configurati da chi scarica il progetto. |
| **[Demo online](https://orizona.melvinrosales.com/)** | Nome utente e password da richiedere a **Melvin Rosales**, creatore del repository. | Ambiente già configurato per la dimostrazione. |

Il login è un'aggiunta della versione live per limitare l'accesso pubblico alle API. Non è incluso nella copia locale di questo repository e le credenziali della demo non servono per eseguirla sul proprio computer.

Nella demo, la radice pubblica punta a `backend/public`, mentre configurazioni e sorgenti del backend restano fuori dalla cartella esposta dal dominio. Le credenziali riservate vengono condivise privatamente.

<a id="struttura"></a>

## 📂 Struttura del progetto

| Percorso | Contenuto |
|---|---|
| `frontend/src/App.vue` | Interfaccia della chat e schede dei risultati. |
| `frontend/src/services/chatApi.js` | Richieste al backend e ripristino della conversazione. |
| `frontend/src/style.css` | Stili dell'applicazione. |
| `frontend/public/` | Logo e risorse statiche. |
| `frontend/vite.config.js` | Configurazione del server di sviluppo e del proxy. |
| `backend/public/chat.php` | Endpoint JSON dell'applicazione. |
| `backend/src/Agent.php` | Conversazione con Gemini e gestione delle richieste al tool. |
| `backend/src/GeminiClient.php` | Client Gemini Interactions API. |
| `backend/src/EcoFreightClient.php` | Client EcoFreight e normalizzazione dei risultati. |
| `backend/src/ConversationRepository.php` | Persistenza MySQL e query preparate. |
| `backend/src/Config.php` | Caricamento della configurazione. |
| `backend/config/system-prompt.txt` | Istruzioni dell'assistente. |
| `backend/database/schema.sql` | Schema del database. |
| `backend/tests/run.php` | Test del backend. |
| `backend/tools/check.php` | Controlli dell'ambiente da riga di comando. |

### Tabelle del database

| Tabella | Dati conservati |
|---|---|
| `conversations` | Conversazioni e riferimenti per il loro recupero. |
| `messages` | Cronologia dei messaggi. |
| `tool_calls` | Chiamate al calcolatore e risultati. |
| `turn_requests` | Identificativi degli invii, per gestire le richieste ripetute. |
| `rate_limits` | Conteggio delle richieste per i limiti di utilizzo. |

<a id="verifiche"></a>

## 🧪 Test e verifica

### Test del backend

Dalla cartella principale del progetto, con PHP disponibile nella shell:

```powershell
php backend/tests/run.php
```

La suite verifica il ciclo agente/tool con provider simulati: gestione del contesto, contratti delle richieste, validazione degli argomenti, errori dei servizi e conservazione dei risultati. Questi test non consumano le quote delle API reali.

Le integrazioni MySQL opzionali richiedono un database di test dedicato. Il funzionamento completo dei provider deve essere verificato nell'ambiente configurato.

### Prova manuale della chat

1. Inviare: **«Vorrei stimare le emissioni per 20 kg di bagagli da Milano a Roma in treno.»**
2. Controllare la scheda del risultato e il contesto della stima.
3. Inviare: **«E se il peso fosse 25 kg?»**
4. Verificare che l'assistente mantenga il tragitto e aggiorni il peso.
5. Aggiornare la pagina e controllare il ripristino dei messaggi nello stesso browser.

<a id="controlli-e-limiti"></a>

## 🛡️ Controlli e limiti

| Controllo | Comportamento |
|---|---|
| **Configurazione privata** | Le chiavi restano nel backend e i file privati sono esclusi da Git. |
| **Validazione delle richieste** | Controlli su origini, formato JSON, lunghezza e parametri del tool. |
| **Accesso al database** | Query preparate con PDO. |
| **Conversazioni** | Token conservato come hash nel database. |
| **Invii ripetuti** | Identificativi delle richieste per evitare di elaborare nuovamente lo stesso turno. |
| **Limiti predefiniti** | 40 turni per conversazione, fino a 3 chiamate al calcolatore per messaggio e 60 operazioni di scrittura per IP ogni ora. |

Le stime dipendono dai dati e dalle assunzioni di EcoFreight. Disponibilità e quote dipendono anche dai provider. I limiti applicativi non costituiscono un budget globale di consumo: il progetto non implementa un tetto giornaliero globale per le API.

<a id="autore"></a>

## 👨‍💻 Autore e riferimenti

**Melvin Rosales** · Progetto formativo **Start2Impact — Agenti AI per Sviluppo**.

- 🌐 [Demo Orizon AI — accesso riservato](https://orizona.melvinrosales.com/)
- 💻 [Repository GitHub](https://github.com/rack09/orizon-ai)
- 📘 [Documentazione Vue](https://vuejs.org/guide/introduction.html)
- ⚡ [Documentazione Vite](https://vite.dev/guide/)
- 🐘 [PHP: query preparate con PDO](https://www.php.net/manual/en/pdo.prepared-statements.php)
- 🤖 [Gemini Interactions API](https://ai.google.dev/gemini-api/docs/interactions)
- 🌱 [EcoFreight OpenAPI](https://ecofreight.co/openapi.json)

---

<div align="center">

**Orizon AI · Nuovi orizzonti. Scelte consapevoli.**

</div>
