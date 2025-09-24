# 🌞 Sistema de Gestão de Usinas e Consumidores Fotovoltaicos

## Visão geral
A plataforma **Energia Assinatura** centraliza o gerenciamento operacional de usinas fotovoltaicas e dos consumidores
associados. O backend em Laravel expõe uma API autenticada via JWT para cadastro completo de usinas, consumidores,
créditos energéticos e rotinas de cálculo. O frontend em Vue 3 consome essa API para oferecer dashboards, gráficos
analíticos e geração de relatórios em PDF que apoiam a tomada de decisão financeira e operacional.

## Estrutura do monorepo
| Diretório | Descrição |
|-----------|-----------|
| `api-laravel/` | API RESTful em Laravel 12 com camadas de controllers, services e geração de PDFs com Browsershot. |
| `front/` | Single Page Application em Vue 3 + Vite com roteamento autenticado, dashboards e cadastros. |
| `ansible/` | Playbooks e roles para provisionamento e deploy automatizado (backend, frontend e serviços de apoio). |
| `terraform/` | Definições IaC para rede AWS, instâncias EC2, RDS PostgreSQL e componentes de segurança. |

## Arquitetura da aplicação
### Frontend (Vue 3 + Vite)
- SPA criada com **Vue 3**, **vue-router**, **Bootstrap 5**, **Chart.js** e **SweetAlert2**, compilada com **Vite**.【F:front/package.json†L1-L28】
- Rotas protegidas por guarda de autenticação que redireciona visitantes não autenticados para a tela de login.【F:front/src/router/index.js†L1-L108】
- Sessão persistida em `localStorage`, interceptando respostas 401/419 para limpar o token e redirecionar para login.【F:front/src/main.js†L1-L50】【F:front/src/utils/auth.js†L1-L25】
- Dashboards apresentam KPIs, gráficos de linha e barras e filtros por usina/ano, consumindo a API com `VITE_API_URL` configurável.【F:front/src/components/Relatorios.vue†L1-L200】
- A tela inicial permite baixar PDFs de usinas/consumidores gerados pelo backend e sumariza geração, consumo e saldo energético.【F:front/src/views/Home.vue†L120-L220】

### Backend (Laravel 12 + JWT)
- Projeto Laravel 12 com PHP 8.2, `tymon/jwt-auth` para autenticação e `spatie/browsershot` para renderizar PDFs com Chrome headless.【F:api-laravel/composer.json†L1-L55】
- A API cobre cadastros de endereços, clientes, consumidores, usinas, vínculos, créditos, faturamento, dados reais e vendedores, além de rotas para geração de PDFs e cálculo de geração mensal com idempotência.【F:api-laravel/routes/api.php†L24-L135】
- O cálculo mensal considera tarifas, reservas, compensações e expiração de créditos, atualizando tabelas relacionais em transação única e retornando métricas financeiras e ambientais.【F:api-laravel/app/services/CalculoGeracaoService.php†L17-L187】
- A rota de cálculo exige cabeçalho `Idempotency-Key`, evitando recomputações conflitantes e reaproveitando respostas anteriores.【F:api-laravel/app/Http/Controllers/CalculoGeracaoController.php†L21-L64】
- Relatórios PDF são produzidos com Browsershot a partir de dados agregados de geração, faturamento e créditos, incorporando logotipos/ícones e métricas por mês.【F:api-laravel/app/Http/Controllers/PDFController.php†L1-L200】

### Infraestrutura como código e deploy
- O Terraform define provedor AWS ≥1.6, cria rede single-AZ com VPC, sub-redes públicas/privadas, EC2 (bastion/app) e RDS Postgres, com variáveis parametrizáveis para CIDR, tipos de instância, credenciais e limites de segurança.【F:terraform/main.tf†L1-L62】【F:terraform/variables.tf†L1-L122】
- O inventário Ansible inclui bastion, servidor de aplicação privado e host de frontend, aplicando ProxyCommand para salt SSH.【F:ansible/inventory.ini†L1-L8】
- O playbook principal executa roles para preparar diretórios, instalar PHP/Nginx, publicar o backend, habilitar queue & scheduler e construir o frontend.【F:ansible/site.yml†L1-L47】
- A role do backend instala dependências Composer/NPM, renderiza `.env`, roda migrações, otimiza caches e cria serviços systemd para worker de fila.【F:ansible/roles/backend/tasks/main.yml†L1-L116】
- A role do frontend compila o SPA com Vite e publica artefatos no Nginx, renderizando `.env.production` com `VITE_API_URL` apontando para a API publicada.【F:ansible/roles/frontend/tasks/main.yml†L1-L101】【F:ansible/group_vars/all/all.yml†L1-L31】
- A role `schedule` cria timer systemd para `php artisan schedule:run`, mantendo rotinas recorrentes ativas no servidor.【F:ansible/roles/schedule/tasks/main.yml†L1-L18】

## Pré-requisitos locais
- Node.js 20 LTS (ou compatível com Vite 5) e npm.
- PHP 8.2 com extensões comuns (pdo_pgsql/pdo_mysql, mbstring, bcmath, intl).
- Composer 2.x.
- Banco PostgreSQL ou MySQL (produção usa Postgres). Configure um banco e credenciais de acesso.
- Google Chrome/Chromium e Node.js disponíveis no PATH para o Browsershot gerar PDFs.【F:ansible/group_vars/all/all.yml†L23-L27】

## Configurando o backend
1. Instale dependências PHP:
   ```bash
   cd api-laravel
   composer install
   ```
2. Copie o arquivo de ambiente e ajuste variáveis de banco, filas e URLs:
   ```bash
   cp .env.example .env
   ```
   - Defina `APP_URL`, `DB_CONNECTION`, `DB_HOST`, `DB_DATABASE`, `DB_USERNAME`, `DB_PASSWORD` e `QUEUE_CONNECTION` (padrão `database`).【F:api-laravel/.env.example†L1-L44】
3. Gere chaves de aplicação e JWT:
   ```bash
   php artisan key:generate
   php artisan jwt:secret
   ```
4. Crie tabelas e dados de apoio:
   ```bash
   php artisan migrate --seed
   ```
   (o seeder padrão cria usuários fictícios para testes rápidos.)【F:api-laravel/database/seeders/DatabaseSeeder.php†L11-L20】
5. Crie o link de storage público (necessário para imagens dos PDFs):
   ```bash
   php artisan storage:link
   ```
6. Execute a aplicação. Em desenvolvimento, utilize o script que inicia servidor HTTP, queue listener, logs em tempo real e Vite de forma integrada:
   ```bash
   composer run dev
   ```
   Esse script habilita `php artisan serve`, `queue:listen`, `pail` e `npm run dev` simultaneamente.【F:api-laravel/composer.json†L52-L55】

> **Filas & agendamentos**: se preferir executar processos manualmente, rode `php artisan queue:listen` em um terminal e `php artisan schedule:work` em outro. Em produção, os serviços systemd criados pelas roles `backend` e `schedule` mantêm esses processos ativos.【F:ansible/roles/backend/tasks/main.yml†L102-L115】【F:ansible/roles/schedule/tasks/main.yml†L1-L18】

## Configurando o frontend
1. Instale as dependências JavaScript:
   ```bash
   cd front
   npm install
   ```
2. Crie um arquivo `.env` (ou `.env.local`) definindo a URL pública da API:
   ```bash
   echo "VITE_API_URL=http://localhost:8000/api" > .env.local
   ```
3. Execute o servidor de desenvolvimento Vite:
   ```bash
   npm run dev
   ```
4. Acesse `http://localhost:5173` e utilize as credenciais criadas via `/register` ou o usuário seed.

> O frontend envia automaticamente o token JWT armazenado no navegador e revalida a sessão em cada navegação.【F:front/src/utils/auth.js†L5-L25】【F:front/src/main.js†L15-L44】

## Relatórios, dashboards e fluxos principais
- **Cadastros**: telas para criação/edição de consumidores, usinas, vínculos e distribuidores. O backend expõe rotas REST para cada entidade com suporte a atualização/remoção.【F:api-laravel/routes/api.php†L36-L135】
- **Distribuição de créditos**: tela dedicada para vincular consumidores à geração das usinas e administrar saldos (rota `/usina-consumidor`).【F:front/src/router/index.js†L65-L75】
- **Dashboards & KPIs**: cards numéricos e gráficos no menu “Relatórios”, consolidando geração média, consumo total, saldo disponível e evolução de cadastros por ano.【F:front/src/components/Relatorios.vue†L1-L200】
- **Cálculo mensal**: formulário que envia tarifa, geração e valores pagos para a rota `/usinas/{id}/faturamento/{ano}/mes/{mes}/calculo`, obtendo créditos, reservas e indicadores ambientais.【F:api-laravel/routes/api.php†L125-L126】【F:api-laravel/app/services/CalculoGeracaoService.php†L34-L147】
- **Relatórios PDF**: botões no frontend requisitam `/gerar-pdf-usina/{id}` e `/gerar-pdf-consumidores/{id}` para baixar documentos formatados com gráficos e totais financeiros.【F:api-laravel/routes/api.php†L134-L135】【F:front/src/views/Home.vue†L120-L220】

## Automação de infraestrutura e deploy
1. **Provisionamento AWS com Terraform**
   ```bash
   cd terraform
   terraform init
   terraform plan -var="allowed_ssh_cidr=SEU_IP/32" -var="ssh_public_key=..."
   terraform apply -var="allowed_ssh_cidr=SEU_IP/32" -var="ssh_public_key=..."
   ```
   Ajuste demais variáveis (`project_name`, `db_*`, tipos de instância) conforme necessidade.【F:terraform/variables.tf†L8-L122】

2. **Deploy com Ansible**
   - Atualize `inventory.ini` com IPs/usuários corretos e copie a chave SSH do bastion para `/tmp/key_nopass.pem` (ou ajuste `ansible_ssh_common_args`).【F:ansible/inventory.ini†L1-L8】
   - Popule `group_vars/all/vault.yml` (criptografado) com segredos como `DB_PASSWORD`, `APP_KEY` e `JWT_SECRET` — as variáveis dinâmicas já apontam para esses valores.【F:ansible/group_vars/all/all.yml†L14-L31】
   - Execute o playbook:
     ```bash
     cd ansible
     ansible-playbook site.yml
     ```
     O playbook clona o repositório, instala dependências, constrói os artefatos e configura Nginx para servir API e SPA.【F:ansible/site.yml†L1-L47】【F:ansible/roles/backend/tasks/main.yml†L1-L116】【F:ansible/roles/frontend/tasks/main.yml†L1-L101】

## Testes e qualidade
- Backend: `php artisan test` (ou `vendor/bin/phpunit`) para rodar a suíte Laravel.
- Frontend: `npm run build` garante que o bundle produção compila sem erros.
- Laravel Pint pode ser executado para padronizar código PHP: `./vendor/bin/pint`.

## Autor
Desenvolvido por **Emerson Okopnik** – contato: <emer00k@gmail.com>.
