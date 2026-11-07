# Changelog

## 2.0.0 — 2026-11-07

- Namespaced rewrite for Joomla 5/6 with DI provider and SubscriberInterface events.
- Removal by WebAssetManager name, family and URL fragment, including dependent active assets.
- Custom scripts and CSS with ordered dependencies and loading attributes.
- Generator/author handling, ordered metadata and sanitised MOTD comment, also on error pages.
- Frontend/backend safeguards, English and Italian translations; plugin name "System - Head Remover" following the JED convention.
- New `headremover` element: manual migration from 1.1.0.
- Reproducible ZIP build and GitHub update server with the package SHA-256.
- Verified in Docker on Joomla 5.4.10 (PHP 8.3) and 6.2.0 (PHP 8.4); JED Checker 2.4.4 with no errors or warnings. See TEST_REPORT.md.
