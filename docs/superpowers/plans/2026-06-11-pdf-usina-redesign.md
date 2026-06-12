# Redesenho do PDF da Usina — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Refatorar o PDF da usina para 100% design system Líder Energy, remover seções de Auditoria/Parâmetros (layout quebrado), corrigir rótulos enganosos, zerar dependências de rede do Browsershot e garantir paridade exata com o motor de faturamento.

**Architecture:** O Blade (`usina.blade.php`) continua arquivo único, reestilizado sobre tokens CSS do design system. `UsinaPdfViewModel` perde as seções removidas e ganha os dados corrigidos (slice de 6 meses, "convertido em receita"). `PDFController` ganha um `inlineAsset()` genérico e perde os waits de rede. Chart.js e fontes ficam locais/embutidos.

**Tech Stack:** Laravel 10 + Blade, Browsershot (Chrome headless), Chart.js v4 (UMD local), Nunito + JetBrains Mono (woff2 base64), PHPUnit (sqlite in-memory).

**Spec:** `docs/superpowers/specs/2026-06-11-pdf-usina-redesign-design.md`

**Como rodar os testes (host não tem PHP):**
```bash
cd "/Users/matheus/Desktop/Projetos App.nosync/emerson-lider-energy/api-laravel"
docker run --rm -v "$PWD":/app -w /app php:8.3-cli vendor/bin/phpunit            # suíte toda
docker run --rm -v "$PWD":/app -w /app php:8.3-cli vendor/bin/phpunit --filter=PdfMotorUnicoTest
```

**Atenção (memória do projeto):** podem existir sessões paralelas mexendo no backend na mesma branch. Antes de cada commit, `git status` e adicionar SOMENTE os arquivos deste plano.

---

### Task 1: `Format::numero()` + diretiva `@numero`

Números puros (kg de CO₂, contagem de árvores) hoje usam `number_format` cru no Blade. `Format` é a fonte única de formatação (§4.3 da spec).

**Files:**
- Modify: `api-laravel/app/Support/Format.php`
- Modify: `api-laravel/app/Providers/AppServiceProvider.php:28-31`
- Test: `api-laravel/tests/Unit/FormatNumeroTest.php` (criar)

- [ ] **Step 1: Write the failing test**

```php
<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Support\Format;
use PHPUnit\Framework\TestCase;

class FormatNumeroTest extends TestCase
{
    public function test_numero_formata_pt_br_sem_unidade(): void
    {
        $this->assertSame('3.943', Format::numero(3943.2, 0));
        $this->assertSame('197,16', Format::numero(197.16));
        $this->assertSame('0', Format::numero(null, 0));
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `docker run --rm -v "$PWD":/app -w /app php:8.3-cli vendor/bin/phpunit --filter=FormatNumeroTest`
Expected: FAIL — `Call to undefined method App\Support\Format::numero()`

- [ ] **Step 3: Implement `Format::numero()`**

Em `Format.php`, após `percentual()`:

```php
    /** Número puro pt-BR (contagens, kg): 3943.2 -> "3.943,20". */
    public static function numero(float|int|null $valor, int $casas = 2): string
    {
        return number_format((float) ($valor ?? 0), $casas, ',', '.');
    }
```

- [ ] **Step 4: Registrar a diretiva**

Em `AppServiceProvider.php`, após a linha da diretiva `percentual`:

```php
        Blade::directive('numero', fn (string $expr) => "<?php echo \\App\\Support\\Format::numero($expr); ?>");
```

- [ ] **Step 5: Run test to verify it passes**

Run: `docker run --rm -v "$PWD":/app -w /app php:8.3-cli vendor/bin/phpunit --filter=FormatNumeroTest`
Expected: PASS (1 test, 3 assertions)

- [ ] **Step 6: Commit**

```bash
git add app/Support/Format.php app/Providers/AppServiceProvider.php tests/Unit/FormatNumeroTest.php
git commit -m "feat(pdf): Format::numero() + diretiva @numero para contagens pt-BR"
```

---

### Task 2: `UsinaPdfViewModel` — remover auditoria/parâmetros, dados corrigidos

Decisões §3.1: Auditoria e Parâmetros saem do PDF (e do ViewModel — nada mais os consome). O slice "últimos 6 meses" sai do Blade e vira `dadosCreditos`. Cada linha ganha `convertido_receita` (crédito vencido pago, R$) e o ViewModel expõe `temConvertidoReceita`. Totais ganham nomes honestos.

**Files:**
- Modify: `api-laravel/app/Http/ViewModels/UsinaPdfViewModel.php`
- Test: `api-laravel/tests/Feature/PdfMotorUnicoTest.php`

- [ ] **Step 1: Atualizar o teste (TDD — novo contrato do ViewModel)**

Em `PdfMotorUnicoTest::test_viewmodel_usa_termos_do_motor_sem_recalcular`, **remover** os blocos de asserts de "Seção Parâmetros" (linhas ~136-139) e "Auditoria" (linhas ~141-146) e **adicionar** no lugar:

```php
        // §3.1: auditoria e parâmetros NÃO existem mais no ViewModel.
        $this->assertArrayNotHasKey('auditoria', $vm);
        $this->assertArrayNotHasKey('parametros', $vm);

        // Demonstrativo: dadosCreditos é o slice (≤6 meses) pronto para o Blade.
        $this->assertArrayHasKey('dadosCreditos', $vm);
        $this->assertLessThanOrEqual(6, count($vm['dadosCreditos']));
        $linha = $vm['dadosCreditos']['Maio/26'];
        $this->assertArrayHasKey('guardado', $linha);
        $this->assertArrayHasKey('creditado_kwh', $linha);
        $this->assertArrayHasKey('vencimento', $linha);
        $this->assertArrayHasKey('meses_utilizados', $linha);
        // Crédito vencido convertido em receita (R$) — 0 neste cenário.
        $this->assertEqualsWithDelta(0.0, $linha['convertido_receita'], 0.01);
        $this->assertFalse($vm['temConvertidoReceita']);

        // Totais com nomes honestos (motor): kWh guardado, CUO, valor final.
        $this->assertArrayHasKey('totalGuardadoKwh', $vm);
        $this->assertArrayHasKey('totalCuo', $vm);
        $this->assertArrayHasKey('totalValorFinal', $vm);
```

- [ ] **Step 2: Run test to verify it fails**

Run: `docker run --rm -v "$PWD":/app -w /app php:8.3-cli vendor/bin/phpunit --filter=test_viewmodel_usa_termos_do_motor_sem_recalcular`
Expected: FAIL — `Failed asserting that an array does not have the key 'auditoria'`

- [ ] **Step 3: Refatorar o ViewModel**

Em `UsinaPdfViewModel::montar()`:

1. **Remover** a montagem de `$auditoria` (bloco "Auditoria §8", linhas ~144-156) e a variável `$auditoria = []`.
2. **Substituir** a montagem de `$dadosFaturamento[$label]` por (somente chaves consumidas pelo Blade + convertido):

```php
            $dadosFaturamento[$label] = [
                'guardado' => $termos->guardadoKwh->valor(),
                'creditado_kwh' => $this->somaConsumoFifoKwh($resposta),
                'vencimento' => $this->labelData($vencimento),
                'meses_utilizados' => $this->mesesUtilizadosTexto($resposta),
                // Crédito vencido PAGO como receita no Valor Final (§3.1 — nunca
                // rotular como perda; o motor o converte em dinheiro).
                'convertido_receita' => $termos->receitaExpiracao->emReais(),
            ];
```

3. **No `return`**, remover as chaves `'auditoria'`, `'parametros'`, `'saldo'`, `'maxGeracao'`, `'totalEnergiaReceber'`, `'totalFaturaConcessionaria'`, `'totalFaturasEmitidas'`, `'totalReceitaExpiracao'` e adicionar:

```php
            // Demonstrativo de Créditos: últimos 6 meses, pronto (zero lógica no Blade).
            'dadosCreditos' => array_slice($dadosFaturamento, -6, null, true),
            'temConvertidoReceita' => collect($dadosFaturamento)
                ->contains(fn (array $l): bool => $l['convertido_receita'] > 0),

            // Totais com nomes honestos — somam APENAS valores do motor.
            'totalGuardadoKwh' => $totalGuardadoKwh,
            'totalCuo' => $totalCuo,
            'totalValorFinal' => $totalValorFinal,
```

4. Manter `'dadosFaturamento' => $dadosFaturamento` **fora** do return (variável local apenas); o Blade só consome `dadosCreditos`. Remover também `$totalReceitaExpiracao` (acumulador órfão) e o `$media`/`$liquida` que só serviam à auditoria.

5. Remover o uso agora órfão de `somaExpiracaoKwh()` (método inteiro, linhas ~263-269) — `convertido_receita` usa `receitaExpiracao` (R$) direto dos termos.

- [ ] **Step 4: Run tests**

Run: `docker run --rm -v "$PWD":/app -w /app php:8.3-cli vendor/bin/phpunit --filter=PdfMotorUnicoTest`
Expected: PASS (4 tests)

- [ ] **Step 5: Commit**

```bash
git add app/Http/ViewModels/UsinaPdfViewModel.php tests/Feature/PdfMotorUnicoTest.php
git commit -m "refactor(pdf): ViewModel sem auditoria/parametros; dadosCreditos (6 meses) e convertido_receita prontos p/ o Blade"
```

---

### Task 3: Assets locais (Chart.js, fontes, logo correto)

Zero rede no render (§4.4). Fonte-da-verdade do logo: `design-system/assets/logo-color.png` (§4.1).

**Files:**
- Create: `api-laravel/public/vendor/chart.umd.js`, `api-laravel/public/vendor/chartjs-plugin-datalabels.min.js`, `api-laravel/public/vendor/README.md`
- Create: `api-laravel/public/fonts/nunito-{400,600,700,800}.woff2`, `api-laravel/public/fonts/jetbrains-mono-{400,700}.woff2`
- Create: `api-laravel/public/img/logo-lider-energy-color.png`

- [ ] **Step 1: Baixar Chart.js v4 + plugin datalabels (versões pinadas)**

```bash
cd "/Users/matheus/Desktop/Projetos App.nosync/emerson-lider-energy/api-laravel"
mkdir -p public/vendor public/fonts
curl -fsSL -o public/vendor/chart.umd.js "https://cdn.jsdelivr.net/npm/chart.js@4.4.9/dist/chart.umd.js"
curl -fsSL -o public/vendor/chartjs-plugin-datalabels.min.js "https://cdn.jsdelivr.net/npm/chartjs-plugin-datalabels@2.2.0/dist/chartjs-plugin-datalabels.min.js"
head -c 200 public/vendor/chart.umd.js   # sanity: deve começar com banner/função, NÃO com "<html"
```

Expected: arquivos ~200KB e ~12KB; conteúdo JS válido.

- [ ] **Step 2: Baixar fontes woff2 (Fontsource, latin)**

```bash
for w in 400 600 700 800; do
  curl -fsSL -o "public/fonts/nunito-$w.woff2" "https://cdn.jsdelivr.net/fontsource/fonts/nunito@latest/latin-$w-normal.woff2"
done
for w in 400 700; do
  curl -fsSL -o "public/fonts/jetbrains-mono-$w.woff2" "https://cdn.jsdelivr.net/fontsource/fonts/jetbrains-mono@latest/latin-$w-normal.woff2"
done
file public/fonts/*.woff2   # todos devem ser "Web Open Font Format (Version 2)"
```

Expected: 6 arquivos woff2 (~20-40KB cada).

- [ ] **Step 3: Copiar o logo correto da marca**

```bash
cp "../design-system/assets/logo-color.png" public/img/logo-lider-energy-color.png
file public/img/logo-lider-energy-color.png   # PNG 5206x1632
```

Não remover `logo-consorcio-lider-energy.png` (o frontend Vue ainda o usa em `TheNavbar.vue`/`Home.vue`).

- [ ] **Step 4: Documentar versões**

Create `public/vendor/README.md`:

```markdown
# Vendor assets (PDF render, zero-rede)

Embutidos no HTML do PDF pelo PDFController (spec docs/superpowers/specs/2026-06-11-pdf-usina-redesign-design.md §4.4).

- chart.umd.js — Chart.js v4.4.9 (https://cdn.jsdelivr.net/npm/chart.js@4.4.9/dist/chart.umd.js)
- chartjs-plugin-datalabels.min.js — v2.2.0
- ../fonts/*.woff2 — Nunito 400/600/700/800, JetBrains Mono 400/700 (Fontsource, latin subset)
```

- [ ] **Step 5: Commit**

```bash
git add public/vendor public/fonts public/img/logo-lider-energy-color.png
git commit -m "feat(pdf): assets locais (Chart.js 4.4.9, datalabels 2.2.0, Nunito/JetBrains Mono woff2, logo color da marca)"
```

---

### Task 4: `PDFController` — `inlineAsset()` genérico, logo, zero-rede no Browsershot

**Files:**
- Modify: `api-laravel/app/Http/Controllers/PDFController.php`

- [ ] **Step 1: Generalizar o inlining (DRY)**

Substituir o método `inlinePublicImage()` por:

```php
  /** Gera um Data URI de qualquer asset em public/ (imagem, fonte). */
  private function inlineAsset(string $relativePath, string $mimeFallback = 'application/octet-stream'): string {
    $contents = $this->publicFileContents($relativePath);
    if ($contents === null) {
        return self::TRANSPARENT_PIXEL;
    }
    $mime = mime_content_type(public_path($relativePath)) ?: $mimeFallback;
    return 'data:' . $mime . ';base64,' . base64_encode($contents);
  }

  /** Conteúdo bruto de um arquivo em public/ (JS inline), ou null. */
  private function publicFileContents(string $relativePath): ?string {
    $path = public_path($relativePath);
    if (!is_file($path)) {
        return null;
    }
    $contents = file_get_contents($path);
    return $contents === false ? null : $contents;
  }

  private function inlinePublicImage(string $relativePath): string {
    return $this->inlineAsset($relativePath, 'image/png');
  }

  /** CSS @font-face com woff2 embutido (zero rede no Browsershot). */
  private function buildFontFaceCss(): string {
    $fontes = [
        ['Nunito', 400, 'fonts/nunito-400.woff2'],
        ['Nunito', 600, 'fonts/nunito-600.woff2'],
        ['Nunito', 700, 'fonts/nunito-700.woff2'],
        ['Nunito', 800, 'fonts/nunito-800.woff2'],
        ['JetBrains Mono', 400, 'fonts/jetbrains-mono-400.woff2'],
        ['JetBrains Mono', 700, 'fonts/jetbrains-mono-700.woff2'],
    ];

    return collect($fontes)->map(function (array $f) {
        [$family, $weight, $path] = $f;
        $uri = $this->inlineAsset($path, 'font/woff2');
        return "@font-face{font-family:'$family';font-weight:$weight;font-style:normal;src:url($uri) format('woff2');}";
    })->implode("\n");
  }
```

- [ ] **Step 2: Trocar o logo e passar JS/fontes ao Blade**

Em `gerarUsinaPDF()`: na lista `$imagensInline`, trocar `'logo' => 'img/logo-consorcio-lider-energy.png'` por `'logo' => 'img/logo-lider-energy-color.png'`. No `array_merge($viewModel, [...])`, adicionar:

```php
            'chartJs' => $this->publicFileContents('vendor/chart.umd.js') ?? '',
            'datalabelsJs' => $this->publicFileContents('vendor/chartjs-plugin-datalabels.min.js') ?? '',
            'fontFaceCss' => $this->buildFontFaceCss(),
```

- [ ] **Step 3: Browsershot sem waits de rede**

Substituir a configuração do PDF principal por:

```php
        $pdf = $this->configureBrowsershot(Browsershot::html($html))
            ->format('A4')
            ->showBackground()
            ->deviceScaleFactor(1)
            // Sem CDN/Google Fonts no HTML: nada de waitUntilNetworkIdle/setDelay.
            // O Blade seta window.chartRendered ao terminar o gráfico (try/finally).
            ->waitForFunction('window.chartRendered === true')
            ->timeout(30)
            ->pdf();
```

- [ ] **Step 4: Remover código morto**

Remover o método `appendPdfPages()` (referencia `Fpdi`/`StreamReader` não importados — fatal se chamado; nunca é chamado).

- [ ] **Step 5: Verificar suíte (nada deve quebrar — controller não tem teste direto)**

Run: `docker run --rm -v "$PWD":/app -w /app php:8.3-cli vendor/bin/phpunit`
Expected: PASS (todos; era 61+ na Fase 7, agora 62+ com FormatNumeroTest)

- [ ] **Step 6: Commit**

```bash
git add app/Http/Controllers/PDFController.php
git commit -m "perf(pdf): inlineAsset generico, logo da marca, Browsershot zero-rede (waitForFunction, timeout 30s, sem setDelay)"
```

---

### Task 5: Reescrever `usina.blade.php` (design system + conteúdo §3.1)

**Files:**
- Modify: `api-laravel/resources/views/usina.blade.php` (reescrita completa)
- Test: `api-laravel/tests/Feature/PdfMotorUnicoTest.php` (novo teste de render)

- [ ] **Step 1: Write the failing render test**

Adicionar a `PdfMotorUnicoTest`:

```php
    public function test_blade_renderiza_no_design_system_sem_secoes_removidas(): void
    {
        $usina = $this->usina(media: 10000, menor: 5000, tarifa: 0.50, rede: 'Trifásico');
        $this->geracaoReal((int) $usina->usi_id, (int) $usina->cli_id, 2026, ['maio' => 8600]);

        $vm = (new UsinaPdfViewModel($this->service()))->montar($usina, 2026, 5);

        $px = 'data:image/png;base64,iVBORw0KGgo=';
        $html = \View::file(resource_path('views/usina.blade.php'), array_merge($vm, [
            'logo' => $px, 'iconeSol' => $px, 'iconeRelogio' => $px, 'iconeWeb' => $px,
            'iconeWpp' => $px, 'iconeEmail' => $px, 'iconeCo2' => $px, 'iconeArvore' => $px,
            'iconeDinheiro' => $px, 'iconeLampada' => $px, 'iconeInstagram' => $px,
            'iconeLinkedin' => $px,
            'uc' => '562606800', 'celescInvoiceBase64' => '', 'celescInvoiceId' => '',
            'chartJs' => '/*chart*/', 'datalabelsJs' => '/*dl*/', 'fontFaceCss' => '/*fonts*/',
        ]))->render();

        // Seções removidas (§3.1) ausentes.
        $this->assertStringNotContainsStringIgnoringCase('auditoria', $html);
        $this->assertStringNotContainsStringIgnoringCase('parâmetros de cálculo', $html);

        // Rótulos honestos presentes.
        $this->assertStringContainsString('Guardado (kWh)', $html);
        $this->assertStringContainsString('Vencimento do crédito', $html);
        $this->assertStringContainsString('Crédito guardado acumulado (kWh)', $html);
        $this->assertStringContainsString('CUO acumulado (R$)', $html);
        $this->assertStringContainsString('Valor a receber acumulado (R$)', $html);

        // Coluna "Convertido em receita" só quando > 0 (cenário sem expiração).
        $this->assertStringNotContainsString('Convertido em receita', $html);

        // Zero rede: nenhum CDN/Google Fonts; assets inline presentes.
        $this->assertStringNotContainsString('googleapis.com', $html);
        $this->assertStringNotContainsString('cdn.jsdelivr.net', $html);
        $this->assertStringContainsString('/*chart*/', $html);
        $this->assertStringContainsString('/*fonts*/', $html);

        // Design system aplicado; paleta antiga banida.
        $this->assertStringContainsString('#F39325', $html);   // Lider Orange
        $this->assertStringContainsString('Nunito', $html);
        $this->assertStringNotContainsString('Montserrat', $html);
        $this->assertStringNotContainsString('#470b07', $html); // marrom antigo
        $this->assertStringNotContainsString('#f44336', $html); // vermelho antigo
        $this->assertStringNotContainsString('#d32f2f', $html);

        // Valor do motor presente formatado (R$ 3.615,01 — caso do teste acima).
        $this->assertStringContainsString('3.615,01', $html);
    }
```

- [ ] **Step 2: Run test to verify it fails**

Run: `docker run --rm -v "$PWD":/app -w /app php:8.3-cli vendor/bin/phpunit --filter=test_blade_renderiza_no_design_system`
Expected: FAIL (Blade atual contém "AUDITORIA", Montserrat, CDNs…)

- [ ] **Step 3: Reescrever o Blade completo**

Substituir TODO o conteúdo de `resources/views/usina.blade.php` por:

```blade
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
      --color-smoke: #B0B0B0;
      --color-mist: #E5E0D9;
      --color-linen: #FAF6F1;
      --color-paper: #FFFFFF;
      --grad-sun: linear-gradient(135deg, #F9B566 0%, #F39325 45%, #D97613 100%);
      --radius-sm: 6px;
      --radius-md: 12px;
      --radius-lg: 20px;
      --radius-pill: 999px;
      --space-1: 4px; --space-2: 8px; --space-3: 12px; --space-4: 16px;
      --shadow-sm: 0 2px 6px rgba(61,61,61,0.08);
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

    .page { padding: 14px 16px 58px; } /* reserva o rodapé fixo */

    .num { font-family: var(--font-mono); }

    .card {
      background: var(--color-paper);
      border-radius: var(--radius-lg);
      box-shadow: var(--shadow-sm);
      padding: var(--space-3);
    }

    .eyebrow {
      font-weight: 700;
      font-size: 7.5pt;
      letter-spacing: 0.14em;
      text-transform: uppercase;
      color: var(--color-primary-deep);
      margin: var(--space-3) 0 var(--space-2);
    }

    /* ---------- Cabeçalho ---------- */
    .header {
      display: flex;
      align-items: center;
      gap: var(--space-3);
    }
    .header .logo img { height: 52px; display: block; }
    .header .divider { width: 1px; align-self: stretch; background: var(--color-mist); }
    .company-info h2 { margin: 0 0 2px; font-size: 9pt; font-weight: 800; }
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
      margin: var(--space-2) 0;
      font-size: 7pt;
      color: var(--color-graphite);
    }
    .meta-row .icon { width: 11px; height: 11px; vertical-align: -2px; margin-right: 2px; }
    .contact-item { margin-left: var(--space-3); }

    /* ---------- Faixa de destaque (gradiente só-laranja, uso único) ---------- */
    .highlight-bar {
      display: flex;
      justify-content: space-around;
      align-items: center;
      gap: var(--space-3);
      background: var(--grad-sun);
      color: #fff;
      border-radius: var(--radius-lg);
      padding: var(--space-2) var(--space-4);
      font-size: 8pt;
    }
    .highlight-bar p { margin: 0; }
    .highlight-bar .destaque { font-size: 10pt; font-weight: 800; }

    /* ---------- Geração ---------- */
    .geracao-container { display: flex; gap: var(--space-3); align-items: stretch; }
    .grafico { flex: 1 1 62%; }
    .grafico canvas { width: 100%; max-width: 460px; height: auto; }
    .dados-geracao { flex: 1 1 38%; background: var(--color-linen); box-shadow: none; border: 1px solid var(--color-mist); }
    .dados-geracao h3 { margin: 0 0 var(--space-2); font-size: 9.5pt; font-weight: 800; text-align: center; }
    .dados-geracao .kwh-destaque { color: var(--color-primary-deep); font-weight: 800; }
    .item-geracao { display: flex; align-items: center; gap: var(--space-2); margin: var(--space-2) 0; }
    .item-geracao img { width: 34px; height: 34px; }
    .item-geracao strong { color: var(--color-accent-leaf-deep); }

    /* ---------- Tabelas ---------- */
    .data-table {
      width: 100%;
      border-collapse: collapse;
      font-size: 7pt;
      text-align: center;
    }
    .data-table thead th {
      background: var(--color-ink);
      color: #fff;
      font-weight: 700;
      padding: 4px 5px;
      border: none;
    }
    .data-table thead tr th:first-child { border-radius: var(--radius-sm) 0 0 0; }
    .data-table thead tr th:last-child { border-radius: 0 var(--radius-sm) 0 0; }
    .data-table tbody td {
      padding: 3px 5px;
      border-bottom: 1px solid var(--color-mist);
    }
    .data-table tbody tr:nth-child(even) { background: var(--color-linen); }
    .data-table .valor-final { font-weight: 700; color: var(--color-primary-deep); }

    /* ---------- Linha inferior ---------- */
    .linha-final { display: flex; gap: var(--space-3); margin-top: var(--space-3); align-items: flex-start; }
    .bloco-creditos { flex: 1.4; }
    .coluna-direita { flex: 1; display: flex; flex-direction: column; gap: var(--space-3); }

    .historico-valores { width: 100%; border-collapse: collapse; font-size: 7.5pt; }
    .historico-valores td { padding: 3px 4px; border-bottom: 1px solid var(--color-mist); }
    .historico-valores td:last-child { text-align: right; font-weight: 700; }

    .badge-total {
      display: inline-block;
      background: var(--color-primary-soft);
      color: var(--color-primary-deep);
      font-weight: 700;
      font-size: 7pt;
      padding: 3px 10px;
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
      color: #fff;
      font-size: 7.5pt;
      display: flex;
      justify-content: space-between;
      align-items: center;
      padding: var(--space-2) var(--space-4);
    }
    .rodape .icon { width: 14px; height: 14px; vertical-align: -3px; margin-right: 4px; }
    .rodape .icon-social { width: 16px; height: 16px; vertical-align: middle; margin-left: 6px; }
    .rodape a { color: #fff; }
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

    <div class="highlight-bar">
      <p><strong>UC:</strong> <span class="num">{{ $uc }}</span></p>
      <p><strong>Fonte de geração:</strong> UFV</p>
      <p><strong>Valor kWh:</strong> <span class="num">@tarifa($usina->comercializacao->valor_kwh)</span></p>
      <p class="destaque"><span class="num">@reais($valorReceber)</span></p>
    </div>

    <div class="eyebrow">Demonstrativo de geração</div>

    <div class="geracao-container">
      <div class="card grafico">
        <canvas id="graficoGeracao"></canvas>
      </div>
      <div class="card dados-geracao">
        <h3>Dados de geração de energia</h3>
        <p>Sua geração de energia foi de <span class="kwh-destaque num">@kwh($geracaoMes)</span>, isso é igual a:</p>
        <div class="item-geracao">
          <img src="{{ $iconeCo2 }}" alt="">
          <span><strong class="num">@numero($co2Evitado, 0) kg</strong> de emissão de CO₂ evitada</span>
        </div>
        <div class="item-geracao">
          <img src="{{ $iconeArvore }}" alt="">
          <span><strong class="num">@numero($arvores, 0)</strong> árvores plantadas</span>
        </div>
      </div>
    </div>

    <div class="eyebrow">Dados de geração e faturamento</div>
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
            <td class="num">@reais($dados['creditado'])</td>
            <td class="num">@reais($dados['cuo'])</td>
            <td class="num valor-final">@reais($dados['valor_final'])</td>
          </tr>
        @endforeach
      </tbody>
    </table>

    <div class="linha-final">
      <div class="card bloco-creditos">
        <div class="eyebrow" style="margin-top:0;">Demonstrativo de créditos</div>
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
                <td>{{ $dados['vencimento'] ?? '-' }}</td>
                <td class="num">@kwh($dados['guardado'])</td>
                <td class="num">@kwh($dados['creditado_kwh'] ?? 0)</td>
                <td>{{ $dados['meses_utilizados'] ?? '-' }}</td>
                @if($temConvertidoReceita)
                  <td class="num">{{ $dados['convertido_receita'] > 0 ? \App\Support\Format::reais($dados['convertido_receita']) : '-' }}</td>
                @endif
              </tr>
            @endforeach
          </tbody>
        </table>
      </div>

      <div class="coluna-direita">
        <div class="card">
          <div class="eyebrow" style="margin-top:0;">Histórico de valores</div>
          <table class="historico-valores">
            <tbody>
              <tr>
                <td>Crédito guardado acumulado (kWh)</td>
                <td class="num">@kwh($totalGuardadoKwh)</td>
              </tr>
              <tr>
                <td>CUO acumulado (R$)</td>
                <td class="num">@reais($totalCuo)</td>
              </tr>
              <tr>
                <td>Valor a receber acumulado (R$)</td>
                <td class="num">@reais($totalValorFinal)</td>
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
        const valores = {!! json_encode($valoresGeracao) !!};
        const labels = {!! json_encode($nomesMeses) !!};
        const maxY = valores.length ? Math.max(...valores) * 1.1 : 10;

        new Chart(document.getElementById('graficoGeracao').getContext('2d'), {
          type: 'line',
          data: {
            labels: labels,
            datasets: [{
              label: 'Geração Mensal (kWh)',
              data: valores,
              fill: true,
              backgroundColor: 'rgba(243, 147, 37, 0.10)',
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
      } finally {
        window.chartRendered = true; // sinal p/ Browsershot waitForFunction
      }
    });
  </script>
</body>
</html>
```

Notas de implementação:
- `animation: false` — render imediato (PDF não precisa de animação; reduz a janela até `chartRendered`).
- O `try/finally` garante `chartRendered = true` mesmo se o Chart falhar — o Browsershot nunca fica preso até o timeout.
- Único uso de `Format::` direto (célula condicional "Convertido em receita") é apresentação pura; alternativa com diretiva exigiria `@if` aninhado em expressão — aceito.

- [ ] **Step 4: Run tests**

Run: `docker run --rm -v "$PWD":/app -w /app php:8.3-cli vendor/bin/phpunit --filter=PdfMotorUnicoTest`
Expected: PASS (5 tests — os 4 existentes + render)

- [ ] **Step 5: Suíte completa**

Run: `docker run --rm -v "$PWD":/app -w /app php:8.3-cli vendor/bin/phpunit`
Expected: PASS

- [ ] **Step 6: Commit**

```bash
git add resources/views/usina.blade.php tests/Feature/PdfMotorUnicoTest.php
git commit -m "feat(pdf): usina.blade.php 100% design system (tokens, Nunito/JetBrains Mono, grad-sun unico), sem Auditoria/Parametros, rotulos honestos, Chart.js inline zero-rede"
```

---

### Task 6: Revisão adversarial + validação final

- [ ] **Step 1: Grep de regressões**

```bash
cd api-laravel
grep -n "Montserrat\|googleapis\|jsdelivr\|#470b07\|#f44336\|#d32f2f\|#fbc02d\|#ededed" resources/views/usina.blade.php && echo "FALHOU: resíduo encontrado" || echo "OK: zero resíduo"
grep -n "waitUntilNetworkIdle\|setDelay" app/Http/Controllers/PDFController.php | grep -v "gerarConsumidoresPDF" && echo "verificar" || echo "OK"
grep -nE "\*|/|\+ [0-9]|- [0-9]" resources/views/usina.blade.php | grep -v "json_encode\|maxY\|toFixed\|chart" || echo "OK: sem aritmética de faturamento no Blade"
```

- [ ] **Step 2: Revisão por subagentes (paralela)** — despachar 3 revisores:
  1. **Valores:** conferir que cada variável impressa no Blade existe no retorno do ViewModel e vem dos termos do motor (4 termos, FIFO, convertido em receita); rodar `PdfMotorUnicoTest`.
  2. **Marca:** conferir o Blade contra `design-system/colors_and_type.css` e README (gradiente só-laranja e único, verde só em CO₂/árvores, sem emoji/unicode-ícone, radii/sombras/eyebrow corretos, logo `logo-lider-energy-color.png`).
  3. **Perf:** conferir zero referência externa no HTML final, `waitForFunction` + `timeout(30)` no controller, `animation: false`, try/finally do `chartRendered`.

- [ ] **Step 3: Suíte completa final**

Run: `docker run --rm -v "$PWD":/app -w /app php:8.3-cli vendor/bin/phpunit`
Expected: PASS

- [ ] **Step 4: Validação visual — DO USUÁRIO**

Gerar o PDF real (staging, usina do Eder, Maio/2026) e pedir ao usuário para validar: layout em 1 página A4, rodapé sem sobreposição, Valor a Receber R$ 5.700,65, visual da marca. NÃO declarar concluído sem essa validação.

---

## Self-review (executado na escrita do plano)

- **Cobertura da spec:** §4.1 logo → Task 3/4; §4.2 mapa visual → Task 5; §4.3 SOLID/DRY (numero, slice, inlineAsset) → Tasks 1/2/4; §4.4 perf → Tasks 3/4/5; §4.5 paridade → testes nas Tasks 2/5 + Task 6; §3.1 conteúdo → Tasks 2/5. Layout 1 página → Task 6 Step 4 (validação visual).
- **Placeholders:** nenhum; todo código está inline.
- **Consistência de tipos/nomes:** `dadosCreditos`/`temConvertidoReceita`/`totalGuardadoKwh`/`totalCuo`/`totalValorFinal` definidos na Task 2 e consumidos na Task 5; `chartJs`/`datalabelsJs`/`fontFaceCss` definidos na Task 4 e consumidos na Task 5 e no teste.
