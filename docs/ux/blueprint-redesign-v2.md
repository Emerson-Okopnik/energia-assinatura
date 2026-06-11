I have all the facts I need. The structure is confirmed: tabs at FaturarUsina.vue L793-1064, navbar nav items, auth via `GET /user`, and the `/faturar/:usinaId?/:ano?/:mes?` route already exists. Producing the blueprint.

# Blueprint de Implementação — Redesign Faturamento Líder Energy

> Direção única, aprovada. Backend read-only. Todos os arquivos abaixo são caminhos absolutos a partir de `front/src/`. Estado atual confirmado: `App.vue` monta `<TheNavbar/>`, Bootstrap importado DEPOIS dos tokens em `main.js` (bug de marca), rota `/faturar/:usinaId?/:ano?/:mes?` já existe com params opcionais, abas em `FaturarUsina.vue` L793–1064, logos de marca já presentes em `assets/brand/logo-color.png`.

---

## 1. AppShell + Sidebar global

**Arquivos:** criar `components/layout/TheAppShell.vue` e `components/layout/AppSidebar.vue`; editar `App.vue`; mover lógica de auth de `TheNavbar.vue`.

**`App.vue`** passa a decidir chrome pela rota:
```
<TheAppShell v-if="!ehLogin"><router-view/></TheAppShell>
<router-view v-else/>
```
onde `ehLogin = computed(() => ['Login','Register'].includes(route.name))`. Login e Register NÃO recebem sidebar. `TheNavbar.vue` deixa de ser montado (não apagar até paridade validada).

**`TheAppShell.vue`** — grid raiz espelhando `AppShell.jsx`:
- `display:grid; grid-template-columns:240px 1fr; min-height:100vh; background:var(--color-linen)`.
- Coluna 1 = `<AppSidebar/>`. Coluna 2 = `<main>` em flex-column: topbar sticky + slot de conteúdo.
- **Topbar sticky**: `position:sticky; top:0; z-index:10; background:rgba(255,255,255,0.75); backdrop-filter:blur(16px); border-bottom:1px solid var(--color-mist)`. Conteúdo: hambúrguer Lucide (`menu`, 20px, só `<=992px`) à esquerda; título da página (de `route.meta.titulo` ou `route.name`) ao centro-esquerda; à direita saudação "Bem-vindo de volta," + nome do operador + avatar 36px `var(--grad-sun)` com iniciais. Avatar abre menu clicável (não hover) com "Sair".
- **Conteúdo**: `<slot/>` envolto em wrapper `padding:28px 36px`. O `max-width:var(--max-w-app); margin:0 auto` fica em cada view (não no shell) para a topbar ocupar largura total.

**`AppSidebar.vue`** — `<aside>` `background:var(--color-paper); padding:var(--space-6); border-right:1px solid var(--color-mist); display:flex; flex-direction:column`:
- Logo: `import logo from '@/assets/brand/logo-color.png'`, `height:36px; margin-bottom:var(--space-7)`.
- Nav agrupada por eyebrow:
  - **FATURAMENTO** → `Faturar` (`/faturar`, `file-text`), `Lista de usinas` (`/usinas`, `sun`)
  - **CADASTROS** → `Cadastrar usina` (`/cadastro-usina`, `plus-circle`), `Consumidores` (`/consumidores`, `users`), `Distribuir créditos` (`/distribuicao`, `share-2`), `Relatórios` (`/relatorio`, `bar-chart`)
- Cada item = `<router-link>` (usa `.router-link-active` nativo): `display:flex; gap:10px; padding:10px 12px; border-radius:var(--radius-md); font-weight:var(--fw-semibold); color:var(--color-graphite)`. Ativo: `background:rgba(243,147,37,0.10); color:var(--color-primary-deep); font-weight:var(--fw-bold)`. **Resolve F13.**
- Ícones Lucide SVG inline, stroke 1.75, 18px, `currentColor`.
- Rodapé (`margin-top:auto`): helpCard `background:var(--color-linen); border-radius:var(--radius-md); padding:var(--space-4)` — "Precisa de ajuda?" + CTA pill laranja "Abrir chat".
- `focus-visible: var(--shadow-focus)` preservado em todo item.

**Auth migrada de `TheNavbar.vue`:** `fetchAuthenticatedUser` (GET `/user`), `onAuthChange`, `logout`. Sidebar/topbar só renderizam quando `isAuthenticated()`.

**Responsividade (`<=992px`):** grid vira `1fr`; aside vira off-canvas `position:fixed; transform:translateX(-100%)` → `0` quando `mobileOpen`, `transition:280ms var(--ease-out-quart)`; overlay `var(--color-ink-overlay)` + `backdrop-filter:blur(16px)`. Hambúrguer na topbar alterna `mobileOpen`. Fecha em Escape, clique no overlay e clique em item (reusar `closeAll`/`onKeydown` de `TheNavbar`). `@media (prefers-reduced-motion)` desliga a transição.

---

## 2. Lista de usinas — tela inicial `/usinas`

**Arquivo:** criar `views/UsinasFaturamento.vue`. **Rota:** registrar `name:'usinas'` apontando para a nova view (substitui o atual `Usinas.vue` da rota `/usinas`; `Usinas.vue` antigo fica órfão, migra depois). O índice `/faturar` (sem `:usinaId`) também redireciona/renderiza esta lista para coerência da sidebar.

**Layout:** UMA `SectionCard` `max-width:var(--max-w-app); margin:0 auto`, eyebrow "FATURAMENTO", título "Usinas".
- **Busca** (topo do card): `BaseField` + input com lupa Lucide, `box-shadow:var(--shadow-focus)` no foco, debounce 200ms. Filtro acento-insensível (reusar `normalizar()`) por `cliente.nome`, `usina.uc` e `cidade`.
- **`DataTable`** (não cards — fila de trabalho lê melhor em tabela), thead JetBrains Mono 10px uppercase slate, divisores `var(--color-mist)`. Colunas: **Cliente** · **UC** · **Cidade/UF** · **CIA energia** · **Média geração** (kWh, mono, direita, `tabular-nums`) · **Estado do mês corrente** (`BaseBadge` dot: success "Faturado" / warning "Pendente") · **Ação** (`BaseButton` ghost "Abrir").
- **Estado do mês em lote:** reusar `carregarEstadosUsinas()` + `temDadosMes()` de `FaturarUsina.vue` (mesmos endpoints anuais).
- **Linha clicável inteira:** `cursor:pointer`, hover `translateY(-1px)+var(--shadow-sm)`, `router.push('/faturar/'+usiId+'/'+anoAtual+'/'+mesAtual)`.
- **Paginação client-side:** componente novo `Paginacao.vue` no rodapé do card (ver §7), 12 por página default. Computed: `lista → filtroBusca → slice(pagina)`. Reseta para página 1 ao mudar busca (watch).
- **Persistência:** `?q=&page=` em query params (sobrevive ao "voltar" do browser).
- **Empty-state** amigável quando a busca não casa.

Zero cores fora da paleta — elimina `.table-dark #212529` e badges Bootstrap do `Usinas.vue` antigo. **Resolve F4.**

---

## 3. Página da usina — `/faturar/:usinaId/:ano/:mes` — PÁGINA ÚNICA, sem abas

**Arquivo:** refatorar `views/FaturarUsina.vue` quando `:usinaId` presente. **Remover** o bloco de abas (L793–1064: `aba`, `ABAS`, `aoNavegarAbas`, `role=tablist`, `v-show` por painel, `tab-${id}`). Substituir por coluna única rolável `.workspace { max-width:var(--max-w-app); margin:0 auto; padding:var(--space-6) var(--space-5) var(--space-10); display:flex; flex-direction:column; gap:var(--space-7) }`.

**Ordem fixa das seções (todas sempre visíveis):**

1. **Hero da usina** — card `border-radius:var(--radius-xl)` (28), `shadow-sm`. Eyebrow "USINA PARCEIRA" (`var(--color-primary-deep)`); nome do cliente `var(--font-display)` 800 ~24px; subtítulo "UC {uc} · {cidade}/{uf} · {cia_energia}". À direita: chips mono de contrato (Fio B, % Lei 14.300, Média/Menor geração, Tarifa — de `parametrosAuditoria`). `BaseButton` ghost "Trocar usina" → volta à lista.

2. **Faixa de stat-cards** — `grid: repeat(auto-fit, minmax(220px,1fr)); gap:var(--space-5)`, padrão `StatValue`: **Reserva total acumulada** (kWh, destaque — pode usar `var(--grad-sun)` parcimoniosamente, único card colorido), **Último faturamento** (R$ + competência), **Créditos distribuídos no ano**, **Meses faturados / 12**. Leitura imediata na 1ª dobra — **resolve F3** (tira reserva da cauda da página).

3. **Card de Apuração** — `SectionCard` eyebrow "APURAÇÃO". Contém o `CompetenciaSelector` (stepper ano ‹2026› + 12 chips mês com badge Faturado/Pendente — **fonte única do ano**, elimina os dois controles dessincronizados de F3) e UM `BaseButton` primário pill com glow **"Apurar {Mês}/{Ano}"** que abre o `ApurarMesModal` (§4). Se o mês já está faturado, abaixo do seletor mostra `LancamentoReadonly` do mês selecionado. **O formulário NÃO fica inline** — sai para o modal.

4. **Card Histórico** — `SectionCard` "Lançamentos de {ano}" (§5).

5. **Card Expectativa anual** — `SectionCard` "Expectativa anual de {ano}" (§6).

**Estado na rota:** trocar ano/mês via `CompetenciaSelector` faz `router.push('/faturar/'+usiId+'/'+ano+'/'+mes)` (mantém o `watch(route.params)` já existente — F5/deep-link seguros). Cada card resolve seu próprio loading/erro/empty localmente (skeleton de stat-cards e linhas; faixa `var(--color-danger-soft)` com "Tentar de novo"; empty-state no histórico/expectativa) sem bloquear a página — **resolve F10**.

---

## 4. Modal de Apuração

**Arquivo:** criar `components/faturamento/ApurarMesModal.vue`. Reaproveita `carregarPreview`/`executarFaturamento` de `FaturarUsina.vue` (extrair para props/emits ou composable).

**Casca DS:** backdrop `var(--color-ink-overlay)`; painel `background:var(--color-paper); border-radius:var(--radius-xl)` (28); `box-shadow:var(--shadow-lg)`; max-width ~720px; entrada `var(--dur-enter)` 280ms `var(--ease-out-quart)`, translate 4–8px + fade, sem bounce; focus-trap; Escape fecha; `prefers-reduced-motion` respeitado.

**Conteúdo em UMA tela rolável** (não fragmentar em 2 passos com navegação separada — preview sempre visível, conferência contínua):
- Eyebrow "CONFERÊNCIA", título "Apurar {Mês}/{Ano}".
- **(a) Inputs** — `BaseField` + `NumberInput` máscara pt-BR (vírgula) com adornos de unidade:
  - Geração bruta (kWh) — hint de proveniência (cadastro vs medido; `null ≠ 0` — **F12**)
  - Consumo* (obrigatório), Fatura de energia* (R$, obrigatório), Adicional CUO (opcional)
  - Validação inline por blur (**F7**); botão Confirmar desabilitado enquanto inválido.
- **(b) `PreviewPanel`** (reusado) — 4 termos como `StatValue` (Fixo / Injetado / Crédito / CUO em tom danger) + linha-fórmula mono `Fixo + Injetado + Crédito − CUO = Total`; `BaseBadge` warning dot "Simulação — valores não salvos"; estado "Recalculando…" durante o debounce do `obterPreview`. **Trocar a borda tracejada por fundo Linen sólido sem borda** (`border-radius:var(--radius-lg)`) — o DS proíbe dashed.
- **(c) `AuditoriaAccordion`** (reusado) — "Parâmetros usados" (tarifa, média, menor geração, Fio B, % lei, desconto de rede) e "Detalhamento": geração bruta → desconto de rede → consumo → **geração líquida**, tabela FIFO "crédito resgatado por origem" e "crédito expirado". **Coração do requisito 4 / fecha F8.**
- **Rodapé fixo:** "Cancelar" (ghost) · "Baixar PDF" (secundário, `gerarPdfUsina`) · **"Confirmar faturamento de {Mês}/{Ano}"** (primário pill com glow, loading, disabled se preview em voo/erro/inválido).

**Confirmar** → chama `salvarConsumoMes` + `salvarCalculo`; toast/Swal de sucesso com competência e valor final (**corrige F6 e F10**).

**Modo refaturamento** (mês já Faturado): cabeçalho `BaseBadge` warning "Refaturando {Mês}/{Ano}"; inputs reidratados de `obterInputsSalvos` + geração do cadastro (**corrige F1 — bug do watch que zerava fatura/consumo**); antes de confirmar, exibe `ConfirmRefaturarDialog` (reusado) com diff Atual × Novo (geração, consumo, fatura, adicional, valor final), botão danger **"Substituir lançamento"** — confirmação destrutiva, nunca save silencioso (**guard de F1**).

**Reverter:** para o mês de `obterUltimoRevertivel`, ação "Reverter" (`estornarMes`) com resumo do que será desfeito (valor faturado, crédito distribuído, reserva, PDF) e nota de por que só o último é revertível (**F11**). Meses não-revertíveis: cadeado + tooltip, nunca só "—".

---

## 5. Auditoria de um mês no histórico

**Onde:** Card Histórico (§3, seção 4). `DataTable` padrão `BillsTable` do `Dashboard.jsx`: thead mono uppercase slate, divisores `var(--color-mist)`, números mono à direita, `BaseBadge` success dot "Faturado". Colunas: **Mês** · **Geração** · **Reserva acumulada** · **Creditado** · **Valor pago** (peso 700) · **Estado** · **[chevron]**. Zebra Linen sutil; mês mais recente no topo; faixa-resumo acima ("{N} meses faturados · total recebido no ano R$ X").

**Expansão por linha:** chevron **Lucide** (nunca unicode ▼ — **F11**). Clicar na linha expande `#row-details` revelando a **mesma composição read-only** do preview: inputs salvos (geração/consumo/fatura/adicional) + `AuditoriaAccordion` alimentado por `obterPreview(usiId, ano, mes, inputsSalvos)` (relê o mês com os inputs persistidos → mesmo detalhamento dos 4 termos, geração líquida, FIFO, expiração) em **modo leitura**. **Atende requisito 5, fecha F8.**

**Ações na linha:** "Apurar" em linha de mês **pendente** abre o `ApurarMesModal` já naquela competência (menos cliques); "Reverter" (danger-soft) só na linha do último revertível, cadeado+tooltip nas demais. Trilha de estorno (`obterHistoricoEstorno` → `linhasTrilha`: lançado/revertido por quem e quando) num `<details>`/accordion ao pé do card.

---

## 6. Expectativa anual e gráfico

**Onde:** Card Expectativa (§3, seção 5), alimentado por `obterProjecao` (independe da apuração do mês — respeita o commit que separou Expectativa da fatura). Parágrafo curto: projeção usa geração contratada/projetada e Fio B, não a fatura real.

- **Cards-resumo** no topo da seção: total projetado no ano, melhor mês, pior mês (leitura sem decifrar gráfico).
- **Desktop `grid:1fr 1fr; gap:var(--space-6)`:** esquerda = `DataTable` 12 meses (Mês · Geração · Média · Fixo · Injetado · Creditado · CUO · Valor final, mono à direita; `tfoot` "Total projetado no ano R$ X" mono peso 700; mês corrente destacado com `background:var(--color-linen)` + borda-esquerda 2px Apricot; mês de menor geração em Danger com legenda); direita = gráfico empilhado Chart.js.
- **Gráfico responsivo:** `width:100%`, container `height` fixa ~360px, `maintainAspectRatio:false` — **elimina o 860/380px fixo de F9**. `<1024px` empilha (gráfico primeiro). Paleta DS já correta: Fixo Apricot `#F9B566`, Injetado Lider Orange `#F39325`, Creditado Leaf `#5FB53A`, CUO Danger `#C53B2F`, linha Valor final Ink `#3D3D3D`. Tooltip/legend em Nunito.

**Atende requisito 6.**

---

## 7. Fidelidade de marca — correções concretas

- **`main.js` (raiz de F9):** mover os imports do Bootstrap para ANTES de `tokens.css`/`main.css`, e criar `assets/bootstrap-overrides.css` carregado por ÚLTIMO mapeando `--bs-body-bg:var(--color-linen)`, `--bs-body-color:var(--color-ink)`, `--bs-body-font-family:var(--font-body)`, `--bs-border-radius:var(--radius-md)`, `--bs-primary` para a paleta laranja, `.card{border:0}`, e neutralizando `.btn`/`.table`/`.badge` default. Sem isso o card branco some no fundo (Bootstrap força `--bs-body-bg:#fff`).
- **Background:** `var(--color-linen) #FAF6F1` em toda a área de conteúdo (raiz do AppShell).
- **Cards:** Paper, `radius-lg`(20) para painéis de dado, `radius-xl`(24/28) para hero/modal; `shadow-sm` tintada charcoal `rgba(61,61,61,*)` — nunca preto puro nem cinza frio; sem bordas duras; **sem dashed/dotted** (trocar a borda tracejada do `PreviewPanel` por fundo Linen sólido).
- **Hover-lift `translateY(-2px)`+shadow-sm→md SÓ em clicáveis** (linha da lista, linha do histórico, chips de mês). Cards estáticos (apuração, expectativa) não levantam.
- **`grad-sun`:** 1 acento por tela — hero "Reserva total" e/ou CTA "Apurar"/"Confirmar" e avatar. Nunca em fundos amplos nem em cards de dado.
- **Tipografia:** Nunito display nos títulos (letter-spacing negativo); JetBrains Mono + `tabular-nums` em TODO número (R$/kWh/UC) na lista, histórico, expectativa, hero. Eyebrows via `.eyebrow` (uppercase, letter-spacing 0.14em, primary-deep).
- **Logo:** `assets/brand/logo-color.png` na sidebar (substitui `logo-consorcio-lider-energy.png`).
- **Badges:** `BaseBadge` dot success/warning/neutral — eliminar `bg-success`/`bg-warning` Bootstrap.
- **Swal:** mixin único na paleta — confirm `#F39325`, danger `#C53B2F`, sucesso `#3FA14A` — elimina azuis default (**F13**).
- **Motion:** hover `var(--dur-hover)`120ms, modal `var(--dur-enter)`280ms, `var(--ease-out-quart)`; fades + translate 4–8px, sem spring; `prefers-reduced-motion` respeitado.
- **Focus:** NUNCA remover; `box-shadow:var(--shadow-focus)` 3px laranja 40% em todo focável. Alinhar `main.css` L9–13 (hoje 2px/0.2) para 3px/0.40.
- **Sem emoji, sem glyph unicode como ícone** — só Lucide.

---

## 8. Componentes a criar / reaproveitar

| Componente | Novo/Reusa | Responsabilidade | Props principais |
|---|---|---|---|
| `TheAppShell.vue` | **novo** | Grid 240px/1fr, topbar sticky, slot de conteúdo, responsivo | — (usa `route`, auth) |
| `AppSidebar.vue` | **novo** | Nav global, logo DS, help-card, estado ativo, drawer mobile | `mobileOpen` |
| `UsinasFaturamento.vue` | **novo** (view) | Lista busca+paginação client-side, navegação p/ usina | — (fetch `listarUsinas`) |
| `Paginacao.vue` | **novo** (base) | Paginação client-side genérica, chevrons Lucide, pills | `total`, `itensPorPagina`, `paginaAtual`, `@update:pagina` |
| `ApurarMesModal.vue` | **novo** | Modal: inputs → preview → auditoria → confirmar/refaturar/reverter | `usina`, `ano`, `mes`, `inputsSalvos`, `modoRefaturar`, `@confirmado` |
| `FaturarUsina.vue` | **refatora** | Página única rolável (remove abas L793–1064), 5 seções empilhadas | rota `:usinaId/:ano/:mes` |
| `bootstrap-overrides.css` | **novo** | Mapear vars Bootstrap → tokens DS | — |
| `PreviewPanel.vue` | **reusa** (ajusta) | 4 termos + fórmula mono; tirar dashed→Linen sólido | preview, estado |
| `AuditoriaAccordion.vue` | **reusa** | Parâmetros + detalhamento FIFO/expiração/geração líquida; modo leitura | auditoria, `readonly` |
| `ConfirmRefaturarDialog.vue` | **reusa** | Diff Atual × Novo, botão destrutivo | atual, novo |
| `LancamentoReadonly.vue` | **reusa** | Resumo do mês faturado | lancamento |
| `CompetenciaSelector.vue` | **reusa** | Stepper ano + 12 chips mês (fonte única do ano) | ano, mes, estados, `@change` |
| `DataTable / BaseButton / BaseBadge / BaseField / NumberInput / StatValue / SectionCard` | **reusa** | Blocos DS | — |
| `ContextHeader.vue` | **substituído** pelo Hero da usina | — | — |
| `UsinaCombobox.vue` | **rebaixado** | Só atalho "trocar usina" no hero (não porta de entrada) | — |

---

## 9. Ordem de implementação (para o time de agentes)

1. **Fundação de marca** — `main.js` (reordenar Bootstrap) + `bootstrap-overrides.css` + alinhar focus de `main.css`. Sem isto, nada parece da marca. *(habilita F9 e tudo abaixo)*
2. **`Paginacao.vue`** — base isolada, testável sozinha.
3. **AppShell** — `TheAppShell.vue` + `AppSidebar.vue`; migrar auth/logo de `TheNavbar.vue`; editar `App.vue` (Login sem chrome); validar todas as páginas existentes ainda renderizam. *(F13, requisito 1)*
4. **Lista de usinas** — `UsinasFaturamento.vue` na rota `/usinas`; busca + `DataTable` + `Paginacao` + estados em lote; linha clicável → `/faturar/:id/:ano/:mes`. *(F4, requisitos 2 e 3)*
5. **Página da usina sem abas** — refatorar `FaturarUsina.vue`: remover tablist (L793–1064), empilhar Hero + stat-cards + card Apuração + Histórico + Expectativa. *(F3, requisito 6)*
6. **`ApurarMesModal.vue`** — extrair `carregarPreview`/`executarFaturamento`; inputs+validação → `PreviewPanel` (sem dashed) → `AuditoriaAccordion`; confirmar/refaturar (`ConfirmRefaturarDialog`)/reverter. *(F1, F6, F7, F8, F10, F12, requisito 4)*
7. **Auditoria no histórico** — linhas expansíveis com `AuditoriaAccordion` em modo leitura via `obterPreview(inputsSalvos)`; chevron Lucide; "Apurar" por linha pendente; trilha de estorno. *(F8, F11, requisito 5)*
8. **Expectativa + gráfico** — cards-resumo, `DataTable` 12 meses, gráfico responsivo `maintainAspectRatio:false`, paleta DS. *(F9, requisito 6)*
9. **Polimento de marca** — Swal mixin, badges, hover-lift só em clicáveis, motion/focus tokens, varredura final de glyphs unicode e cores fora da paleta. *(F13, requisito 7)*

**Arquivos-chave (absolutos):**
- `/Users/matheus/Desktop/Projetos App.nosync/emerson-lider-energy/front/src/main.js` (reordenar Bootstrap)
- `/Users/matheus/Desktop/Projetos App.nosync/emerson-lider-energy/front/src/App.vue` (montar AppShell, Login sem chrome)
- `/Users/matheus/Desktop/Projetos App.nosync/emerson-lider-energy/front/src/views/FaturarUsina.vue` (remover abas L793–1064; página única)
- `/Users/matheus/Desktop/Projetos App.nosync/emerson-lider-energy/front/src/components/TheNavbar.vue` (fonte da lógica de auth a migrar; deixa de ser montado)
- A criar: `front/src/components/layout/TheAppShell.vue`, `AppSidebar.vue`; `front/src/views/UsinasFaturamento.vue`; `front/src/components/base/Paginacao.vue`; `front/src/components/faturamento/ApurarMesModal.vue`; `front/src/assets/bootstrap-overrides.css`.