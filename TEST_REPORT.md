# Verifica del 7 novembre 2026

## Eseguito

- Lettura del PHP e del manifest della 1.1.0 dallo ZIP locale, senza modificarlo.
- Consultazione dei sorgenti ufficiali Joomla 5.4/6.2 per eventi, plugin, Document e WebAssetManager.
- `python3 tests/static_check.py`: superato. XML, elemento/namespace, file inclusi, quattro file lingua, chiavi tradotte, riferimenti subform, licenza, assenza API Joomla 3 vietate, server aggiornamenti, integrità ZIP, uguaglianza contenuti e build riproducibile.
- `bash -n build.sh tests/docker-test.sh`: superato.
- Compilazione sintattica Python degli script di verifica: superata.
- Revisione manuale del codice: nessun ripristino involontario di `assetManager` tramite Document; rimozione transitiva dei dipendenti; delimitatori MOTD neutralizzati; escaping dei meta; URL custom limitati a HTTP(S) e percorsi locali.

## Non eseguibile in questa sessione

| Prova | Esito osservato |
| --- | --- |
| Joomla 5.4 in `joomla:5` + MariaDB | Non eseguita: accesso al socket Docker negato dal sandbox. |
| Joomla 6.2 in `joomla:6` + MariaDB | Non eseguita, stesso blocco. |
| curl sulla home dei due siti | Non eseguito: nessun sito di test avviato. |
| `php -l` con PHP 8.3 | Non eseguito: interprete locale assente, container PHP non avviabile. |
| JED Checker 2.4.4 | Pacchetto ritrovato in `/private/tmp/claude-501/.../scratchpad/jed/tool/release-2.4.4/`, ma non eseguito: richiede PHP/Joomla. Nessun esito positivo dichiarato. |

`./tests/docker-test.sh` è stato tentato: si è fermato alla verifica iniziale `docker info` con `connect: operation not permitted`. Nessun container, volume o rete è stato creato da questa sessione; non ci sono container di test da rimuovere.

Lo script predisposto installerà il pacchetto con Joomla Installer e abiliterà i parametri di prova. Confronterà via curl HTML prima/dopo: JS e CSS aggiunti in ordine, attributi defer/module/media, rimozione per URL attraverso WAM e Document, rimozione per nome e dipendenti, asset non selezionati conservati, generator/author rimossi, meta name/property/http-equiv ordinati, escaping e MOTD sanificato immediatamente dopo head. Controllerà anche l’esclusione incondizionata del backend con `frontend_only=0`. I risultati non vanno considerati acquisiti finché lo script non è stato eseguito con successo.

Il codice è preparato per Joomla 5 e 6; la compatibilità runtime non è ancora validata.

## Repository e release

Repository locale: `/Users/eddy/Documents/SVILUPPO/head-remover-joomla`, branch `main`, tag annotato locale `v2.0.0`.

Destinazione prevista: `https://github.com/pcrt/head-remover-joomla`; il remoto `origin` è predisposto. L’esistenza del repository pubblico non è verificata.

Tentativi effettuati:

- `gh repo create pcrt/head-remover-joomla --public --source . --push`: fallito con `error connecting to api.github.com`.
- `gh release create v2.0.0 dist/plg_system_headremover-2.0.0.zip --repo pcrt/head-remover-joomla --title "Head Remover 2.0.0" --notes-file NOTE_RELEASE.md`: fallito con lo stesso errore di rete.
- Anche curl dalla shell non risolve github.com. Lo stato dell’autenticazione GitHub non è verificabile con affidabilità da questa sessione.

ZIP pronto: `dist/plg_system_headremover-2.0.0.zip`.
SHA-256: `0da6b9f9fb9133b46055343098161b49493b174852b2c18e436b61e786c9705b`.

Restano da eseguire in un ambiente con Docker e rete disponibili: test funzionali e lint, controllo JED Checker, pubblicazione del repository/tag/release. A Eddy resta inoltre la gestione manuale della scheda JED, inclusa la verifica dell’account proprietario; nessuna modifica alla scheda è stata tentata.
