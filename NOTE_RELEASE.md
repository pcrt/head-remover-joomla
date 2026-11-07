Head Remover 2.0.0 is a rewrite of the plugin for Joomla 5 and 6: asset handling through the WebAssetManager, removals by name, family and URL including their dependants, ordered JS/CSS with attributes, custom metadata, removal of the generator and author tags, and a sanitised MOTD comment. English and Italian interface. GPL-2.0-or-later.

It is a new installation (`headremover`), separate from 1.1.0 (`head_remover`). The manual migration is described in the README. The old version is left untouched. It appears in the plugin manager as "System - Head Remover".

Requires PHP 8.1+ and the requirements of the Joomla version in use (PHP 8.3+ for Joomla 6). Disabling `core` or other dependencies can break site features: try it on a copy first.

Verified in Docker on Joomla 5.4.10 (PHP 8.3) and Joomla 6.2.0 (PHP 8.4) with MariaDB 11; PHP 8.1 and 8.4 lint; JED Checker 2.4.4 with no errors or warnings. The update server publishes the SHA-256 of the package.
