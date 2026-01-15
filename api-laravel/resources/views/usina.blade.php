<!DOCTYPE html>
<html lang="pt-br">
<head>
  <meta charset="UTF-8">
  <title>Fatura - {{ $usina->cliente->nome }}</title>
  <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;700&display=swap" rel="stylesheet">
  <style>
    html, body {
      height: 100%;
      margin: 0;
      padding: 0;
    }

    body {
      font-family: Arial, sans-serif;
      background-color: #ffffff;
      font-size: 8pt;
    }

    .wrapper {
      min-height: 100%;
      display: flex;
      flex-direction: column;
    }

    .content {
      flex: 1 0 auto;
    }

    .rodape {
      position: fixed;
      bottom: 0;
      left: 0;
      right: 0;
      z-index: 10;
    }

    .body-2 {
      padding: 10px;
    }

    .logo img {
      height: 80px;
    }

    .header-2 {
      display: flex;
      justify-content: space-between;
      align-items: center;
      background-color: #ededed;
      padding: 10px;
      border-radius: 12px 12px 0px 0px;
      max-width: 1200px;
      height: 10%;
      margin-bottom: 6px;
    }

    .header-3 {
      display: flex;
      justify-content: space-between;
      align-items: center;
      font-family: 'Montserrat', sans-serif;
      font-size: 8pt;
      gap: 8px;
      flex-wrap: wrap;
      margin-bottom: 8px; 
    }

    .info-box {
      display: flex;
      align-items: center;
      background-color: #ededed;
      border-radius: 0px 0px 12px 12px;
      padding: 10px 18px;
      margin: 0;
      font-family: 'Montserrat', sans-serif;
      color: #333;
      gap: 12px;
      flex-wrap: wrap;
    }

    .contact-item {
      display: flex;
      align-items: center;
      gap: 4px;
      font-size: 7.5pt;
    }

    .highlight-bar {
      display: flex;
      justify-content: space-around;
      align-items: center;
      background: linear-gradient(to right, #ffee58, #fbbc04, #f4511e);
      padding: 10px 20px;
      border-radius: 20px 20px 0 0;
      font-family: 'Montserrat', sans-serif;
      font-weight: 500;
      color: #333;
      gap: 20px;
    }

    .demonstrativo-geracao {
      background: linear-gradient(to right, #ffee58, #fbbc04, #f4511e);
      text-align: center;
      padding: 12px;
      font-family: 'Montserrat', sans-serif;
      font-size: 14pt;
      font-weight: 700;
      color: #333;
      border-radius: 0 0 20px 20px;
      margin-top: 5px;
    }

    .highlight-bar p {
      margin: 0;
    }

    .highlight-bar strong {
      font-weight: 700;
    }

    .geracao-container {
      display: flex;
      justify-content: space-between;
      align-items: flex-start;
      padding: 20px;
      max-width: 1200px;
    }

    .grafico {
      flex: 1 1 70%;
      display: flex;
      flex-direction: column;
    }

    .dados-geracao {
      flex: 1 1 30%;
      background-color: #ededed;
      border-radius: 12px;
      padding: 10px;
      font-family: 'Montserrat', sans-serif;
      font-size: 10pt;
      color: #333;
      text-align: center;
    }

    .dados-geracao h3 {
      font-size: 14pt;
      margin-bottom: 10px;
      font-weight: 700;
      color: #333;
    }

    .item-geracao {
      display: flex;
      align-items: center;
      gap: 10px;
      margin: 16px 0;
      text-align: left;
    }

    .icon-geracao {
      width: 48px;
      height: 48px;
    }

    .icon {
      width: 18px;
      height: 18px;
      margin: 0;
    }

    .data-table {
      width: 100%;
      border-collapse: collapse;
      font-family: 'Montserrat', sans-serif;
      font-size: 7pt;
      text-align: center;
    }

    .data-table th, .data-table td {
      padding: 2px 4px;
      line-height: 1.1;
      text-align: center;
      font-size: 7pt;
    }

    .badge {
      display: inline-block;
      padding: 4px 8px;
      border-radius: 8px;
      background-color: #fbbc04;
      color: #333;
      font-family: 'Montserrat', sans-serif;
      font-size: 8pt;
      font-weight: 600;
    }

    /* Borda só no cabeçalho */
    .data-table thead th {
      border: 1px solid #470b07;
    }

    /* Borda só no corpo */
    .data-table tbody td {
      border: 1px solid #d32f2f;
    }

    /* Estilo de fundo do cabeçalho */
    .data-table thead {
      background: #470b07;
      color: white;
    }

    .data-table th {
      font-weight: 700;
    }

    .titulo-tabela {
      background: linear-gradient(to right, #f44336, #fbc02d);
      color: white;
      font-family: 'Montserrat', sans-serif;
      font-size: 10pt;
      font-weight: 600;
      text-align: center;
      padding: 6px;
      border-radius: 12px 12px 0 0;
    }

    .company-info {
      flex: 1.5;
      padding: 0 20px;
      font-family: 'Montserrat', sans-serif;
    }

    .company-info h2 {
      margin: 0;
      font-weight: 500;
    }

    .company-info p {
      margin: 2px 0;
      font-weight: 200;
    }

    .divider {
      height: 70px;
      width: 2px;
      background-color: #333;
      margin: 0 15px;
    }

    .details-2 {
      display: flex;
      flex: 1;
    }

    .details-2 img {
      margin-top: 6px;
    }

    .details {
      flex: 1;
    }

    .details p {
      margin: 5px 0;
    }

    .details strong {
      display: block;
      margin-top: 2px;
    }

    .details .icon {
      margin-right: 5px;
    }

    .linha-3-colunas {
      display: flex;
      gap: 10px;
      margin-top: 10px;
      font-family: 'Montserrat', sans-serif;
      font-size: 8pt;
    }

    .bloco {
      padding: 10px;
      flex: 1;   
    }

    .bloco-creditos {
      background: #fff;
      flex: 1.2;
    }

    .bloco-creditos .bloco-titulo {
      text-align: center;
      background: linear-gradient(to right, #f44336, #fbc02d);
      color: #fff;
      font-weight: bold;
      border-radius: 8px 8px 0 0;
      padding: 4px;
      margin-bottom: 0px;
      font-size: 9pt;
    }

    .bloco-titulo {
      text-align: center;
      background: linear-gradient(to right, #f44336, #fbc02d);
      color: #fff;
      font-weight: bold;
      border-radius: 8px 8px 0 0;
      padding: 4px;
      margin-bottom: 0px;
      font-size: 9pt;
    }

    .bloco-creditos table {
      width: 100%;
      font-size: 7pt;
      text-align: center;
      border-collapse: collapse;
    }

    .bloco-creditos th {
      background-color: #e74c3c;
      border: 1px solid #e74c3c;
      color: #fff;
      padding: 2px;
    }

    .bloco-creditos td {
      padding: 2px;
      border: 1px solid #ddd;
    }

    .bloco-observacoes {
      background: #eaeaea;
      flex: 1.3;
      margin-bottom: 5px;
      border-radius: 8px;   
    }

    .bloco-observacoes strong {
      color: #470b07;
      display: block;
      margin-bottom: 5%;
    }

    .bloco-historico {
      flex: 1;
      display: flex;
      flex-direction: column;
      gap: 6px;
    }

    .bloco-historico .bloco-titulo {
      text-align: center;
      font-weight: bold;
      margin-top: 16px;
    }

    .historico-valores {
      width: 100%;
      font-size: 7pt;
      border-collapse: collapse;
    }

    .historico-valores td {
      padding: 2px 4px;
      text-align: right;
    }

    .historico-valores td:first-child {
      text-align: left;
    }

    .historico-valores .saldo {
      background: #d32f2f;
      color: #fff;
      font-weight: bold;
    }

    .rodape {
      width: 100%;
      box-sizing: border-box;
      background: linear-gradient(to right, #fdd835, #f57c00, #e53935);
      color: #fff;
      font-family: 'Montserrat', sans-serif;
      font-size: 9pt;
      display: flex;
      justify-content: space-between;
      align-items: center;
      padding: 8px 16px;
      gap: 8px;
      flex-wrap: wrap;
      border-top: 2px solid transparent; /* evita linha branca */
    }

    .rodape-esquerda,
    .rodape-direita {
      display: flex;
      align-items: center;
      gap: 6px;
      flex-wrap: wrap;
    }

    .rodape .icon {
      width: 20px;
      height: 20px;
      vertical-align: middle;
    }

    .rodape .icon-social {
      width: 22px;
      height: 22px;
      border-radius: 4px;
      padding: 2px;
    }

  </style>
</head>
<body>
  <div class="wrapper">
    <div class="content">
      <div class="body-2">
        <div class="header">
          <div class="header-2">
            <div class="logo">
              <img src="{{ $logo }}" alt="Logo" style="width: 250px;">
            </div>

            <div class="company-info">
              <h2><strong>CONSÓRCIO LÍDER ENERGY</strong></h2>
              <p><strong>CNPJ:</strong> 58.750.788/0001-33</p>
              <p>R. BRUNISLAU BLONKOVSKI, 131</p>
              <p>SANTA TEREZINHA/SC</p>
              <p>89199-000</p>
            </div>

            <div class="divider"></div>

            <div class="details">
              <div class="details-2">
                <img src="{{ $iconeSol }}" alt="Ícone de sol" class="icon">
                <p><strong>Produção:</strong> {{ $mesAnoSelecionado }}</p>
              </div>
              <div class="details-2">
                <img src="{{ $iconeDinheiro }}" alt="Ícone de dinheiro" class="icon">
                <p><strong>Valor a receber:</strong> {{ number_format($valorReceber, 2, ',', '.') }}</p>
              </div>
            </div>

            <div class="divider"></div>

            <div class="details">
              <p><strong>Usina:</strong></p>
              <p>{{ $usina->cliente->nome }}</p>
            </div>
          </div>

          <div class="header-3">
            <div class="info-box">
              <img src="{{ $iconeRelogio }}" alt="Ícone de Relógio" class="icon">
              <span>Data de emissão: <strong>{{ \Carbon\Carbon::now()->format('d/m/Y') }}</strong></span>
            </div>
            <div class="info-box">
              <div class="contact-item">
                <img src="{{ $iconeWeb }}" alt="Ícone Web" class="icon">
                <span><strong>www.consorcioliderenergy</strong>.com.br</span>
              </div>
              <div class="contact-item">
                <img src="{{ $iconeWpp }}" alt="Ícone WhatsApp" class="icon">
                <span>47 99661-4967</span>
              </div>
              <div class="contact-item">
                <img src="{{ $iconeEmail }}" alt="Ícone Email" class="icon">
                <span>contato@<strong>liderenergy</strong>.com.br</span>
              </div>
            </div>
          </div>
        </div>
        <div class="highlight-bar">
          <p><strong>UC:</strong> {{ $uc }}</p>
          <p><strong>Fonte de Geração:</strong> UFV</p>
          <p><strong>Valor Kwh:</strong> R$ {{$usina->comercializacao->valor_kwh}}</p>
          <p><strong>Valor a receber:</strong> R$ {{ number_format($valorReceber, 2, ',', '.') }}</p>
        </div>
      
        <div class="demonstrativo-geracao">
          DEMONSTRATIVO DE GERAÇÃO
        </div>

        @php
          // Fatores de conversão atualizados
          $fatorEmissao = 0.4;    // kg CO2 evitado por kWh gerado (média no Brasil)
          $kgPorArvore = 20;      // kg CO2 capturado por árvore por ano

          // Cálculos
          $co2Evitado = $geracaoMes * $fatorEmissao; // em kg
          $arvores = $co2Evitado / $kgPorArvore;
        @endphp

        <div class="geracao-container">
          <div class="grafico">
            <div class="block-title" style="margin-bottom: 10px; font-weight: bold;">Gráfico de Geração da Usina</div>
            <canvas id="graficoGeracao"  style="width: 100%; max-width: 480px; height: auto;"></canvas>
          </div>

          <div class="dados-geracao">
            <h3>Dados de Geração<br>de Energia</h3>
            <p>Sua geração de energia foi de <span style="color: orangered;"><strong>{{ number_format($geracaoMes, 2, ',', '.') }} Kwh</span><br>isso é igual a:</p>

            <div class="item-geracao">
              <img src="{{ $iconeCo2 }}" alt="Ícone CO2" class="icon-geracao">
              <span><strong>{{ number_format($co2Evitado, 0, ',', '.') }}Kg</strong> de <strong>emissão de CO₂ evitada</strong></span>
            </div>

            <div class="item-geracao">
              <img src="{{ $iconeArvore }}" alt="Ícone Árvore" class="icon-geracao">
              <span><strong>{{ number_format($arvores, 0, ',', '.') }}</strong> árvores <strong>plantadas</strong></span>
            </div>
          </div>
        </div>

        <div>
          <div class="titulo-tabela">
            DADOS DE GERAÇÃO E FATURAMENTO
          </div>
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
                  <td>{{ number_format($dados['geracao_kwh'] ?? 0, 2, ',', '.') }}</td>
                  <td>{{ number_format($dados['fixo'], 2, ',', '.') }}</td>
                  <td>{{ number_format($dados['injetado'], 2, ',', '.') }}</td>
                  <td>{{ number_format($dados['creditado'], 2, ',', '.') }}</td>
                  <td>{{ number_format($dados['cuo'], 2, ',', '.') }}</td>
                  <td>{{ number_format($dados['valor_final'], 2, ',', '.') }}</td>
                </tr>
              @endforeach
            </tbody>
          </table>
        </div>

      <div class="linha-3-colunas">
        <!-- Demonstrativo de Créditos -->
        <div class="bloco bloco-creditos">
          <div class="bloco-titulo">DEMONSTRATIVO DE CRÉDITOS</div>
          @php
              $ultimos6Meses = collect($dadosFaturamento)->reverse()->take(6)->reverse();
          @endphp

          <table class="data-table">
              <thead>
                  <tr>
                      <th>Mês</th>
                      <th>Mês de vencimento</th>
                      <th>Valor Guardado</th>
                      <th>Creditado (kWh)</th>
                      <th>Meses Resgatados</th>
                  </tr>
              </thead>
              <tbody>
                  @foreach($ultimos6Meses as $mes => $dados)
                      <tr>
                          <td>{{ $mes }}</td>
                          <td>{{ $dados['vencimento'] ?? '-' }}</td>
                          <td>{{ number_format($dados['guardado'], 2, ',', '.') }} Kwh</td>
                          <td>{{ number_format($dados['creditado_kwh'] ?? 0, 2, ',', '.') }} kWh</td>
                          <td>{{ $dados['meses_utilizados'] ?? '-' }}</td>
                      </tr>
                  @endforeach
              </tbody>
          </table>
        </div>

        <div class="bloco bloco-historico">
          <div class="bloco-titulo">HISTÓRICO DE VALORES</div>
          <table class="historico-valores">
            <thead>
              <tr>
                <th>Descrição</th>
                <th>Valor</th>
              </tr>
            </thead>
            <tbody>
              <tr>
                <td>Total acum de energia a receber</td>
                <td>{{ number_format($totalEnergiaReceber, 2, ',', '.') }} kWh</td>
              </tr>
              <tr>
                <td>Total acum de fatura concessionária</td>
                <td>R$ {{ number_format($totalFaturaConcessionaria, 2, ',', '.') }}</td>
              </tr>
              <tr>
                <td>Total acum de faturas emitidas</td>
                <td>R$ {{ number_format($totalFaturasEmitidas, 2, ',', '.') }}</td>
              </tr>
            </tbody>
          </table>
          <div class="bloco bloco-observacoes">
            <strong>Observações:</strong>
            <p>{{ $observacoes }}</p>
          </div>
        </div>
      </div>
    </div>

    <footer class="rodape">
      <div class="rodape-esquerda">
        <img src="{{ $iconeLampada }}" alt="Lâmpada" class="icon">
        <span>Pense bem antes de imprimir!</span>
      </div>
      <div class="rodape-direita">
        <span>Siga a Lider Energy nas redes sociais:</span>
        <a href="https://www.linkedin.com/company/liderenergy" target="_blank">
          <img src="{{ $iconeLinkedin }}" alt="LinkedIn" class="icon-social">
        </a>
        <a href="https://www.instagram.com/liderenergy" target="_blank">
          <img src="{{ $iconeInstagram }}" alt="Instagram" class="icon-social">
        </a>
      </div>
    </footer>
  </div>
  <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
  <script src="https://cdn.jsdelivr.net/npm/chartjs-plugin-datalabels"></script>
  <script>
    document.addEventListener("DOMContentLoaded", function () {
      const valores = {!! json_encode($valoresGeracao) !!};
      const labels = {!! json_encode($nomesMeses) !!};

      const maxGeracao = Math.max(...valores);
      const margem = maxGeracao * 0.1;
      const maxY = maxGeracao + margem;

      const ctx = document.getElementById('graficoGeracao').getContext('2d');

      new Chart(ctx, {
        type: 'line',
        data: {
          labels: labels,
          datasets: [{
            label: 'Geração Mensal (kWh)',
            data: valores,
            fill: true,
            borderColor: 'rgb(243, 153, 86)',
            tension: 0.3,
            pointBackgroundColor: 'rgb(243, 153, 86)',
          }]
        },
        options: {
          responsive: true,
          aspectRatio: 1.8,
          plugins: {
            legend: {
              display: false
            },
            datalabels: {
              color: '#4F4F4F',
              anchor: 'end',
              align: 'top',
              formatter: function(value) {
                return value.toFixed(2);
              },
              font: {
                size: 8,
                weight: 'bold'
              }
            }
          },
          scales: {
            y: {
              beginAtZero: true,
              suggestedMax: maxY,
              ticks: {
                precision: 0
              }
            }
          }
        },
        plugins: [ChartDataLabels]
      });

      window.chartRendered = true;
    });
  </script>
</body>
</html>
