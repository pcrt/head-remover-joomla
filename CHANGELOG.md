# Changelog

## 2.0.0 — 2026-11-07

- Riscrittura namespaced per Joomla 5/6 con provider DI ed eventi SubscriberInterface.
- Rimozione per nome WebAssetManager, famiglia e frammento URL, inclusi gli asset attivi dipendenti.
- Script e CSS personalizzati con dipendenze ordinate e attributi di caricamento.
- Gestione generator/author, metadati ordinati e commento MOTD sanificato, presente anche sulle pagine di errore.
- Protezioni frontend/backend, traduzioni italiano e inglese; nome del plugin «System - Head Remover» secondo la convenzione JED.
- Nuovo elemento headremover: migrazione manuale dalla 1.1.0.
- Build ZIP riproducibile e server aggiornamenti GitHub con SHA-256 del pacchetto.
- Verificato in Docker su Joomla 5.4.10 (PHP 8.3) e 6.2.0 (PHP 8.4); JED Checker 2.4.4 senza errori né avvisi. Vedere TEST_REPORT.md.
