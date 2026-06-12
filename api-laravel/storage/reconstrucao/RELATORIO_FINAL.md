# Relatório de Consolidação — Correção dos Cálculos de Faturamento

> Documento de fechamento do redesenho dos cálculos de geração (Consórcio Líder Energy).
> Consolida **tudo que estava errado**, o **impacto** e **como foi corrigido**.
> Branch: `fix/recalculo-faturamento-auditoria` · Data: 2026-06-11

---

## 1. Resumo executivo

O sistema calculava o faturamento de geração de forma **inconsistente e incorreta**. A mesma conta
existia em **três lugares diferentes** (frontend, motor de gravação e geração de PDF), cada um com uma
fórmula distinta — a ponto de o **mesmo mês** aparecer com **quatro valores diferentes** (ex.: Eder, Maio/2026:
banco R$ 6.961,71 / tela R$ 6.862,30 / PDF R$ 6.009,22 / correto R$ 5.700,65).

**Impacto financeiro medido** (reconstrução de todas as 67 usinas, comparando o que o sistema creditou
vs. o que deveria): o sistema **creditou R$ 69.554 a mais** no agregado, dominado por um único bug
(creditar sem haver déficit).

A correção **unificou todo o cálculo numa única fonte de verdade** (um motor de domínio testado),
implementou um **livro-razão (ledger) auditável** para a reserva de créditos, e fez o frontend e o PDF
apenas **lerem** o resultado — nunca recalcular.

---

## 2. Tudo que estava errado

### 🔴 Críticos (afetam valores pagos ao cliente)

| # | Problema | Onde | Efeito |
|---|---|---|---|
| C1 | **Crédito sem déficit** — o sistema creditava mesmo quando a geração ≥ média (não faltava nada) | engine antigo + frontend `creditadoTabela()` | 43 casos, **−R$ 63.115**. Ex.: Colina creditou R$ 7.080 num mês sem déficit |
| C2 | **Crédito não respeitava o saldo da reserva** — creditava energia que não estava guardada | `creditadoTabela()` (frontend) | 2 casos, −R$ 1.694 |
| C3 | **Crédito expirado contado em dobro** — o valor expirado era somado ao faturamento E ao crédito | `CalculoGeracaoService:109,143` | Ex.: Eder +R$ 305 num mês |
| C4 | **FIFO não atravessava anos** — crédito antigo (de anos anteriores) não era usado para compensar | `CalculoGeracaoService:118-129` | 15 casos, cliente recebia a menos (+R$ 3.117) |
| C5 | **Cálculo triplicado e divergente** — 3 fórmulas diferentes (frontend, gravação, PDF) | 3 lugares | até 4 valores para o mesmo mês |
| C6 | **Salvar usava o motor antigo (buggado)** enquanto o preview usava o novo | rota `.../calculo` | preview certo, gravação errada |

### 🟡 Estruturais / de dados

| # | Problema | Efeito |
|---|---|---|
| E1 | **Auditoria destruída** — o resgate de crédito apagava o histórico do mês de origem | impossível auditar de onde veio cada crédito |
| E2 | **Precisão `float`** em colunas monetárias (valor_fixo, menor_geracao, fio_b) | erros de centavos |
| E3 | **Faturamento congelado** — o mês era calculado antes do consumo ser informado e nunca recalculado | Eder Maio calculado com consumo 0 |
| E4 | **`dados_consumo_usina` duplicado** — cada lançamento/revert criava nova linha (108 pares) | leitura de consumo ambígua |
| E7 | **Crédito em mês sem geração real registrada** — Valdemar Gremski (UC 98650262), Ago/2025: R$ 4.288,75 creditados sem geração que os justifique | crédito indevido OU geração não importada; requer verificação manual |
| E5 | **Unidades inconsistentes** — formatadores divergentes (kWh/R$) espalhados | apresentação confusa |
| E6 | **Cálculo de negócio no template** (PDF) e no frontend | manutenção e divergência |

---

## 3. Impacto financeiro (antes × depois)

Reconstrução de **67 usinas** a partir da geração real, comparando o crédito que o sistema gerou (ANTES)
com o crédito correto (DEPOIS):

| Tipo de erro | Casos | Impacto |
|---|---|---|
| 🔴 Crédito sem déficit | 43 | **−R$ 63.115,34** |
| 🟣 Crédito sem geração registrada | 1 | −R$ 4.288,75 |
| 🟠 Creditou além do déficit | 7 | −R$ 3.572,40 |
| 🟡 Creditou além da reserva | 2 | −R$ 1.694,38 |
| 🔵 Creditou a menos (FIFO não usou reserva antiga) | 15 | +R$ 3.116,97 |
| | **68** | **−R$ 69.553,90** (creditado a mais) |

> Detalhe por usina em `relatorio.html` (filtrável, apresentável).

---

## 4. Como foi corrigido (arquitetura nova)

Fórmula oficial, **única e confirmada** (validada contra a planilha do cliente):

```
Valor Final = Valor Fixo + Valor Variável + Crédito − CUO  (+ Receita de Expiração, quando houver)
```

- **Fonte única de cálculo:** `App\Domain\Faturamento\CalculadoraGeracaoLinear` (PHP puro, testado). Frontend,
  gravação e PDF **apenas leem** o resultado.
- **Reserva como livro-razão (ledger):** `credito_ledger` com lançamentos imutáveis (CREDITO/CONSUMO/EXPIRACAO).
  O resgate vira um lançamento — o histórico nunca é apagado (auditoria completa).
- **FIFO cross-ano:** o crédito mais antigo é consumido primeiro, atravessando anos (padrão ANEEL).
- **Expiração (180 dias):** crédito vencido vira **receita** indo pra frente; no histórico, apenas expira (sem pagar).
- **Precisão exata:** colunas monetárias migradas de `float` para `decimal`; dinheiro tratado em centavos inteiros.
- **Geração líquida no backend** (consumo − desconto de rede), não mais no frontend.
- **Unidades padronizadas:** kWh para energia, R$ para dinheiro, via formatadores únicos (front e PDF).

---

## 5. Validação (evidências)

| Caso | Esperado | Resultado |
|---|---|---|
| Eder Alcione (UC 562606800), Maio/2026 | R$ 5.700,65 | ✅ **5.700,65** (motor + staging) |
| Luci Vilce (UC 19771547), Set/2025 | R$ 4.801,50 | ✅ **4.801,50** |
| Colina Eco Solar (UC 3085733401), Fev/2026 | Crédito R$ 0,00 (sem déficit) | ✅ **0,00** (era R$ 7.080) |

- **61 testes automatizados** verdes (golden + unitários + integração + persistência).
- Backfill do ledger validado no staging: **idempotente** (re-rodar não duplica).
- **Relatório antes×depois auditado e reproduzível** (2026-06-11): regenerado do dump pristino de produção
  (`energia_antes_20260611_164628.dump`) com resultado byte a byte idêntico; cobertura de crédito verificada por SQL
  independente (nenhum R$ creditado fica fora — meses sem geração registrada viram o tipo "Crédito sem geração registrada").
  Regras de exibição honestas: valor final DEPOIS exclui receita de expiração retroativa (não é paga) e é omitido (—)
  quando não há demonstrativo ANTES para reconciliar a fatura manual do CUO.
- Descoberta importante: o **valor final é robusto ao consumo** — `Variável + Crédito = (média − menor) × tarifa`
  (a geração líquida se cancela), então o total não depende do valor exato do consumo, só a divisão entre as linhas.

---

## 6. Pendências antes de produção

1. **Aplicar em produção** (migrations decimal + criar `credito_ledger`) — só com aprovação e backup.
2. **Rodar o backfill** (`php artisan ledger:reconstruir`) em produção após as migrations.
3. **Teste visual** da tela e do PDF pelo time (feito no staging; confirmar em produção).
4. **Reconciliar o consumo** dos meses calculados com consumo incompleto (recalcular quando o consumo for informado).
5. **Deduplicar** `dados_consumo_usina` (108 pares) — limpeza de dados.
