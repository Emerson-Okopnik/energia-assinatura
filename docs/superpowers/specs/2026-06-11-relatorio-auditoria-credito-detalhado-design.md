# Relatório de Auditoria de Crédito — versão detalhada e corrigida

> Spec de design. Brainstorm concluído e aprovado em 2026-06-11.
> Substitui a reconstrução atual (`api-laravel/storage/reconstrucao/reconstruir.php`)
> por uma que dirige o motor de cálculo único do domínio e produz um relatório auditável completo.

## 1. Problema

O relatório atual ([relatorio.html](../../../api-laravel/storage/reconstrucao/relatorio.html), gerado por
[reconstruir.php](../../../api-laravel/storage/reconstrucao/reconstruir.php)) compara apenas o **crédito**
(Antes × Depois) e diverge da especificação canônica
([REGRAS_DE_CALCULO.md](../../calculo/REGRAS_DE_CALCULO.md)) em pontos materiais:

1. **Usa geração BRUTA, não líquida** (§4, §6, §9). Nunca consulta `dados_consumo`. Déficit/excedente/FIFO
   rodam sobre a bruta → o maior balde do relatório ("Crédito sem déficit", R$ -63.357,02) é o mais suspeito:
   uma usina com bruta ≥ média pode ter líquida < média, tornando o crédito legítimo.
2. **Ignora o saldo inicial migrado de 21 usinas** (§12). Reconstrói a reserva do zero → FIFO sem de onde
   consumir nessas usinas → falsos "Creditou além da reserva" / "Creditou a menos".
3. **Expira o crédito ANTES do consumo FIFO** (§7 exige o inverso: consome primeiro, expira o que sobrou).
4. **Reimplementa o FIFO/cálculo** num script isolado — viola o requisito do cliente de **cálculo único, zero
   duplicação** (CALCULO_CONTEXTO.md). O relatório valida uma 2ª implementação, não o motor de produção.

## 2. Objetivo

Regerar o relatório aplicando a metodologia correta e **dirigindo o motor de domínio real**
(`App\Domain\Faturamento`), entregando um documento auditável que sirva tanto de evidência para o cliente
quanto de teste de aceitação do motor que irá para produção.

Não-objetivos: alterar produção; refatorar o `CalculoGeracaoService` legado; corrigir o upsert de consumo no
app (apenas reportar a duplicidade); reconciliar a metodologia própria do Variável da planilha do cliente.

## 3. Decisões tomadas (brainstorm)

- **Base:** corrigir a metodologia (geração líquida + saldo inicial + ordem de expiração) E detalhar.
- **Camadas:** as quatro — transparência da geração líquida; drill-down por usina (ledger FIFO);
  faturamento completo (4 termos); qualidade de dados & saldos iniciais.
- **Cobertura/formato:** todas as 67 usinas num único HTML com collapse/expand; as ~28 divergentes
  abertas por padrão, as demais recolhidas (auditoria sob demanda).
- **Abordagem:** A — o relatório dirige o motor de domínio (`CalculadoraGeracaoLinear`), não reimplementa
  o cálculo.

## 4. Arquitetura (Abordagem A)

```
Staging (PDO :5440, cópia de prod, somente leitura)
  │
  ▼
CarregadorStaging          ── carrega usinas, geração real, consumo (dedup), crédito ANTES, saldos
  │                            de reserva; monta EntradaCalculoMes por (usina, mês)
  ▼
ReconstrutorLedger (NOVO, Domain) ── percorre a timeline mês a mês por usina:
  │   • injeta lotes SALDO_INICIAL (21 migradas)
  │   • chama CalculadoraGeracaoLinear.calcular() (motor único)
  │   • acumula guardado como novo lote; subtrai consumos FIFO; aplica expiração
  │   • emite lançamentos de ledger (SALDO_INICIAL / CREDITO / CONSUMO / EXPIRACAO)
  ▼
ColetorResultados          ── junta DEPOIS (motor) com ANTES (geracao_faturamento_pdf / creditos_distribuidos),
  │                            classifica divergências, agrega por tipo e por usina
  ▼
RenderizadorHtml           ── gera relatorio.html (4 camadas, 67 usinas, collapse) + CSV
```

O runner roda contra **staging** via `require vendor/autoload.php` (o Domain é livre de framework — não
precisa subir o Laravel). Não escreve em produção nem no staging.

### Unidades e responsabilidades

| Unidade | O que faz | Depende de |
|---|---|---|
| `CarregadorStaging` | Lê staging por PDO; deduplica consumo por `(usina, ano)` pelo registro mais recente; calcula geração líquida; monta `EntradaCalculoMes`. | PDO, VOs do Domain |
| `ReconstrutorLedger` (Domain) | Orquestra o loop mensal: monta lotes (incl. `SALDO_INICIAL`), chama a Calculadora, atualiza a reserva, emite o ledger. Função do redesenho, reusável fora do relatório. | `CalculadoraGeracaoLinear`, `LoteReserva`, `Competencia`, `Kwh` |
| `ColetorResultados` | Casa DEPOIS×ANTES, classifica tipo de erro, agrega. | resultados do reconstrutor; dados ANTES |
| `RenderizadorHtml` | Monta o HTML/CSV finais. | resultados agregados |

`ReconstrutorLedger` é a única peça nova de domínio. A Calculadora, `MotorFifo` e `ServicoExpiracao` já existem
e são reusados como estão.

## 5. Reconstrução corrigida (regras)

- **Geração líquida (§9):** `liquida = max(bruta − max(consumo − desconto_rede, 0), 0)`, com desconto por
  `usina.rede`: Trifásico 100 / Bifásico 50 / Monofásico 30 kWh.
- **Dedup de consumo:** `dados_consumo_usina` tem 108 pares duplicados por `(usina, ano)`; usar o registro de
  `updated_at` mais recente. (As tabelas de geração e crédito estão limpas.)
- **Saldo inicial (§12):** 21 usinas com déficit histórico > excedente recebem um lote `SALDO_INICIAL` com o
  total da reserva do ano mais antigo (decisão do cliente). As 45 demais reconstroem só da geração.
- **Expiração (§7):** consumo FIFO primeiro; expira o que sobrou (180 dias da origem). Delegado ao
  `ServicoExpiracao`. O crédito expirado vira receita uma vez (não soma ao termo Crédito).
- **Crédito (§6):** limitado ao faltante do mês E ao saldo real da reserva, FIFO cross-ano (mais antigo primeiro).

## 6. Estrutura do relatório

1. **Cards de topo:** impacto financeiro consolidado — creditado a mais / a menos, e impacto no valor final
   faturado (Antes×Depois somando os 4 termos).
2. **Geração líquida (transparência):** por usina/mês — bruta, consumo (final, deduplicado), desconto de rede
   (com o tipo de conexão), líquida resultante. Mostra de onde sai cada número antes do crédito.
3. **Faturamento completo — 4 termos Antes×Depois:** Fixo + Variável + Crédito − CUO por competência, com a
   diferença por termo e o impacto no valor final. ANTES de `geracao_faturamento_pdf`; DEPOIS do motor.
4. **Drill-down por usina (ledger FIFO):** timeline mês a mês — guardou / consumiu (e de qual mês-origem via
   FIFO) / expirou; saldo da reserva a cada passo. Collapse/expand: 28 divergentes abertas, demais recolhidas.
5. **Qualidade de dados & saldos iniciais:** 108 consumos duplicados, 21 saldos iniciais migrados, eventos de
   expiração, e casos que precisam de investigação manual (ex.: divergência inexplicada após correção).

Saídas: `relatorio.html` (autocontido) e `relatorio-antes-depois.csv` (linha por usina×mês com todos os termos).
Unidades sempre rotuladas: energia em kWh, dinheiro em R$.

## 7. Validação (oráculo golden)

O relatório deve reproduzir os casos golden; se não reproduzir, o motor ou a carga estão errados:

| Caso | UC | Período | Esperado |
|---|---|---|---|
| Eder Alcione Stalter | 562606800 | Mai/2026 | Total R$ 5.700,65 (Fixo 3.894,36 + Variável 1.129,14 + Crédito 1.561,11 − CUO 883,96) |
| Colina Eco Solar | 3085733401 | Fev/2026 | Crédito R$ 0,00 (geração = média, sem déficit) |
| Luci Vilce Penkaç | 19771547 | Set/2025 | Total R$ 4.801,50 (Fixo 3.342,18 + Variável 2.154,45 − CUO 695,13) |

## 8. Ressalvas conhecidas (registrar no relatório, não bloqueiam)

- **Variável do Eder:** a planilha do cliente (R$ 1.129,14) exige consumo=108 kWh, que não existe no sistema
  (tem 0 ou 134). A fórmula `(liquida − menor) × tarifa` fica parametrizada; o valor de entrada depende de
  reconciliação com o cliente. O relatório mostra o resultado com o consumo final do banco e sinaliza a usina.
- **Base do Fio B no CUO:** §5 usa a geração como base; resta confirmar bruta vs. líquida (diferença ~8 kWh
  absorvida pelo `faturaEnergia` manual). Seguir a regra documentada (geração) e sinalizar.
- **`faturaEnergia` e `adicional_cuo`:** inputs manuais; o relatório usa o que está no banco e marca quando ausente.

## 9. Riscos

- **Saldo inicial impreciso:** o total da reserva do ano mais antigo pode estar corrompido para algumas das 21
  → mitigar listando-as na seção de qualidade de dados para conferência caso a caso.
- **Tamanho do HTML:** 67 usinas com timeline podem gerar página grande → collapse por usina e CSV como anexo
  para análise pesada.
- **Acoplamento ao staging:** o runner depende do container `energia_staging` (:5440); documentar no cabeçalho
  do script como subir/restaurar.
