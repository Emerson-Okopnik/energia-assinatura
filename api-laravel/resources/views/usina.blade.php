<!DOCTYPE html>
<html lang="pt-br">
<head>
  <meta charset="UTF-8">
  <title>Demonstrativo de geração - {{ $usina->cliente->nome }}</title>
  <style>
    /* ============================================================
       Tokens — design-system/colors_and_type.css (fonte única; DRY)
    ============================================================ */
    :root {
      --color-primary: #F39325;
      --color-primary-deep: #D97613;
      --color-primary-warm: #F9B566;
      --color-primary-soft: #FDE6CB;
      --color-accent-leaf: #5FB53A;
      --color-accent-leaf-deep: #3F8F22;
      --color-ink: #3D3D3D;
      --color-graphite: #5C5C5C;
      --color-slate: #7A7A7A;
      --color-smoke: #B0B0B0;
      --color-mist: #E5E0D9;
      --color-linen: #FAF6F1;
      --color-paper: #FFFFFF;
      --grad-sun: linear-gradient(135deg, var(--color-primary-warm) 0%, var(--color-primary) 45%, var(--color-primary-deep) 100%);
      --radius-sm: 6px;
      --radius-md: 12px;
      --radius-lg: 20px;
      --radius-xl: 28px;
      --radius-pill: 999px;
      --space-1: 4px; --space-2: 8px; --space-3: 12px; --space-4: 16px;
      --shadow-xs: 0 1px 2px rgba(61,61,61,0.06);
      --shadow-sm: 0 2px 6px rgba(61,61,61,0.08);
      --shadow-md: 0 8px 24px rgba(61,61,61,0.10);
      --font-display: 'Nunito', system-ui, sans-serif;
      --font-body: 'Nunito', system-ui, sans-serif;
      --font-mono: 'JetBrains Mono', ui-monospace, monospace;
    }

    /* Fontes embutidas (zero rede) — geradas pelo PDFController. */
    {!! $fontFaceCss !!}

    html, body { margin: 0; padding: 0; }

    body {
      font-family: var(--font-body);
      background: var(--color-linen);
      color: var(--color-ink);
      font-size: 8.5pt;
      line-height: 1.35;
    }

    .page { padding: 12px 18px 56px; } /* reserva o rodapé fixo */

    .num { font-family: var(--font-mono); }

    /* ---------- Cards (DS: branco, radius-lg, shadow-sm, sem borda) ---------- */
    .card {
      background: var(--color-paper);
      border-radius: var(--radius-lg);
      box-shadow: var(--shadow-sm);
      padding: var(--space-3) var(--space-4);
    }

    /* ---------- Hierarquia DS: eyebrow (kicker) + título display ---------- */
    .eyebrow {
      font-family: var(--font-body);
      font-weight: 700;
      font-size: 7pt;
      letter-spacing: 0.14em;
      text-transform: uppercase;
      color: var(--color-primary-deep);
      margin: 0;
    }
    .eyebrow--leaf { color: var(--color-accent-leaf-deep); }
    .section-title {
      font-family: var(--font-display);
      font-weight: 800;
      font-size: 12pt;
      line-height: 1.15;
      letter-spacing: -0.01em;
      color: var(--color-ink);
      margin: 2px 0 0;
    }
    .section-head { margin: var(--space-3) 0 var(--space-1); }
    .card-head { margin: 0 0 var(--space-2); }
    .card-head .section-title { font-size: 10.5pt; }

    /* ---------- Cabeçalho ---------- */
    .header {
      display: flex;
      align-items: center;
      gap: var(--space-3);
    }
    .header .logo img { height: 38px; display: block; }
    .header .divider { width: 1px; align-self: stretch; background: var(--color-mist); }
    .company-info h2 {
      margin: 0 0 2px;
      font-family: var(--font-display);
      font-weight: 800;
      font-size: 9pt;
      letter-spacing: -0.01em;
    }
    .company-info p { margin: 1px 0; font-size: 7pt; color: var(--color-graphite); }
    .details { font-size: 7.5pt; }
    .details p { margin: 2px 0; }
    .details .icon { width: 12px; height: 12px; vertical-align: -2px; margin-right: 3px; }
    .details strong { color: var(--color-ink); }

    .meta-row {
      display: flex;
      justify-content: space-between;
      align-items: center;
      flex-wrap: wrap;
      gap: var(--space-2);
      margin: var(--space-2) 0 var(--space-3);
      font-size: 7pt;
      color: var(--color-graphite);
    }
    .meta-row .icon { width: 11px; height: 11px; vertical-align: -2px; margin-right: 2px; }
    .contact-item { margin-left: var(--space-3); }

    /* ---------- Hero do valor a receber (único uso sancionado do grad-sun) ---------- */
    .hero {
      position: relative;
      overflow: hidden;
      background: var(--grad-sun);
      color: var(--color-paper);
      border-radius: var(--radius-xl);
      box-shadow: var(--shadow-md);
      padding: var(--space-3) var(--space-4);
    }
    .hero .hero-circle {
      position: absolute;
      right: -36px; top: -48px;
      width: 150px; height: 150px;
      border-radius: 50%;
      background: rgba(255,255,255,0.15);
    }
    .hero .hero-body { position: relative; }
    .hero .hero-eyebrow {
      font-family: var(--font-body);
      font-weight: 700;
      font-size: 7pt;
      letter-spacing: 0.14em;
      text-transform: uppercase;
      color: rgba(255,255,255,0.85);
      margin: 0;
    }
    .hero .hero-line {
      display: flex;
      align-items: baseline;
      gap: var(--space-2);
      margin-top: var(--space-1);
    }
    .hero .hero-valor {
      font-family: var(--font-display);
      font-weight: 800;
      font-size: 26pt;
      line-height: 1;
      letter-spacing: -0.02em;
    }
    .hero .hero-pill {
      font-family: var(--font-body);
      font-weight: 700;
      font-size: 7.5pt;
      background: rgba(255,255,255,0.25);
      padding: 3px 10px;
      border-radius: var(--radius-pill);
    }

    /* ---------- Faixa de informações (primary-soft: contraste AA) ---------- */
    .highlight-bar {
      display: flex;
      justify-content: space-around;
      align-items: center;
      gap: var(--space-3);
      background: var(--color-primary-soft);
      color: var(--color-ink);
      border-radius: var(--radius-md);
      padding: var(--space-2) var(--space-4);
      font-size: 7.5pt;
      margin-top: var(--space-2);
    }
    .highlight-bar p { margin: 0; }
    .highlight-bar strong { color: var(--color-primary-deep); }

    /* ---------- Geração ---------- */
    .geracao-container { display: flex; gap: var(--space-3); align-items: stretch; }
    .grafico { flex: 1 1 62%; }
    .grafico canvas { width: 100%; max-width: 460px; height: auto; }
    .dados-geracao {
      flex: 1 1 38%;
      background: var(--color-linen);
      box-shadow: none;
      border: 1px solid var(--color-mist);
    }
    .dados-geracao .kwh-destaque {
      font-weight: 800;
      color: var(--color-primary-deep);
    }
    .dados-geracao > p { margin: 0 0 var(--space-2); color: var(--color-graphite); }
    .item-geracao {
      display: flex;
      align-items: center;
      gap: var(--space-3);
      margin: var(--space-2) 0 0;
      padding-top: var(--space-2);
      border-top: 1px solid var(--color-mist);
    }
    .item-geracao img { width: 30px; height: 30px; }
    .stat-line { display: flex; align-items: baseline; gap: var(--space-1); }
    .stat-num {
      font-family: var(--font-display);
      font-weight: 800;
      font-size: 13pt;
      line-height: 1.1;
      letter-spacing: -0.02em;
      color: var(--color-ink);
    }
    .stat-unit { font-family: var(--font-mono); font-size: 7.5pt; color: var(--color-graphite); }
    .stat-caption { font-size: 7.5pt; color: var(--color-graphite); margin-top: 1px; }

    /* ---------- Tabelas (DS BillsTable: header claro mono/slate) ---------- */
    .data-table {
      width: 100%;
      border-collapse: collapse;
      font-size: 7pt;
      text-align: center;
    }
    .data-table thead th {
      background: transparent;
      font-family: var(--font-mono);
      font-weight: 700;
      font-size: 6.5pt;
      text-transform: uppercase;
      letter-spacing: 0.1em;
      color: var(--color-slate);
      padding: 4px 4px 5px;
      border: none;
      border-bottom: 1px solid var(--color-mist);
    }
    .data-table tbody td {
      padding: 4px 4px 5px;
      border-bottom: 1px solid var(--color-mist);
      color: var(--color-ink);
    }
    .data-table tbody tr:last-child td { border-bottom: none; }
    .data-table .credito { color: var(--color-accent-leaf-deep); font-weight: 600; }
    .data-table .valor-final { font-weight: 700; color: var(--color-primary-deep); }

    /* ---------- Linha inferior ---------- */
    .linha-final { display: flex; gap: var(--space-3); margin-top: var(--space-3); align-items: flex-start; }
    .bloco-creditos { flex: 1.4; }
    .coluna-direita { flex: 1; display: flex; flex-direction: column; gap: var(--space-3); }

    .historico-valores { width: 100%; border-collapse: collapse; }
    .historico-valores td {
      padding: 5px 0;
      border-bottom: 1px solid var(--color-mist);
      font-size: 7.5pt;
      color: var(--color-graphite);
    }
    .historico-valores tr:last-child td { border-bottom: none; }
    .historico-valores td:last-child {
      text-align: right;
      font-family: var(--font-display);
      font-weight: 800;
      font-size: 9.5pt;
      letter-spacing: -0.01em;
      color: var(--color-ink);
      white-space: nowrap;
    }
    .historico-valores .total-destaque td:last-child { color: var(--color-primary-deep); }

    .badge-total {
      display: inline-block;
      background: var(--color-primary-soft);
      color: var(--color-primary-deep);
      font-weight: 700;
      font-size: 7pt;
      padding: 4px 12px;
      border-radius: var(--radius-pill);
      margin-top: var(--space-2);
    }

    .bloco-observacoes p { margin: var(--space-1) 0 0; font-size: 7.5pt; color: var(--color-graphite); }
    .bloco-observacoes strong { color: var(--color-ink); }

    /* ---------- Rodapé ---------- */
    .rodape {
      position: fixed;
      bottom: 0; left: 0; right: 0;
      background: var(--color-ink);
      color: var(--color-paper);
      font-size: 7.5pt;
      display: flex;
      justify-content: space-between;
      align-items: center;
      padding: var(--space-2) var(--space-4);
    }
    .rodape .icon { width: 14px; height: 14px; vertical-align: -3px; margin-right: 4px; }
    .rodape .icon-social { width: 16px; height: 16px; vertical-align: middle; margin-left: 6px; }
    .rodape a { color: var(--color-paper); }
  </style>
</head>
<body>
  <div class="page">

    <div class="card header">
      <div class="logo"><img src="{{ $logo }}" alt="Líder Energy"></div>
      <div class="divider"></div>
      <div class="company-info">
        <h2>CONSÓRCIO LÍDER ENERGY</h2>
        <p>CNPJ: <span class="num">58.750.788/0001-33</span></p>
        <p>R. Brunislau Blonkovski, 131</p>
        <p>Santa Terezinha/SC — <span class="num">89199-000</span></p>
      </div>
      <div class="divider"></div>
      <div class="details">
        <p><img src="{{ $iconeSol }}" alt="" class="icon"><strong>Produção:</strong> {{ $mesAnoSelecionado }}</p>
        <p><img src="{{ $iconeDinheiro }}" alt="" class="icon"><strong>Valor a receber:</strong> <span class="num">@reais($valorReceber)</span></p>
      </div>
      <div class="divider"></div>
      <div class="details">
        <p><strong>Usina:</strong></p>
        <p>{{ $usina->cliente->nome }}</p>
      </div>
    </div>

    <div class="meta-row">
      <span><img src="{{ $iconeRelogio }}" alt="" class="icon">Data de emissão: <strong class="num">{{ \Carbon\Carbon::now()->format('d/m/Y') }}</strong></span>
      <span>
        <span class="contact-item"><img src="{{ $iconeWeb }}" alt="" class="icon">www.consorcioliderenergy.com.br</span>
        <span class="contact-item"><img src="{{ $iconeWpp }}" alt="" class="icon"><span class="num">47 99661-4967</span></span>
        <span class="contact-item"><img src="{{ $iconeEmail }}" alt="" class="icon">contato@liderenergy.com.br</span>
      </span>
    </div>

    {{-- Hero do valor (DS SavingsHero — único uso do grad-sun) --}}
    <div class="hero">
      <div class="hero-circle"></div>
      <div class="hero-body">
        <div class="hero-eyebrow">Valor a receber</div>
        <div class="hero-line">
          <span class="hero-valor">@reais($valorReceber)</span>
          <span class="hero-pill">{{ $mesAnoSelecionado }}</span>
        </div>
      </div>
    </div>

    <div class="highlight-bar">
      <p><strong>UC:</strong> <span class="num">{{ $uc }}</span></p>
      <p><strong>Fonte de geração:</strong> UFV</p>
      <p><strong>Valor kWh:</strong> <span class="num">@tarifa($usina->comercializacao->valor_kwh)</span></p>
    </div>

    <div class="section-head">
      <div class="eyebrow">Geração</div>
      <div class="section-title">Demonstrativo de geração</div>
    </div>

    <div class="geracao-container">
      <div class="card grafico">
        <canvas id="graficoGeracao"></canvas>
      </div>
      <div class="card dados-geracao">
        <div class="card-head">
          <div class="eyebrow eyebrow--leaf">Impacto ambiental</div>
          <div class="section-title">Dados de geração de energia</div>
        </div>
        <p>Sua geração de energia foi de <span class="kwh-destaque num">@kwh($geracaoMes)</span>, isso é igual a:</p>
        <div class="item-geracao">
          <img src="{{ $iconeCo2 }}" alt="">
          <div>
            <div class="stat-line"><span class="stat-num">@numero($co2Evitado, 0)</span><span class="stat-unit">kg</span></div>
            <div class="stat-caption">de emissão de CO₂ evitada</div>
          </div>
        </div>
        <div class="item-geracao">
          <img src="{{ $iconeArvore }}" alt="">
          <div>
            <div class="stat-line"><span class="stat-num">@numero($arvores, 0)</span><span class="stat-unit">árvores</span></div>
            <div class="stat-caption">plantadas, em equivalência</div>
          </div>
        </div>
      </div>
    </div>

    <div class="section-head">
      <div class="eyebrow">Faturamento</div>
      <div class="section-title">Dados de geração e faturamento</div>
    </div>
    <div class="card">
      <table class="data-table">
        <thead>
          <tr>
            <th>Mês</th>
            <th>Geração (kWh)</th>
            <th>Valor Fixo (R$)</th>
            <th>Injetado (R$)</th>
            <th>Creditado (R$)</th>
            <th>CUO (R$)</th>
            <th>Valor Final (R$)</th>
          </tr>
        </thead>
        <tbody>
          @foreach($dadosMensais as $mes => $dados)
            <tr>
              <td>{{ $mes }}</td>
              <td class="num">@kwh($dados['geracao_kwh'] ?? 0)</td>
              <td class="num">@reais($dados['fixo'])</td>
              <td class="num">@reais($dados['injetado'])</td>
              <td class="num credito">@reais($dados['creditado'])</td>
              <td class="num">@reais($dados['cuo'])</td>
              <td class="num valor-final">@reais($dados['valor_final'])</td>
            </tr>
          @endforeach
        </tbody>
      </table>
    </div>

    <div class="linha-final">
      <div class="card bloco-creditos">
        <div class="card-head">
          <div class="eyebrow">Créditos</div>
          <div class="section-title">Demonstrativo de créditos</div>
        </div>
        <table class="data-table">
          <thead>
            <tr>
              <th>Mês</th>
              <th>Vencimento do crédito</th>
              <th>Guardado (kWh)</th>
              <th>Creditado (kWh)</th>
              <th>Meses resgatados</th>
              @if($temConvertidoReceita)
                <th>Convertido em receita (R$)</th>
              @endif
            </tr>
          </thead>
          <tbody>
            @foreach($dadosCreditos as $mes => $dados)
              <tr>
                <td>{{ $mes }}</td>
                <td class="num">{{ $dados['vencimento'] ?? '-' }}</td>
                <td class="num">@kwh($dados['guardado'])</td>
                <td class="num">@kwh($dados['creditado_kwh'] ?? 0)</td>
                <td class="num">{{ $dados['meses_utilizados'] ?? '-' }}</td>
                @if($temConvertidoReceita)
                  <td class="num credito">{{ $dados['convertido_receita'] > 0 ? \App\Support\Format::reais($dados['convertido_receita']) : '-' }}</td>
                @endif
              </tr>
            @endforeach
          </tbody>
        </table>
      </div>

      <div class="coluna-direita">
        <div class="card">
          <div class="card-head">
            <div class="eyebrow">Acumulados</div>
            <div class="section-title">Histórico de valores</div>
          </div>
          <table class="historico-valores">
            <tbody>
              <tr>
                <td>Crédito guardado acumulado (kWh)</td>
                <td>@kwh($totalGuardadoKwh)</td>
              </tr>
              <tr>
                <td>CUO acumulado (R$)</td>
                <td>@reais($totalCuo)</td>
              </tr>
              <tr class="total-destaque">
                <td>Valor a receber acumulado (R$)</td>
                <td>@reais($totalValorFinal)</td>
              </tr>
            </tbody>
          </table>
          <span class="badge-total">Período: {{ $mesAnoSelecionado }}</span>
        </div>

        @if($observacoes !== '')
          <div class="card bloco-observacoes">
            <strong>Observações:</strong>
            <p>{{ $observacoes }}</p>
          </div>
        @endif
      </div>
    </div>
  </div>

  <footer class="rodape">
    <span><img src="{{ $iconeLampada }}" alt="" class="icon">Pense bem antes de imprimir!</span>
    <span>
      Siga a Líder Energy nas redes sociais:
      <a href="https://www.linkedin.com/company/liderenergy"><img src="{{ $iconeLinkedin }}" alt="LinkedIn" class="icon-social"></a>
      <a href="https://www.instagram.com/liderenergy"><img src="{{ $iconeInstagram }}" alt="Instagram" class="icon-social"></a>
    </span>
  </footer>

  {{-- Chart.js v4 + datalabels LOCAIS, inline (zero rede; ver public/vendor/README.md) --}}
  <script>{!! $chartJs !!}</script>
  <script>{!! $datalabelsJs !!}</script>
  <script>
    document.addEventListener("DOMContentLoaded", function () {
      try {
        const valores = {!! json_encode($valoresGeracao, JSON_HEX_TAG | JSON_INVALID_UTF8_SUBSTITUTE) ?: '[]' !!};
        const labels = {!! json_encode($nomesMeses, JSON_HEX_TAG | JSON_INVALID_UTF8_SUBSTITUTE) ?: '[]' !!};
        const maxY = valores.length ? Math.max(...valores) * 1.1 : 10;

        new Chart(document.getElementById('graficoGeracao').getContext('2d'), {
          type: 'line',
          data: {
            labels: labels,
            datasets: [{
              label: 'Geração Mensal (kWh)',
              data: valores,
              fill: true,
              backgroundColor: 'rgba(243, 147, 37, 0.08)',
              borderColor: '#F39325',
              tension: 0.3,
              pointBackgroundColor: '#F39325',
            }]
          },
          options: {
            responsive: true,
            animation: false,
            aspectRatio: 1.8,
            plugins: {
              legend: { display: false },
              datalabels: {
                color: '#5C5C5C',
                anchor: 'end',
                align: 'top',
                formatter: (v) => v.toFixed(2),
                font: { family: 'JetBrains Mono', size: 8, weight: 'bold' }
              }
            },
            scales: {
              y: {
                beginAtZero: true,
                suggestedMax: maxY,
                grid: { color: '#E5E0D9' },
                ticks: { precision: 0, color: '#5C5C5C' }
              },
              x: {
                grid: { display: false },
                ticks: { color: '#5C5C5C' }
              }
            }
          },
          plugins: [ChartDataLabels]
        });
      } catch (e) {
        window.chartError = String(e); // diagnóstico sem quebrar o contrato de não-travar
      } finally {
        window.chartRendered = true; // sinal p/ Browsershot waitForFunction
      }
    });
  </script>
</body>
</html>
