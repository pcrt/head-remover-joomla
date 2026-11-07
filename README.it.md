# Head Remover

_English: [README.md](README.md)_

Plugin di sistema di Protocolli Creativi per controllare gli asset e i metadati dell’head HTML in Joomla 5 e 6. Licenza **GPL-2.0-or-later**.

## Requisiti

Il codice del plugin richiede PHP 8.1 o successivo; restano validi i requisiti più restrittivi della versione Joomla installata (Joomla 6 richiede PHP 8.3 o successivo). Non richiede jQuery o Bootstrap e non usa le API globali di Joomla 3.

**Stato della verifica del 7 novembre 2026:** pacchetto compilato, controlli statici, `php -l` con PHP 8.1 e 8.4, test funzionali in Docker su Joomla 5.4.10 (PHP 8.3) e Joomla 6.2.0 (PHP 8.4) con MariaDB 11, JED Checker 2.4.4 senza errori né avvisi. Dettagli e output in `TEST_REPORT.md`.

## Installazione

1. Compilare con `./build.sh` (Python 3), oppure scaricare `plg_system_headremover-2.0.0.zip` dagli allegati della release GitHub.
2. In Joomla: Sistema → Installa → Estensioni, caricare lo ZIP.
3. In Sistema → Gestisci → Plugin, aprire **System - Head Remover** (in italiano **Sistema - Head Remover**), configurare e abilitare. Il nome segue la convenzione JED per i plugin, `{Tipo} - {Nome}`; la scheda JED si chiama semplicemente «Head Remover».
4. Ordinare il plugin dopo le estensioni che aggiungono asset; svuotare la cache e verificare le pagine interessate.

Il plugin è un’installazione nuova, elemento `headremover`, gruppo `system`. Il server aggiornamenti punta al file `updateserver/extension.xml` del branch `main` di questo repository: contiene due aggiornamenti equivalenti per le serie 5 e 6, con il download dalla release GitHub e la SHA-256 del pacchetto, che Joomla verifica prima di installare. `build.sh` aggiorna la SHA-256 a ogni compilazione.

## Parametri

| Parametro | Comportamento |
| --- | --- |
| Solo frontend | Attivo inizialmente. Limita l’azione al sito pubblico. |
| Escludi sempre il backend | Attivo inizialmente. Per agire nel backend occorre disattivare entrambe le limitazioni. CLI, API e documenti non HTML (feed, JSON) restano esclusi. |
| Nomi degli asset | Un nome esatto per riga: `jquery`, `core`, `keepalive`, `messages`. Il suffisso `.*` comprende una famiglia, per esempio `bootstrap.*`. Elenco inizialmente vuoto; agisce su script e stili attivi. |
| URL da rimuovere | Righe ripetibili con tipo script/stile/entrambi e URL completo o frammento. Confronto per sottostringa, sensibile alle maiuscole; righe vuote ignorate. |
| Script personalizzati | URL e modalità normale, defer, async o module. |
| CSS personalizzati | URL e attributo media (inizialmente `all`). |
| Rimuovi generator/author | Opzioni indipendenti, inizialmente disattive. |
| Metadati personalizzati | Righe ordinate con attributo `name`, `property` o `http-equiv`, nome e contenuto. I valori sono codificati per HTML. |
| MOTD | Commento subito dopo l’apertura di `<head>`; delimitatori neutralizzati. |

**Disabilitare `core` può rompere moduli, menu e altre funzioni.** Il WebAssetManager riattiverebbe un asset se richiesto da una dipendenza: per rendere effettiva la rimozione il plugin disabilita anche gli asset attivi che lo richiedono, transitivamente (con `core` spariscono per esempio `keepalive`, `messages`, le opzioni script e i componenti Bootstrap). La stessa regola vale per la rimozione tramite URL. Provare su una copia del sito prima di abilitare rimozioni.

Le aggiunte vengono registrate nel WebAssetManager e ogni riga dipende dalla precedente dello stesso tipo. L’ordine dei tag è deterministico; `async` non garantisce l’ordine di esecuzione, e moduli/defer seguono le regole del browser. Non esiste un ordine di esecuzione comune tra CSS e JavaScript.

Sono accettati URL HTTP(S), URL relativi al protocollo e percorsi locali. I percorsi senza slash iniziale sono relativi alla radice Joomla (anche in sottocartella); quelli con slash iniziale sono relativi alla radice del dominio. I percorsi locali vengono emessi come URL assoluti del sito corrente. Il plugin non carica né verifica i file sul server. Aggiungere percorsi esistenti e fonti affidabili; eventuali restrizioni CSP restano applicate dal browser.

La rimozione riguarda risorse registrate tramite WebAssetManager o le API pubbliche di Document, anche da template ed estensioni. Tag scritti direttamente nel template, tag in `addCustomTag`, inserimenti successivi di altri plugin e risorse caricate dal browser non vengono riscritti. Non rimuove indiscriminatamente script inline.

I metadati personalizzati vengono aggiunti nell’ordine delle righe tramite i custom tag del documento: Joomla li emette in fondo all’head, dopo gli script, nel punto in cui il template include `<jdoc:include type="scripts" />`. Possono ripetere lo stesso nome (per esempio più `og:image`); righe identiche vengono emesse una sola volta. Per un `http-equiv` che deve precedere gli script, come `Content-Security-Policy`, usare l’intestazione HTTP del server. Per evitare duplicati con i metadati del template vanno coordinati con quest’ultimo.

Asset e metadati sono elaborati in `onBeforeCompileHead`, con priorità bassa. Solo il posizionamento del commento già sanificato avviene sull’HTML finale, in `onAfterRender` (prima dell’eventuale compressione gzip) e, per le pagine di errore come la 404, in `onBeforeRespond`, perché Joomla non lancia `onAfterRender` dopo un’eccezione. È consigliabile mantenere il meta charset all’inizio dell’head; il commento non introduce contenuto eseguibile.

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
JEDCHECKER_ZIP=/percorso/com_jedchecker-2.4.4.zip ./tests/docker-test.sh
```

Il pacchetto è riproducibile: contiene solo `src/`, con nomi ordinati e timestamp fissi; il checksum è in `dist/*.sha256` e in `updateserver/extension.xml`.

`tests/docker-test.sh` richiede Docker funzionante e rete per scaricare `php:8.1-cli`, `php:8.4-cli`, `joomla:5-php8.3-apache`, `joomla:6-php8.4-apache` e `mariadb:11`. Esegue `php -l` con PHP 8.1 e 8.4, installa due siti temporanei (serie 5.4 e 6.2, versione verificata), inietta nel template Cassiopeia una fixture con asset registrati via WebAssetManager e via Document, installa il plugin con `php cli/joomla.php extension:install`, lo abilita con parametri di prova e confronta l’HTML acquisito con curl: aggiunte in ordine con attributi, rimozioni per nome, per famiglia (`bootstrap.*`) e per URL con i dipendenti, asset non selezionati conservati, generator/author rimossi, metadati ordinati con escaping, MOTD sanificato subito dopo `<head>` anche sulla pagina 404, esclusione del backend. Nel backend esegue il login, controlla l’elenco dei plugin e il form di modifica (schede, etichette tradotte, subform) e salva i parametri attraverso il form reale, verificandone l’effetto sul frontend. Installa poi una seconda volta lo stesso ZIP (configurazione e HTML invariati), disabilita il plugin (HTML uguale a quello iniziale) e controlla che non ci siano messaggi PHP nei log del container e in `administrator/logs`. Gli HTML e i log restano in `tests/results/`. Rimuove soltanto i propri container e la propria rete anche in caso di errore; nessun container esistente viene modificato.

Con `JEDCHECKER_ZIP` lo script installa anche JED Checker in ciascun sito ed esegue tutte le sue regole sul pacchetto tramite `tests/jedchecker-run.php`, fallendo in presenza di errori o avvisi. Il pacchetto del componente si ottiene dall’archivio sorgente della release su https://github.com/joomla-extensions/jedchecker/releases (la release non ha allegati): `zip -r com_jedchecker-2.4.4.zip .` dalla cartella estratta. JED Checker 2.4.4 usa ancora `Joomla\CMS\Filesystem\*`, rimosso in Joomla 6: il componente si installa ma la sua interfaccia non funziona in Joomla 6; il runner da CLI aggira il problema con un alias verso `Joomla\Filesystem\*` e produce gli stessi risultati delle regole in Joomla 5.

## Riferimenti tecnici

API ufficiali consultate: [WebAssetManager](https://api.joomla.org/cms-5/classes/Joomla-CMS-WebAsset-WebAssetManager.html), [HtmlDocument](https://api.joomla.org/cms-5/classes/Joomla-CMS-Document-HtmlDocument.html), [sorgente dei plugin Joomla](https://github.com/joomla/joomla-cms/tree/6.2-dev/plugins/system).

## Licenza

Copyright © 2026 Protocolli Creativi. Software libero distribuito secondo GNU GPL versione 2 o successiva (SPDX `GPL-2.0-or-later`); testo della versione 2 in [LICENSE](LICENSE). Nessuna affiliazione con il progetto Joomla; non sono inclusi loghi del progetto.
