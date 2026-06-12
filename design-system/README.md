# Líder Energy — Design System

> Energia solar inteligente, por assinatura. Sem obras, sem placas, sem burocracia.

Líder Energy (operating as **Consórcio Líder Energy**) is a Brazilian energy-subscription consortium that connects solar power plants (*usineiros*) with electricity consumers. Consumers get up to ~28% off their power bill without installing anything; plant owners get predictable recurring revenue from their excess generation.

Legal entity: *Liberdade Energia Consórcio De Consumidores De Energia Eletrica* — CNPJ 58.750.788/0001-33. Regulated under Brazilian **Lei 14.300/22** and ANEEL (Agência Nacional de Energia Elétrica).

Language: **Brazilian Portuguese** — all product copy, UI, and marketing.

---

## Sources used to build this system

- `uploads/LIDER ENERGY COR.png` — full-color horizontal logo (orange mark + dark wordmark)
- `uploads/LIDER ENERGY PRETO.png` — dark/monochrome logo
- `uploads/LIDER ENERGY SOLIDO.png` — solid-orange variant of the mark
- `uploads/LIDER ENERGY BRANCO.png` — white (all-white) logo for dark surfaces
- Public site: https://consorcioliderenergy.com/ — scraped for copy, audience segments, FAQ tone, and feature language

> ⚠️ **Missing source:** `uploads/Líder Energy - Manual de Marca.pdf` was referenced in the brief but **not present** on disk. The typography, exact hex values, and grid rules in this system are **reasoned from the logo, the public site, and the category** — if the manual de marca turns up later, re-run this skill so we can lock in exact hex codes, the house typeface(s), and any grid/clearspace rules.

---

## Audiences (used for tone + imagery decisions)

1. **Consumidor final** — residents, small businesses, condomínios. Wants a lower light bill with zero friction. Copy leads with *economize*, *sem instalar nada*, *prático*.
2. **Usineiros / investidores** — solar plant owners, ESG funds, agro and industrial generators with surplus. Copy leads with *receita recorrente*, *previsibilidade*, *segurança jurídica*.

Both audiences share the same brand — the same mark, the same palette. Segmentation is done through copy and imagery, not separate sub-brands.

---

## Products / surfaces in scope

| Surface | What it is | Status in this repo |
|---|---|---|
| **Marketing site** (`consorcioliderenergy.com`) | Single-page lead-gen site: hero, two audience panes, "Como funciona", FAQ, contact. | Recreated in `ui_kits/marketing/` |
| **Consumer app/portal** *(assumed)* | Where a subscriber sees their bill savings, their usage, their plant assignment. | Recreated as a plausible UI in `ui_kits/portal/` — flagged as **speculative**, no source code was provided. |

---

## Content fundamentals

**Language:** Brazilian Portuguese exclusively. No English-language CTAs.

**Tone:** Warm, practical, and reassuring. The brand is selling trust in an unfamiliar regulatory model (energia por assinatura), so copy leans **concrete and benefit-first** rather than aspirational. It speaks *to* you (second person — "sua conta", "você economiza") without being overly chummy.

**Casing:**
- Section headers: ALL CAPS is used sparingly, mostly on nav links (`SOBRE NÓS`, `SOLUÇÕES`, `FAQ`, `CONTATO`).
- CTAs on the site are ALL CAPS: `CONVERSE COM UM ESPECIALISTA`.
- Body and H1/H2 are sentence case: *"Energia solar inteligente"*, *"Para Consumidores Finais"*.

**Voice characteristics** (drawn from live copy):
- Short, declarative sentences. Two-clause structures with a comma and "e": *"Economize na conta de luz e tenha energia limpa"*.
- Concrete numbers: *"até 28% na sua conta de luz"*, *"até 29% de economia"*, *"Lei 14.300/22"*. Percentages and statute numbers do heavy trust-building work.
- Bulleted benefits stated as nouns or adjective phrases, not sentences: *"Sem custo de instalação / Sem obras ou equipamentos / Redução real na fatura / Energia limpa e legal"*.
- FAQ answers begin with a direct yes/no when possible: *"Sim, o consórcio de energia solar por assinatura é legal e regulamentado..."*.
- Audience labels are capitalized noun phrases: *"Para Consumidores Finais"*, *"Para Usineiros e Investidores"*.

**Vocabulary to prefer:** *economizar, rentabilizar, assinatura, consórcio, créditos de energia, usina, fatura, conta de luz, sustentável, segurança jurídica, sem burocracia, previsível, recorrente*.

**Vocabulary to avoid:** corporate jargon (*"sinergia", "disruptivo", "solução 360°"*), hype (*"revolucionário", "incrível"*), English loanwords where Portuguese works (*"subscription"* → *"assinatura"*, *"savings"* → *"economia"*).

**No emoji.** The source site uses none; the category (regulated energy) is not a place for 🌞⚡.

**Punctuation:** Ordinary Brazilian Portuguese punctuation. No em-dashes used as sentence breakers. No interrobangs.

**Example copy that's on-brand:**
> Energia solar inteligente, para quem quer economizar ou rentabilizar.
>
> Economize até 28% na sua conta de luz sem precisar instalar nada.
>
> Transforme sua usina solar em uma fonte de receita previsível e recorrente.

**Example copy that's off-brand:**
> 🌟 Unlock the power of the sun! ✨ Join the solar revolution today!

---

## Visual foundations

### Palette
The brand is built on a single, confident **orange** drawn from the logomark's sunflower/aperture shape, paired with a warm **charcoal** wordmark. A small **leaf green** accent appears inside the mark's central "house + four panels" glyph.

| Role | Hex | Notes |
|---|---|---|
| Primary — Lider Orange | `#F39325` | The hero color. CTAs, mark, key callouts. Sampled from `LIDER ENERGY COR.png`. |
| Primary deep — Ember | `#D97613` | Hover/press, gradient bottom, deeper fills. |
| Primary warm — Apricot | `#F9B566` | Highlights, soft surfaces, illustrative mid-tones. |
| Accent — Leaf | `#5FB53A` | The small green 2×2 grid inside the mark. Used for "clean/legal" signals (sustentável, regulamentado) — never dominant. |
| Charcoal — Ink | `#3D3D3D` | Wordmark color; body text on light. |
| Neutral — Graphite | `#5C5C5C` | Secondary text. |
| Neutral — Smoke | `#B0B0B0` | Borders, disabled text. |
| Surface — Linen | `#FAF6F1` | Warm off-white page surface. |
| Surface — Paper | `#FFFFFF` | Cards, overlays. |
| Success | `#3FA14A` | Payment confirmed, bill reduced. |
| Warning | `#E0A41C` | Pending plant assignment, document required. |
| Danger | `#C53B2F` | Contract issue, failed payment. |

> Green is the accent, **not** a secondary primary. Keep its footprint small so it reads as a signal, the way it does inside the logomark.

### Typography
The wordmark is a soft-geometric rounded sans with a tall x-height — very close to **Nunito** / Nunito Sans, with similar characteristics to **Quicksand** and **Rubik**. Without the brand manual on disk we're **substituting Nunito** (Google Fonts) as the display + UI face, with Inter as a neutral body fallback for dense UI.

> ⚠️ **Font substitution flagged:** Confirm with the brand manual which typeface the wordmark is set in. If it's a licensed face (e.g., *Core Rhino*, *Uni Sans Heavy*, *Omnes Rounded*, *Gotham Rounded*), please drop the TTF/WOFF2 into `fonts/` and we'll re-bind the CSS variables.

- **Display / UI headings:** Nunito (700, 800, 900)
- **Body / UI:** Nunito (400, 500, 600, 700)
- **Numeric / monospace:** JetBrains Mono — for invoice lines, CNPJs, and kWh readings in dashboards.

See `colors_and_type.css` for the full set of CSS custom properties and semantic classes.

### Corner radii
Rounded but not pill-y — mirrors the soft wordmark's terminals without looking bubbly.

- `--radius-sm: 6px` — tags, chips
- `--radius-md: 12px` — inputs, small buttons
- `--radius-lg: 20px` — cards, panels
- `--radius-xl: 28px` — hero cards, feature illustrations container
- `--radius-pill: 999px` — primary CTAs (the site uses pill CTAs)

### Elevation / shadow system
Soft, warm shadows. Never a hard drop. Always tinted slightly toward the charcoal, not pure black.

- `--shadow-xs`: `0 1px 2px rgba(61,61,61,0.06)` — input focus ring shadow partner
- `--shadow-sm`: `0 2px 6px rgba(61,61,61,0.08)` — menu, hovercard
- `--shadow-md`: `0 8px 24px rgba(61,61,61,0.10)` — lifted cards
- `--shadow-lg`: `0 20px 48px rgba(61,61,61,0.14)` — modals, toasts
- `--shadow-glow`: `0 12px 40px rgba(243,147,37,0.28)` — for hero CTAs on light backgrounds (used sparingly)

No neumorphic inner shadows. No colored drop shadows except the orange glow on the primary hero CTA.

### Spacing (4px base)
`4, 8, 12, 16, 20, 24, 32, 40, 48, 64, 80, 96`. Variables: `--space-1` through `--space-12`.

### Borders
- `1px solid` neutrals for utility borders: `var(--color-smoke)` at ~20% opacity on white, full on linen.
- `2px solid` for emphasis borders (e.g., selected audience card).
- No dashed or dotted borders in the core system.

### Backgrounds
- **Primary page surface:** warm off-white (*Linen* `#FAF6F1`). Never pure cold gray.
- **Hero / marketing sections:** alternate Linen and white, with occasional orange radial glow top-left behind the headline.
- **Photography treatment:** natural daylight solar farms, rooftops, Brazilian landscapes. Warm, slightly golden color grading to match the palette. Not overly saturated. No black-and-white photography. No grain/film emulation.
- **Illustrations:** the site uses flat, rounded illustrations of people + solar panels — friendly but not cartoony. If commissioning, prefer: rounded geometry, limited palette (orange + green + warm neutrals), thick strokes, no gradients inside illustrations.
- No repeating patterns or textures in the core system. The logomark's "sunflower" shape can be used as a subtle decorative mask on hero backgrounds at ≤ 8% opacity.

### Gradients
Used **rarely** and always within the orange family. Never cross to purple, blue, or green. Approved:
- `--grad-sun`: `linear-gradient(135deg, #F9B566 0%, #F39325 45%, #D97613 100%)` — for the CTA hover state and the hero-mark fill on solid surfaces.
- Avoid: multi-hue mesh gradients, blue→purple gradients, glassmorphic frosted panels.

### Transparency & blur
- Transparency is used for shadow colors and for subtle logo watermarks (e.g., `rgba(243,147,37,0.06)` wash behind testimonial quotes).
- Backdrop blur is allowed only on overlay headers when content scrolls behind (*mobile nav drawer, sticky portal header*). `backdrop-filter: blur(16px)` with a 70%-opacity linen.

### Motion
- **Easing:** `cubic-bezier(0.22, 1, 0.36, 1)` (ease-out quart) for enter/exit, `cubic-bezier(0.4, 0, 0.2, 1)` for micro-interactions.
- **Durations:** `120ms` (hover), `180ms` (press), `280ms` (card → modal), `420ms` (page-section enter).
- **Fades and soft translates (4–8px)**, no bounces, no spring overshoot.
- On scroll reveal: 8px translate + opacity 0→1 over 420ms, staggered 60ms per child. No parallax.

### Interactive states
- **Hover (buttons, primary):** background shifts from `--color-primary` to `--color-primary-deep`; no scale change.
- **Hover (buttons, secondary/outline):** fill becomes `--color-primary` at 8% alpha, border color intensifies.
- **Hover (cards/links):** lift by `translateY(-2px)` + shadow bumps `--shadow-sm` → `--shadow-md`, **only** on clickable cards.
- **Press (all):** `scale(0.98)`, 80ms.
- **Focus visible:** 3px offset outline in `--color-primary` at 40% alpha. Never remove focus rings.
- **Disabled:** 40% opacity, `cursor: not-allowed`. No color change.

### Layout rules
- **Max content width:** 1200px for marketing, 1280px for app dashboards.
- **Gutters:** 24px mobile, 40px tablet, 72px desktop.
- **Vertical rhythm in marketing:** 96px between major sections on desktop, 64px on mobile.
- Sticky elements: site header, portal sidebar. Both use a blurred linen background when content scrolls beneath.

### Cards
Default card: white fill, `--radius-lg` (20px), `--shadow-sm`, no border. Hoverable cards lift and pick up `--shadow-md`. Section-divider cards (the two audience panes on the site) use Linen fill + a 2px Apricot border on the currently-selected state.

---

## Iconography

The source site uses **custom flat PNG icons** (`icone_01.png`–`icone_04.png`) in the "Como funciona" section — round warm-colored illustrations rather than line icons. For the rest of the UI (form fields, portal navigation, dashboard stats), no branded icon set was provided.

**Strategy:**
- For the "Como funciona" numbered steps, we preserve the illustration-style PNG approach — ready for the client to drop in real renders.
- For everything else (form fields, portal chrome, dashboard), we use **Lucide** (via CDN, stroke-only, 1.75px weight) as a neutral, rounded-friendly substitute that reads well next to the rounded wordmark.

> ⚠️ **Icon substitution flagged:** If Líder has a house icon set, replace `lucide` CDN with the real set and update `ui_kits/*/README.md` accordingly.

- No emoji in product UI.
- No unicode glyphs as icons (✓, ★ etc.) — use Lucide's `check`, `star`.
- Icon color defaults to `currentColor` so it inherits text color. Primary-colored icons (orange) are reserved for active/selected states and data visualization.
- Sizes: 16, 20, 24, 32 px. Align to 4px grid.

---

## Index / manifest

```
README.md                  ← you are here
SKILL.md                   ← Agent-Skills-compatible entry point
colors_and_type.css        ← tokens + semantic classes
assets/                    ← logos + any future imagery
  logo-color.png           ← horizontal, orange mark + charcoal wordmark
  logo-solid.png           ← horizontal, solid-orange mark + charcoal wordmark
  logo-black.png           ← horizontal, all charcoal
  logo-white.png           ← horizontal, all white (for dark surfaces)
fonts/                     ← (empty — using Google Fonts CDN for Nunito)
preview/                   ← design-system cards rendered into the tab
ui_kits/
  marketing/               ← recreation of consorcioliderenergy.com
  portal/                  ← speculative subscriber portal
```

There are no slides in this repo — no deck template was provided.
