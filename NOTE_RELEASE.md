Head Remover 2.0.0 riscrive il plugin per Joomla 5 e 6: gestione degli asset con WebAssetManager, rimozioni per nome, famiglia e URL con i dipendenti, JS/CSS ordinati con attributi, metadati personalizzati, rimozione di generator/author e commento MOTD sanificato. Interfaccia italiana e inglese. GPL-2.0-or-later.

È una nuova installazione (`headremover`), distinta dalla 1.1.0 (`head_remover`). Migrazione manuale descritta nel README. La vecchia versione resta invariata. Nel gestore plugin compare come «System - Head Remover».

Richiede PHP 8.1+ e i requisiti della versione Joomla utilizzata (PHP 8.3+ per Joomla 6). Disabilitare `core` o altre dipendenze può compromettere le funzioni del sito: provare prima su una copia.

Verificato in Docker su Joomla 5.4.10 (PHP 8.3) e Joomla 6.2.0 (PHP 8.4) con MariaDB 11; lint PHP 8.1 e 8.4; JED Checker 2.4.4 senza errori né avvisi. Il server aggiornamenti pubblica la SHA-256 del pacchetto.
