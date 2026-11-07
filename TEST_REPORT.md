# Verifica del 7 novembre 2026

Verifica a runtime eseguita con Docker 27.4.0 (linux/arm64) sul pacchetto `dist/plg_system_headremover-2.0.0.zip`, SHA-256 `79eef10463e17e8f95c498132ac1b4a3f5a1cc5639ec8052423d14f449b8ebef`. Comando: `JEDCHECKER_ZIP=/percorso/com_jedchecker-2.4.4.zip ./tests/docker-test.sh` (esito `ALL PASS`, exit code 0); gli HTML e i log acquisiti restano in `tests/results/`.

## Ambiente

| Componente | Immagine / versione |
| --- | --- |
| Joomla 5 | `joomla:5-php8.3-apache` → Joomla 5.4.10, PHP 8.3.36, Apache 2.4.69 |
| Joomla 6 | `joomla:6-php8.4-apache` → Joomla 6.2.0, PHP 8.4.27, Apache 2.4.69 |
| Database | `mariadb:11`, un database per sito (`joomla5`, `joomla6`) |
| Lint | `php:8.1-cli` (PHP 8.1.34) e `php:8.4-cli` (PHP 8.4.27) |
| JED Checker | 2.4.4, archivio sorgente della release GitHub (la release non ha allegati), impacchettato con `zip -r` |

## Esito per verifica

| Verifica | Joomla 5.4.10 | Joomla 6.2.0 |
| --- | --- | --- |
| `php -l` su `src/` e `tests/` con PHP 8.1 e 8.4 | nessun errore | nessun errore |
| Controllo statico `tests/static_check.py` (manifest, lingue, subform, API vietate, licenza, update server con SHA-256, build riproducibile) | PASS | PASS |
| Installazione da CLI `php cli/joomla.php extension:install --path=…zip` | `Extension installed successfully`, plugin disabilitato, update site registrato | idem |
| Plugin installato ma disabilitato: HTML della home identico a quello precedente (token CSRF e media version mascherati) | identico | identico |
| Plugin abilitato: rimozione per nome (`hr.fixture.base`) e dei dipendenti, famiglia `bootstrap.*` (`collapse.min.js`, `bootstrap.min.css`), per URL via WebAssetManager e via Document; asset non selezionati conservati (`core.min.js`, opzioni script, `keep-*`) | PASS | PASS |
| Script e CSS aggiunti nell’ordine indicato con `defer`/`module` e `media`; `javascript:` rifiutato | PASS | PASS |
| Meta `generator` e `author` rimossi; meta personalizzati `name`/`property`/`http-equiv` in ordine con escaping | PASS | PASS |
| MOTD sanificato (`-->` e `<script>` neutralizzati) subito dopo `<head>`, anche sulla pagina 404 | PASS | PASS |
| Backend escluso con `frontend_only=0` e `exclude_backend=1` (pagina di login amministrativa invariata) | PASS | PASS |
| Backend: login, elenco plugin con nome «System - Head Remover», form di modifica senza chiavi non tradotte e con 4 subform, salvataggio dal form reale applicato sul frontend | PASS | PASS |
| Seconda installazione della stessa zip: record `#__extensions` invariato (enabled e hash dei parametri), un solo update site, HTML identico | PASS | PASS |
| Plugin disabilitato dopo le prove: HTML identico a quello iniziale | PASS | PASS |
| Nessun messaggio PHP nel log del container (Apache/PHP) né file in `administrator/logs` con `error_reporting = 'maximum'` | nessuno | nessuno |
| JED Checker 2.4.4, tutte le 12 regole sul pacchetto | 0 errori, 0 avvisi, 0 notice | 0 errori, 0 avvisi, 0 notice (vedi nota) |

Prove manuali aggiuntive, con gli stessi container, non comprese nella pipeline: rimozione di `core` (spariscono anche `keepalive`, `messages`, `passwordview`, `bootstrap.collapse`, il blocco delle opzioni script e gli script di template/menu che ne dipendono, senza errori); parametri nel formato oggetto salvato dal form (`{"scripts":{"scripts0":{…}}}`); backend elaborato con entrambe le protezioni disattivate; feed RSS (`?format=feed`) non toccato; compressione gzip/deflate attiva nella configurazione globale: MOTD presente sulla home e sulla 404, nessun errore.

## Note

- JED Checker 2.4.4 è un componente per Joomla 4/5 e usa `Joomla\CMS\Filesystem\Folder`, rimosso in Joomla 6: si installa in Joomla 6, ma la sua interfaccia va in errore. `tests/jedchecker-run.php` esegue le stesse regole da CLI con un alias verso `Joomla\Filesystem\*`; i risultati in Joomla 5 e 6 coincidono.
- La prima esecuzione del checker segnalava l’avviso «The name of the plugin ('Head Remover') must comply with the JED naming conventions in the form '{Type} - {Extension Name}'»: il nome tradotto è ora «System - Head Remover» (italiano «Sistema - Head Remover»). La scheda JED resta «Head Remover».
- La regola JAMSS segnalava la sequenza esadecimale `"\x1f\x8b"` usata per riconoscere un body già compresso: sostituita dal controllo dell’intestazione `Content-Encoding`.
- La pagina 404 non riceve `onAfterRender` (il corpo viene impostato dall’ExceptionHandler): il MOTD viene collocato in `onBeforeRespond` come ripiego, dopo la verifica che la risposta non sia già compressa.
- `InstallerHelper::unpack()` estrae l’archivio accanto al file: nella pipeline la zip viene copiata in una cartella scrivibile del container prima di `extension:install`. L’immagine ufficiale abilita opcache con `revalidate_freq=2`: la fixture del template viene applicata prima della prima richiesta HTTP.
- La 1.1.0 (`/Users/eddy/Documents/SVILUPPO/protocollicreativi.it/plg_head_remove/1.0.1.zip`) non è stata modificata.

## Repository e release

Repository locale `/Users/eddy/Documents/SVILUPPO/head-remover-joomla`, branch `main`, tag locale `v2.0.0` sul commit finale. Nessuna azione pubblica è stata eseguita: repository GitHub `pcrt/head-remover-joomla`, release con la zip allegata e scheda JED restano da fare a mano (vedere README e NOTE_RELEASE.md).
