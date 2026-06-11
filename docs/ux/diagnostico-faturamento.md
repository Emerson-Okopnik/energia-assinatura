## Findings consolidados

Verificação contra o código (`CalculoGeracao.vue`, `router/index.js`): todas as evidências citadas conferem (select sem busca L6-11; expectativa antes da apuração L14-68; gráfico 860px fixo L66; ano duplicado L74 vs L262-264; watch que zera consumo/fatura L387-399; payload de consumo zerando 11 meses L662-668; salvar sem guard L702-768; `.campo-info` imitando input L930-939; `required` inerte L104; cores fora da paleta L920-928 e Swal L751/792; `<p>` inválido no `<tfoot>` L267).

| # | Finding consolidado (merge de) | Prioridade | Dor | Lentes |
|---|---|---|---|---|
| F1 | **Sobrescrita de mês faturado sem guard + watch zera fatura/consumo ao trocar de mês** — re-salvar envia `fatura_energia: 0` silenciosamente. Seletor de mês não mostra estado faturado/pendente. *(merge: navegação#5, formulários#1, transparência#2, visual#10)* | **alta** | 4 | todas |
| F2 | **Fluxo de dados do consumo quebrado**: `salvarConsumoUsina` zera os outros 11 meses no payload e calcula `media` errada; formulário nunca reidrata valores persistidos. Pré-requisito funcional do redesign. *(formulários#5)* | **alta** | 5 | formulários |
| F3 | **Arquitetura da página invertida e sem hierarquia**: Expectativa ocupa a 1ª dobra; botão Salvar a ~150 linhas dos inputs, depois do histórico; histórico no meio do fluxo; cauda solta (Reserva/Obs/PDF/Voltar); dois controles de ano dessincronizados (input number sem watcher vs paginação no tfoot). *(merge: navegação#3,4,6,7,8,10 + formulários#8)* | **alta** | 2 | navegação |
| F4 | **Seleção de usina sem busca e sem contexto persistente** — select nativo, label pobre, usina some ao rolar. *(navegação#1)* | **alta** | 1 | navegação |
| F5 | **Rota sem parâmetros** — estado morre no F5, sem deep-link, voltar do browser sai da página. *(navegação#2)* | **alta** | 2 | navegação |
| F6 | **Preview indistinguível de dado salvo + saídas calculadas imitam inputs** (`.campo-info` = form-control disfarçado); mês faturado renderiza como formulário vazio editável. *(merge: formulários#2, transparência#1,8, visual#7)* | **alta** | 3, 5 | todas |
| F7 | **Obrigatório vs opcional invisível; validação só explode no Swal ao salvar**; `required` inerte; sem min, ano livre, type=number com ponto vs UI pt-BR com vírgula (placeholder do Adicional sugere formato rejeitado). *(merge: formulários#3,4,6, transparência#7)* | **alta** | 6 | formulários |
| F8 | **Transparência do cálculo**: fórmula de 4 termos (Fixo/Injetado/Crédito/CUO) nunca aparece na apuração; parâmetros (tarifa, média, rede, Fio B) longe do preview; histórico não mostra os inputs de cada lançamento. *(merge: transparência#3,4,5)* | **alta** | 5 | transparência |
| F9 | **Zero tokens do design system**: base.css é template Vue (Inter, dark-mode), laranja errado #f28c1f/#d97706, Tailwind 4 declarado mas não wired, botões/tabelas/badges Bootstrap default, gráfico em paleta proibida (azul/verde Tailwind) e 860px fixo. *(merge: visual#1,2,3,4,5,8,9)* | **alta** | — (habilitador) | design-visual |
| F10 | **Erros de API silenciosos e sem loading** — falha de rede idêntica a "sem dados"; sem indicador "Recalculando…" durante debounce+request do preview; feedback pós-salvar genérico sem competência/valor. *(merge: transparência#6,10 + formulários#7 disabled sem explicação)* | **média** | 5 | transparência |
| F11 | **Estorno opaco** — modal fala de "cache do PDF", não mostra o que desfaz, sem explicação de por que só o último mês é revertível; trilha de auditoria atrás de link `▼` quase invisível (unicode vetado pelo DS). *(merge: transparência#9 + navegação#7 parcial)* | **média** | 5 | transparência |
| F12 | **Geração pré-preenchida com 0 ambíguo** — sem distinção entre "sem cadastro" e "0 kWh real", preview não dispara em mês sem cadastro, sem hint de proveniência. *(formulários#9)* | **baixa** | 5 | formulários |
| F13 | **Navbar sem estado ativo, dropdown hover-only, fundo #1f1f1f fora da paleta, focus ring removido; Swal com azuis default**. *(merge: navegação#9, visual#11)* | **baixa** | 2 | nav/visual |

**Cobertura das 6 dores**: 1→F4 · 2→F3,F5 · 3→F6 · 4→F1 · 5→F2,F8,F10,F11,F12 · 6→F7. **Todas cobertas**, nenhum finding adicional necessário.

## Macro-estrutura proposta

Direção única: **master-detail em 2 níveis com abas no workspace** (descartado o wizard/stepper — o preview em tempo real é o coração da tela e um wizard o esconderia).

**Rotas** (`router/index.js`):
- `/faturar` → nível 1; `/faturar/:usinaId/:ano/:mes` → nível 2; redirect de `/calculo-geracao` → `/faturar`. Trocar usina/competência = `router.push` (histórico, deep-link, F5 seguro).

**Nível 1 — `/faturar` (fila de trabalho)**
- Combobox/lista de usinas com busca client-side (nome do cliente/usina) e badge do estado do mês corrente ("Maio pendente" / "Maio faturado" via `temDadosMes`). Sem mudança de endpoint.

**Nível 2 — `/faturar/:usinaId/:ano/:mes` (workspace)**, página Linen, max-width 1280px:

1. **Header de contexto sticky** (branco translúcido + backdrop-blur, padrão AppShell.jsx): cliente + usina (com troca rápida via combobox), stat-chips de Fio B, % Lei 14300 e **Reserva Total Acumulada** (sai da cauda da página), competência atual.
2. **Seletor de competência**: stepper de ano `‹ 2026 ›` (limite 2024..atual+1) + 12 chips de mês, cada um com badge de estado — Faturado (success+dot) / Pendente (neutro) / Selecionado (outline laranja). Fonte única de verdade do ano (elimina o input duplicado e a paginação do tfoot).
3. **Abas**: `Apuração` (default) | `Expectativa anual` | `Histórico`.
   - **Apuração** — um SectionCard "Apuração de {Mês}/{Ano}" com dois estados:
     - *A faturar*: inputs (geração com hint de proveniência, consumo* obrigatório, fatura R$, adicional opcional) em máscara pt-BR com adornos de unidade → painel **"Simulação — valores não salvos"** (fundo Linen, borda tracejada, badge warning, estado "Recalculando…"): StatValues + linha da fórmula `Fixo + Injetado + Crédito − CUO = Total` em JetBrains Mono + accordions "Parâmetros usados neste cálculo" e "Detalhamento de auditoria (FIFO/expiração)" → rodapé do card com botão primário pill **"Faturar Maio/2026"** (desabilitado se inválido/preview em voo) + secundário "Baixar PDF" (com campo Observações no diálogo do PDF).
     - *Faturado*: pares label/valor em leitura (sem moldura de input), badge "Faturado em DD/MM por X", ações "Refaturar" (diálogo com diff atual vs novo, botão danger) e "Reverter" (com resumo do que será desfeito).
     - Sem usina/competência: fieldset disabled + empty-state explicativo.
   - **Expectativa anual** — texto explicativo, tabela 12 meses (.table-lider) e gráfico responsivo (100%, paleta DS: Apricot/Lider Orange/Leaf/Danger/Ink).
   - **Histórico** — tabela anual com badge de estado por linha, linhas expansíveis mostrando inputs do lançamento, ação Reverter só no último (cadeado+tooltip nos demais), accordion da trilha de auditoria (chevron Lucide, não ▼).

**Correções funcionais embutidas (não-visuais, obrigatórias)**: F2 (carregar consumo anual existente, mesclar payload, media correta), F1 (reidratar inputs do mês faturado em vez de zerar), F12 (null+placeholder em vez de 0).

## Componentes a criar

Em `front/src/components/base/` (genéricos) e `front/src/components/faturamento/` (do fluxo):

| Componente | Responsabilidade | Props principais |
|---|---|---|
| `BaseButton.vue` | Botões do DS (pill primary, secondary, ghost, danger/danger-soft) | `variant`, `size`, `loading`, `disabled` |
| `BaseBadge.vue` | Badges pill com dot (success/warning/danger/neutral soft) | `variant`, `dot`, `label`/slot |
| `BaseField.vue` | Wrapper label + obrigatório/opcional + hint + erro inline | `label`, `required`, `optionalLabel`, `hint`, `error` |
| `NumberInput.vue` (Currency/Kwh) | type=text inputmode=decimal, máscara pt-BR (vírgula), adorno prefixo/sufixo; emite `Number` | `modelValue`, `prefix`/`suffix`, `min`, `placeholder` |
| `StatValue.vue` | Valor calculado/métrica: label eyebrow + valor JetBrains Mono (substitui `.campo-info`) | `label`, `value`, `unit`, `tone`, `loading` |
| `SectionCard.vue` | Card de seção (Paper, radius-lg, shadow-sm) com eyebrow + título + slot footer | `eyebrow`, `title`, slots `default`/`footer` |
| `DataTable.vue` (ou `.table-lider`) | Tabela padrão portal: thead mono/slate, divisores mist, números mono à direita | `columns`, `rows`, slots por célula |
| `UsinaCombobox.vue` | Busca/seleção de usina com filtro client-side e estado do mês | `usinas`, `modelValue`, `competencia` |
| `ContextHeader.vue` | Header sticky: usina, cliente, stat-chips (Fio B, % Lei, Reserva), troca de usina | `usina`, `fioB`, `percentualLei`, `reservaTotal` |
| `CompetenciaSelector.vue` | Stepper de ano + 12 chips com badge de estado | `ano`, `mes`, `estadosPorMes`, `minAno`, emite `update` |
| `PreviewPanel.vue` | Painel simulação: badge warning, fórmula 4 termos, stats, "Recalculando…", empty/error state | `preview`, `loading`, `error` |
| `AuditoriaAccordion.vue` | Parâmetros + geração líquida + FIFO + expiração colapsáveis | `preview` |
| `ConfirmRefaturarDialog.vue` | Diff atual vs novo (geração/consumo/fatura/adicional/total) com confirmação destrutiva | `lancamentoAtual`, `previewNovo`, `competencia` |
| `LancamentoReadonly.vue` | Estado "Faturado": pares label/valor + badge + ações Refaturar/Reverter/PDF | `lancamento`, `competencia`, `revertivel` |

Infra: `front/src/assets/tokens.css` (variáveis do DS importadas antes do Bootstrap) + `Swal.mixin` com cores da paleta.

## Ordem de implementação sugerida

1. **Fundação visual** — tokens.css, limpar base.css/main.css (dark-mode, Inter, link verde, `place-items:center`), grep global #f28c1f/#d97706, Swal.mixin, decidir Tailwind (recomendo remover do package.json por ora). *(F9)*
2. **Correções funcionais de dados** — F2 (consumo anual mesclado + media), reidratação do mês faturado (F1 parte de dados), null vs 0 na geração (F12). Antes de qualquer redesign, pois mudam o fluxo de dados do formulário.
3. **Componentes base** — BaseButton, BaseBadge, BaseField, NumberInput, StatValue, SectionCard, DataTable.
4. **Guard de sobrescrita + estados do mês** — CompetenciaSelector com badges, modo leitura/refaturar, diálogo de diff. *(F1 — maior risco operacional)*
5. **Reestruturação da página** — rotas `/faturar/...` com redirect, ContextHeader, abas Apuração/Expectativa/Histórico, card de apuração com botão no rodapé, PreviewPanel + fórmula 4 termos + AuditoriaAccordion, validação inline. *(F3, F4, F5, F6, F7, F8)*
6. **Polimento** — loading/error states por fetch (F10), estorno com resumo e tooltips (F11), gráfico na paleta DS responsivo, histórico expansível, navbar (estado ativo, Ink, focus ring) (F13).