# 📢 Ouvidoria Escolar — EEEP Dom Walfrido

Sistema web de ouvidoria desenvolvido pelo Grêmio Estudantil para receber e gerenciar manifestações de alunos, professores e funcionários da escola.

---

## 🗂️ Estrutura de Pastas

```
ouvidoriaDW/
├── .env.example              ← Modelo de variáveis de ambiente (copie para .env)
├── .htaccess                 ← Regras de segurança e cache do Apache
├── .gitignore                ← Arquivos ignorados pelo Git
├── index.html                ← Página pública principal
├── login.html                ← Login do aluno
├── cadastro.html             ← Cadastro do aluno
├── redefinir.html            ← Redefinição de senha
├── README.md                 ← Este arquivo
│
├── adm/                      ← Painel administrativo (Grêmio)
│   ├── login.html
│   ├── index.html            ← Dashboard com estatísticas e gráficos
│   └── manifestacao.html     ← Detalhe, resposta e mudança de status
│
├── api/                      ← Backend PHP
│   ├── config/
│   │   ├── env.php           ← Loader do arquivo .env
│   │   ├── db.php            ← Conexão PDO (singleton)
│   │   └── cors.php          ← Headers CORS e funções auxiliares
│   ├── adm/                  ← Endpoints exclusivos do painel admin
│   │   ├── session_check.php ← Middleware de autenticação admin
│   │   ├── me.php            ← Verifica sessão e retorna dados do admin
│   │   ├── login.php
│   │   ├── logout.php
│   │   ├── stats.php         ← Estatísticas do dashboard
│   │   ├── manifestacoes.php ← Listagem com filtros e paginação
│   │   └── atualizar.php     ← Atualizar status e feedback
│   ├── cadastro.php
│   ├── login.php
│   ├── logout.php
│   ├── session.php
│   ├── forgot.php            ← Solicitar recuperação de senha
│   ├── reset.php             ← Redefinir senha via token
│   ├── manifestacoes.php     ← Registrar nova manifestação
│   ├── consulta.php          ← Consultar protocolo público
│   ├── setores.php           ← Listar setores
│   ├── tipos.php             ← Listar tipos de manifestação
│   └── upload.php            ← Upload de arquivos anexados
│
├── assets/
│   ├── css/
│   │   ├── style.css         ← Estilos principais (paleta Ceará + Dom Walfrido)
│   │   └── auth.css          ← Estilos das páginas de autenticação
│   ├── js/
│   │   ├── utils.js          ← Funções utilitárias (toast, counter, sanitize)
│   │   ├── form.js           ← Formulário multi-step de manifestação
│   │   ├── ajax.js           ← Comunicação com a API
│   │   ├── auth.js           ← Cadastro e login do aluno
│   │   └── main.js           ← Interações UI gerais
│   └── img/
│       └── logo2.png         ← Logo da escola
│
├── db/
│   ├── schemaOuvidoriaDw.sql ← Schema completo e único do banco de dados
│   └── gerar_hash.php        ← Gera hash bcrypt (⚠️ remover em produção)
│
└── uploads/                  ← Arquivos enviados (gerado automaticamente)
    └── manifestacoes/
        └── {IDmanifest}/
```

---

## ⚙️ Requisitos

- PHP 8.1 ou superior
- MySQL 5.7+ ou MariaDB 10.4+
- Apache com `mod_rewrite` e `mod_headers` habilitados
- Extensões PHP: `pdo_mysql`, `fileinfo`, `mbstring`

---

## 🚀 Deploy — Passo a passo

### 1. Banco de dados

Importe o schema único no phpMyAdmin ou via linha de comando:

```sql
SOURCE db/schemaOuvidoriaDw.sql;
```

O arquivo já inclui todas as tabelas, views e dados iniciais (setores e tipos).

Crie o primeiro administrador — gere o hash em `db/gerar_hash.php?senha=SuaSenha`:

```sql
INSERT INTO tbadm (nome, cargo, email, senha)
VALUES ('Nome do Membro', 'Presidente', 'email@escola.edu.br', '$2y$12$HASH_GERADO_AQUI');
```

### 2. Variáveis de ambiente

```bash
cp .env.example .env
```

Edite o `.env` com as credenciais reais:

```dotenv
DB_HOST=localhost
DB_PORT=3306
DB_NAME=dbouvidoria
DB_USER=usuario_do_banco
DB_PASS=senha_do_banco

APP_URL=https://seudominio.com.br
APP_ENV=production

MAIL_HOST=smtp.gmail.com
MAIL_PORT=587
MAIL_USER=seuemail@gmail.com
MAIL_PASS=senha_de_app_do_gmail
MAIL_FROM_NAME=Ouvidoria Escolar
```

### 3. Ativar HTTPS

No `.htaccess`, descomente o bloco de redirecionamento:

```apache
RewriteEngine On
RewriteCond %{HTTPS} off
RewriteRule ^(.*)$ https://%{HTTP_HOST}%{REQUEST_URI} [L,R=301]
```

### 4. Remover arquivo de geração de hash

```bash
rm db/gerar_hash.php
```

### 5. Permissões da pasta de uploads

```bash
chmod 750 uploads/
```

---

## 🔐 Segurança implementada

| Recurso | Detalhe |
|---|---|
| Senhas | bcrypt com cost 12 |
| SQL Injection | PDO com prepared statements em todos os endpoints |
| XSS | htmlspecialchars em todas as saídas |
| CSRF | SameSite=Strict nos cookies de sessão |
| Session Fixation | session_regenerate_id() após login |
| Cookie Hijacking | secure=true em produção via APP_ENV |
| Upload malicioso | Validação MIME real com finfo + nome aleatório |
| Acesso não autorizado | Middleware session_check.php em todos os endpoints admin |
| Rate limiting | 5 tentativas por 5 minutos no login admin |
| Arquivos sensíveis | .htaccess bloqueia .env, db/, uploads/ |
| Sessões separadas | Prefixo adm_ isola sessão do admin da sessão do aluno |

---

## 🛠️ Tecnologias

- **PHP 8.x** — Backend e API REST
- **MySQL / MariaDB** — Banco de dados relacional
- **Bootstrap 5.3** — Layout e componentes responsivos
- **jQuery 3.7.1** — AJAX e manipulação DOM
- **Font Awesome 6.5** — Ícones
- **Chart.js 4.4** — Gráficos no painel admin
- **Google Fonts** — Fraunces + DM Sans

---

## 🧪 Ambiente de desenvolvimento

1. Instale o [XAMPP](https://www.apachefriends.org/)
2. Clone o repositório em `htdocs/ouvidoriaDW`
3. Importe o banco: `SOURCE db/schemaOuvidoriaDw.sql`
4. Configure o `.env` com `APP_ENV=development` e `APP_URL=http://localhost/ouvidoriaDW`
5. Acesse `http://localhost/ouvidoriaDW`

> Em desenvolvimento, o cookie `secure` é automaticamente desativado para funcionar em HTTP local.

---

## 📜 Legislação de base

- **Lei nº 13.460/2017** — Defesa dos direitos do usuário dos serviços públicos
- **Lei nº 13.709/2018 (LGPD)** — Proteção de dados pessoais
- **Lei nº 12.527/2011 (LAI)** — Lei de Acesso à Informação

---

## 👨‍💻 Desenvolvido por

Carlos Eduardo e Wellington
Alunos do curso de Informática — EEEP Dom Walfrido Vieira Teixeira
Projeto desenvolvido como sistema de ouvidoria escolar para o Grêmio Estudantil.