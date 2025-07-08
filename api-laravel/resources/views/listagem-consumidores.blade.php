<!DOCTYPE html>
<html lang="pt-br">
<head>
  <meta charset="UTF-8">
  <title>Cadastro de Unidades Consumidoras</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
  <style>
    body {
    font-family: 'Calibri', sans-serif;
    font-size: 9pt; /* Ajustado de 11pt para 9pt */
    margin: 0;
    padding: 0;
    }

    .campo {
      border: 1px solid #000;
      height: 28px;
      padding: 4px;
      margin-bottom: 4px;
    }

    h6, .fw-bold {
      font-weight: bold;
      font-family: 'Calibri', sans-serif;
    }

    table {
      width: 100%;
      table-layout: fixed;
    }

    table th, table td {
      font-size: 9pt;
      padding: 0%;
      margin: 0%;
      vertical-align: middle;
      font-family: 'Calibri', sans-serif;
    }

    .table th, .table td {
      border: 1px solid #000;
    }

    table, th, td {
      border: 1px solid #000 !important;
      border-collapse: collapse;
      padding: 2px 4px;
      text-align: center;
    }

    .table thead th {
      background-color: #e9ecef !important;
      font-weight: bold;
      text-align: center;
    }

    table, th, td {
      border: 1px solid #000;
      border-collapse: collapse;
      font-family: Calibri, sans-serif;
      font-size: 9pt;
    }

    .col-numero { width: 1.5%; text-align: center; }
    .col-nome { width: 26%; }
    .col-percentual { width: 12%; padding: 5px;}

    .number-col {
      width: 20px;
    }

    p, li {
      margin: 4px 0;
    }
  </style>
</head>
<body>

<h6 class="text-center fw-bold">
  Formulário para alteração do cadastro de Unidades Consumidoras participantes do Sistema de Compensação<br>
  <span class="text-danger">Geração Compartilhada - CONSÓRCIOS</span>
</h6>

<p>
  Solicito que o excedente de energia injetada na rede pela unidade consumidora nº. <u>_______________</u>,
  que esteja disponível para alocação nos termos da ReN Aneel 1.059/2023, seja rateada entre as unidades consumidoras abaixo relacionadas, conforme percentuais discriminados.
</p>

@php
  $porcentagemTotal = 0;
@endphp

<table style="width: 100%; table-layout: fixed;">
  <thead>
    <tr>
      <th class="col-numero"></th>
      <th colspan="4">Dados da(s) Unidade(s) Consumidora(s) Beneficiária(s)</th>
      <th class="col-percentual" rowspan="2">(%) do Excedente de Geração Destinado à UC</th>
    </tr>
    <tr>
      <th class="col-numero"></th>
      <th class="col-nome">Nome do Titular da Unidade Consumidora (UC)(Consórcio/Consorciado)</th>
      <th class="col-cpf">CPF/CNPJ do Titular da UC(Consórcio/Consorciado)</th>
      <th class="col-id">Nº de Identificação da UC Beneficiária</th>
      <th class="col-endereco">Endereço da UC Beneficiária</th>
    </tr>
  </thead>
  @php
    $linha = 0;
  @endphp
  <tbody>
    @foreach($consumidores as $index => $item)
      @php
        $percentual = ($item->consumidor->dado_consumo->media * 100) / ($usina->dadoGeracao->media ?: 1);
        $porcentagemTotal += $percentual;
        $linha += 1;
      @endphp
      <tr>
        <td class="col-numero" style="text-align: center;">{{ $index + 1 }}</td>
        <td class="col-nome">{{ $item->consumidor->cliente->nome }}</td>
        <td class="col-cpf">{{ $item->consumidor->cliente->cpf_cnpj }}</td>
        <td class="col-id">{{ $item->consumidor->con_id }}</td>
        <td class="col-endereco">{{ $item->consumidor->cliente->endereco->cidade ?? '-' }}</td>
        <td class="col-percentual" style="text-align: right;">
          {{ number_format($percentual, 2, ',', '.') }}%
        </td>
      </tr>
    @endforeach
    <tr>
      <td>{{$linha + 1}}</td>
      <td> </td>
      <td> </td>
      <td> </td>
      <td> </td>
      <td> </td>
    </tr>
  </tbody>
  <tfoot>
    <tr>
      <td colspan="5" style="text-align: right; font-weight: bold;">Soma dos percentuais do excedente de geração</td>
      <td class="col-percentual" style="font-weight: bold; text-align: right;">
        {{ number_format($porcentagemTotal, 2, ',', '.') }}%
      </td>
    </tr>
  </tfoot>
</table>


<p class="mt-3">
  Declaro ainda estar ciente e concordar que:<br>
  a) A soma dos percentuais informados limita-se e não excede à 100%, sendo que, caso resulte em valor inferior, a diferença será alocada na unidade consumidora geradora. O número de casas decimais de alocação do excedente de geração para cada UC deve ser de no máximo duas (ex.: 9,34 %).<br>
  <strong>Importante:</strong> Cálculos automáticos de soma, em função de arredondamentos, podem levar a erros levando à totalização incorreta.
</p>

<p><strong>b)</strong> Somente poderá ser cadastrada como beneficiária as unidades consumidoras CATIVAS sob mesma titularidade do Consórcio ou de seus consorciados, condicionado à comprovação por documentação específica quanto ao enquadramento nos termos da ReN Aneel 1.059/2023.</p>
<p><strong>c)</strong> Qualquer divergência em relação aos itens acima invalidam este documento.</p>
<p><strong>d)</strong> Em caso de encerramento da relação contratual do atual titular de qualquer dessas unidades consumidoras (nos termos do art. 70 da ReN Aneel 414/2010), incluindo a migração para o mercado livre, o percentual alocado à mesma será transferido para a unidade consumidora geradora, até o envio de novo formulário para redefinição do rateio.</p>
<p><strong>e)</strong> Este documento cancela e substitui qualquer outra solicitação anterior de cadastro de beneficiários relacionada à unidade consumidora geradora acima identificada, sendo que as informações cadastradas com base no especificado neste documento somente serão alteradas mediante entrega de novo formulário, sendo de responsabilidade exclusiva do representante formalmente designado do Consórcio, a emissão e entrega do mesmo.</p><br>

<table class="table table-bordered bold" style="font-size: 7pt; text-align: left; padding: 0%">
  <tbody>
    <tr>
      <td><strong>Titular da Unidade Consumidora (Razão Social do Consórcio):</strong></td>
      <td colspan="2"></td>
    </tr>
    <tr>
      <td><strong>E-mail para contato:</strong></td>
      <td colspan="2"></td>
    </tr>
    <tr>
      <td><strong>CPF/CNPJ:</strong></td>
      <td colspan="2"></td>
    </tr>
  </tbody>
</table>

    <table class="table table-bordered" style="font-size: 7pt; margin-top: 8px; padding: 0%">
    <tbody>
        <tr>
        <td><strong>Nome do Responsável Pessoa Física formalmente designado:</strong></td>
        <td colspan="2"></td>
        </tr>
        <tr>
        <td><strong>CPF:</strong></td>
        <td colspan="2"></td>
        </tr>
        <tr>
        <td><strong>Assinatura (Pessoa física: titular. Pessoa jurídica: responsável formalmente autorizado):</strong></td>
        <td colspan="2"></td>
        </tr>
    </tbody>
    </table>

<p><strong>Data:</strong> ___/___/20__</p>

<p class="fw-bold">Instruções para Documentações Complementares</p>
<p>Juntamente com o formulário, deverá ser apresentado documento pessoal onde conste a assinatura, para validação.</p>

<p><strong>Conforme Art. 279 da Lei 6404/76</strong>, o consórcio será constituído mediante contrato aprovado pelo órgão da sociedade competente para autorizar a alienação de bens do ativo não circulante, do qual constarão:</p>
<ol type="I">
  <li>A designação do consórcio;</li>
  <li>O empreendimento que constitua o objeto do consórcio; (micro ou minigeração);</li>
  <li>A duração, endereço e foro;</li>
  <li>A definição das obrigações e responsabilidade de cada sociedade consorciada, e das prestações específicas;</li>
  <li>Normas sobre recebimento de receitas e partilha de resultados; (quotas)</li>
  <li>Normas sobre administração do consórcio, contabilização, representação das sociedades consorciadas e taxa de administração, se houver; (responsável pela administração)</li>
  <li>Forma de deliberação sobre assuntos de interesse comum, com o número de votos que cabe a cada consorciado;</li>
  <li>Contribuição de cada consorciado para as despesas comuns, se houver.</li>
</ol>

<p><strong>Parágrafo único:</strong> O contrato de consórcio e suas alterações serão arquivados no registro do comércio do lugar da sua sede, devendo a certidão do arquivamento ser publicada.</p>

<p class="mt-3"><strong>Importante:</strong> caso no contrato não conste a relação atualizada dos consorciados, deverá ser apresentada documentação complementar hábil.</p>

<p>Para a Lei 11.795/2008 apresentar o contrato de participação em consórcio.</p>
</body>
</html>
