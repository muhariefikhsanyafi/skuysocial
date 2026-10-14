# Shared theme and mobile navigation browser regression

Build first, then use an installed Chromium/Edge browser and PHP 8.3+:

```powershell
npm run build
npm run test:browser:theme-navigation -- '--php=C:\laragon\bin\php\php-8.3.30-Win32-vs16-x64\php.exe' '--browser=C:\Program Files (x86)\Microsoft\Edge\Application\msedge.exe' '--output=C:\path\to\qa-artifacts'
```

The dependency-free Node runner starts its own loopback Laravel server and a hidden browser. It migrates a newly created temporary SQLite file, seeds synthetic `example.test` accounts, logs in through the real form, and removes that database/browser profile afterwards. It does not accept an application URL or use an existing database. No uploaded files are created. Close a Vite development server before using built assets; the runner rejects `public/hot`.

The runner holds the main Alpine module while CSS and the actual Blade document paint. It records paint entries, root class mutations, animation-frame samples, early/settled screenshots, and checks that saved theme selection precedes first paint. It also tests real anchor navigation, direct navigation, reloads, repeated navigation, invalid/missing preferences, denied storage, and theme toggling. Existing defaults remain dark-first for Feed and system preference for other pages.

Badge checks compare icon-relative geometry, dimensions, colors, and transform across Feed/Profile/shared routes, both themes, 390/320 px, and real unread counts 0/1/2/12. Active state is also applied to the same real navigation nodes to isolate state styling from Activity's intentional mark-as-read behavior. Messages is additionally tested on its real active route. Desktop 1536/1280 checks preserve sticky geometry against optional before evidence. Browser checks exercise Like/Bookmark persistence and Avatar Studio opening, controlled preview failure, closing, and focus restoration; the provider is not contacted.

External document resources are blocked for reproducible offline screenshots, so these use the project's font fallback. The CSP case injects a local hash-authorized policy to verify the theme bootstrap; unrelated legacy inline scripts/fonts can be blocked under that deliberately stricter policy and remain recorded in results. The PHP test separately checks the Vite nonce integration. This does not verify Production headers or strict-CSP compatibility of the whole application.

Output contains screenshots and `results.json`. Normal `after` mode exits nonzero on failed checks. Optional `--phase=before` records known failures without failing the process, for comparison before a repair. If a sibling `before/results.json` exists, `after` also compares approved Feed badges and Desktop geometry against it. Keep generated evidence outside Git.
