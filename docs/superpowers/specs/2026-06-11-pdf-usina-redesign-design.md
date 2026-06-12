# Redesenho do PDF da Usina — Design

**Data:** 2026-06-11
**Branch:** `feat/ui-faturamento-redesign`
**Arquivo-alvo principal:** `api-laravel/resources/views/usina.blade.php` (755 linhas)
**Relacionados:** `api-laravel/app/Http/Controllers/PDFController.php`, `api-laravel/app/Http/ViewModels/UsinaPdfViewModel.php`, `api-laravel/app/Support/Format.php`

---

## 1. Problema

O PDF da usina (fatura/demonstrativo de geração) tem três problemas:

1. **Design fora da marca.** Usa Montserrat, gradientes vermelho→amarelo (`#f44336`/`#fbc02d`), headers marrom (`#470b07`) e cinza (`#ededed`) — nada disso pertence ao design system Líder Energy (Líder Orange `#F39325`, Linen, Ink, Nunito + JetBrains Mono).
2. **Logo errado.** Usa `logo-consorcio-lider-energy.png` (2419×811, variante "Consórcio", mark amarelado) em vez da fonte-da-verdade da marca `design-system/assets/logo-color.png` (= `LIDER ENERGY COR.png`, 5206×1632).
3. **Lento.** Chart.js + plugin datalabels e Google Fonts vêm de **CDN externo**, forçando `waitUntilNetworkIdle()` + `setDelay(1500)` + `timeout(90)` no Browsershot. A espera de rede é a maior causa da lentidão.

Há também resíduo de não-DRY/SRP no template: hex mágicos repetidos no CSS, um slice de dados (`$ultimos6Meses`) e dois `number_format` crus dentro do Blade.

4. **Layout quebrado (estado atual).** O PDF gera, mas o layout sai quebrado. Causa provável: a Fase 6 adicionou duas tabelas (Auditoria §8 + Parâmetros de Cálculo) a um layout A4 de página única com rodapé `position: fixed` — o conteúdo estoura a página/sobrepõe o rodapé. Decisão do usuário: **essas duas seções NÃO devem aparecer no PDF** (são informação interna de auditoria, não do cliente). A remoção ataca a causa; a reestilização deve garantir que o conteúdo restante caiba em 1 página A4 sem sobreposição (verificar no render final).

5. **Rótulos enganosos nos dados exibidos.** "Valor Guardado" é kWh (não dinheiro); "Total acum de energia a receber" é o crédito guardado em kWh; "Total acum de fatura concessionária" é na verdade o CUO (fatura + fio B + adicional); "Total acum de faturas emitidas" é o Valor Final calculado. E o crédito que vence é **pago como receita** no Valor Final, mas nada no PDF mostra isso (e "expirado" soa como perda para o cliente).

## 2. Restrições e contexto

- **O cálculo já é única-fonte-de-verdade (Fase 6 do redesenho do motor).** O Blade NÃO recalcula nada: lê de `UsinaPdfViewModel`, que orquestra `FaturamentoService::calcularMes(persistir: false)`. Formatação centralizada em `App\Support\Format` (diretivas `@kwh`, `@reais`, `@tarifa`, `@percentual`). Ver memória `regras-calculo-faturamento`.
- **Decisão de escopo (arquitetura Blade):** manter **arquivo único** reestilizado (não fragmentar em componentes) — é um PDF de página única; o ganho de fragmentar não justifica o risco. DRY vem dos tokens CSS, não de partials.
- **Decisão de escopo (rede):** zerar dependências externas — Chart.js e fontes **locais/embutidas**. É o único jeito de remover `waitUntilNetworkIdle`/`setDelay` e cortar o tempo de verdade.
- **Design system é autoritativo:** tokens em `design-system/colors_and_type.css` (idênticos ao bundle oficial Líder Energy). Sem emoji, sem unicode como ícone (mantêm-se os PNGs atuais), copy pt-BR.

## 3. Objetivo

Refatorar o **design** do PDF para 100% de aderência ao design system Líder Energy, aplicando SOLID/DRY/CLEAN na camada de apresentação, eliminando a latência de rede do Browsershot, e **garantindo que todo valor exibido bate exatamente com o motor de cobrança refatorado** (Fases 0–7).

Fora de escopo: alterar regra de cálculo, fragmentar o Blade em componentes, mexer no `gerarConsumidoresPDF` (PDF de consumidores) ou no merge da fatura Celesc.

### 3.1 Conteúdo do PDF (decisões do usuário, 2026-06-11)

- **REMOVER** as seções "Auditoria de Geração e Créditos" e "Parâmetros de Cálculo" do Blade (e a montagem correspondente no ViewModel pode ser mantida apenas se usada por outra coisa; senão, remover também — manter o ViewModel enxuto).
- **Demonstrativo de Créditos** (mantém, com correções):
  - "Valor Guardado" → **"Guardado (kWh)"** (o dado é energia, não dinheiro).
  - "Mês de vencimento" → **"Vencimento do crédito"**.
  - Adicionar **"Convertido em receita (R$)"** (crédito vencido pago no Valor Final), exibido apenas quando > 0 no mês. Enquadramento positivo obrigatório: o crédito vencido NÃO é perda — o motor o paga como receita; o rótulo nunca deve usar só "expirado"/"perdido".
- **Histórico de Valores** (mantém, com rótulos honestos alinhados ao motor):
  - "Total acum de energia a receber" → **"Crédito guardado acumulado (kWh)"**.
  - "Total acum de fatura concessionária" → **"CUO acumulado (R$)"**.
  - "Total acum de faturas emitidas" → **"Valor a receber acumulado (R$)"**.
  - Separar unidades no rótulo (a coluna "Valor" mistura kWh e R$) e estilizar o `thead` hoje sem estilo.
- **Tabela "Dados de Geração e Faturamento"** (4 termos do motor) — mantém como está, só reestiliza.
- **Cabeçalho:** "Valor a Receber" aparece 2× (header + highlight bar); manter 1× no header + 1× em destaque é aceitável — decisão fina de layout na implementação.

---

## 4. Design

### 4.1 Correção do logo

- Copiar `design-system/assets/logo-color.png` → `api-laravel/public/img/logo-lider-energy-color.png`.
- No `PDFController`, trocar a entrada `'logo' => 'img/logo-consorcio-lider-energy.png'` por `'img/logo-lider-energy-color.png'`.
- Variante **color** (mark laranja→ember + wordmark Ink) é a correta sobre Linen/branco. Não remover o arquivo antigo (pode ser usado em outro lugar — verificar antes; se órfão, pode-se manter por segurança).

### 4.2 Identidade visual (mapa antigo → design system)

| Elemento | Hoje | Design System |
|---|---|---|
| Fonte texto/títulos | Montserrat (CDN) | **Nunito** (400/500/600/700/800), embutida local |
| Fonte numérica | Montserrat | **JetBrains Mono** em números densos (kWh, R$, tarifa, CNPJ, tabelas), embutida local |
| Superfície página | `#ffffff` | **Linen `--color-linen` #FAF6F1** |
| Gradiente | `#f44336`→`#fbc02d` (vermelho→amarelo) | **`--grad-sun`** (`#F9B566`→`#F39325`→`#D97613`), uso parcimonioso |
| Header de tabela (fundo) | `#470b07` (marrom) | **Ink `--color-ink` #3D3D3D** |
| Borda de tabela | `#d32f2f` (vermelho) | **`--color-mist`/`--color-smoke`** 1px |
| Painéis/blocos | `#ededed`, cantos variados | **card branco**, `--radius-lg` (20px), `--shadow-sm`, sem borda |
| Títulos de seção | barra com gradiente colorido | **eyebrow**: uppercase, `--color-primary-deep`, `letter-spacing: 0.14em`, `font-weight: 700` |
| Badge "saldo" | vermelho `#d32f2f` | **pill** `--color-primary-soft` bg / `--color-primary-deep` text (`--radius-pill`) |
| Acento verde | ausente | **Leaf `#5FB53A`** só em sinais "limpo" (CO₂ evitado, árvores), nunca dominante |
| Gráfico de geração | linha laranja `rgb(243,153,86)` | manter `#F39325`; eixos/labels em Ink/Graphite |
| Radii em geral | mistos (8/12/20) | escala DS: `--radius-sm/md/lg/pill` |
| Sombras | ausentes/duras | `--shadow-xs/sm` (tinta charcoal, nunca preto puro) |

Regras de marca a respeitar: gradiente **só** na família laranja; verde é sinal, não cor secundária; sem emoji; sem unicode como ícone; copy pt-BR sentence-case (títulos de seção podem ser uppercase como eyebrow).

### 4.3 SOLID / DRY / CLEAN

- **DRY (CSS):** declarar todos os tokens usados num bloco `:root` no topo do `<style>` (cores, espaçamentos `--space-*`, radii, sombras, `--grad-sun`, famílias de fonte). Todo estilo referencia `var(--token)` — zero hex/medida mágica repetida.
- **SRP (Blade sem lógica):**
  - Mover o cálculo `$ultimos6Meses = collect($dadosFaturamento)->reverse()->take(6)->reverse()` (hoje em `@php` na ~linha 616 do Blade) para o `UsinaPdfViewModel`, expondo `dadosCreditos` já fatiado.
  - Trocar `number_format(...)` cru (CO₂ ~linha 507, árvores ~linha 512) por diretiva apropriada. CO₂ e árvores são contagens (kg / unidades), não kWh nem R$ — usar uma diretiva/helper de número pt-BR coerente com `Format` (ex.: adicionar `Format::numero()` + `@numero`, ou formatar no ViewModel). Decisão de implementação: **adicionar `Format::numero(valor, casas)` e `@numero`** para manter `Format` como fonte única de formatação.
- **DRY (inlining de assets):** generalizar `inlinePublicImage()` do controller para um `inlineAsset(string $relativePath, string $mimeFallback)` reutilizável por imagens, fontes (woff2) e JS, evitando duplicar a lógica base64. `inlinePublicImage` passa a delegar nele.

### 4.4 Performance — zerar rede

- **Self-host Chart.js + datalabels:** baixar `chart.umd.js` (Chart.js v4) e `chartjs-plugin-datalabels` para `api-laravel/public/vendor/`. Embutir inline no Blade (via `inlineAsset` → `<script>...conteúdo...</script>`) ou referenciar como arquivo local servido pelo Browsershot. Preferir **inline** para não depender de servidor HTTP durante o render.
- **Fontes embutidas:** baixar Nunito (pesos 400/500/600/700/800) e JetBrains Mono (400/500/700) em woff2 para `api-laravel/public/fonts/`. Declarar `@font-face` com `src: url(data:font/woff2;base64,...)` (embutido) — zero requisição externa. Remover o `<link>` do Google Fonts e o `<link>` Montserrat.
- **PDFController:** remover `waitUntilNetworkIdle()` e `setDelay(1500)`; reduzir `timeout(90)` → `timeout(30)`. Manter `window.chartRendered` como sinal; se necessário, trocar `setDelay` por um pequeno `waitForFunction('window.chartRendered === true')` (sem rede, resolve em ms). Meta: render determinístico ~1–2s.

> Pin de versão: registrar a versão exata de Chart.js/datalabels baixada (comentário no Blade ou um README curto em `public/vendor/`), para reprodutibilidade.

### 4.5 Paridade com o motor (garantia obrigatória)

Todo valor exibido deve vir do `UsinaPdfViewModel`/`FaturamentoService` e bater com o motor refatorado (Fases 0–7). Itens a verificar:

- **4 termos da fórmula** por mês: Valor Fixo, Injetado (= valor variável), Creditado (= crédito), CUO, e Valor Final = Fixo + Injetado + Crédito − CUO.
- **FIFO cross-ano**, crédito expirado (kWh e R$), meses utilizados.
- **CO₂ evitado / árvores** (fatores do motor: 0,4 kg/kWh; 20 kg/árvore).
- **Caso âncora:** Eder Alcione, UC 562606800, Maio/2026 → **Valor a Receber R$ 5.700,65 EXATO** no PDF.
- **Nenhum recálculo no Blade** após a refatoração (grep deve continuar limpo de aritmética de faturamento no template).

---

## 5. Estratégia de execução (time de agentes)

Workflow com fan-out nas frentes independentes, depois implementação e revisão adversarial.

**Fase 1 — Preparação (paralela):**
1. **Asset agent** — baixa Chart.js v4 + datalabels e fontes (Nunito/JetBrains Mono woff2) para `public/vendor/` e `public/fonts/`; copia `logo-color.png` para `public/img/`. Entrega caminhos + versões.
2. **Paridade agent** — audita `UsinaPdfViewModel` ↔ `FaturamentoService`; confirma que cada variável do Blade tem origem no motor; lista a lógica a mover do Blade (slice 6 meses, number_format); confirma o caso âncora.
3. **Tokens agent** — produz o mapa exato "estilo atual → token DS" para cada regra CSS do Blade.

**Fase 2 — Implementação (sequencial, depende da Fase 1):**
- `Format::numero()` + diretiva `@numero`.
- `UsinaPdfViewModel`: expor `dadosCreditos` (6 meses) e qualquer número pré-formatado/derivado que saia do Blade.
- `PDFController`: `inlineAsset()` genérico; trocar logo; remover `waitUntilNetworkIdle`/`setDelay`; `timeout(30)`; inline de JS/fontes.
- `usina.blade.php`: remover seções Auditoria + Parâmetros; aplicar correções de conteúdo do §3.1 (rótulos do demonstrativo/histórico, coluna "Convertido em receita"); `:root` de tokens; reestilização completa pelo mapa; `@font-face` embutido; remover CDNs; trocar `number_format` por diretivas; garantir que o conteúdo cabe em 1 página A4 sem sobrepor o rodapé fixo.

**Fase 3 — Revisão adversarial (paralela):**
- **Revisor de valores** — reexecuta/inspeciona o caso âncora e confere os 4 termos + FIFO + expiração; garante zero recálculo no Blade.
- **Revisor de marca** — confere aderência ao DS (cores, fontes, radii, gradiente só-laranja, verde como sinal, logo correto).
- **Revisor de perf** — confirma remoção de toda dependência externa e dos waits de rede; valida que o Blade compila.

---

## 6. Critérios de sucesso

1. PDF da usina renderiza usando **apenas** tokens/assets do design system (logo color correto, Nunito + JetBrains Mono, Linen, gradiente só-laranja, headers Ink, cards brancos com radii/sombra do DS).
2. **Zero** dependência de rede externa no render (sem Google Fonts, sem CDN Chart.js); `PDFController` sem `waitUntilNetworkIdle`/`setDelay`; `timeout(30)`.
3. **Zero** aritmética de faturamento no Blade; `$ultimos6Meses` e `number_format` saíram do template; `Format` é a fonte única de formatação.
4. Caso âncora **Eder Mai/2026 = R$ 5.700,65** confere no PDF; os 4 termos + FIFO + expiração + CO₂/árvores batem com o motor.
5. Blade compila; PDF gera; tempo de geração materialmente menor (meta ~1–2s de render, sem timeouts).
6. Seções Auditoria e Parâmetros de Cálculo **ausentes** do PDF; conteúdo cabe em 1 página A4 sem sobrepor o rodapé (layout quebrado atual resolvido).
7. Rótulos corrigidos conforme §3.1: "Guardado (kWh)", "Vencimento do crédito", "Convertido em receita (R$)" (só quando > 0), "Crédito guardado acumulado (kWh)", "CUO acumulado (R$)", "Valor a receber acumulado (R$)".
8. **Validação visual final do PDF é do usuário** (não renderizamos screenshot sem pedido).

## 7. Riscos

- **Render do gráfico sem `setDelay`:** mitigar com `waitForFunction('window.chartRendered')`; Chart.js inline resolve em ms.
- **Tamanho do HTML:** fontes + Chart.js embutidos em base64 incham o HTML passado ao Browsershot. Aceitável (gerado em memória); se for problema, servir como arquivo local em vez de inline.
- **Sessões paralelas na mesma branch** (backend ledger): antes de commitar, separar o que é deste trabalho (ver memória `redesign-ui-faturamento`).
