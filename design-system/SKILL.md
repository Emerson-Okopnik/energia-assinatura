---
name: lider-energy-design
description: Use this skill to generate well-branded interfaces and assets for Líder Energy (Consórcio Líder Energy), a Brazilian solar-energy subscription consortium, either for production or for throwaway prototypes/mocks. Contains essential design guidelines, colors, type, fonts, assets, and UI kit components for prototyping.
user-invocable: true
---

Read the `README.md` file within this skill, and explore the other available files.

Key files:
- `README.md` — brand context, audience, content fundamentals, visual foundations, iconography, file index
- `colors_and_type.css` — tokens and semantic classes (primary/neutrals/semantic colors, spacing, radii, shadows, Nunito type scale)
- `assets/` — logo variants (color, solid, black, white)
- `preview/` — design-system specimen cards
- `ui_kits/marketing/` — recreation of consorcioliderenergy.com (Header, Hero, AudiencePane, HowItWorks, FAQ, Footer)
- `ui_kits/portal/` — speculative subscriber dashboard (AppShell, Dashboard)

If creating visual artifacts (slides, mocks, throwaway prototypes, etc.), copy assets out and create static HTML files for the user to view. If working on production code, you can copy assets and read the rules here to become an expert in designing with this brand.

**Language:** All product copy is Brazilian Portuguese. No English CTAs. No emoji. Tone is warm, practical, benefit-first.

**Palette anchors:** Líder Orange `#F39325`, Ember `#D97613`, Leaf `#5FB53A` (small accent only), Ink `#3D3D3D`, Linen `#FAF6F1`.

**Flagged substitutions** (swap if client provides originals):
- Typeface: Nunito (close to the wordmark's rounded geometric sans — brand manual not on disk).
- Icons: Lucide via CDN.

If the user invokes this skill without any other guidance, ask them what they want to build or design, ask some questions, and act as an expert designer who outputs HTML artifacts *or* production code, depending on the need.
