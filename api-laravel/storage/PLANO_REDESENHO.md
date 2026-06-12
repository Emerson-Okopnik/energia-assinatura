I now have full grounding in the plan. Producing the consolidated audit.

---

# CONSOLIDAÇÃO DA AUDITORIA DE COBERTURA — REDESENHO DOS CÁLCULOS

Engenheiro Líder. Documento de referência: `api-laravel/storage/CALCULO_CONTEXTO.md`. Abaixo consolido as 4 auditorias por camada contra as 5 exigências do cliente (unidades, auditoria fácil, cobertura total, SOLID/DRY/CLEAN, documento formal).

---

## 1. CHECKLIST DE COBERTURA CONSOLIDADO POR CAMADA

Legenda: ✅ já no plano/regras (CALCULO_CONTEXTO.md) · ⚠️ parcial (existe mas viola requisito) · ❌ LACUNA (não previsto/não coberto)

### 1.1 BACKEND — motor de cálculo

| Item | Status | Onde | Nota |
|---|---|---|---|
| Fórmula oficial 4 termos (Fixo + Variável + Crédito − CUO) | ✅ | CALCULO_CONTEXTO.md:8,103; CalculoGeracaoService.php:143; PDFController.php:225 | Confirmada, mas calculada em 2 lugares (viola fonte única) |
| Crédito limitado ao déficit (media − geração) | ✅ | CalculoGeracaoService.php:119-124 | Lógica correta no service; frontend não respeita (P3b) |
| Idempotência (Idempotency-Key + hash payload + 409) | ✅ | CalculoGeracaoController.php:22-42 | Bom |
| Transação atômica (DB::transaction) | ✅ | CalculoGeracaoService.php:37 | Bom |
| Expiração de crédito → vira receita | ✅ regra / ⚠️ impl | CALCULO_CONTEXTO.md:14-16; CalculoGeracaoService.php:92-107 | Regra OK; impl com bug P3a (dupla contagem :109 + :143) e ordem errada (expira antes do FIFO) |
| FIFO cross-ano | ❌ | CalculoGeracaoService.php:118-140 (só ano corrente) | BUG P1. Regra existe (md:4-7), impl não cobre |
| Geração líquida (consumo/desconto rede) no backend | ❌ | só frontend CalculoGeracao.vue:507-523 | Backend recebe `mesGeracao` pronto; não valida/recalcula |
| Cálculo centralizado de cada termo (Fixo/Injetado/CUO/Crédito) | ❌ | espalhado: Vue + Service + PDFController | Não existe `Calculadora` única |
| Validação de inputs (relações lógicas, coerência) | ⚠️ | CalculoGeracaoRequest.php:16-26 | Só `numeric|min:0`; sem regras de coerência |
| Precisão decimal (decimal vs float) | ❌ | migrations usam `float` | Erro cumulativo; cliente exige centavos exatos |
| Testes unitários | ❌ | só ExampleTest | Nenhum teste de FIFO/expiração/déficit |

### 1.2 FRONTEND (CalculoGeracao.vue)

| Item | Status | Onde | Nota |
|---|---|---|---|
| Fórmulas removidas do front (front só lê e exibe) | ❌ | CalculoGeracao.vue:458-506 | injetado/creditado/creditadoTabela/cuo/valorFinal* ainda no front |
| `getDescontoRede()` (regra de negócio) movida ao backend | ❌ | CalculoGeracao.vue:516-523 | Lógica trifásico=100/bifásico=50/monofásico=30 no front |
| `atualizarValores()` não duplica backend | ❌ | CalculoGeracao.vue:433-456 | Pré-calcula client-side |
| Endpoint de PREVIEW (calcula sem persistir) | ❌ | só POST /calculo final | Falta GET .../preview |
| Frontend confia 100% no retorno do backend | ❌ | CalculoGeracao.vue:706-717 vs 433-456 | Duas fontes de verdade |
| `creditadoTabela()` respeita saldo da reserva | ❌ | CalculoGeracao.vue:482-490 | BUG P3b: `(media−valor)*kwh` sem checar saldo |
| Formatador único (kWh / R$) | ❌ | formatKwh:358, formatMoeda:362, formatCurrency:374 + 18× toFixed | 3 formatadores divergentes |
| Composable/util de formatação compartilhado | ❌ | não existe /src/composables nem /src/utils/formatters.js | Duplicado em AtualizarUsina/Distribuicao/Relatorios |
| Breakdown de auditoria (passo a passo) | ❌ | nenhum | Sem detalhamento de CUO/crédito/FIFO/expiração |
| Validação/guards (NaN, negativo, infinity) | ❌ | nenhum | injetado() com media=0 retorna valor errado |

### 1.3 PERSISTÊNCIA / AUDITORIA (ledger)

| Item | Status | Onde | Nota |
|---|---|---|---|
| Modelo de reserva = ledger imutável (escolhido) | ✅ regra / ❌ impl | CALCULO_CONTEXTO.md:11-13 | Decidido no plano; tabela `credito_ledger` NÃO existe |
| Snapshot antes/depois de cada mutação | ✅ | CalculoGeracaoService.php:69-81; HistoricoEstorno.php | Bom para reversão, agregado |
| Estorno funcional | ✅ | EstornoGeracaoService.php:25-116 | Snapshot-based; só último mês (md:38-42) |
| Lançamentos CREDITO/CONSUMO/EXPIRACAO com origem→consumo | ❌ | não existe | Impossível rastrear "crédito de DEZ/25 consumido em MAI/26" |
| Colunas mensais como cache materializado | ❌ | 12 colunas são a única fonte (decremento destrutivo) | P2 |
| Backfill integrado (command/migration) | ❌ | reconstrucao/reconstruir.php (script isolado, staging) | Não integrado em prod |
| Precisão decimal nas tabelas | ❌ | float em todas | decimal(12,2)/decimal(14,4) necessário |
| Backfill da geração real (não dos saldos corrompidos) | ✅ regra | CALCULO_CONTEXTO.md:73-76 | Metodologia validada (Eder, Colina) |

### 1.4 EXIBIÇÃO / PDF (usina.blade.php + PDFController)

| Item | Status | Onde | Nota |
|---|---|---|---|
| number_format 2 casas em valores monetários | ✅ | usina.blade.php:531-556 | Consistente na tabela principal |
| Unidades nos cabeçalhos (kWh / R$) | ✅ | usina.blade.php:533-541 | Bom |
| Demonstrativo de créditos (6 meses, vencimento) | ✅ | usina.blade.php:567-589; PDFController:404-414 | Bom |
| Totais (energia a receber, fatura concessionária, faturas emitidas) | ✅ | usina.blade.php:601-612 | Bom |
| PDF lê resultado único (não recalcula) | ❌ | PDFController.php:218-225 recalcula injetado/CUO | Viola fonte única; risco de divergência |
| CO2/árvores parametrizados | ❌ | usina.blade.php:495-502 hardcoded (0.4 / 20) | Não auditável |
| valor_kwh formatado | ❌ | usina.blade.php:487 (`{{$usina->...valor_kwh}}` sem number_format) | "0.51" vs "0,51" |
| Coluna "Crédito Expirado" por mês | ❌ | nenhuma seção | Cliente exige (expirado vira receita) |
| Consumo por origem (FIFO auditável: quanto de cada mês) | ❌ | usina.blade.php:567-589 | Só mostra "meses utilizados", não quantidade |
| Geração projetada vs realizada (déficit) | ❌ | faltam colunas | Sem coluna Esperada/Diferença/Faltante |
| Parâmetros de cálculo (fio_b, percentual_lei, valor_fixo) | ❌ | PDFController:170-174 usa, não exibe | Sem seção "Parâmetros" |

### 1.5 PADRONIZAÇÃO DE UNIDADES (transversal)

| Item | Status | Nota |
|---|---|---|
| Regra "sempre kWh / sempre R$" | ✅ regra | CALCULO_CONTEXTO.md:24 |
| Backend emite unidades explícitas (sufixo _kwh/_reais) | ⚠️ | payload usa sufixos (Vue:691-698), mas internamente float cru |
| Frontend formatador único | ❌ | 3 formatadores + 18× toFixed |
| PDF formatação uniforme | ⚠️ | tabela OK, mas valor_kwh:487 cru e CO2 com 0 casas |

---

## 2. LACUNAS CRÍTICAS QUE O PLANO ATUAL NÃO COBRE (a adicionar)

O plano (CALCULO_CONTEXTO.md) já descreve corretamente: FIFO cross-ano (regra), ledger (decisão), expiração-vira-receita, fonte única, backfill da geração real, padronização de unidades, documento formal. As lacunas abaixo são **itens que o plano cita superficialmente ou não cita** e precisam virar tarefas explícitas:

**P-CRÍTICAS (bugs confirmados, regra existe mas impl falta):**
- **L1 — FIFO cross-ano não implementado.** `CalculoGeracaoService.php:118-140` itera só ano corrente; `$reservaAnoAnterior` só expira (`:95-107`), nunca consome. Prova Eder: 1192 kWh de dez/2025 não usados (md:51,59). Prova Colina: 11800 kWh ago/2025 (md:83-86).
- **L2 — Dupla contagem de crédito expirado.** `CalculoGeracaoService.php:109` soma expirado em valorPago e `:143` soma de novo em creditoGerado. +R$305,49 / +607 kWh no Eder (md:39-40,71).
- **L3 — `creditadoTabela()` credita energia inexistente.** `CalculoGeracao.vue:482-490` faz `(media−valor)*kwh` sem checar saldo (P3b, md:41).

**L-NOVAS (não detalhadas no plano):**
- **L4 — Geração líquida só no frontend.** `CalculoGeracao.vue:507-523` (incl. `getDescontoRede()` :516-523). Backend nunca valida/recalcula. O plano não menciona consumo/desconto-rede como responsabilidade do backend.
- **L5 — Precisão `float` em todas as tabelas.** Migrations de `valor_acumulado_reserva`, `creditos_distribuidos`, `faturamento_usina`, `dados_geracao_real`. O plano não tem tarefa de migração float→decimal.
- **L6 — Backfill não integrado.** `storage/reconstrucao/reconstruir.php` é script PDO de staging; falta `app/Console/Commands/ReconstruirLedgerReserva.php` + migration da tabela `credito_ledger`.
- **L7 — Zero testes.** Nenhum teste para FIFO/expiração/déficit/idempotência. O plano não exige suíte.
- **L8 — Endpoint de PREVIEW inexistente.** Só POST final que persiste. Sem ele o front continua pré-calculando.
- **L9 — Composable/util de formatação inexistente.** `/src/composables` e `/src/utils/formatters.js` não existem; duplicação em 4 componentes.
- **L10 — PDF recalcula em vez de ler.** `PDFController.php:218-225`. Plano diz "PDF só lê o resultado único" (md:182) mas não há tarefa de persistir injetado/CUO no service e ler de `GeracaoFaturamentoPdf`.
- **L11 — Exibição de auditoria fraca em PDF e tela** (ver §4): falta expirado/mês, consumo por origem, projetada×realizada, parâmetros de cálculo, valor_kwh formatado, CO2 parametrizado (`usina.blade.php:487,495-502`).
- **L12 — Validação de inputs incompleta.** `CalculoGeracaoRequest.php:16-26` só `numeric|min:0`.
- **L13 — Relatório de divergência pós-consolidação.** `storage/reconstrucao/relatorio.html` é pré-refatoração. Cliente exige "RELATÓRIO COMPLETO de tudo que está errado após consolidar" (md:19-20). Falta comando que gere antes×depois do ledger.

**Requisitos do cliente que ficariam DESCOBERTOS se nada além do plano atual for feito:**
- "Padronizar unidades" → descoberto no frontend (L9) e parcialmente no PDF (L11).
- "Exibir tudo para auditoria fácil" → **largamente descoberto** (L11 + ausência de ledger exposto na tela).
- "Cobertura total backend+frontend+persistência" → frontend (remoção de fórmulas, L3/L4/L8) e persistência (ledger/decimal, L1/L5/L6) descobertos na implementação.
- "SOLID/DRY/CLEAN" → descoberto enquanto cálculo estiver em 3 lugares (Vue/Service/PDFController).
- "Documento formal" → não iniciado (ver §5).

---

## 3. ESTRATÉGIA DE PADRONIZAÇÃO DE UNIDADES (kWh / R$)

Princípio: **uma unidade tem dono em cada camada; formatação acontece só na borda de exibição.**

### 3.1 Backend — Value Objects
Criar `App\Domain\Faturamento\ValueObject`:
- `Kwh` (encapsula `float`/`int` de energia; método `valor()`, `mais()`, `menos()`, `comTarifa(Tarifa): Reais`).
- `Reais` (centavos como `int` internamente para eliminar erro de float; `formatar()` retorna `"R$ 1.561,11"`).
- `Tarifa` (R$/kWh).

A `CalculadoraGeracaoLinear` (ver §6) recebe e devolve VOs, nunca floats soltos. Persistência converte VO → coluna `decimal(14,4)` (kWh) / `decimal(12,2)` (R$). Migration float→decimal (L5) é pré-requisito. Sufixos de coluna padronizados: `*_kwh` e `*_reais` (já presente no payload Vue:691-698).

### 3.2 Frontend — formatador único
Criar `front/src/utils/formatters.js`:
```
export function formatKwh(valor, casas = 2)  // "1.234,56 kWh"
export function formatReais(valor, casas = 2) // "R$ 1.234,56"  (toLocaleString pt-BR, COM prefixo)
```
E `front/src/composables/useFormatters.js` reexportando para uso em template.
Ações:
- Remover `formatKwh:358`, `formatMoeda:362`, `formatCurrency:374` de `CalculoGeracao.vue` e os **18 `.toFixed`** (linhas 46,48-52,348,360,364,439,445,450,453,711,716,717).
- O R$ e o "kWh" passam a vir SEMPRE do formatador, nunca soltos no template (eliminar `R$ {{ x.toFixed(2) }}`).
- Migrar `AtualizarUsina.vue`, `Distribuicao.vue`, `Relatorios.vue` ao mesmo util (DRY cross-component).

### 3.3 PDF — helper Blade único
Criar `app/Support/Format.php` (ou Blade directives `@kwh` / `@reais`) usado em `usina.blade.php`:
- Substituir `number_format(...)` espalhado por `@reais($v)` / `@kwh($v)`.
- Corrigir `usina.blade.php:487` (valor_kwh sem formatação) → `@reais` com unidade composta "R$/kWh".
- CO2/árvores (`:495-502`): mover fatores para config/usina e formatar com 2 casas padronizadas (ou documentar precisão 0).

Resultado: **três formatadores divergentes → um por camada, todos com a mesma convenção pt-BR.**

---

## 4. O QUE EXIBIR PARA AUDITORIA FÁCIL (cada número rastreável ao ledger)

### 4.1 Tela (CalculoGeracao.vue) e PDF — tabela principal de geração/faturamento
Por mês, colunas com breakdown completo:
- Geração Real (kWh) | Geração Esperada/Média (kWh) | **Faltante = Média − Geração (kWh)**
- Valor Fixo (R$)
- Injetado/Variável (R$) — com nota da fórmula (≥média usa média−menor; <média usa geração−menor)
- **Crédito Resgatado (kWh) e (R$)** — limitado ao faltante e ao saldo
- **Crédito Expirado (kWh) e (R$)** — quanto venceu e virou receita neste mês
- CUO (R$) — com breakdown: faturaEnergia + (consumo × fio_b × percentual_lei/100) + adicional_cuo
- Valor Final (R$)

### 4.2 Seção/tela "Ledger de Reserva" (a nova `credito_ledger`)
Linhas imutáveis rastreáveis: `mes_origem`, `vencimento`, `tipo (CREDITO|CONSUMO|EXPIRACAO)`, `kwh`, `valor_reais`, `referencia_consumo (origem→consumo)`, `usuario`, `data`. Permite responder "crédito de DEZ/25 consumido em MAI/26: 1192 kWh".
Exibir por mês de consumo o detalhamento FIFO: "Consumiu 124 de nov/25 + 1192 de dez/25 + 1737 de jan/26" (exatamente o caso Eder, md:67).

### 4.3 Seção "Parâmetros de Cálculo" (PDF e tela)
`valor_fixo`, `tarifa (valor_kwh)`, `fio_b`, `percentual_lei`, `menor_geracao`, `media`, fatores CO2/árvore. Hoje usados em `PDFController:170-174` mas nunca exibidos.

### 4.4 Detalhe de desconto de rede
"Consumo do mês X kWh − Desconto Rede (tipo: trifásico) Y kWh = Geração Líquida Z kWh" — hoje invisível (`CalculoGeracao.vue:507-523`).

### 4.5 Validações visuais
- Garantir e exibir `creditado ≤ faltante × tarifa` (regra md:75).
- Alerta de crédito a vencer (dias restantes até 180).

---

## 5. ESTRUTURA DO DOCUMENTO DE REGRAS DE CÁLCULO

Local no repo: **`docs/calculo/REGRAS_DE_CALCULO.md`** (raiz do monorepo, fora de `storage/` que é volátil). Acompanhar de `docs/calculo/CHANGELOG.md` para versionar mudanças de regra. `CALCULO_CONTEXTO.md` permanece como histórico de investigação; este documento é a especificação canônica.

Seções:
1. **Glossário e Unidades** — kWh, R$, definição de cada termo, convenção de formatação (pt-BR, casas decimais).
2. **Fórmula Oficial** — Valor Final = Fixo + Variável(Injetado) + Crédito − CUO; definição matemática de cada termo com exemplo numérico (caso Eder mai/26 → R$ 5.700,65, md:106-111).
3. **Valor Fixo** — origem (`comercializacao.valor_fixo` vs `menor_geracao × tarifa`), resolver discrepância backend/frontend.
4. **Valor Variável / Injetado** — fórmula condicional ≥média / <média.
5. **CUO** — fórmula completa com fio_b, percentual_lei (Lei 14.300), adicional, faturaEnergia.
6. **Crédito e Reserva** — déficit, limite ao saldo, **FIFO cross-ano** (consome do mais antigo/próximo do vencimento), exemplo Eder/Colina.
7. **Expiração** — prazo (~180 dias), regra "só expira o que sobrou após FIFO", vira receita no mês do vencimento (md:14-16).
8. **Ledger** — esquema da tabela `credito_ledger`, tipos de lançamento, imutabilidade, relação origem→consumo.
9. **Geração Líquida e Desconto de Rede** — fórmula por tipo de conexão.
10. **Idempotência, Transação e Estorno** — comportamento e limites (só último mês).
11. **Precisão e Arredondamento** — decimal, regra de arredondamento, tolerância de centavos.
12. **Backfill / Reconstrução** — por que reconstrói da geração real, não dos saldos corrompidos (md:73-76).
13. **Casos de Validação (golden)** — Eder UC 562606800 mai/26; Luci UC 19771547 set/25; Colina UC 3085733401 fev/26 — entradas e saídas esperadas, usados como base dos testes.

---

## 6. PLANO FINAL DE FASES (atualizado, SOLID/DRY/CLEAN)

**FASE 0 — Especificação e testes-golden (pré-código)**
- Escrever `docs/calculo/REGRAS_DE_CALCULO.md` (§5).
- Criar testes golden falhando: Eder, Luci, Colina (TDD). Cobre L7/L13 (base do relatório).

**FASE 1 — Núcleo de cálculo único (SOLID/DRY) + Value Objects**
- `App\Domain\Faturamento\ValueObject\{Kwh,Reais,Tarifa}` (§3.1) — elimina float solto (L5).
- `App\Domain\Faturamento\Calculadora\CalculadoraGeracaoLinear` com `calcularFixo/Injetado/Cuo/Credito/ValorFinal/GeracaoLiquida` — **fonte única**. Remove duplicação Vue/Service/PDFController.
- `ValidadorEntrada` (regras de coerência, L12).
- `CalculoGeracaoService` passa a orquestrar a Calculadora (Single Responsibility).

**FASE 2 — Persistência: ledger + precisão**
- Migration `credito_ledger` (§4.2) + migrations float→decimal (L5).
- `GestorReserva` (FIFO cross-ano + expiração pós-FIFO) escrevendo lançamentos imutáveis — corrige **L1, L2**. Colunas mensais viram cache materializado.
- Persistir injetado/CUO/crédito/expirado por mês (corrige L10, base de auditoria).

**FASE 3 — Backfill integrado**
- `app/Console/Commands/ReconstruirLedgerReserva.php` portando `reconstruir.php`, reconstruindo da geração real (md:73-76) — L6. Rodar dry-run e gerar relatório de divergência antes×depois — **L13**.

**FASE 4 — API (preview + cálculo único)**
- `GET .../preview` (calcula sem persistir) e `POST .../calculo` (persiste) usando o MESMO núcleo (L8). Retornar todos os termos + ledger no payload.

**FASE 5 — Frontend (front só lê e exibe)**
- Deletar `injetado/creditado/creditadoTabela/cuo/valorFinal*/atualizarValores/getDescontoRede` (`CalculoGeracao.vue:433-523`) — corrige **L3, L4** e DRY.
- `src/utils/formatters.js` + `useFormatters` (§3.2); migrar 4 componentes — L9. Remover 18 toFixed.
- Renderizar breakdown de auditoria e ledger (§4) — L11.
- Guards de input (L12 no front).

**FASE 6 — PDF (só lê)**
- `PDFController` deixa de recalcular; lê de `GeracaoFaturamentoPdf` — L10. Helper Blade `@kwh/@reais` (§3.3), corrigir `:487`, parametrizar CO2 `:495-502`. Adicionar colunas expirado/origem-FIFO/projetada×realizada/faltante e seção Parâmetros — L11.

**FASE 7 — Relatório de consolidação e fechamento**
- Comando que gera relatório formal de divergências pós-consolidação (cliente, md:19-20).
- Suíte de testes verde (L7); revisão SOLID/DRY/CLEAN; atualizar CHANGELOG do documento.

**Garantias SOLID/DRY/CLEAN:** SRP (Calculadora ≠ GestorReserva ≠ GestorFaturamento ≠ Validador); DRY (uma fórmula no backend, front e PDF apenas leem); CLEAN (VOs com nomes claros, sem efeito colateral oculto, docblocks + testes golden por caso real).

---

**Cobertura final dos requisitos do cliente:** (1) unidades → Fases 1/5/6; (2) auditoria fácil → Fases 2/5/6 + §4; (3) cobertura total → Fases 1-6; (4) SOLID/DRY/CLEAN → Fase 1 + transversal; (5) documento formal → Fase 0 (§5). Nenhum requisito do cliente fica descoberto **se as 7 fases forem executadas**; o risco residual é o backfill (Fase 3) sobre saldos migrados sem geração real lançada (md:87-88), que exige decisão de negócio sobre saldos iniciais antes de reconstruir o ledger.

Arquivos-chave citados: `/Users/matheus/Desktop/Projetos App.nosync/emerson-lider-energy/api-laravel/storage/CALCULO_CONTEXTO.md`, `/Users/matheus/Desktop/Projetos App.nosync/emerson-lider-energy/api-laravel/app/Services/CalculoGeracaoService.php`, `/Users/matheus/Desktop/Projetos App.nosync/emerson-lider-energy/api-laravel/app/Http/Controllers/PDFController.php`, `/Users/matheus/Desktop/Projetos App.nosync/emerson-lider-energy/api-laravel/resources/views/usina.blade.php`, `/Users/matheus/Desktop/Projetos App.nosync/emerson-lider-energy/front/src/components/CalculoGeracao.vue` (caminho a confirmar), `/Users/matheus/Desktop/Projetos App.nosync/emerson-lider-energy/api-laravel/storage/reconstrucao/reconstruir.php`. Documento a criar: `/Users/matheus/Desktop/Projetos App.nosync/emerson-lider-energy/docs/calculo/REGRAS_DE_CALCULO.md`.