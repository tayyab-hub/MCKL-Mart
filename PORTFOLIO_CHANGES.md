# Portfolio changes — 14 October 2026

## Subsequent live-demo hardening candidate

Prepared on `codex/public-demo-hardening` without changing the live files, production database or GitHub. Adds persistent bounded abuse controls, generated demo identities, record caps, Secure cookies for the public host, a domain-scoped HTTPS redirect and manual WinSCP packaging. The original eight screenshots and architecture diagrams are preserved. The README now links the actual InfinityFree demo and distinguishes deployed behavior from this candidate. See [deployment evidence and outstanding verification](docs/LIVE_DEPLOYMENT_REVIEW.md).

**Muhammad Tayyab — Individual Web Computing Project**

The earlier repair fixed configuration handling, validation, checkout/cart edge cases, responsive behavior and security controls. Its detailed findings and historical evidence remain in [REPAIR_REPORT.md](docs/REPAIR_REPORT.md).

The local configuration is now working: the normal Apache site returns HTTP 200, and the dedicated application account successfully reads the 17-product, four-category portfolio catalogue. The private configuration was preserved and excluded from publication; its contents were not printed.

## Publication preparation

- Reworked README into a project overview, real screenshot gallery, feature/stack tables, architecture and ER diagrams, installation, testing, security controls and honest limitations.
- Retained all 17 author-supplied AI-generated product images and documented their confirmed ChatGPT Image Generation origin and visual review.
- Replaced the standalone logo with an original code-and-cart mark that says Academic Demo; kept the existing blue/gold design and product-image paths.
- Refreshed three existing screenshots and added five more, all captured from the application with disposable data.
- Added asset and screenshot manifests, source/provenance notes, reproducible testing notes and publication-review evidence.
- Limited workflow permissions to read-only repository contents and expanded JavaScript syntax checks.
- Initialized Git and prepared an explicitly reviewed publication candidate. Private configuration, logs, test databases, tool archives and backups remain excluded.

No existing database was reset or overwritten. SCAMGUARD, ResumeIQ and SlipFlow were not modified. After the author's explicit approval, the project was published from reviewed commit `9a00bd8` to [tayyab-hub/MCKL-Mart](https://github.com/tayyab-hub/MCKL-Mart), preserving its history. Both GitHub Actions jobs passed on the first run; all eight screenshots and both Mermaid diagrams were verified in the public README. See [PUBLICATION_REVIEW.md](docs/PUBLICATION_REVIEW.md) for the evidence and remaining application limitations.
