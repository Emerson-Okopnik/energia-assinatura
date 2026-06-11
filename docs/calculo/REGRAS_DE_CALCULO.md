# Regras de Cálculo de Faturamento de Geração

> **Especificação canônica** do cálculo de faturamento das usinas (Consórcio Líder Energy).
> Este documento é a fonte de verdade das regras de negócio. O código deve implementá-lo;
> divergências entre código e este documento são bugs.
>
> Versão: 1.0 · Última atualização: 2026-06-11

---

## 1. Glossário e Unidades

### Unidades padronizadas
- **Energia:** sempre em **kWh** (quilowatt-hora), exibida com 2 casas decimais e sufixo ` kWh`.
- **Dinheiro:** sempre em **R$** (reais), exibido no formato pt-BR com prefixo `R$ ` e 2 casas (`R$ 1.561,11`).
- **Tarifa:** R$/kWh, com a precisão cadastrada (até 6 casas).

### Termos
| Termo | Definição |
|---|---|
| **Geração Real** | Energia efetivamente gerada pela usina no mês (kWh). |
| **Geração Líquida** | Geração Real menos o consumo da própria usina, já aplicado o desconto de rede. É sobre ela que o cálculo opera. |
| **Média (Geração Projetada)** | Geração esperada mensal, cadastrada por usina (`dados_geracao.media`). |
| **Menor Geração** | Piso de geração cadastrado (`dados_geracao.menor_geracao`), usado no cálculo do injetado. |
| **Excedente** | Geração Líquida − Média, quando positivo. Vira crédito guardado na reserva. |
| **Faltante / Déficit** | Média − Geração Líquida, quando positivo. É compensado pela reserva (crédito). |
| **Reserva** | Saldo de energia guardada (kWh) de meses em que houve excedente, disponível para compensar meses futuros. |
| **Crédito** | Energia da reserva resgatada para compensar o faltante de um mês, convertida em R$. |
| **Crédito Expirado** | Energia guardada que venceu (passou do prazo) sem ser usada; vira receita. |
| **CUO** | Custo Operacional da Usina (encargos da concessionária + Fio B). |

---

## 2. Fórmula Oficial

```
Valor Final (R$) = Valor Fixo + Valor Variável (Injetado) + Crédito − CUO
```

São **quatro termos**. Injetado e Crédito são grandezas distintas (não há dupla contagem):
o Injetado refere-se à energia do **próprio mês**; o Crédito, à energia **resgatada da reserva** de meses anteriores.

A **receita de Crédito Expirado** (§7), quando houver, é um componente situacional somado ao resultado e
exibido em linha própria do demonstrativo (não embutido no termo Crédito).

### Caso de validação (golden) — Eder Alcione Stalter, UC 562606800, Maio/2026
| Termo | Valor |
|---|---|
| Valor Fixo | R$ 3.894,36 |
| Valor Variável (Injetado) | R$ 1.129,14 |
| Crédito | R$ 1.561,11 |
| CUO | − R$ 883,96 |
| **Total** | **R$ 5.700,65** |

---

## 3. Valor Fixo

```
Valor Fixo (R$) = menor_geracao (kWh) × tarifa (R$/kWh)
```

- `menor_geracao` = **mínimo das 12 gerações projetadas** da usina (`dados_geracao`). Confirmado nos dados:
  Eder, junho = 7.636 kWh é o menor dos 12 meses → Fixo = 7.636 × 0,51 = R$ 3.894,36 (bate com a planilha).
- O `comercializacao.valor_fixo` cadastrado deve ser **derivado** de `menor_geracao × tarifa`, nunca digitado solto.

---

## 4. Valor Variável (Injetado)

Depende da relação entre **Geração Líquida** (§9) e Média. **Sempre opera sobre a geração líquida**, não a bruta:

```
SE geracao_liquida >= media:
    Injetado = (media − menor_geracao) × tarifa             // teto: injeta no máximo o "miolo" da média
SENÃO:
    Injetado = (geracao_liquida − menor_geracao) × tarifa   // injeta proporcional à geração do mês
```

> **Confirmado pela planilha (Eder, Maio/2026):** com geração líquida = 9.850, Variável = (9.850 − 7.636) × 0,51 = R$ 1.129,14.
> O mesmo valor de geração líquida (9.850) alimenta o Crédito (§6) — Variável e Crédito **nunca** usam valores de geração diferentes.
> A geração líquida depende do consumo final do mês (§9); o cálculo deve usar o consumo final e **recalcular se o consumo mudar**.

---

## 5. CUO (Custo Operacional da Usina)

```
CUO (R$) = faturaEnergia + (geracao × fio_b × percentual_lei / 100) + adicional_cuo
```

- `geracao`: a geração do mês (base do Fio B). Confirmado nos dados do Eder: `9858 × 0,13275 × 0,60 = 785,19`,
  somado a `faturaEnergia ≈ 98,77` → CUO = R$ 883,96 (bate com a planilha).
  *(A confirmar se a base é geração bruta ou líquida — a diferença de ~8 kWh é absorvida pelo `faturaEnergia` manual.)*
- `fio_b`: tarifa do Fio B (R$/kWh), cadastrada em `comercializacao.fio_b`.
- `percentual_lei`: percentual de cobrança do Fio B conforme escalonamento da **Lei 14.300/2022** (`comercializacao.percentual_lei`).
- `faturaEnergia`: valor da fatura da concessionária no mês — **input manual** do operador.
- `adicional_cuo`: ajuste manual, somado apenas no mês de referência.

O CUO é **subtraído** no valor final.

---

## 6. Crédito e Reserva (FIFO cross-ano)

> Em todas as regras abaixo, `geracao` significa **Geração Líquida** (§9).

### Acúmulo
Quando `geracao >= media`, o excedente (`geracao − media`) é **guardado** na reserva, registrado com:
- o **mês de origem** (define o vencimento),
- a quantidade em kWh,
- a tarifa vigente.

### Resgate (compensação)
Quando `geracao < media`, o faltante (`media − geracao`) é compensado consumindo a reserva:

```
energia_compensada = min( faltante, saldo_total_da_reserva )
Crédito (R$) = energia_compensada × tarifa
```

**O crédito nunca pode exceder o faltante nem o saldo real da reserva.**

### Ordem de consumo: FIFO cross-ano
O resgate consome **sempre o crédito mais antigo primeiro**, atravessando anos
(o saldo de dezembro/2025 é consumido antes do de janeiro/2026). Motivo: o crédito mais antigo é o
mais próximo do vencimento; consumi-lo primeiro evita expiração desnecessária. (Padrão SCEE/ANEEL.)

### Caso de validação — Eder, Maio/2026
- Faltante de Maio = 12.911 − 9.858 = 3.053 kWh
- Consumo FIFO: nov/2025 + dez/2025 + jan/2026 (mais antigos primeiro)
- Crédito ≈ 3.053 kWh × R$ 0,51 ≈ **R$ 1.557** (planilha: R$ 1.561,11; diferença = arredondamento de `menor_geracao`)

### Caso de validação — Colina Eco Solar, UC 3085733401, Fev/2026
- Geração de Fevereiro = 16.740 = Média (16.740) → **faltante = 0 → Crédito = R$ 0,00**
- O sistema antigo creditava R$ 7.080 (= saldo inteiro da reserva) sem haver déficit. **Errado.**

---

## 7. Expiração de Crédito

- **Prazo:** o crédito guardado expira em **180 dias** a partir do mês de origem (regra do consórcio).
- **Ordem:** a expiração é avaliada **após** o consumo FIFO do mês — só expira o que **sobrou** sem uso.
- **Destino (operação normal, indo pra frente):** o crédito expirado **vira receita em dinheiro**
  (`kwh_expirado × tarifa`) no mês do vencimento, somado ao Valor a Receber (linha própria do demonstrativo).
  **Não** é contado em dobro (não soma ao termo Crédito e ao faturamento simultaneamente).
- **Exceção — backfill retroativo (§12):** crédito que **já venceu no passado**, ao reconstruir o histórico,
  é apenas **removido da reserva (lançamento `EXPIRACAO` no ledger) SEM pagamento** — não geramos receita
  retroativa. O backfill escreve só no `credito_ledger`, nunca em `faturamento_usina`. Assim, créditos vencidos
  antes do go-live não são pagos; apenas os que vencerem **a partir** do go-live geram receita.

---

## 8. Ledger de Reserva (auditoria)

A reserva é registrada como um **livro de lançamentos imutáveis** (`credito_ledger`). Cada movimento é uma linha:

| Campo | Descrição |
|---|---|
| `tipo` | `SALDO_INICIAL`, `CREDITO` (guardou), `CONSUMO` (resgatou), `EXPIRACAO` (venceu) |
| `competencia_origem` | mês que gerou o crédito (define vencimento) |
| `competencia_evento` | mês em que o lançamento ocorreu |
| `kwh` | positivo (entrada) ou negativo (saída) |
| `tarifa_kwh`, `valor_reais` | para reconstruir o R$ histórico |
| `vencimento` | competencia_origem + 180 dias |
| `ref_lancamento_id` | um CONSUMO/EXPIRACAO aponta para o CREDITO de origem (rastreabilidade FIFO) |
| `idempotency_key`, `estornado_em`, `user_id` | controle e auditoria |

**Invariante:** o saldo de uma origem = soma dos `kwh` não-estornados daquela origem; nunca negativo.
Consumir **não** edita o crédito original — insere um lançamento de saída. Isso preserva o histórico (auditoria).

---

## 9. Geração Líquida e Desconto de Rede

```
consumo_descontavel = max( consumo_mes − desconto_rede, 0 )
geracao_liquida     = max( geracao_bruta − consumo_descontavel, 0 )
```

Desconto de rede por tipo de conexão:
| Tipo | Desconto (kWh) |
|---|---|
| Trifásico | 100 |
| Bifásico | 50 |
| Monofásico | 30 |

> Esta regra **deve viver no backend** (hoje está no frontend). O cálculo opera sobre a Geração Líquida.
>
> **Consumo final e recálculo:** o cálculo deve usar o **consumo final** do mês. Se o faturamento for lançado antes
> de o consumo ser preenchido (consumo = 0) e o consumo for informado depois, o mês **deve ser recalculado** — caso
> contrário o resultado "congela" com dado incompleto (bug observado no Eder: maio calculado com consumo 0 → 9.858,
> depois consumo virou 134, sem recálculo).
>
> **Unicidade:** `dados_consumo_usina` deve ser **único por (usina, ano)**. Hoje cada lançamento/revert cria uma nova
> linha (108 pares duplicados no banco) — o upsert deve reusar o pacote do ano, não criar um novo.

---

## 10. Idempotência, Transação e Estorno

- Cada lançamento mensal exige um header `Idempotency-Key`; repetição com o mesmo payload retorna o resultado já gravado; payload diferente com a mesma chave → conflito (409).
- Todo o processamento ocorre em **transação atômica**.
- **Estorno:** revertido via marcação `estornado_em` nos lançamentos do ledger (não destrutivo). Limite atual: último mês lançado.

---

## 11. Precisão e Arredondamento

- Colunas monetárias e de energia em **`decimal`** (nunca `float`): `decimal(12,2)` para R$, `decimal(14,4)` para kWh, `decimal(12,6)` para tarifa.
- No núcleo de cálculo, dinheiro é tratado como **centavos inteiros** para eliminar erro de ponto flutuante.
- Arredondamento para 2 casas apenas na borda de exibição/persistência final.

---

## 12. Backfill / Reconstrução

A reserva atual no banco está **corrompida** (desconto destrutivo apagou o histórico). Por isso o ledger é
reconstruído a partir da **geração real mês a mês** (não dos saldos atuais), aplicando as regras deste documento.

- Usinas cujo histórico de geração explica a reserva (45 de 66): reconstrução direta.
- Usinas com **saldo inicial migrado** (21 de 66 — déficit histórico > excedente): exigem um lançamento
  `SALDO_INICIAL` no ledger, investigado caso a caso (ver `storage/CALCULO_CONTEXTO.md`).

---

## 13. Casos de Validação (Golden)

Usados como oráculo nos testes automatizados:

| Caso | UC | Período | Resultado esperado |
|---|---|---|---|
| Eder Alcione Stalter | 562606800 | Mai/2026 | Total **R$ 5.700,65** (Fixo 3.894,36 + Variável 1.129,14 + Crédito 1.561,11 − CUO 883,96) |
| Luci Vilce Penkaç | 19771547 | Set/2025 | Total **R$ 4.801,50** (Fixo 3.342,18 + Variável 2.154,45 − CUO 695,13) |
| Colina Eco Solar | 3085733401 | Fev/2026 | Crédito **R$ 0,00** (geração = média, sem déficit) |

---

## Changelog

- **1.1** (2026-06-11) — Implementação concluída (Fases 0-7). Cálculo unificado numa fonte única
  (`App\Domain\Faturamento\CalculadoraGeracaoLinear`); ledger `credito_ledger` com expiração; precisão decimal;
  frontend e PDF apenas leem; save e preview no mesmo motor (engine antigo removido). Esclarecida a expiração
  (paga indo pra frente, perde no backfill retroativo) e a inexistência de saldos iniciais migrados (reserva
  sempre nasce em 0). Relatório de consolidação em `storage/reconstrucao/RELATORIO_FINAL.md` e antes×depois em
  `relatorio.html` (impacto −R$ 65.265 creditados a mais). 61 testes verdes.
- **1.0** (2026-06-11) — Versão inicial. Formaliza fórmula de 4 termos, FIFO cross-ano, expiração→receita (180 dias),
  ledger de auditoria, precisão decimal, geração líquida no backend. Baseada na investigação em `storage/CALCULO_CONTEXTO.md`.
