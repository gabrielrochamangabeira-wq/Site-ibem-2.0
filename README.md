# 🎓 IBEM - Instituto Brasileiro de Educação Moderna
### Portal Institucional, Catálogo de Cursos & Sistema de Gestão Escolar

> *"Pensou em cursos, pense bem. Faça IBEM!"*

![PHP](https://img.shields.io/badge/PHP-7.4%2B-777BB4?style=for-the-badge&logo=php&logoColor=white)
![MySQL](https://img.shields.io/badge/MySQL-005C84?style=for-the-badge&logo=mysql&logoColor=white)
![HTML5](https://img.shields.io/badge/HTML5-E34F26?style=for-the-badge&logo=html5&logoColor=white)
![CSS3](https://img.shields.io/badge/CSS3-1572B6?style=for-the-badge&logo=css3&logoColor=white)
![JavaScript](https://img.shields.io/badge/JavaScript-F7DF1E?style=for-the-badge&logo=javascript&logoColor=black)
![MikroTik](https://img.shields.io/badge/MikroTik-Hotspot%20Ready-black?style=for-the-badge)

---

## 📌 Visão Geral do Projeto

O **IBEM (Instituto Brasileiro de Educação Moderna)** é uma solução web integrada voltada para instituições de ensino técnico, profissionalizante e servidores de rede local (**MikroTik Hotspot / Captive Portal**). 

O sistema combina uma **Landing Page Institucional moderna de alto impacto**, com apresentação de cursos técnicos e profissionalizantes, e um **Portal Escolar completo** com três níveis de acesso (Administrador, Professor e Aluno), distribuição de apostilas, módulos com síntese de voz (TTS) e ficha de matrícula online.

Projetado com arquitetura **100% autossuficiente e offline-first** (ícones vetoriais SVG animados e estilos nativos), garantindo carregamento instantâneo mesmo em redes locais, captive portals ou sem dependência de CDNs de terceiros.

---

## 🚀 Principais Funcionalidades

### 🌟 1. Landing Page Institucional
- **Cabeçalho Limpo e Moderno**: Logo oficial centralizado, tipografia Google Fonts (*Poppins* e *Inter*) e destaques em ribbons modernos.
- **Navegação Categorizada**: Filtro rápido entre Cursos Técnicos e Cursos Profissionalizantes.
- **Micro-animações Contínuas em SVG**: Ícones vetoriais leves com animações pulsantes e interativas ao clique, sem uso de GIFs ou bibliotecas pesadas.
- **Totalmente Responsivo**: Otimizado para smartphones, tablets e desktops.

### 🎓 2. Catálogo Oficial com 26 Cursos Técnicos (12 a 14 meses)
Grade técnica completa com modal interativo detalhando cargas horárias, habilitações e botão para matrícula direta:
1. **01 - Segurança do Trabalho** (1440h)
2. **02 - Eletrotécnica** (1440h)
3. **03 - Mecânica Industrial** (1440h)
4. **04 - Administração** (1200h)
5. **05 - Automação Industrial** (1440h)
6. **06 - Edificações** (1440h)
7. **07 - Eletrônica** (1440h)
8. **08 - Logística** (1000h)
9. **09 - Contabilidade** (1000h)
10. **10 - Nutrição e Dietética** (1440h)
11. **11 - Secretaria Escolar** (1000h)
12. **12 - Recursos Humanos** (1000h)
13. **13 - Marketing e Comunicação** (1000h)
14. **14 - Informática** (1260h)
15. **15 - Vendas** (1000h)
16. **16 - Estética e Cosmetologia** (1440h)
17. **17 - Transações Imobiliárias** (1200h)
18. **18 - Informática para Internet** (1240h)
19. **19 - Guia de Turismo** (1000h)
20. **20 - Serviços Jurídicos** (1440h)
21. **21 - Redes de Computadores** (1200h)
22. **22 - Eletroeletrônica** (1440h)
23. **23 - Manutenção e Suporte em Informática** (1440h)
24. **24 - Programação de Jogos Digitais** (1440h)
25. **25 - Computação Gráfica** (1200h)
26. **26 - Desenvolvimento de Sistemas** (1440h)

### 💼 3. Cursos Profissionalizantes & Qualificação Rápida
- **Informática Básica e Avançada** (12 meses, mais de 20 cursos práticos)
- **Eletricista Predial & Residencial** (4 semanas em salas temáticas)
- **Máquinas Pesadas** (Operador de Escavadeira, Retroescavadeira e Pá Carregadeira)
- **Pacote Administrativo** (10 meses, 14 cursos)
- **Pacote de Saúde** (10 meses, 14 cursos essenciais)
- **Pacote de Beleza** (07 meses, estética facial e massoterapia)
- **Pacote de Veterinária** (07 meses, auxiliar veterinário e pet shop)
- **Pacote de Turismo** (07 meses, agenciamento e guia local)
- **Pacote KIDS** (20 meses, informática, inglês dinâmico e natação)
- **Natação** (Horários matutinos, vespertinos e noturnos)
- **EJA - Educação de Jovens e Adultos** (04 meses, modalidade 100% EAD)

### 👥 4. Portal Escolar / Gestão de Alunos e Professores
- **Painel do Aluno**: Acesso a materiais didáticos, apostilas em PDF e status de matrícula.
- **Salas de Aula Interativas**: Módulos de estudo com **leitura por voz (Web Speech API / Text-to-Speech)** e registro de conclusão.
- **Painel do Professor**: Upload seguro de materiais didáticos para turmas e contagem de materiais ativos.
- **Painel do Administrador (`admin.php`)**: Interface unificada de gestão escolar conectada via login do `index.php`. Inclui **Dashboard** com métricas analíticas e acompanhamento de **solicitações de aulas pendentes** com aprovação em 1 clique, área de **Adicionar Aluno** (com gerador de senha e liberação opcional da 1ª aula), área de **Adicionar Aula** (com suporte a conteúdo didático) e central interativa de **Liberar Aulas** (por aluno individual ou coletivo).
- **Ficha de Matrícula Online**: Formulário completo (`matricula.html`) com validação de dados pessoais, cursos selecionados e termos de aceite.
- **Integração MikroTik Hotspot**: Arquivos `advert.html` e `error.html` para redirecionamento nativo em roteadores MikroTik RouterOS.

---

## 📁 Estrutura de Arquivos

```text
├── assets/                     # Recursos visuais e estilos
│   ├── css/
│   │   └── style.css           # Folha de estilos unificada (design system & animações)
│   └── img/
│       ├── logo.png            # Logotipo oficial em alta resolução
│       └── curso*.png/jpg/jpeg # Imagens e ilustrações dos cursos
│
├── database/                   # Banco de dados
│   └── database.sql            # Script SQL de criação das tabelas e dados iniciais
│
├── portal/                     # Portal Escolar Otimizado (PHP)
│   ├── admin.php               # Painel Administrativo (Dashboard, Alunos, Aulas, Liberação de Aulas)
│   ├── aluno.php               # Painel do Aluno (Grade de aulas, status e solicitação de liberação)
│   ├── aulas.php               # Sala de aula interativa com síntese de voz (TTS) e conclusão
│   ├── login.php               # Processamento seguro de autenticação (Admin e Aluno)
│   ├── logout.php              # Encerramento de sessão
│   └── config.php              # Conexão com o banco de dados (MySQL)
│
├── .gitignore                  # Arquivos ignorados pelo Git
├── index.php                   # Landing Page oficial e página principal de acesso ao portal
├── matricula.html              # Ficha cadastral e matrícula online completa
├── LICENSE                     # Licença MIT
└── README.md                   # Documentação completa do projeto
```

---

## 🛠️ Requisitos do Sistema

- **Servidor Web**: Apache 2.4+ / Nginx / XAMPP / WampServer / Servidor MikroTik Hotspot
- **Linguagem**: PHP 7.4 ou superior
- **Extensões PHP**: `mysqli`, `session`, `fileinfo`
- **Banco de Dados**: MySQL 5.7+ ou MariaDB 10.3+

---

## ⚙️ Instalação e Configuração

### 1. Clonar ou Baixar o Repositório
```bash
git clone https://github.com/SEU-USUARIO/servidor-ibem.git
cd servidor-ibem
```

### 2. Configurar o Banco de Dados
1. Abra seu gerenciador MySQL (ex: **phpMyAdmin**, HeidiSQL ou terminal MySQL).
2. Crie o banco de dados e importe o arquivo `database.sql`:
```sql
CREATE DATABASE portal_escolar CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
```
3. Execute o script contido em [database.sql](database.sql).

### 3. Configurar Conexão do PHP
1. Renomeie o arquivo `config.example.php` para `config.php` (ou edite o arquivo `config.php` existente):
```php
<?php
$host = "localhost";
$db   = "portal_escolar";
$user = "seu_usuario";
$pass = "sua_senha";

$conn = new mysqli($host, $user, $pass, $db);
if ($conn->connect_error) {
    die("Erro de conexão: " . $conn->connect_error);
}
?>
```

### 4. Permissões da Pasta de Uploads
Certifique-se de que a pasta `uploads/` possui permissão de escrita pelo servidor web:
```bash
chmod 775 uploads/
```

### 5. Executar o Projeto
Se estiver usando o servidor embutido do PHP para testes:
```bash
php -S localhost:8000
```
Acesse `http://localhost:8000` em seu navegador.

---

## 🔑 Acessos de Teste (Usuários Padrão)

Após importar o arquivo `database.sql`, os seguintes perfis já estarão pré-configurados:

| Tipo | E-mail de Acesso | Painel Redirecionado |
| :--- | :--- | :--- |
| **Administrador** | `admin@ibem.com.br` | `admin.php` |
| **Professor** | `professor@ibem.com.br` | `professor.php` |
| **Aluno** | `aluno@ibem.com.br` | `aluno.php` |

---

## 📡 Integração com MikroTik Hotspot

Para utilizar como página de autenticação de Hotspot:
1. Copie os arquivos da pasta para o diretório de arquivos do RouterOS (via FTP, Winbox em `Files -> hotspot`).
2. O arquivo `error.html` e `advert.html` já estão compatíveis com as variáveis de redirecionamento do RouterOS.
3. Para testes em rede interna, o formulário de login redireciona diretamente para o Portal Escolar.

---

## 📄 Licença

Este projeto está licenciado sob os termos da licença [MIT](LICENSE).

---

<p align="center">
  Desenvolvido para <strong>Instituto Brasileiro de Educação Moderna (IBEM)</strong><br>
  <em>"Pensou em cursos, pense bem. Faça IBEM!"</em>
</p>
