# Contexto da investigação: falha de cálculo

## >>> REGRAS DE NEGÓCIO CONFIRMADAS PELO CLIENTE <<<
- ORDEM DE RESGATE DE CRÉDITO: **FIFO — o crédito mais antigo é usado primeiro** (padrão ANEEL/SCEE).
  Atravessa anos: o saldo mais antigo (independente do ano) deve ser consumido antes do mais novo,
  pois é o mais próximo do vencimento (~180 dias). Hoje o código (CalculoGeracaoService.php:129) só
  varre os meses do ano corrente em ordem jan->dez, sem cruzar anos nem ordenar por vencimento real.
- FÓRMULA OFICIAL: Valor Final = Valor Fixo + Valor Variável(injetado) + Crédito − CUO (4 termos).
- (PENDENTE) Crédito expirado: o que fazer quando energia guardada vence sem uso — vira receita,
  some, ou aparece como crédito? Hoje é contado 2x (linhas 109 e 143) = bug.
- MODELO DE RESERVA ESCOLHIDO: tabela de LANÇAMENTOS (ledger). Guardado original imutável;
  cada resgate vira um lançamento (saída) referenciando o mês-origem. Permite FIFO real
  atravessando anos e auditoria completa. Substitui as 12 colunas mensais descontadas destrutivamente.
- CRÉDITO EXPIRADO: quando energia guardada vence (>prazo sem uso), vira RECEITA EM DINHEIRO no mês
  do vencimento (kWh*tarifa entra no faturamento). MAS só expira o que SOBROU após o FIFO consumir tudo
  que deveria — garantir que o consumo correto aconteça antes de qualquer expiração.
- EXIGÊNCIAS DO REDESENHO (cliente): (1) certificar que tudo foi consumido como deveria;
  (2) ZERO redundância e ZERO fórmula duplicada — cálculo ÚNICO; (3) SOLID/DRY/CLEAN CODE;
  (4) auditoria definitiva (ledger); (5) RELATÓRIO COMPLETO de tudo que está errado após
  consolidar e unificar os cálculos.
- ALERTA: valor de Maio existe em 4 versões: banco faturamento=6961,71 / tela=6862,30 / PDF=6009,22 / correto=5700,65.

## >>> REQUISITOS ADICIONAIS DO CLIENTE (refinamento) <<<
- PADRONIZAR UNIDADES: sempre kWh para energia, R$ para dinheiro — consistente em backend, frontend e PDF.
  Hoje no front há formatadores divergentes: formatMoeda (sem R$, toFixed) vs formatCurrency (locale pt-BR) vs toFixed solto. Unificar.
- EXIBIR TUDO necessário para AUDITORIA FÁCIL: cada número rastreável até a origem (ledger). Nada escondido.
- COBERTURA TOTAL: backend + frontend + persistência para auditoria.
- SOLID, DRY, CLEAN CODE.
- DOCUMENTO FORMAL POSTERIOR: especificação que formaliza e explica as regras de cálculo (deve viver no repo, ex: docs/calculo/REGRAS_DE_CALCULO.md).

## >>> SALDOS INICIAIS MIGRADOS (investigação caso a caso) <<<
Das 66 usinas com geração: 45 têm geração que explica a reserva; 21 têm déficit > excedente (precisaram de SALDO INICIAL migrado).
DECISÃO: investigar caso a caso (cliente). Para o backfill do ledger, essas 21 precisam de lançamento de abertura
(SALDO_INICIAL) com a energia que tinham ao entrar no sistema, senão o FIFO não tem de onde consumir.
Top: Edo Eloi Weber (UC 113906836, ~29961 kWh), Daniela Guerra (58473979, ~21233), Eliane Felczak (59139624, ~18900),
Rodrigo Novacoski (40161767, ~8843), Roney Schwecerski (59150280, ~7044), Solar Jungblut (59088586, ~6935), etc.
Muitas com excedente=0 (nunca geraram acima da média no histórico lançado) -> saldo veio de migração/lançamento anterior ao sistema.
Plano consolidado completo salvo em: api-laravel/storage/PLANO_REDESENHO.md (7 fases, 13 lacunas L1-L13).

## >>> BRIEFING DE LACUNAS — DECISÕES DO CLIENTE (consolidado) <<<
- menor_geracao = MÍNIMO das 12 gerações projetadas (Eder: jun=7636). media = média das 12 (12911). CONFIRMADO nos dados.
- Valor Fixo = menor_geracao × tarifa (Eder: 7636×0,51 = 3894,36, bate com planilha). CONFIRMADO.
- faturaEnergia (parte do CUO): INPUT MANUAL do operador (campo "Fatura de Energia da Usina" em CalculoGeracao.vue:17).
  Integração CELESC existe mas só anexa PDF, não preenche. DECISÃO: manter input manual. (Eder maio: ~98,77.)
- CUO = faturaEnergia + (consumo × fio_b × percentual_lei/100) + adicional_cuo. (Eder: 98,77 + 9858×0,13275×0,60... rever base do fio_b: usa geração ou consumo?)
- Desconto de rede CONFIRMADO correto: Trifásico 100 / Bifásico 50 / Monofásico 30 kWh. Mover regra ao backend.
- Saldo inicial das 21 usinas migradas: DECISÃO = usar o 'total' atual da reserva do ano mais antigo como lançamento SALDO_INICIAL no ledger.

## >>> ITEM ABERTO QUE PRECISA DO CLIENTE (não bloqueia Fase 1) <<<
- VARIÁVEL EXATO: cliente quer que o Valor Variável bata com a planilha dele (Eder maio = 1.129,14).
  O sistema, com a fórmula documentada (geracao−menor)×tarifa = (9858−7636)×0,51 = 1.133,22.
  Para dar 1.129,14 precisaria geracao=9850 (8 kWh a menos). Testado geração líquida (consumo maio=134, desconto trif. 100
  -> líquida 9824 -> 1.115,88): NÃO bate. CONCLUSÃO: a planilha do cliente usa metodologia própria não derivável dos dados.
  AÇÃO: obter do cliente a metodologia/planilha detalhada do Variável (ou mais casos) para reconciliar. A FÓRMULA
  (geracao_liquida − menor)×tarifa fica parametrizada no núcleo; só o valor de entrada precisa reconciliação.
- OBS: há linhas DUPLICADAS em dados_consumo_usina do Eder (maio aparece 2× com 134) — qualidade de dados a investigar.

## >>> RESOLUÇÃO DO VARIÁVEL + DUPLICADO (descoberta final) <<<
A planilha do cliente reconcilia 100% com geração=9850 (Fixo 3894,36 + Variável 1129,14 + Crédito 1561,11 − CUO 883,96 = 5700,65).
Variável E Crédito usam o MESMO 9850 -> a divergência é no VALOR DA GERAÇÃO (líquida), não na fórmula. FÓRMULA CONFIRMADA:
  Fixo = menor × tarifa ; Variável = (geracao_liquida − menor) × tarifa ; Crédito = (media − geracao_liquida) × tarifa [limitado à reserva FIFO]
  geracao_liquida = geracao_bruta − max(consumo − desconto_rede, 0)

CAUSA RAIZ dos 8 kWh (9858 vs 9850) e do "duplicado/revert":
- Consumo de maio nas linhas dados_consumo_usina do Eder por data de criação: fev/mar/abr/mai=0, jun09=134, jun11=134.
- Quando MAIO foi lançado (04/mai) o consumo ainda era 0 -> sistema calculou líquida=9858 -> Variável=1133,22 (= PDF do sistema).
- Consumo preenchido depois (134) NÃO disparou recálculo -> faturamento "congelado" com dado incompleto. BUG.
- consumo=0 -> 1133,22 (sistema) ; consumo=108 -> 9850 -> 1129,14 (planilha cliente) ; consumo=134 -> 9824 -> 1115,88.
- Cliente usou consumo=108 (não está no sistema: sistema tem 0 ou 134). Origem do 108 a confirmar com cliente.
- DUPLICADO: cada lançamento/revert cria NOVA linha dados_consumo_usina p/ mesmo (usina,ano) -> 108 PARES duplicados no sistema.
  creditos_distribuidos_usina e dados_geracao_real_usina estão LIMPOS (0 duplicatas). Problema só no consumo.

IMPLICAÇÕES PARA O REDESENHO:
- Cálculo DEVE usar geração líquida com o consumo FINAL; recalcular se o consumo mudar (ou exigir consumo antes de calcular).
- dados_consumo_usina precisa ser único por (usina,ano) — deduplicar e corrigir o upsert.
- Geração líquida = responsabilidade do BACKEND (hoje no front, getDescontoRede).

## >>> 3 PROBLEMAS CONFIRMADOS PELO CLIENTE (verificados no código) <<<
- PROBLEMA 1: não puxa o guardado mais antigo de OUTRO ANO.
  Local: CalculoGeracaoService.php:118-120 e :129. $reservaAnterior = só reserva->total do ano atual;
  o loop de desconto percorre só $reserva (ano corrente). $reservaAnoAnterior só é usada para EXPIRAR
  (linhas 95-107), nunca para COMPENSAR. → fere o FIFO cross-ano.
- PROBLEMA 2: ao usar crédito, zera/decrementa a coluna mensal de origem (CalculoGeracaoService.php:135),
  apagando o histórico do quanto foi guardado. Guardado e saldo estão na MESMA coluna. → prejudica auditoria.
  SOLUÇÃO: ledger (ver acima).
- PROBLEMA 3: credita kWh a mais. DUAS causas: (3a) crédito expirado contado 2x — linha 143 soma
  $creditoExpirado que já entrou em valorPago na linha 109 (intermitente: só em meses com expiração);
  (3b) frontend creditadoTabela() em CalculoGeracao.vue:482-490 faz (media-valor)*kwh SEM checar saldo
  da reserva → credita energia inexistente na exibição.

## >>> DADOS REAIS DO BANCO (Eder UC 562606800) — confirmam os 3 problemas <<<
- comercializacao: valor_kwh=0,51 · valor_fixo=3894 · fio_b=0,13275 · percentual_lei=60,00 · valor_final_media=5813,34
- dados_geracao (projetada): media=12911 · menor_geracao=7636 · (jan17315 fev15888 mar14014 abr11441 mai8923 jun7636 jul8476 ago11049 set11664 out14238 nov16755 dez17538)
- geracao_real 2026: jan15089 fev13951 mar14672 abr11592 mai9858 (resto vazio)
- geracao_real 2025: nov14354 dez14103
- creditos_distribuidos 2026: abr=672,69 · maio=1866,60 (resto 0)
- valor_acumulado_reserva 2026: marco=1443 kWh · total=0
- valor_acumulado_reserva 2025: dezembro=1192 kWh · total=1192 (esse é o saldo MAIS ANTIGO, deveria ser usado 1º via FIFO)
- faturamento_usina 2026: jan5281,41 fev5378,92 mar5321,50 abr5564,60 mai6961,71 (tela mostra 6862,30/6009,22 — ainda divergem; banco tem 6961,71!)

VALIDAÇÃO MAIO/26:
- Injetado = (9858-7636)*0,51 = 1133,22 (sistema bate; planilha quer 1129,14 -> menor_geracao deveria ser ~7644)
- Faltante Maio = 12911-9858 = 3053 kWh ; crédito correto 1561,11 = 3061 kWh (≈faltante)
- Crédito sistema 1866,60 = 3660 kWh = 599 kWh A MAIS que o faltante
- Reserva real disponível = 1443(2026 mar) + 1192(2025 dez) = 2635 kWh -> sistema creditou 3660 > 2635 (creditou MAIS que o saldo!)
- PROVA P1: os 1192 kWh de 2025 (mais antigos) não foram usados via FIFO cross-ano.
- PROVA P3: creditou o faltante inteiro (3053+) sem limitar à reserva; bug creditadoTabela() (media-valor)*kwh sem checar saldo.
- CUO: 9858*0,13275*0,60 = 785,19, mas sistema=884,60 -> há faturaEnergia (~99) informado, investigar.

## >>> AUDITORIA RECONSTRUÍDA (Eder) — a reserva do banco está CORROMPIDA <<<
Reconstrução a partir da geração real vs media=12911 (FIFO cross-ano, mais antigo primeiro):
- nov/2025 guardou 1443 | dez/2025 guardou 1192 | jan/2026 guardou 2178 | fev/2026 guardou 1040 | mar/2026 guardou 1761
- abr/2026 faltou 1319 -> consumiu 1319 de nov/2025
- mai/2026 faltou 3053 -> consumiu 3053 (124 nov/2025 restante + 1192 dez/2025 + 1737 jan/2026)
- SALDO REAL remanescente após maio = jan441 + fev1040 + mar1761 = 3242 kWh
- BANCO mostra 2635 kWh (1192 dez/2025 + 1443 mar/2026) nos meses ERRADOS -> faltam 607 kWh e atribuição trocada.
- CRÉDITO CORRETO MAIO = faltante 3053 * 0,51 = 1557,03 ≈ planilha 1561,11 (dif = arredondamento menor_geracao).
- SISTEMA creditou 3660 kWh (1866,60) = 607 kWh A MAIS. Os MESMOS 607 kWh que sumiram da auditoria (P2)
  são os 607 creditados a mais (P3) — é o MESMO bug: desconto destrutivo sem FIFO correto perde o controle do saldo.
- CONCLUSÃO: backfill do ledger NÃO pode partir dos saldos atuais (corrompidos). Tem que RECONSTRUIR da geração
  real mês a mês (DadosGeracaoReal) vs media, aplicando FIFO cross-ano, para popular o ledger corretamente.
- CRÉDITO = limitado ao FALTANTE do mês (media - geracao), consumindo a reserva FIFO; quando a reserva real é
  suficiente (como aqui), faltante e consumo coincidem. Reserva real é reconstruída, não lida do saldo atual.

## >>> RECONSTRUÇÃO EXECUTADA (staging, 67 usinas) <<<
- Script: api-laravel/storage/reconstrucao/reconstruir.php (PHP+PDO, roda em container php-pgsql contra staging :5440).
- Staging: container docker `energia_staging` (postgres:16, porta 5440, senha staging), restaurado do dump.
- Eder validado: crédito ANTES 1866,60 -> DEPOIS 1557,03 (planilha 1561,11; dif 4,08 = erro menor_geracao). Metodologia OK.
- RESULTADO GERAL: 60 linhas (mês×usina) com divergência; diferença líquida total = -R$ 66.835,82 (sistema creditou a MAIS).
- CASO COLINA (UC 3085733401) decifrado: ago/2025 guardou 14300, set consumiu 2500, sobrou 11800 na reserva.
  Em fev/2026 geração=16740=media -> faltante=0 -> crédito correto=0. MAS sistema creditou 7080=11800 kWh
  (= saldo INTEIRO da reserva) sem haver déficit. BUG: sistema despeja a reserva como crédito sem déficit.
  Reconstrução deu 0 (correto). Confirmado bug real, não falso positivo.
- PENDENTE validar: separar definitivamente bug real de limitação da reconstrução (saldos migrados sem geração lançada).
  Query inicial não achou nenhuma usina com saldo no ano mais antigo sem geração — investigar se há saldos iniciais reais.

## >>> FASE 3 BACKFILL — DRY-RUN E DECISÃO PENDENTE (expiração retroativa) <<<
Comando ledger:reconstruir criado. Dry-run no staging: 67 usinas, 30 batem / 37 divergem / 44 com SALDO_INICIAL.
Correções técnicas aplicadas: saldoLegado soma todos os anos (era só 1); unique no idempotency_key; truncate+gravação
em transação; filtro de competência futura (Colina tinha jun/2027); teste de expiração movido p/ 2025. 43 testes verdes.

DESCOBERTA CRÍTICA — EXPIRAÇÃO RETROATIVA:
A reconstrução aplica expiração de 180d retroativamente. Isso ZERA crédito antigo não usado.
Ex: Colina (UC 3085733401) tem 10700 kWh parados desde ago/2025 -> ledger reconstruído = 0 (tudo expirou).
Legado mantém 10700. Muitas das 37 divergências vêm disso. PERGUNTA AO CLIENTE (peso financeiro):
- (a) aplicar expiração retroativa (crédito antigo >180d é perdido), ou
- (b) NÃO expirar retroativo (preservar reserva atual; começar o relógio de 180d a partir do go-live), ou
- (c) expirar mas pagar receita ao cliente pelo crédito expirado (impacto financeiro grande).
As divergências (ledger vs legado) são o ANTES×DEPOIS esperado (legado corrompido) — não são erro.
Reconciliação NÃO precisa "fechar"; ela QUANTIFICA a correção.

## >>> STATUS ACESSO BANCO <<<
- ACESSO RESOLVIDO: do bastion, chave /home/ubuntu/KeyliderEnergy.pem entra no app (ubuntu@10.0.2.2, host App-LiderEnergy).
  Banco via `sudo -u postgres psql -d energia_assinatura` (peer auth, sem senha). Release real NÃO é /var/www/energia-assinatura/repo (esse é clone sem vendor); usar psql direto.
- Banco Postgres é LOCAL no servidor de app 10.0.2.2 (NÃO é o RDS do terraform). Porta 5432 aberta e
  alcançável a partir do bastion (5.161.237.241). psql 16 já instalado no bastion.
- Credenciais dev locais (arco/arco_dev_password) NÃO funcionam em prod (auth failed).
- Não há chave SSH para entrar no 10.0.2.2; senha real do banco está no .env do app (inacessível por SSH).
- Cliente TEM acesso ao painel do provedor (IPs sugerem Hetzner). Caminho: pelo painel, adicionar chave
  SSH ao 10.0.2.2 ou usar console web, entrar, e rodar `php artisan tinker` (sem expor senha) OU ler DB_PASSWORD.

## >>> DADOS DEFINITIVOS — caso Eder Alcione Stalter, UC 562606800, Maio/26 <<<
## (este é o caso âncora: temos o valor correto ao lado da saída errada)

FÓRMULA CORRETA CONFIRMADA: Valor Final = Valor Fixo + Valor Variável + Crédito − CUO
(São 4 termos. NÃO é dupla contagem — injetado/variável e crédito são coisas diferentes.)

### Valores CORRETOS (planilha enviada pelo cliente, Maio/26):
- Valor Fixo:    R$ 3.894,36
- Valor Variável: R$ 1.129,14
- CUO:           -R$   883,96
- Crédito:        R$ 1.561,11
- **TOTAL CORRETO: R$ 5.700,65**

### Valores ERRADOS gerados pelo sistema (PDF, Maio/26):
- Valor Fixo:  R$ 3.894,00  (diff -0,36 vs correto — centavos)
- Injetado:    R$ 1.133,22  (diff +4,08 vs Variável correto)
- Creditado:   R$ 1.866,60  (diff +305,49 vs Crédito correto)  <<< FALHA PRINCIPAL
- CUO:         R$   884,60  (diff +0,64 vs correto — centavos)
- **TOTAL ERRADO: R$ 6.009,22**  (inflado em R$ 308,57)

### CONCLUSÃO DO CONFRONTO:
A FALHA PRINCIPAL está no termo CREDITADO: sistema gera 1.866,60, correto é 1.561,11 (erro de R$ 305,49 = quase toda a diferença final).
O cliente NÃO sabe a fórmula exata do crédito correto — precisamos descobrir no código por que sai 1.866,60.
Diferenças menores (Variável +4,08; Fixo -0,36; CUO -0,64) TAMBÉM precisam bater exato (cliente exige precisão total).

### Dados de geração do Eder (da tabela do PDF, valorKwh = R$ 0,51, fixo informado ≈ 3894):
- Jan/26: geração 15089, injetado 2690,25, creditado 0,00,    cuo 1303,20, final 5281,05
- Fev/26: geração 13951, injetado 2690,25, creditado 0,00,    cuo 1205,69, final 5378,56
- Mar/26: geração 14672, injetado 2690,25, creditado 0,00,    cuo 1263,11, final 5321,14
- Abr/26: geração 11592, injetado 2017,56, creditado 672,69,  cuo 1020,01, final 5564,24
- Mai/26: geração  9858, injetado 1133,22, creditado 1866,60, cuo  884,60, final 6009,22
- Tela do sistema mostra Abril "Creditado R$ 672,69" e Maio "Creditado R$ 1.866,60" / "Valor Pago R$ 6.862,30" (ATENÇÃO: tela mostra 6.862,30, PDF mostra 6.009,22 — TAMBÉM divergem entre si)
- Nas 3 telas: Jan-Mar creditado 0, Abril 1443 kWh guardado / R$672,69 creditado, Maio R$1.866,60 creditado

OBS: injetado parece TETO em 2690,25 nos meses de alta geração (>=média): 2690,25/0,51 = 5275 kWh = (media - menorGeracao). Quando geração cai, injetado cai proporcionalmente.

---


## Dados de referência CORRETOS (CSV — Luci Vilce Penkaç, UC 19771547, set/2025)

Demonstrativo de Pagamento esperado:
- Valor Fixo:    R$ 3.342,18
- Valor Variável: R$ 2.154,45
- CUO (Custo Operacional): -R$ 695,13
- **Total: R$ 4.801,50**

Fórmula implícita do CSV: **Total = Valor Fixo + Valor Variável − CUO**
(3342,18 + 2154,45 − 695,13 = 4801,50)

Geração Realizada (Compensável):
- Julho: 9671, Agosto: 11981, Setembro: 10993
Geração Projetada (média esperada): 10371 (todos os meses)
Observações: kWh CELESC = R$ 0,53; TUSD FIO B = R$ 0,132750

Demonstrativo de Créditos:
- ago/25: crédito acumulado 1.610, vencimento fev/26
- set/25: crédito acumulado 622, vencimento mar/25 (provável erro de digitação no CSV)

## Saídas do sistema atual (PDFs — possivelmente ERRADAS)

### PDF 1 — Rodrigo Novacoski, UC 40161767, valor kWh R$ 0,53, abril/26
Tabela "DADOS DE GERAÇÃO E FATURAMENTO": Mês | Geração | Valor Fixo | Injetado | Creditado | CUO | Valor Final
- Jan/26: 8078,00 | 2.085,00 | 1.469,16 | 0,00 | 677,10 | 2.877,06
- Fev/26: 6902,00 | 2.085,00 | 1.469,16 | 0,00 | 583,43 | 2.970,73
- Mar/26: 5998,00 | 2.085,00 | 1.093,92 | 375,24 | 514,77 | 3.039,39
- Abr/26: 4682,00 | 2.085,00 |   396,44 | 455,80 | 411,93 | 2.525,31
Tabela segundo formato: Mês | Geração | Valor Guardado | Creditado | Valor Pago
- Janeiro 8078 | 0 | R$0 | R$2.876,15  (NOTE: difere de 2.877,06 acima)
- Abril 4682 | 0 | R$455,80 | R$3.142,25 (NOTE: difere de 2.525,31)

### PDF 2 — Eder Alcione Stalter, UC 562606800, valor kWh R$ 0,51, maio/26
Tabela faturamento: Mês | Geração | Valor Fixo | Injetado | Creditado | CUO | Valor Final
- Jan/26: 15089,00 | 3.894,00 | 2.690,25 | 0,00 | 1.303,20 | 5.281,05
- Fev/26: 13951,00 | 3.894,00 | 2.690,25 | 0,00 | 1.205,69 | 5.378,56
- Mar/26: 14672,00 | 3.894,00 | 2.690,25 | 0,00 | 1.263,11 | 5.321,14
- Abr/26: 11592,00 | 3.894,00 | 2.017,56 | 672,69 | 1.020,01 | 5.564,24
- Mai/26: 9858,00  | 3.894,00 | 1.133,22 | 1.866,60 | 884,60 | 6.009,22
Valor a Receber (cabeçalho): R$ 6.009,22

## Código-chave
- api-laravel/app/Services/CalculoGeracaoService.php — motor de cálculo (reserva/crédito/expiração/faturamento), persiste no banco
- api-laravel/app/Http/Controllers/PDFController.php (linhas ~217-234) — RECALCULA injetado/cuo/valor_final na geração do PDF
- api-laravel/resources/views/usina.blade.php — template do PDF
