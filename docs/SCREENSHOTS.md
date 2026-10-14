# Screenshot gallery evidence

All eight files below are genuine browser captures made on 14 October 2026 from the current application after the new academic-project logo was installed. They replace the earlier three-image gallery where applicable. No synthetic website mockups, browser chrome, credentials or real customer records are included. Sizes below describe the requested browser viewport; returned image dimensions differ slightly and are recorded exactly in the manifest.

| File | Capture | Content |
| --- | --- | --- |
| `screenshots/homepage-desktop.jpg` | 1440 × 900 viewport | Paused first slide, project logo and academic notice |
| `screenshots/catalogue-desktop.jpg` | 1440 × 900 viewport | Meals category and four products |
| `screenshots/product-desktop.jpg` | 1440 × 900 viewport | Tuna Melt Sandwich, description, price and stock |
| `screenshots/cart-desktop.jpg` | 1440 × 900 viewport | Green tea and sandwich, quantity controls and RM 12.40 total |
| `screenshots/checkout-desktop.jpg` | 1440 × 900 browser, full-page capture | Fictional delivery details and simulated card selection |
| `screenshots/order-history-desktop.jpg` | 1440 × 900 viewport | Saved fictional order with two lines; actual status remains Pending |
| `screenshots/catalogue-mobile.jpg` | 390 × 844 viewport | Responsive catalogue and mobile navigation |
| `screenshots/product-tablet.jpg` | 768 × 1024 viewport | Responsive product details |

The browser workflow added products, completed a guest demo checkout, registered the fictional customer, verified same-session order linking and displayed the resulting order history. It then logged out. `Demo Shopper`, `shopper@example.test`, `0100000000` and `10 Example Street, Demo City` are test fixtures. They do not identify a real customer or an operational delivery address. No password field was captured.

The preview used a newly created disposable MariaDB schema. Existing portfolio and coursework records were not used for screenshot writes. Its catalogue was restored from the clean disposable import after integration tests deliberately modified product fixtures. Screenshots are JPEGs directly returned by the browser capture tool; their original dimensions and hashes are recorded in `SCREENSHOT_MANIFEST.json`.

`layout-checks.json` remains historical evidence of 32 measurements across eight pages and four widths from the earlier repair. It is not relabeled as a new run. Fresh desktop/mobile/tablet captures and a no-overflow mobile measurement were performed after the logo change. Neither set constitutes accessibility certification.

[Artwork provenance](ASSETS.md) documents the author-supplied AI illustrations and the original project logo. [README gallery](../README.md#screenshot-gallery) embeds the screenshots using relative links.
