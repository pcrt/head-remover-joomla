# Head Remover

Plugin di sistema di Protocolli Creativi per controllare gli asset e i metadati dell’head HTML in Joomla 5 e 6. Licenza **GPL-2.0-or-later**.

## Requisiti

Il codice del plugin richiede PHP 8.1 o successivo; restano validi i requisiti più restrittivi della versione Joomla installata (Joomla 6 richiede PHP 8.3 o successivo). Non richiede jQuery o Bootstrap e non usa le API globali di Joomla 3.

**Stato della verifica del 7 novembre 2026:** pacchetto compilato e controlli statici eseguiti; test Docker su Joomla 5.4/6.2, lint PHP 8.3 e JED Checker non eseguiti in questa sessione, a causa dell’accesso negato al socket Docker e dell’assenza di un interprete PHP locale. La compatibilità runtime resta da verificare prima della pubblicazione. Vedere `TEST_REPORT.md`.

## Installazione

1. Compilare con `./build.sh` (Python 3), oppure scaricare `plg_system_headremover-2.0.0.zip` dagli allegati della release quando pubblicata.
2. In Joomla: Sistema → Installa → Estensioni, caricare lo ZIP.
3. In Sistema → Gestisci → Plugin, aprire **Head Remover**, configurare e abilitare.
4. Ordinare il plugin dopo le estensioni che aggiungono asset; svuotare la cache e verificare le pagine interessate.

Il plugin è un’installazione nuova, elemento `headremover`, gruppo `system`. Il server aggiornamenti punta al file `updateserver/extension.xml` del branch `main` di questo repository. Il file contiene due aggiornamenti equivalenti destinati alle serie 5 e 6.

## Parametri

| Parametro | Comportamento |
| --- | --- |
| Solo frontend | Attivo inizialmente. Limita l’azione al sito pubblico. |
| Escludi sempre il backend | Attivo inizialmente. Per agire nel backend occorre disattivare entrambe le limitazioni. CLI, API e documenti non HTML restano esclusi. |
| Nomi degli asset | Un nome esatto per riga: `jquery`, `core`, `keepalive`, `messages`. Il suffisso `.*` comprende una famiglia, per esempio `bootstrap.*`. Elenco inizialmente vuoto; agisce su script e stili attivi. |
| URL da rimuovere | Righe ripetibili con tipo script/stile/entrambi e URL completo o frammento. Confronto per sottostringa, sensibile alle maiuscole; righe vuote ignorate. |
| Script personalizzati | URL e modalità normale, defer, async o module. |
| CSS personalizzati | URL e attributo media (inizialmente `all`). |
| Rimuovi generator/author | Opzioni indipendenti, inizialmente disattive. |
| Metadati personalizzati | Righe ordinate con attributo `name`, `property` o `http-equiv`, nome e contenuto. I valori sono codificati per HTML. |
| MOTD | Commento subito dopo l’apertura di `<head>`; delimitatori neutralizzati. |

**Disabilitare `core` può rompere moduli, menu e altre funzioni.** Il WebAssetManager riattiverebbe un asset se richiesto da una dipendenza: per rendere effettiva la rimozione il plugin disabilita anche gli asset attivi che lo richiedono, transitivamente. La stessa regola vale per la rimozione tramite URL. Provare su una copia del sito prima di abilitare rimozioni.

Le aggiunte vengono registrate nel WebAssetManager e ogni riga dipende dalla precedente dello stesso tipo. L’ordine dei tag è deterministico; `async` non garantisce l’ordine di esecuzione, e moduli/defer seguono le regole del browser. Non esiste un ordine di esecuzione comune tra CSS e JavaScript.

Sono accettati URL HTTP(S), URL relativi al protocollo e percorsi locali. I percorsi senza slash iniziale sono relativi alla radice Joomla (anche in sottocartella); quelli con slash iniziale sono relativi alla radice del dominio. Il plugin non carica né verifica i file sul server. Aggiungere percorsi esistenti e fonti affidabili; eventuali restrizioni CSP restano applicate dal browser.

La rimozione riguarda risorse registrate tramite WebAssetManager o le API pubbliche di Document, anche da template ed estensioni. Tag scritti direttamente nel template, tag in `addCustomTag`, inserimenti successivi di altri plugin e risorse caricate dal browser non vengono riscritti. Non rimuove indiscriminatamente script inline. I metadati personalizzati vengono aggiunti nell’ordine delle righe e possono ripetere lo stesso nome; per evitare duplicati con i metadati del template vanno coordinati con quest’ultimo.

Asset e metadati sono elaborati in `onBeforeCompileHead`, con priorità bassa. Solo il posizionamento del commento già sanificato avviene in `onAfterRender`, perché le API dei custom tag lo collocherebbero alla fine dell’head. È consigliabile mantenere il meta charset all’inizio dell’head; il commento non introduce contenuto eseguibile.

## Migrazione manuale dalla 1.1.0

La 1.1.0 usa l’elemento `head_remover`: Joomla tratta la 2.0 come un altro plugin. Nessun parametro viene importato automaticamente e nessun file della vecchia estensione viene modificato.

1. Annotare o esportare i parametri del vecchio plugin; effettuare un backup del sito.
2. Installare la 2.0 lasciandola inizialmente disabilitata.
3. Ricreare le righe di `additional_remover` in URL da rimuovere (JS → script, CSS → stile).
4. Ricreare le righe di `additional_add` in Script/CSS personalizzati, mantenendo l’ordine; configurare gli attributi se necessari.
5. Ricopiare `unsetgenerator` e `unsetauthor`; copiare `additional_meta`: `name` → nome, `value` → contenuto, `httpeq` → attributo http-equiv.
6. Non copiare ciecamente i vecchi interruttori Bootstrap 2, MooTools, caption, modal o HTML5 fallback: riguardano file di Joomla 3. Individuare gli asset realmente presenti in Joomla 5/6 prima di scegliere le rimozioni. Per jquery/core usare i nomi WebAssetManager correnti.
7. Ricopiare un eventuale MOTD configurato esternamente (il PHP della 1.1.0 esaminata non lo emette).
8. Disabilitare la vecchia versione e abilitare la nuova; verificare sorgente HTML, console browser, moduli e pagine con componenti interattivi. Non tenere abilitate entrambe.

## Compilazione e test

```sh
./build.sh
python3 tests/static_check.py
./tests/docker-test.sh
```

Il pacchetto è riproducibile: contiene solo `src/`, con nomi ordinati e timestamp fissi; il checksum è in `dist/*.sha256`. `tests/docker-test.sh` richiede Docker funzionante e rete per scaricare `php:8.3-cli`, `joomla:5`, `joomla:6` e `mariadb:11`. Installa siti temporanei, verifica le versioni esatte delle serie 5.4/6.2, installa e abilita il plugin con parametri, acquisisce l’HTML via curl e verifica aggiunte, rimozioni, ordine e delimitatori del commento. Rimuove soltanto i propri container e la propria rete anche in caso di errore. Nessun container esistente viene modificato.

Per JED Checker 2.4.4 usare lo ZIP in una Joomla di test con il componente installato; il controllo non sostituisce le prove funzionali. La scheda JED e la verifica dell’account proprietario vanno gestite da Eddy.

## Riferimenti tecnici

API ufficiali consultate: [WebAssetManager](https://api.joomla.org/cms-5/classes/Joomla-CMS-WebAsset-WebAssetManager.html), [HtmlDocument](https://api.joomla.org/cms-5/classes/Joomla-CMS-Document-HtmlDocument.html), [sorgente dei plugin Joomla](https://github.com/joomla/joomla-cms/tree/6.2-dev/plugins/system).

## Licenza

Copyright © 2026 Protocolli Creativi. Software libero distribuito secondo GNU GPL versione 2 o successiva (SPDX `GPL-2.0-or-later`); testo della versione 2 in [LICENSE](LICENSE). Nessuna affiliazione con il progetto Joomla; non sono inclusi loghi del progetto.
