# Correção da `fatura_energia` no faturamento + auditoria refeita (PAGA TUDO)

> Design · 2026-06-17 · branch `fix/recalculo-faturamento-auditoria`
> Objetivo: deixar o **sistema** com o valor correto de cada mês (como deveria ter
> sido), sem mexer no que já foi pago ao cliente; e refazer a **auditoria** para ser
> a prova histórica fiel dos erros (pago antigo × correto).

---

## 1. Contexto e diagnóstico

O backfill (`ledger:reconstruir`) rodou em produção (12/jun 04:11) re-materializando
todos os meses **com `fatura_energia = 0`** — porque o histórico não tinha as faturas
da concessionária. Como `CUO = fatura_energia + (geração × fio_b × %lei/100) + adicional`,
e `Valor Final = Fixo + Variável + Crédito − CUO + ReceitaExpiração`, faturar com
fatura 0 produz **CUO subdimensionado → Valor Final inflado** exatamente pelo valor da
fatura faltante.

**Caso âncora — Romeu de Mello (UC 521206860), estado real de prod (dump 2026-06-17):**

| Mês | Valor pago hoje (fatura=0) | Correto (fatura real) | Diferença = fatura |
|-----|---------------------------:|----------------------:|-------------------:|
| Jan/2026 | 4.110,00 | **2.446,29** | 1.663,71 |
| Fev/2026 | 3.101,32 | 1.443,93 | 1.657,39 |
| Mar/2026 | 2.866,78 | 1.209,76 | 1.657,02 |
| Abr/2026 | 6.965,46 | 5.291,03 | 1.674,43 |
| Mai/2026 | 3.850,86 | 3.850,86 (lançamento manual, já certo) | 0 |

A diferença, mês a mês, é **exatamente** a `fatura_energia` daquele mês. Reproduzido
contra o motor de domínio; bate centavo a centavo.

### Decisão de negócio: PAGA TUDO

Crédito que expira (180 dias) **vira pagamento ao cliente no mês do vencimento,
inclusive retroativo** (vencido antes do go-live). Isto **revoga** a antiga §12 de
`REGRAS_DE_CALCULO.md` ("vencido retroativo só sai da reserva, sem pagamento").

Consequência: o **motor já está correto** — `CalculadoraGeracaoLinear` soma
`receitaExpiracao` no `valorFinal`. O backfill, ao persistir, já pagou a expiração.
**O único erro em produção é a `fatura_energia = 0`.**

### Estado real de produção (lido do dump em banco limpo)

- 494 competências em `geracao_faturamento_pdf`.
- **4 com fatura > 0** (lançamentos manuais de 15/jun: Romeu Mai, UC 113906836 Mai,
  Eder Mai, UC 6656137 Jun).
- **490 com fatura = 0** (backfill de 12/jun).

### Recuperabilidade da fatura

A fatura real **não existe mais em produção** (o backfill zerou inclusive
`geracao_faturamento_pdf.fatura_energia`). A única fonte é o **dump pré-correção**
(`energia_antes_20260611_164628.dump`), onde o CUO antigo ainda embute a fatura:

```
fatura_derivada = max( cuo_PDF_antigo − geração_bruta × fio_b × %lei/100 , 0 )
```

Cobertura medida no dump: **270 de 311** competências com PDF têm fatura derivável > 0.
As demais (e os meses sem PDF antigo) ficam com fatura 0 — **genuinamente nunca tiveram
fatura informada**; não há dado a recuperar (não há `adicional_cuo` persistido; consumo
não compõe CUO).

---

## 2. Objetivo e não-objetivos

**Objetivo:**
1. O sistema passa a registrar o **Valor Final correto** de cada mês (fatura real
   aplicada; expiração paga — PAGA TUDO).
2. A **auditoria** refeita reflete fielmente **pago antigo × correto**, por cliente.

**Não-objetivos:**
- Não re-cobrar nem estornar nada do que já foi pago ao cliente (passado é passado).
- Não criar um novo cálculo: reusa o motor único (`CalculadoraGeracaoLinear`).
- Não inventar fatura onde não há dado (meses sem fonte ficam em 0; entregamos a lista).

---

## 3. Componentes

### 3.1 Tabela-fonte da fatura (extraída do dump)

Um artefato intermediário, gerado **a partir do dump antigo**, com uma linha por
(usina, competência): `usi_id` (ou `uc` para casar entre bancos), `competencia`,
`fatura_energia` derivada. Conferível por humano **antes** de qualquer escrita em prod.

Formato: CSV/seed versionável fora do git (contém base de prod) — extraído por um
script de leitura do dump restaurado num Postgres temporário.

### 3.2 Comando `faturamento:corrigir-fatura`

Segue o padrão de `ReconstruirLedgerReserva`. Para cada usina, em ordem cronológica,
para cada mês com geração real, chama `FaturamentoService::calcularMes(persistir: true)`
com a **fatura por precedência**:

1. **`geracao_faturamento_pdf.fatura_energia` de produção, se > 0** → usa essa
   (preserva os 4 lançamentos manuais — nunca sobrescreve trabalho humano).
2. Senão, **fatura da tabela-fonte** (derivada do dump).
3. Senão, **0** (sem fonte).

Garantias:
- **Idempotente:** `calcularMes` limpa os lançamentos do evento e faz updateOrCreate
  nas colunas/cache — re-rodar produz o mesmo estado (validado: 2ª rodada = 0 mudanças,
  ledger sem duplicação).
- **Guard de competência futura:** não processa mês > ano/mês corrente (igual ao backfill).
- **`--dry-run`:** não grava; emite CSV antes×depois para conferência.
- **Transação:** a escrita roda dentro de `DB::transaction`.
- **Motor inalterado:** a expiração continua paga (PAGA TUDO).

Saída: CSV `correcao-fatura-antes-depois.csv` (usina, competência, valor_antes,
valor_depois, delta, fatura_origem ∈ {prod, dump, zero}).

### 3.3 Auditoria refeita (`reconstruir.php`)

Dois ajustes no script existente:

1. **Pagar a expiração** (linha 248): trocar `valorFinal − receitaExpiracao` por
   `valorFinal` (PAGA TUDO). O ANTES (`finalA`) continua sendo o
   `geracao_faturamento_pdf.valor_final` do **dump pré-correção** (= valor pago ao
   cliente). O DEPOIS passa a ser o valor correto cheio.
2. **Bug do 1º mês:** investigar e corrigir o caso em que a fatura derivada não é
   aplicada no primeiro mês com PDF de uma usina (detectado na UC 109983181, Jan/2026).
   A correção do sistema (3.2) **não** tem esse bug; a auditoria deve bater com ela.

Resultado: relatório **pago antigo × correto (PAGA TUDO)**, por cliente, com totais
"pagamos a mais" / "pagamos a menos". É a prova histórica preservada (junto do dump).

### 3.4 Documentação da regra

Atualizar `docs/calculo/REGRAS_DE_CALCULO.md` §7 e §12 para refletir **PAGA TUDO**
(expiração sempre vira pagamento no mês do vencimento, inclusive retroativo). Remover
a exceção de backfill retroativo.

---

## 4. Fluxo de aplicação (produção)

1. **Backup** do banco de produção (obrigatório).
2. Restaurar o dump antigo num Postgres temporário; **extrair a tabela-fonte** da fatura
   (3.1); conferir.
3. Rodar `faturamento:corrigir-fatura --dry-run` em produção → gerar CSV antes×depois.
4. **Revisão humana** do CSV (especial atenção aos 4 lançamentos preservados e aos
   meses que ficam em 0).
5. Com aval, rodar sem `--dry-run` (em transação).
6. Refazer a auditoria (3.3) e arquivar como prova histórica.
7. Validar a tela do Romeu (Jan deve ir de 4.110,00 → 2.446,29) e amostras.

---

## 5. Validação (já executada contra clones do dump)

- Correção (fatura por precedência) bate com o valor correto esperado no Romeu (5/5).
- Idempotência confirmada; ledger sem duplicação.
- Precedência preserva os lançamentos manuais (0 meses `prod` alterados).
- Cobertura: 268 corrigidos + 4 preservados = 272; 222 ficam em 0 (sem fonte).
- Impacto (apenas fatura, expiração mantida paga): **−R$ 46.173** no agregado dos meses
  com PDF (sistema deixa de pagar a mais por CUO subdimensionado).

> Nota: toda validação roda em **bancos descartáveis** separados do clone que representa
> o estado real — nunca persistir teste no clone-espelho.

---

## 6. Entregáveis

1. Comando `faturamento:corrigir-fatura` (com `--dry-run`, idempotente, guard de futuro).
2. Tabela-fonte da fatura extraída do dump (+ script de extração).
3. `reconstruir.php` corrigido (paga expiração; bug do 1º mês).
4. `REGRAS_DE_CALCULO.md` §7/§12 atualizado (PAGA TUDO).
5. CSV antes×depois da correção + lista dos meses que ficam em fatura 0 (revisão manual).
6. Auditoria refeita (pago antigo × correto) como prova histórica.

---

## 7. Riscos e mitigações

| Risco | Mitigação |
|-------|-----------|
| Sobrescrever lançamento manual | Precedência: fatura de prod > 0 vence sempre; validado (0 alterados). |
| Fatura derivada incorreta (CUO antigo com base diferente) | 21 casos no dump dão negativo → `max(...,0)` zera; conferência humana do CSV-fonte. |
| Re-rodar e duplicar/divergir | `calcularMes` é idempotente; validado (2ª rodada 0 mudanças). |
| Competência futura lançada por engano | Guard de ano/mês corrente. |
| Auditoria não bater com sistema | Mesmo motor; auditoria corrigida (paga expiração + bug 1º mês). |
| Dump como fonte some | Dumps preservados fora do git; tabela-fonte versionada como artefato. |

---

## 8. Decisões em aberto (resolver no plano)

- Mecanismo exato da tabela-fonte: CSV/seed vs banco lado a lado. **Decidido: tabela-fonte
  extraída do dump** (controlável, conferível). Detalhar formato/local no plano.
- Investigar a causa-raiz do bug do 1º mês na auditoria antes de corrigir.
