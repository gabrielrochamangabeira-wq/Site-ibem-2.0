<?php
session_start();

/* ========================================================
   AUTENTICAÇÃO & CONTROLE DE ACESSO
   Permite apenas administradores autenticados via login.php
======================================================== */
if (!isset($_SESSION['usuario']) || !isset($_SESSION['tipo']) || $_SESSION['tipo'] !== 'admin') {
    // Redireciona para a versão HTML completa interativa do painel administrativo
    header("Location: admin.html");
    exit();
}

require_once "config.php";

if (!$conn) {
    header("Location: admin.html");
    exit();
}

/* ========================================================
   GARANTIA DAS TABELAS NO BANCO DE DADOS
======================================================== */
// Tabela de solicitações de liberação de aulas
$conn->query("CREATE TABLE IF NOT EXISTS `solicitacoes_aulas` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `usuario` VARCHAR(255) NOT NULL,
  `modulo_id` INT(11) NOT NULL,
  `status` ENUM('pendente', 'aprovada', 'rejeitada') NOT NULL DEFAULT 'pendente',
  `data_solicitacao` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `data_resposta` TIMESTAMP NULL DEFAULT NULL,
  `observacao` VARCHAR(255) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_solic_usuario` (`usuario`),
  KEY `idx_solic_modulo` (`modulo_id`),
  KEY `idx_solic_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;");

// Tabela de progresso e liberação de aulas
$conn->query("CREATE TABLE IF NOT EXISTS `progresso_aluno` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `usuario` VARCHAR(255) NOT NULL,
  `modulo_id` INT(11) NOT NULL,
  `liberado` TINYINT(1) DEFAULT 1,
  `concluido` TINYINT(1) DEFAULT 0,
  PRIMARY KEY (`id`),
  KEY `idx_progresso_usuario` (`usuario`),
  KEY `idx_progresso_modulo` (`modulo_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;");

// Tabela de módulos/aulas
$conn->query("CREATE TABLE IF NOT EXISTS `modulos` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `titulo` VARCHAR(255) NOT NULL,
  `texto` LONGTEXT DEFAULT NULL,
  `ordem` INT(11) DEFAULT 1,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;");

$msg_sucesso = "";
$msg_erro = "";
$tab = isset($_GET['tab']) ? $_GET['tab'] : 'dashboard';

/* ========================================================
   PROCESSAMENTO DE AÇÕES DO ADMINISTRADOR
======================================================== */
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $acao = isset($_POST['acao']) ? $_POST['acao'] : '';

    // 1. ADICIONAR ALUNO
    if ($acao === 'adicionar_aluno') {
        $tab = 'alunos';
        $nome = trim($_POST['nome'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $senha = $_POST['senha'] ?? '';
        $liberar_primeira = isset($_POST['liberar_primeira']) ? 1 : 0;

        if (empty($nome) || empty($email) || empty($senha)) {
            $msg_erro = "Por favor, preencha todos os campos obrigatórios para o cadastro do aluno.";
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $msg_erro = "Formato de e-mail inválido.";
        } else {
            // Verificar duplicidade de e-mail
            $stmt = $conn->prepare("SELECT id FROM usuarios WHERE email = ?");
            $stmt->bind_param("s", $email);
            $stmt->execute();
            if ($stmt->get_result()->num_rows > 0) {
                $msg_erro = "Já existe um usuário cadastrado com o e-mail informado.";
            } else {
                $senha_hash = password_hash($senha, PASSWORD_DEFAULT);
                $tipo = 'aluno';
                $stmt_insert = $conn->prepare("INSERT INTO usuarios (nome, email, senha, tipo) VALUES (?, ?, ?, ?)");
                $stmt_insert->bind_param("ssss", $nome, $email, $senha_hash, $tipo);

                if ($stmt_insert->execute()) {
                    $msg_sucesso = "Aluno <strong>" . htmlspecialchars($nome) . "</strong> cadastrado com sucesso!";

                    // Liberar primeira aula automaticamente se solicitado
                    if ($liberar_primeira === 1) {
                        $res_mod = $conn->query("SELECT id FROM modulos ORDER BY ordem ASC, id ASC LIMIT 1");
                        if ($mod = $res_mod->fetch_assoc()) {
                            $mod_id = $mod['id'];
                            $conn->query("INSERT INTO progresso_aluno (usuario, modulo_id, liberado, concluido) VALUES ('" . $conn->real_escape_string($nome) . "', $mod_id, 1, 0) ON DUPLICATE KEY UPDATE liberado=1");
                            $msg_sucesso .= " Primeira aula liberada automaticamente.";
                        }
                    }
                } else {
                    $msg_erro = "Falha ao cadastrar aluno no banco de dados.";
                }
            }
        }
    }

    // 2. EXCLUIR ALUNO
    elseif ($acao === 'excluir_aluno') {
        $tab = 'alunos';
        $id_aluno = intval($_POST['id_aluno'] ?? 0);
        if ($id_aluno > 0) {
            $stmt_info = $conn->prepare("SELECT nome, tipo FROM usuarios WHERE id = ?");
            $stmt_info->bind_param("i", $id_aluno);
            $stmt_info->execute();
            $res = $stmt_info->get_result();
            if ($u = $res->fetch_assoc()) {
                if ($u['tipo'] === 'admin') {
                    $msg_erro = "Não é permitido excluir uma conta de Administrador por esta tela.";
                } else {
                    $aluno_nome = $u['nome'];
                    $conn->query("DELETE FROM usuarios WHERE id = $id_aluno");
                    $conn->query("DELETE FROM progresso_aluno WHERE usuario = '" . $conn->real_escape_string($aluno_nome) . "'");
                    $conn->query("DELETE FROM solicitacoes_aulas WHERE usuario = '" . $conn->real_escape_string($aluno_nome) . "'");
                    $msg_sucesso = "Aluno excluído com sucesso do sistema.";
                }
            }
        }
    }

    // 3. ADICIONAR AULA / MÓDULO
    elseif ($acao === 'adicionar_aula') {
        $tab = 'aulas';
        $titulo = trim($_POST['titulo'] ?? '');
        $ordem = intval($_POST['ordem'] ?? 1);
        $texto = trim($_POST['texto'] ?? '');
        $liberar_para_todos = isset($_POST['liberar_para_todos']) ? 1 : 0;

        if (empty($titulo)) {
            $msg_erro = "O título da aula é obrigatório.";
        } else {
            $stmt_aula = $conn->prepare("INSERT INTO modulos (titulo, ordem, texto) VALUES (?, ?, ?)");
            $stmt_aula->bind_param("sis", $titulo, $ordem, $texto);
            if ($stmt_aula->execute()) {
                $novo_modulo_id = $stmt_aula->insert_id;
                $msg_sucesso = "Nova aula cadastrada com sucesso na grade curricular!";

                // Liberar para todos os alunos se selecionado
                if ($liberar_para_todos === 1) {
                    $res_alunos = $conn->query("SELECT nome FROM usuarios WHERE tipo = 'aluno'");
                    $count_liberados = 0;
                    while ($al = $res_alunos->fetch_assoc()) {
                        $al_nome = $conn->real_escape_string($al['nome']);
                        $conn->query("INSERT INTO progresso_aluno (usuario, modulo_id, liberado, concluido) VALUES ('$al_nome', $novo_modulo_id, 1, 0) ON DUPLICATE KEY UPDATE liberado=1");
                        $count_liberados++;
                    }
                    $msg_sucesso .= " A aula foi disponibilizada para $count_liberados aluno(s).";
                }
            } else {
                $msg_erro = "Erro ao registrar a aula no banco de dados.";
            }
        }
    }

    // 4. EXCLUIR AULA / MÓDULO
    elseif ($acao === 'excluir_aula') {
        $tab = 'aulas';
        $id_aula = intval($_POST['id_aula'] ?? 0);
        if ($id_aula > 0) {
            $conn->query("DELETE FROM modulos WHERE id = $id_aula");
            $conn->query("DELETE FROM progresso_aluno WHERE modulo_id = $id_aula");
            $conn->query("DELETE FROM solicitacoes_aulas WHERE modulo_id = $id_aula");
            $msg_sucesso = "Aula e históricos removidos com sucesso.";
        }
    }

    // 5. LIBERAR / BLOQUEAR AULA INDIVIDUALMENTE
    elseif ($acao === 'alterar_liberacao') {
        $tab = 'liberar';
        $usuario_aluno = trim($_POST['usuario_aluno'] ?? '');
        $modulo_id = intval($_POST['modulo_id'] ?? 0);
        $novo_status = intval($_POST['status'] ?? 1); // 1 = liberado, 0 = bloqueado

        if (!empty($usuario_aluno) && $modulo_id > 0) {
            $u_safe = $conn->real_escape_string($usuario_aluno);
            
            // Verifica se já tem registro
            $chk = $conn->query("SELECT id FROM progresso_aluno WHERE usuario = '$u_safe' AND modulo_id = $modulo_id");
            if ($chk->num_rows > 0) {
                $conn->query("UPDATE progresso_aluno SET liberado = $novo_status WHERE usuario = '$u_safe' AND modulo_id = $modulo_id");
            } else {
                $conn->query("INSERT INTO progresso_aluno (usuario, modulo_id, liberado, concluido) VALUES ('$u_safe', $modulo_id, $novo_status, 0)");
            }

            // Se estiver liberando, marca solicitações pendentes como aprovadas
            if ($novo_status === 1) {
                $conn->query("UPDATE solicitacoes_aulas SET status = 'aprovada', data_resposta = NOW() WHERE usuario = '$u_safe' AND modulo_id = $modulo_id AND status = 'pendente'");
                $msg_sucesso = "Aula liberada com sucesso para <strong>" . htmlspecialchars($usuario_aluno) . "</strong>!";
            } else {
                $msg_sucesso = "Acesso à aula bloqueado para <strong>" . htmlspecialchars($usuario_aluno) . "</strong>.";
            }
        }
    }

    // 6. LIBERAR TODAS AS AULAS PARA UM ALUNO
    elseif ($acao === 'liberar_todas_aluno') {
        $tab = 'liberar';
        $usuario_aluno = trim($_POST['usuario_aluno'] ?? '');
        if (!empty($usuario_aluno)) {
            $u_safe = $conn->real_escape_string($usuario_aluno);
            $res_all_mod = $conn->query("SELECT id FROM modulos");
            while ($m = $res_all_mod->fetch_assoc()) {
                $mid = $m['id'];
                $conn->query("INSERT INTO progresso_aluno (usuario, modulo_id, liberado, concluido) VALUES ('$u_safe', $mid, 1, 0) ON DUPLICATE KEY UPDATE liberado=1");
            }
            $conn->query("UPDATE solicitacoes_aulas SET status = 'aprovada', data_resposta = NOW() WHERE usuario = '$u_safe' AND status = 'pendente'");
            $msg_sucesso = "Todas as aulas foram liberadas com sucesso para <strong>" . htmlspecialchars($usuario_aluno) . "</strong>!";
        }
    }

    // 7. LIBERAR UMA AULA ESPECÍFICA PARA TODOS OS ALUNOS
    elseif ($acao === 'liberar_aula_todos') {
        $tab = 'liberar';
        $modulo_id = intval($_POST['modulo_id'] ?? 0);
        if ($modulo_id > 0) {
            $res_alunos = $conn->query("SELECT nome FROM usuarios WHERE tipo = 'aluno'");
            $cont = 0;
            while ($al = $res_alunos->fetch_assoc()) {
                $u_safe = $conn->real_escape_string($al['nome']);
                $conn->query("INSERT INTO progresso_aluno (usuario, modulo_id, liberado, concluido) VALUES ('$u_safe', $modulo_id, 1, 0) ON DUPLICATE KEY UPDATE liberado=1");
                $cont++;
            }
            $conn->query("UPDATE solicitacoes_aulas SET status = 'aprovada', data_resposta = NOW() WHERE modulo_id = $modulo_id AND status = 'pendente'");
            $msg_sucesso = "A aula selecionada foi liberada para todos os $cont aluno(s) ativos!";
        }
    }

    // 8. DASHBOARD: APROVAR SOLICITAÇÃO DE AULA
    elseif ($acao === 'aprovar_solicitacao') {
        $tab = 'dashboard';
        $solic_id = intval($_POST['solic_id'] ?? 0);
        if ($solic_id > 0) {
            $stmt_s = $conn->prepare("SELECT usuario, modulo_id FROM solicitacoes_aulas WHERE id = ?");
            $stmt_s->bind_param("i", $solic_id);
            $stmt_s->execute();
            $res_s = $stmt_s->get_result();
            if ($solic = $res_s->fetch_assoc()) {
                $u_safe = $conn->real_escape_string($solic['usuario']);
                $m_id = intval($solic['modulo_id']);

                // Atualiza a solicitação para aprovada
                $conn->query("UPDATE solicitacoes_aulas SET status = 'aprovada', data_resposta = NOW() WHERE id = $solic_id");

                // Concede a liberação no progresso do aluno
                $chk = $conn->query("SELECT id FROM progresso_aluno WHERE usuario = '$u_safe' AND modulo_id = $m_id");
                if ($chk->num_rows > 0) {
                    $conn->query("UPDATE progresso_aluno SET liberado = 1 WHERE usuario = '$u_safe' AND modulo_id = $m_id");
                } else {
                    $conn->query("INSERT INTO progresso_aluno (usuario, modulo_id, liberado, concluido) VALUES ('$u_safe', $m_id, 1, 0)");
                }

                $msg_sucesso = "Solicitação aprovada com sucesso! A aula já está disponível para o aluno <strong>" . htmlspecialchars($solic['usuario']) . "</strong>.";
            }
        }
    }

    // 9. DASHBOARD: REJEITAR SOLICITAÇÃO DE AULA
    elseif ($acao === 'rejeitar_solicitacao') {
        $tab = 'dashboard';
        $solic_id = intval($_POST['solic_id'] ?? 0);
        if ($solic_id > 0) {
            $conn->query("UPDATE solicitacoes_aulas SET status = 'rejeitada', data_resposta = NOW() WHERE id = $solic_id");
            $msg_sucesso = "A solicitação foi recusada.";
        }
    }
}

/* ========================================================
   CONSULTA DE DADOS PARA MÉTRICAS E VISUALIZAÇÃO
======================================================== */
// Contadores de métricas
$total_alunos = ($conn->query("SELECT COUNT(*) as c FROM usuarios WHERE tipo = 'aluno'")->fetch_assoc())['c'] ?? 0;
$total_aulas = ($conn->query("SELECT COUNT(*) as c FROM modulos")->fetch_assoc())['c'] ?? 0;
$total_pendentes = ($conn->query("SELECT COUNT(*) as c FROM solicitacoes_aulas WHERE status = 'pendente'")->fetch_assoc())['c'] ?? 0;
$total_concluidas = ($conn->query("SELECT COUNT(*) as c FROM progresso_aluno WHERE concluido = 1")->fetch_assoc())['c'] ?? 0;

// Lista de solicitações de aulas pendentes
$sql_pendentes = "SELECT s.id, s.usuario, s.modulo_id, s.data_solicitacao, s.observacao,
                         m.titulo AS aula_titulo, m.ordem AS aula_ordem,
                         u.email AS aluno_email
                  FROM solicitacoes_aulas s
                  JOIN modulos m ON s.modulo_id = m.id
                  LEFT JOIN usuarios u ON u.nome = s.usuario
                  WHERE s.status = 'pendente'
                  ORDER BY s.data_solicitacao ASC";
$res_pendentes = $conn->query($sql_pendentes);

// Histórico das últimas solicitações respondidas
$sql_historico = "SELECT s.id, s.usuario, s.status, s.data_solicitacao, s.data_resposta,
                         m.titulo AS aula_titulo
                  FROM solicitacoes_aulas s
                  JOIN modulos m ON s.modulo_id = m.id
                  WHERE s.status != 'pendente'
                  ORDER BY s.data_resposta DESC
                  LIMIT 6";
$res_historico = $conn->query($sql_historico);

// Lista de todos os alunos
$sql_alunos = "SELECT u.id, u.nome, u.email, u.criado_em,
                      (SELECT COUNT(*) FROM progresso_aluno p WHERE p.usuario = u.nome AND p.liberado = 1) AS aulas_liberadas,
                      (SELECT COUNT(*) FROM progresso_aluno p WHERE p.usuario = u.nome AND p.concluido = 1) AS aulas_concluidas
               FROM usuarios u
               WHERE u.tipo = 'aluno'
               ORDER BY u.nome ASC";
$res_alunos = $conn->query($sql_alunos);
$alunos_lista = [];
while ($a = $res_alunos->fetch_assoc()) {
    $alunos_lista[] = $a;
}

// Lista de todas as aulas / módulos
$sql_aulas = "SELECT m.id, m.titulo, m.ordem, m.texto,
                     (SELECT COUNT(DISTINCT usuario) FROM progresso_aluno p WHERE p.modulo_id = m.id AND p.liberado = 1) AS alunos_liberados,
                     (SELECT COUNT(DISTINCT usuario) FROM progresso_aluno p WHERE p.modulo_id = m.id AND p.concluido = 1) AS alunos_concluidos
              FROM modulos m
              ORDER BY m.ordem ASC, m.id ASC";
$res_aulas = $conn->query($sql_aulas);
$aulas_lista = [];
$maior_ordem = 0;
while ($m = $res_aulas->fetch_assoc()) {
    $aulas_lista[] = $m;
    if ($m['ordem'] > $maior_ordem) {
        $maior_ordem = $m['ordem'];
    }
}
$proxima_ordem = $maior_ordem + 1;

// Aluno selecionado para a aba "Liberar Aulas"
$filtro_aluno = isset($_GET['aluno']) ? trim($_GET['aluno']) : (count($alunos_lista) > 0 ? $alunos_lista[0]['nome'] : '');
?>
<!DOCTYPE html>
<html lang="pt-BR">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>IBEM - Painel Administrativo de Gestão</title>
  <meta name="description" content="Painel de Controle do Administrador do IBEM - Gestão de Alunos, Aulas e Liberações.">

  <!-- Google Fonts: Poppins & Inter (mesmas fontes do site oficial) -->
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700;800&family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
  <link rel="icon" type="image/png" href="../assets/img/logo.png">

  <style>
    /* ================= PALETA DE CORES & VARIÁVEIS OFICIAIS IBEM ================= */
    :root {
      --primary: #0A3D62;
      --primary-dark: #072740;
      --primary-light: #145DA0;
      --accent: #0284C7;
      --accent-hover: #0369A1;
      --accent-light: #E0F2FE;
      --success: #10B981;
      --success-dark: #059669;
      --success-light: #ECFDF5;
      --warning: #F59E0B;
      --warning-dark: #D97706;
      --warning-light: #FFFBEB;
      --danger: #EF4444;
      --danger-dark: #DC2626;
      --danger-light: #FEF2F2;
      --bg-gradient-start: #072740;
      --bg-gradient-end: #0E4B77;
      --surface: #FFFFFF;
      --surface-alt: #F8FAFC;
      --text-main: #1E293B;
      --text-muted: #64748B;
      --border-light: #E2E8F0;
      --border-focus: #38BDF8;
      --shadow-sm: 0 2px 4px rgba(0, 0, 0, 0.04);
      --shadow-md: 0 4px 14px rgba(10, 61, 98, 0.08);
      --shadow-lg: 0 10px 30px rgba(10, 61, 98, 0.12);
      --radius-sm: 8px;
      --radius-md: 14px;
      --radius-lg: 20px;
      --radius-pill: 9999px;
      --transition: all 0.25s cubic-bezier(0.4, 0, 0.2, 1);
    }

    * {
      box-sizing: border-box;
      margin: 0;
      padding: 0;
    }

    body {
      font-family: 'Poppins', 'Inter', -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
      color: var(--text-main);
      background: linear-gradient(145deg, var(--bg-gradient-start) 0%, var(--bg-gradient-end) 100%);
      min-height: 100vh;
      line-height: 1.6;
      padding: 24px 16px;
      display: flex;
      justify-content: center;
      align-items: flex-start;
    }

    /* Container Principal no mesmo padrão da Landing Page */
    .admin-wrapper {
      width: 100%;
      max-width: 1200px;
      background: var(--surface);
      border-radius: var(--radius-lg);
      box-shadow: 0 24px 60px rgba(0, 0, 0, 0.35);
      overflow: hidden;
      margin: 0 auto;
      animation: fadeIn 0.4s ease-out;
    }

    @keyframes fadeIn {
      from { opacity: 0; transform: translateY(12px); }
      to { opacity: 1; transform: translateY(0); }
    }

    /* ================= HEADER HERO INSTITUCIONAL ================= */
    .admin-header {
      background: linear-gradient(180deg, #F0F7FF 0%, #FFFFFF 100%);
      padding: 36px 32px 24px 32px;
      border-bottom: 1px solid var(--border-light);
      position: relative;
    }

    .header-top {
      display: flex;
      justify-content: space-between;
      align-items: center;
      flex-wrap: wrap;
      gap: 20px;
    }

    .brand-section {
      display: flex;
      align-items: center;
      gap: 18px;
    }

    .brand-logo {
      height: 60px;
      width: auto;
      object-fit: contain;
      filter: drop-shadow(0 4px 6px rgba(0, 0, 0, 0.08));
    }

    .brand-text h1 {
      font-size: 1.45rem;
      font-weight: 700;
      color: var(--primary);
      line-height: 1.2;
    }

    .brand-text p {
      font-size: 0.85rem;
      color: var(--accent);
      font-weight: 500;
      display: flex;
      align-items: center;
      gap: 6px;
    }

    /* Card do Usuário Logado */
    .user-profile-badge {
      display: flex;
      align-items: center;
      gap: 14px;
      background: var(--surface);
      border: 1px solid var(--border-light);
      padding: 10px 18px;
      border-radius: var(--radius-pill);
      box-shadow: var(--shadow-sm);
    }

    .user-avatar {
      width: 42px;
      height: 42px;
      background: linear-gradient(135deg, var(--accent) 0%, var(--primary-light) 100%);
      border-radius: 50%;
      display: flex;
      align-items: center;
      justify-content: center;
      color: white;
      font-weight: 700;
      font-size: 1.1rem;
    }

    .user-info {
      line-height: 1.2;
    }

    .user-name {
      font-size: 0.95rem;
      font-weight: 600;
      color: var(--text-main);
    }

    .user-role {
      font-size: 0.75rem;
      color: var(--accent);
      font-weight: 600;
      text-transform: uppercase;
      letter-spacing: 0.5px;
    }

    .header-actions {
      display: flex;
      gap: 10px;
    }

    .btn-header {
      display: inline-flex;
      align-items: center;
      gap: 8px;
      font-size: 0.82rem;
      font-weight: 600;
      padding: 8px 16px;
      border-radius: var(--radius-pill);
      text-decoration: none;
      transition: var(--transition);
      border: 1px solid var(--border-light);
    }

    .btn-header-site {
      background: var(--surface-alt);
      color: var(--primary);
    }

    .btn-header-site:hover {
      background: var(--accent-light);
      color: var(--accent-hover);
      border-color: var(--accent);
    }

    .btn-header-logout {
      background: var(--danger-light);
      color: var(--danger-dark);
      border-color: #FECACA;
    }

    .btn-header-logout:hover {
      background: var(--danger);
      color: white;
    }

    /* ================= BARRA DE NAVEGAÇÃO POR ABAS (PILLS) ================= */
    .admin-nav {
      display: flex;
      gap: 12px;
      padding: 16px 32px;
      background: var(--surface);
      border-bottom: 1px solid var(--border-light);
      overflow-x: auto;
      scrollbar-width: thin;
    }

    .nav-tab {
      display: inline-flex;
      align-items: center;
      gap: 10px;
      padding: 12px 22px;
      background: var(--surface-alt);
      border: 1px solid var(--border-light);
      border-radius: var(--radius-pill);
      color: var(--text-muted);
      font-size: 0.9rem;
      font-weight: 600;
      cursor: pointer;
      text-decoration: none;
      transition: var(--transition);
      white-space: nowrap;
    }

    .nav-tab:hover {
      color: var(--primary);
      background: #F1F5F9;
      transform: translateY(-1px);
    }

    .nav-tab.active {
      background: linear-gradient(135deg, var(--primary) 0%, var(--primary-light) 100%);
      color: white;
      border-color: var(--primary);
      box-shadow: 0 4px 12px rgba(10, 61, 98, 0.25);
    }

    .badge-counter {
      background: var(--warning);
      color: #78350F;
      font-size: 0.72rem;
      font-weight: 800;
      padding: 2px 8px;
      border-radius: var(--radius-pill);
      animation: pulseAlert 1.8s infinite;
    }

    .badge-counter.active-counter {
      background: #FFFFFF;
      color: var(--primary);
    }

    @keyframes pulseAlert {
      0% { transform: scale(1); }
      50% { transform: scale(1.12); }
      100% { transform: scale(1); }
    }

    /* ================= CONTEÚDO PRINCIPAL ================= */
    .admin-content {
      padding: 32px;
      background: #F8FAFC;
      min-height: 540px;
    }

    /* Avisos e Alertas */
    .alert-box {
      padding: 14px 20px;
      border-radius: var(--radius-md);
      margin-bottom: 24px;
      display: flex;
      align-items: center;
      justify-content: space-between;
      gap: 12px;
      font-size: 0.92rem;
      animation: slideDown 0.3s ease-out;
    }

    @keyframes slideDown {
      from { opacity: 0; transform: translateY(-8px); }
      to { opacity: 1; transform: translateY(0); }
    }

    .alert-success {
      background: var(--success-light);
      color: var(--success-dark);
      border: 1px solid #A7F3D0;
    }

    .alert-danger {
      background: var(--danger-light);
      color: var(--danger-dark);
      border: 1px solid #FECACA;
    }

    /* Cards de Estatísticas do Dashboard */
    .stats-grid {
      display: grid;
      grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
      gap: 20px;
      margin-bottom: 30px;
    }

    .stat-card {
      background: var(--surface);
      border-radius: var(--radius-md);
      padding: 24px;
      border: 1px solid var(--border-light);
      box-shadow: var(--shadow-sm);
      display: flex;
      align-items: center;
      gap: 18px;
      transition: var(--transition);
      position: relative;
      overflow: hidden;
    }

    .stat-card:hover {
      transform: translateY(-3px);
      box-shadow: var(--shadow-md);
    }

    .stat-icon {
      width: 54px;
      height: 54px;
      border-radius: var(--radius-md);
      display: flex;
      align-items: center;
      justify-content: center;
      flex-shrink: 0;
    }

    .stat-icon svg {
      width: 28px;
      height: 28px;
    }

    .icon-alunos { background: #E0F2FE; color: #0284C7; }
    .icon-aulas { background: #EDE9FE; color: #7C3AED; }
    .icon-pendentes { background: #FEF3C7; color: #D97706; }
    .icon-concluidas { background: #DCFCE7; color: #16A34A; }

    .stat-data h3 {
      font-size: 1.85rem;
      font-weight: 700;
      color: var(--text-main);
      line-height: 1.1;
    }

    .stat-data p {
      font-size: 0.85rem;
      color: var(--text-muted);
      font-weight: 500;
      margin-top: 4px;
    }

    /* Cards e Seções de Conteúdo */
    .section-card {
      background: var(--surface);
      border-radius: var(--radius-md);
      border: 1px solid var(--border-light);
      box-shadow: var(--shadow-sm);
      padding: 28px;
      margin-bottom: 28px;
    }

    .section-header-title {
      display: flex;
      justify-content: space-between;
      align-items: center;
      margin-bottom: 22px;
      padding-bottom: 14px;
      border-bottom: 1px solid var(--border-light);
      flex-wrap: wrap;
      gap: 12px;
    }

    .section-header-title h2 {
      font-size: 1.25rem;
      font-weight: 700;
      color: var(--primary);
      display: flex;
      align-items: center;
      gap: 10px;
    }

    .section-header-title p {
      font-size: 0.85rem;
      color: var(--text-muted);
    }

    /* ================= TABELAS MODERNAS ================= */
    .table-responsive {
      width: 100%;
      overflow-x: auto;
    }

    .modern-table {
      width: 100%;
      border-collapse: collapse;
      text-align: left;
      font-size: 0.88rem;
    }

    .modern-table th {
      background: #F1F5F9;
      color: var(--text-muted);
      font-weight: 600;
      padding: 12px 16px;
      border-bottom: 2px solid var(--border-light);
      text-transform: uppercase;
      font-size: 0.76rem;
      letter-spacing: 0.5px;
    }

    .modern-table td {
      padding: 14px 16px;
      border-bottom: 1px solid var(--border-light);
      vertical-align: middle;
      color: var(--text-main);
    }

    .modern-table tr:hover td {
      background: #F8FAFC;
    }

    /* Badges de Status */
    .status-badge {
      display: inline-flex;
      align-items: center;
      gap: 6px;
      padding: 4px 12px;
      border-radius: var(--radius-pill);
      font-size: 0.76rem;
      font-weight: 600;
    }

    .badge-pendente {
      background: #FEF3C7;
      color: #B45309;
      border: 1px solid #FDE68A;
    }

    .badge-liberado, .badge-aprovada {
      background: #DCFCE7;
      color: #15803D;
      border: 1px solid #BBF7D0;
    }

    .badge-bloqueado, .badge-rejeitada {
      background: #FEE2E2;
      color: #B91C1C;
      border: 1px solid #FECACA;
    }

    /* ================= BOTÕES & AÇÕES ================= */
    .btn {
      display: inline-flex;
      align-items: center;
      justify-content: center;
      gap: 8px;
      padding: 8px 16px;
      font-size: 0.84rem;
      font-weight: 600;
      border-radius: var(--radius-sm);
      border: none;
      cursor: pointer;
      text-decoration: none;
      transition: var(--transition);
    }

    .btn-sm {
      padding: 6px 12px;
      font-size: 0.78rem;
    }

    .btn-primary {
      background: linear-gradient(135deg, var(--accent) 0%, var(--primary-light) 100%);
      color: white;
      box-shadow: 0 4px 10px rgba(2, 132, 199, 0.25);
    }

    .btn-primary:hover {
      background: linear-gradient(135deg, var(--accent-hover) 0%, var(--primary) 100%);
      transform: translateY(-1px);
    }

    .btn-success {
      background: var(--success);
      color: white;
      box-shadow: 0 4px 10px rgba(16, 185, 129, 0.25);
    }

    .btn-success:hover {
      background: var(--success-dark);
      transform: translateY(-1px);
    }

    .btn-danger {
      background: var(--danger);
      color: white;
    }

    .btn-danger:hover {
      background: var(--danger-dark);
    }

    .btn-outline {
      background: transparent;
      border: 1px solid var(--border-light);
      color: var(--text-main);
    }

    .btn-outline:hover {
      background: var(--surface-alt);
      border-color: var(--accent);
      color: var(--accent);
    }

    /* ================= FORMULÁRIOS ================= */
    .form-grid {
      display: grid;
      grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
      gap: 20px;
    }

    .form-group {
      margin-bottom: 20px;
    }

    .form-label {
      display: block;
      font-size: 0.85rem;
      font-weight: 600;
      color: var(--text-main);
      margin-bottom: 6px;
    }

    .form-input, .form-select, .form-textarea {
      width: 100%;
      padding: 12px 16px;
      font-size: 0.9rem;
      font-family: inherit;
      border: 1.5px solid var(--border-light);
      border-radius: var(--radius-sm);
      background: var(--surface);
      color: var(--text-main);
      transition: var(--transition);
      outline: none;
    }

    .form-input:focus, .form-select:focus, .form-textarea:focus {
      border-color: var(--accent);
      box-shadow: 0 0 0 3px rgba(2, 132, 199, 0.15);
    }

    .form-textarea {
      min-height: 140px;
      resize: vertical;
    }

    .form-helper {
      font-size: 0.78rem;
      color: var(--text-muted);
      margin-top: 4px;
    }

    .checkbox-container {
      display: flex;
      align-items: center;
      gap: 10px;
      cursor: pointer;
      font-size: 0.88rem;
      font-weight: 500;
      user-select: none;
      padding: 8px 0;
    }

    .checkbox-container input {
      width: 18px;
      height: 18px;
      accent-color: var(--accent);
      cursor: pointer;
    }

    /* Estado Vazio (Empty State) */
    .empty-state {
      text-align: center;
      padding: 40px 20px;
      color: var(--text-muted);
    }

    .empty-state-icon {
      font-size: 2.8rem;
      margin-bottom: 12px;
      display: inline-block;
    }

    .empty-state h4 {
      font-size: 1.1rem;
      font-weight: 600;
      color: var(--text-main);
      margin-bottom: 6px;
    }

    /* Aulas Matrix Cards */
    .aulas-matrix {
      display: grid;
      grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
      gap: 16px;
      margin-top: 16px;
    }

    .aula-card-item {
      background: var(--surface);
      border: 1.5px solid var(--border-light);
      border-radius: var(--radius-md);
      padding: 16px;
      display: flex;
      flex-direction: column;
      justify-content: space-between;
      transition: var(--transition);
    }

    .aula-card-item.liberada {
      border-color: #86EFAC;
      background: #F0FDF4;
    }

    .aula-card-item.bloqueada {
      border-color: #E2E8F0;
      background: #FFFFFF;
    }

    .aula-card-top {
      display: flex;
      justify-content: space-between;
      align-items: flex-start;
      margin-bottom: 10px;
    }

    .aula-card-num {
      background: var(--surface-alt);
      font-size: 0.72rem;
      font-weight: 700;
      padding: 2px 8px;
      border-radius: var(--radius-pill);
      color: var(--text-muted);
      border: 1px solid var(--border-light);
    }

    .aula-card-title {
      font-size: 0.95rem;
      font-weight: 600;
      color: var(--text-main);
      margin-bottom: 12px;
      line-height: 1.3;
    }

    /* Responsividade */
    @media (max-width: 768px) {
      body {
        padding: 12px 8px;
      }
      .admin-header {
        padding: 24px 18px;
      }
      .admin-nav {
        padding: 12px 18px;
      }
      .admin-content {
        padding: 20px 14px;
      }
      .section-card {
        padding: 18px;
      }
      .header-top {
        flex-direction: column;
        align-items: flex-start;
      }
      .user-profile-badge {
        width: 100%;
        justify-content: space-between;
      }
    }
  </style>
</head>

<body>

  <main class="admin-wrapper">

    <!-- ================= CABEÇALHO / HERO ================= -->
    <header class="admin-header">
      <div class="header-top">
        <div class="brand-section">
          <img src="../assets/img/logo.png" alt="Logotipo IBEM" class="brand-logo" onerror="this.style.display='none'">
          <div class="brand-text">
            <h1>Instituto Brasileiro de Educação Moderna</h1>
            <p>
              <svg width="16" height="16" viewBox="0 0 24 24" fill="currentColor">
                <path d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z"/>
              </svg>
              <span>Painel Administrativo Oficial &bull; Gestão Escolar</span>
            </p>
          </div>
        </div>

        <div class="user-profile-badge">
          <div class="user-avatar">
            <?php echo strtoupper(substr($_SESSION['usuario'], 0, 1)); ?>
          </div>
          <div class="user-info">
            <div class="user-name"><?php echo htmlspecialchars($_SESSION['usuario']); ?></div>
            <div class="user-role">Administrador Geral</div>
          </div>
          <div class="header-actions">
            <a href="../index.php" target="_blank" class="btn-header btn-header-site" title="Ver site público">
              <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <path d="M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6"></path>
                <polyline points="15 3 21 3 21 9"></polyline>
                <line x1="10" y1="14" x2="21" y2="3"></line>
              </svg>
              <span>Site</span>
            </a>
            <a href="logout.php" class="btn-header btn-header-logout" title="Sair do painel administrativo">
              <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"></path>
                <polyline points="16 17 21 12 16 7"></polyline>
                <line x1="21" y1="12" x2="9" y2="12"></line>
              </svg>
              <span>Sair</span>
            </a>
          </div>
        </div>
      </div>
    </header>

    <!-- ================= BARRA DE ABAS (PILLS NAVIGATION) ================= -->
    <nav class="admin-nav">
      <a href="?tab=dashboard" class="nav-tab <?php echo $tab === 'dashboard' ? 'active' : ''; ?>">
        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
          <rect x="3" y="3" width="7" height="9"></rect>
          <rect x="14" y="3" width="7" height="5"></rect>
          <rect x="14" y="12" width="7" height="9"></rect>
          <rect x="3" y="16" width="7" height="5"></rect>
        </svg>
        <span>Dashboard & Solicitações</span>
        <?php if ($total_pendentes > 0): ?>
          <span class="badge-counter <?php echo $tab === 'dashboard' ? 'active-counter' : ''; ?>">
            <?php echo $total_pendentes; ?> pendente<?php echo $total_pendentes > 1 ? 's' : ''; ?>
          </span>
        <?php endif; ?>
      </a>

      <a href="?tab=alunos" class="nav-tab <?php echo $tab === 'alunos' ? 'active' : ''; ?>">
        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
          <path d="M16 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path>
          <circle cx="8.5" cy="7" r="4"></circle>
          <line x1="20" y1="8" x2="20" y2="14"></line>
          <line x1="23" y1="11" x2="17" y2="11"></line>
        </svg>
        <span>Adicionar Aluno</span>
      </a>

      <a href="?tab=aulas" class="nav-tab <?php echo $tab === 'aulas' ? 'active' : ''; ?>">
        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
          <path d="M4 19.5A2.5 2.5 0 0 1 6.5 17H20"></path>
          <path d="M6.5 2H20v20H6.5A2.5 2.5 0 0 1 4 19.5v-15A2.5 2.5 0 0 1 6.5 2z"></path>
          <line x1="12" y1="7" x2="12" y2="13"></line>
          <line x1="9" y1="10" x2="15" y2="10"></line>
        </svg>
        <span>Adicionar Aula</span>
      </a>

      <a href="?tab=liberar" class="nav-tab <?php echo $tab === 'liberar' ? 'active' : ''; ?>">
        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
          <rect x="3" y="11" width="18" height="11" rx="2" ry="2"></rect>
          <path d="M7 11V7a5 5 0 0 1 9.9-1"></path>
        </svg>
        <span>Liberar Aulas</span>
      </a>
    </nav>

    <!-- ================= CONTEÚDO DAS ABAS ================= -->
    <div class="admin-content">

      <!-- MENSAGENS DE SUCESSO OU ERRO -->
      <?php if (!empty($msg_sucesso)): ?>
        <div class="alert-box alert-success">
          <div style="display:flex; align-items:center; gap:10px;">
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
              <path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"></path>
              <polyline points="22 4 12 14.01 9 11.01"></polyline>
            </svg>
            <span><?php echo $msg_sucesso; ?></span>
          </div>
          <button type="button" onclick="this.parentElement.remove()" style="background:none; border:none; cursor:pointer; color:inherit; font-size:1.2rem;">&times;</button>
        </div>
      <?php endif; ?>

      <?php if (!empty($msg_erro)): ?>
        <div class="alert-box alert-danger">
          <div style="display:flex; align-items:center; gap:10px;">
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
              <circle cx="12" cy="12" r="10"></circle>
              <line x1="12" y1="8" x2="12" y2="12"></line>
              <line x1="12" y1="16" x2="12.01" y2="16"></line>
            </svg>
            <span><?php echo $msg_erro; ?></span>
          </div>
          <button type="button" onclick="this.parentElement.remove()" style="background:none; border:none; cursor:pointer; color:inherit; font-size:1.2rem;">&times;</button>
        </div>
      <?php endif; ?>

      <!-- ========================================================
           ABA 1: DASHBOARD & SOLICITAÇÕES PENDENTES
      ======================================================== -->
      <?php if ($tab === 'dashboard'): ?>

        <!-- 4 CARDS DE MÉTRICAS RÁPIDAS -->
        <div class="stats-grid">
          <div class="stat-card">
            <div class="stat-icon icon-alunos">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path>
                <circle cx="9" cy="7" r="4"></circle>
                <path d="M23 21v-2a4 4 0 0 0-3-3.87"></path>
                <path d="M16 3.13a4 4 0 0 1 0 7.75"></path>
              </svg>
            </div>
            <div class="stat-data">
              <h3><?php echo $total_alunos; ?></h3>
              <p>Alunos Cadastrados</p>
            </div>
          </div>

          <div class="stat-card">
            <div class="stat-icon icon-aulas">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <path d="M4 19.5A2.5 2.5 0 0 1 6.5 17H20"></path>
                <path d="M6.5 2H20v20H6.5A2.5 2.5 0 0 1 4 19.5v-15A2.5 2.5 0 0 1 6.5 2z"></path>
              </svg>
            </div>
            <div class="stat-data">
              <h3><?php echo $total_aulas; ?></h3>
              <p>Aulas na Grade EAD</p>
            </div>
          </div>

          <div class="stat-card" style="<?php echo $total_pendentes > 0 ? 'border: 2px solid #F59E0B;' : ''; ?>">
            <div class="stat-icon icon-pendentes">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <circle cx="12" cy="12" r="10"></circle>
                <polyline points="12 6 12 12 16 14"></polyline>
              </svg>
            </div>
            <div class="stat-data">
              <h3><?php echo $total_pendentes; ?></h3>
              <p>Solicitações Pendentes</p>
            </div>
          </div>

          <div class="stat-card">
            <div class="stat-icon icon-concluidas">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <polyline points="20 6 9 17 4 12"></polyline>
              </svg>
            </div>
            <div class="stat-data">
              <h3><?php echo $total_concluidas; ?></h3>
              <p>Aulas Concluídas</p>
            </div>
          </div>
        </div>

        <!-- SEÇÃO PRINCIPAL: ACOMPANHAR SOLICITAÇÕES DE AULAS PENDENTES -->
        <div class="section-card">
          <div class="section-header-title">
            <div>
              <h2>
                <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                  <circle cx="12" cy="12" r="10"></circle>
                  <polyline points="12 6 12 12 16 14"></polyline>
                </svg>
                <span>Solicitações de Aulas Pendentes</span>
              </h2>
              <p>Alunos que solicitaram a liberação de novos módulos para estudo.</p>
            </div>

            <?php if ($total_pendentes > 0): ?>
              <span class="status-badge badge-pendente">
                <span class="badge-counter" style="animation:none;"><?php echo $total_pendentes; ?></span> Aguardando liberação
              </span>
            <?php endif; ?>
          </div>

          <?php if ($res_pendentes && $res_pendentes->num_rows > 0): ?>
            <div class="table-responsive">
              <table class="modern-table">
                <thead>
                  <tr>
                    <th>Aluno</th>
                    <th>E-mail</th>
                    <th>Aula Solicitada</th>
                    <th>Data do Pedido</th>
                    <th>Status</th>
                    <th style="text-align:right;">Ações de Liberação</th>
                  </tr>
                </thead>
                <tbody>
                  <?php while ($solic = $res_pendentes->fetch_assoc()): ?>
                    <tr>
                      <td>
                        <strong style="color:var(--primary); font-size:0.95rem;"><?php echo htmlspecialchars($solic['usuario']); ?></strong>
                      </td>
                      <td>
                        <span style="color:var(--text-muted);"><?php echo htmlspecialchars($solic['aluno_email'] ?? 'Não informado'); ?></span>
                      </td>
                      <td>
                        <span class="status-badge" style="background:#EFF6FF; color:#1D4ED8; font-weight:600;">
                          #<?php echo $solic['aula_ordem']; ?> &bull; <?php echo htmlspecialchars($solic['aula_titulo']); ?>
                        </span>
                      </td>
                      <td>
                        <span style="font-size:0.82rem; color:var(--text-muted);">
                          <?php echo date('d/m/Y \à\s H:i', strtotime($solic['data_solicitacao'])); ?>
                        </span>
                      </td>
                      <td>
                        <span class="status-badge badge-pendente">
                          <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><circle cx="12" cy="12" r="10"></circle><polyline points="12 6 12 12 16 14"></polyline></svg>
                          Pendente
                        </span>
                      </td>
                      <td style="text-align:right; white-space:nowrap;">
                        <form method="POST" style="display:inline-block; margin-right:6px;">
                          <input type="hidden" name="acao" value="aprovar_solicitacao">
                          <input type="hidden" name="solic_id" value="<?php echo $solic['id']; ?>">
                          <button type="submit" class="btn btn-success btn-sm" title="Liberar esta aula imediatamente para o aluno">
                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="20 6 9 17 4 12"></polyline></svg>
                            <span>Liberar Aula</span>
                          </button>
                        </form>

                        <form method="POST" style="display:inline-block;" onsubmit="return confirm('Deseja realmente recusar esta solicitação de aula?');">
                          <input type="hidden" name="acao" value="rejeitar_solicitacao">
                          <input type="hidden" name="solic_id" value="<?php echo $solic['id']; ?>">
                          <button type="submit" class="btn btn-outline btn-sm" style="color:var(--danger); border-color:#FECACA;" title="Recusar solicitação">
                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="18" y1="6" x2="6" y2="18"></line><line x1="6" y1="6" x2="18" y2="18"></line></svg>
                            <span>Recusar</span>
                          </button>
                        </form>
                      </td>
                    </tr>
                  <?php endwhile; ?>
                </tbody>
              </table>
            </div>
          <?php else: ?>
            <div class="empty-state">
              <div class="empty-state-icon">🎉</div>
              <h4>Nenhuma solicitação pendente no momento!</h4>
              <p>Todos os pedidos de aulas foram atendidos. Quando um aluno solicitar liberação, ele aparecerá aqui com botões de ação imediata.</p>
            </div>
          <?php endif; ?>
        </div>

        <!-- HISTÓRICO DE SOLICITAÇÕES RECENTES -->
        <?php if ($res_historico && $res_historico->num_rows > 0): ?>
          <div class="section-card">
            <div class="section-header-title">
              <h2>
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                  <circle cx="12" cy="12" r="10"></circle>
                  <polyline points="12 6 12 12 14 10"></polyline>
                </svg>
                <span>Histórico Recente de Solicitações Respondidas</span>
              </h2>
            </div>
            <div class="table-responsive">
              <table class="modern-table">
                <thead>
                  <tr>
                    <th>Aluno</th>
                    <th>Aula</th>
                    <th>Data Solicitação</th>
                    <th>Data Resposta</th>
                    <th>Decisão</th>
                  </tr>
                </thead>
                <tbody>
                  <?php while ($hist = $res_historico->fetch_assoc()): ?>
                    <tr>
                      <td><strong><?php echo htmlspecialchars($hist['usuario']); ?></strong></td>
                      <td><?php echo htmlspecialchars($hist['aula_titulo']); ?></td>
                      <td><?php echo date('d/m/Y H:i', strtotime($hist['data_solicitacao'])); ?></td>
                      <td><?php echo $hist['data_resposta'] ? date('d/m/Y H:i', strtotime($hist['data_resposta'])) : '-'; ?></td>
                      <td>
                        <?php if ($hist['status'] === 'aprovada'): ?>
                          <span class="status-badge badge-aprovada">✔ Aprovada & Liberada</span>
                        <?php else: ?>
                          <span class="status-badge badge-rejeitada">✖ Recusada</span>
                        <?php endif; ?>
                      </td>
                    </tr>
                  <?php endwhile; ?>
                </tbody>
              </table>
            </div>
          </div>
        <?php endif; ?>

      <!-- ========================================================
           ABA 2: ADICIONAR ALUNO (ADD ALUNO)
      ======================================================== -->
      <?php elseif ($tab === 'alunos'): ?>

        <div class="section-card">
          <div class="section-header-title">
            <div>
              <h2>
                <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                  <path d="M16 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path>
                  <circle cx="8.5" cy="7" r="4"></circle>
                  <line x1="20" y1="8" x2="20" y2="14"></line>
                  <line x1="23" y1="11" x2="17" y2="11"></line>
                </svg>
                <span>Cadastrar Novo Aluno</span>
              </h2>
              <p>Adicione alunos ao sistema para acesso às aulas, módulos e materiais didáticos.</p>
            </div>
          </div>

          <form method="POST">
            <input type="hidden" name="acao" value="adicionar_aluno">

            <div class="form-grid">
              <div class="form-group">
                <label class="form-label" for="alunoNome">Nome Completo do Aluno *</label>
                <input type="text" id="alunoNome" name="nome" class="form-input" placeholder="Ex: Carlos Eduardo Silva" required>
              </div>

              <div class="form-group">
                <label class="form-label" for="alunoEmail">E-mail de Acesso (Login) *</label>
                <input type="email" id="alunoEmail" name="email" class="form-input" placeholder="carlos.silva@exemplo.com" required>
                <span class="form-helper">O aluno usará este e-mail para entrar pelo portal no site.</span>
              </div>
            </div>

            <div class="form-grid">
              <div class="form-group">
                <label class="form-label" for="alunoSenha">Senha Inicial de Acesso *</label>
                <div style="display:flex; gap:8px;">
                  <input type="password" id="alunoSenha" name="senha" class="form-input" placeholder="Mínimo 6 caracteres" required>
                  <button type="button" class="btn btn-outline" onclick="gerarSenhaAleatoria()" title="Gerar senha segura">
                    🎲 Gerar
                  </button>
                  <button type="button" class="btn btn-outline" onclick="toggleSenha('alunoSenha', this)" title="Visualizar senha">
                    👁
                  </button>
                </div>
              </div>

              <div class="form-group" style="display:flex; align-items:center;">
                <label class="checkbox-container">
                  <input type="checkbox" name="liberar_primeira" value="1" checked>
                  <span>Liberar automaticamente a <strong>1ª Aula (Módulo 1)</strong> após o cadastro</span>
                </label>
              </div>
            </div>

            <div style="margin-top:10px;">
              <button type="submit" class="btn btn-primary" style="padding: 12px 24px;">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="20 6 9 17 4 12"></polyline></svg>
                <span>Cadastrar Aluno</span>
              </button>
            </div>
          </form>
        </div>

        <!-- LISTA DE ALUNOS CADASTRADOS -->
        <div class="section-card">
          <div class="section-header-title">
            <div>
              <h2>
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                  <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path>
                  <circle cx="9" cy="7" r="4"></circle>
                </svg>
                <span>Alunos Cadastrados no Sistema (<?php echo count($alunos_lista); ?>)</span>
              </h2>
            </div>
            <div style="max-width:280px; width:100%;">
              <input type="text" id="filtroAlunosInput" onkeyup="filtrarTabelaAlunos()" placeholder="🔍 Buscar aluno por nome ou e-mail..." class="form-input" style="padding:8px 14px; font-size:0.82rem;">
            </div>
          </div>

          <?php if (count($alunos_lista) > 0): ?>
            <div class="table-responsive">
              <table class="modern-table" id="tabelaAlunos">
                <thead>
                  <tr>
                    <th>Nome do Aluno</th>
                    <th>E-mail</th>
                    <th>Aulas Liberadas</th>
                    <th>Concluídas</th>
                    <th>Data Cadastro</th>
                    <th style="text-align:right;">Ações</th>
                  </tr>
                </thead>
                <tbody>
                  <?php foreach ($alunos_lista as $aluno): ?>
                    <tr>
                      <td>
                        <strong style="color:var(--primary);"><?php echo htmlspecialchars($aluno['nome']); ?></strong>
                      </td>
                      <td><?php echo htmlspecialchars($aluno['email']); ?></td>
                      <td>
                        <span class="status-badge badge-liberado">
                          🔓 <?php echo $aluno['aulas_liberadas']; ?> / <?php echo $total_aulas; ?>
                        </span>
                      </td>
                      <td>
                        <span class="status-badge" style="background:#EFF6FF; color:#1D4ED8;">
                          🎓 <?php echo $aluno['aulas_concluidas']; ?>
                        </span>
                      </td>
                      <td>
                        <span style="font-size:0.82rem; color:var(--text-muted);">
                          <?php echo $aluno['criado_em'] ? date('d/m/Y', strtotime($aluno['criado_em'])) : '-'; ?>
                        </span>
                      </td>
                      <td style="text-align:right; white-space:nowrap;">
                        <a href="?tab=liberar&aluno=<?php echo urlencode($aluno['nome']); ?>" class="btn btn-primary btn-sm" title="Gerenciar liberação de aulas">
                          <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="11" width="18" height="11" rx="2" ry="2"></rect><path d="M7 11V7a5 5 0 0 1 9.9-1"></path></svg>
                          <span>Liberar Aulas</span>
                        </a>

                        <form method="POST" style="display:inline-block; margin-left:4px;" onsubmit="return confirm('ATENÇÃO: Deseja realmente excluir este aluno? Todo o progresso e acessos dele serão apagados permanentemente.');">
                          <input type="hidden" name="acao" value="excluir_aluno">
                          <input type="hidden" name="id_aluno" value="<?php echo $aluno['id']; ?>">
                          <button type="submit" class="btn btn-outline btn-sm" style="color:var(--danger); border-color:#FECACA;" title="Excluir aluno">
                            <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="3 6 5 6 21 6"></polyline><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path></svg>
                          </button>
                        </form>
                      </td>
                    </tr>
                  <?php endforeach; ?>
                </tbody>
              </table>
            </div>
          <?php else: ?>
            <div class="empty-state">
              <div class="empty-state-icon">👥</div>
              <h4>Nenhum aluno cadastrado ainda</h4>
              <p>Cadastre o primeiro aluno preenchendo o formulário acima.</p>
            </div>
          <?php endif; ?>
        </div>

      <!-- ========================================================
           ABA 3: ADICIONAR AULA (ADD AULA)
      ======================================================== -->
      <?php elseif ($tab === 'aulas'): ?>

        <div class="section-card">
          <div class="section-header-title">
            <div>
              <h2>
                <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                  <path d="M4 19.5A2.5 2.5 0 0 1 6.5 17H20"></path>
                  <path d="M6.5 2H20v20H6.5A2.5 2.5 0 0 1 4 19.5v-15A2.5 2.5 0 0 1 6.5 2z"></path>
                  <line x1="12" y1="7" x2="12" y2="13"></line>
                  <line x1="9" y1="10" x2="15" y2="10"></line>
                </svg>
                <span>Cadastrar Nova Aula / Módulo</span>
              </h2>
              <p>Crie novas aulas com conteúdo didático interativo para a plataforma de estudos.</p>
            </div>
          </div>

          <form method="POST">
            <input type="hidden" name="acao" value="adicionar_aula">

            <div class="form-grid">
              <div class="form-group" style="grid-column: span 2;">
                <label class="form-label" for="aulaTitulo">Título da Aula / Módulo *</label>
                <input type="text" id="aulaTitulo" name="titulo" class="form-input" placeholder="Ex: Módulo 4 - Técnicas de Comunicação e Gestão de Conflitos" required>
              </div>

              <div class="form-group">
                <label class="form-label" for="aulaOrdem">Ordem na Grade Curricular *</label>
                <input type="number" id="aulaOrdem" name="ordem" class="form-input" value="<?php echo $proxima_ordem; ?>" min="1" required>
                <span class="form-helper">Sequência em que o aluno verá a aula no curso (1, 2, 3...).</span>
              </div>

              <div class="form-group" style="display:flex; align-items:center;">
                <label class="checkbox-container">
                  <input type="checkbox" name="liberar_para_todos" value="1">
                  <span>Liberar imediatamente esta aula para <strong>todos os alunos já cadastrados</strong></span>
                </label>
              </div>
            </div>

            <div class="form-group">
              <label class="form-label" for="aulaTexto">Conteúdo Didático da Aula (Texto / Instruções)</label>
              
              <!-- Barra de ferramentas rápida para o texto -->
              <div style="margin-bottom:8px; display:flex; gap:6px; flex-wrap:wrap;">
                <button type="button" class="btn btn-outline btn-sm" onclick="inserirFormatacao('**', '**')"><strong>B</strong> Negrito</button>
                <button type="button" class="btn btn-outline btn-sm" onclick="inserirFormatacao('### ', '\n')">Título</button>
                <button type="button" class="btn btn-outline btn-sm" onclick="inserirFormatacao('• ', '\n')">Lista</button>
                <button type="button" class="btn btn-outline btn-sm" onclick="inserirFormatacao('\n---\n', '')">Separador</button>
              </div>

              <textarea id="aulaTexto" name="texto" class="form-textarea" style="min-height:220px;" placeholder="Digite aqui o conteúdo que o aluno irá ler ou ouvir na sala de aula interativa com síntese de voz (TTS)..."></textarea>
              <span class="form-helper">Este texto poderá ser ouvido pelo aluno com narração por voz (Web Speech API).</span>
            </div>

            <button type="submit" class="btn btn-primary" style="padding: 12px 24px;">
              <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="20 6 9 17 4 12"></polyline></svg>
              <span>Salvar e Publicar Aula</span>
            </button>
          </form>
        </div>

        <!-- LISTA DE AULAS CADASTRADAS -->
        <div class="section-card">
          <div class="section-header-title">
            <h2>
              <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <path d="M4 19.5A2.5 2.5 0 0 1 6.5 17H20"></path>
                <path d="M6.5 2H20v20H6.5A2.5 2.5 0 0 1 4 19.5v-15A2.5 2.5 0 0 1 6.5 2z"></path>
              </svg>
              <span>Grade de Aulas Cadastradas (<?php echo count($aulas_lista); ?>)</span>
            </h2>
          </div>

          <?php if (count($aulas_lista) > 0): ?>
            <div class="table-responsive">
              <table class="modern-table">
                <thead>
                  <tr>
                    <th style="width:70px;">Ordem</th>
                    <th>Título do Módulo / Aula</th>
                    <th>Alunos com Acesso</th>
                    <th>Conclusões</th>
                    <th style="text-align:right;">Ações</th>
                  </tr>
                </thead>
                <tbody>
                  <?php foreach ($aulas_lista as $aula): ?>
                    <tr>
                      <td>
                        <span class="status-badge" style="background:#F1F5F9; color:var(--primary); font-weight:700;">
                          #<?php echo $aula['ordem']; ?>
                        </span>
                      </td>
                      <td>
                        <strong style="color:var(--text-main); font-size:0.95rem;"><?php echo htmlspecialchars($aula['titulo']); ?></strong>
                        <p style="font-size:0.78rem; color:var(--text-muted); margin-top:2px;">
                          <?php echo mb_strimwidth(strip_tags($aula['texto'] ?? ''), 0, 95, '...'); ?>
                        </p>
                      </td>
                      <td>
                        <span class="status-badge badge-liberado">
                          👥 <?php echo $aula['alunos_liberados']; ?> alunos
                        </span>
                      </td>
                      <td>
                        <span class="status-badge" style="background:#EFF6FF; color:#1D4ED8;">
                          ✔ <?php echo $aula['alunos_concluidos']; ?>
                        </span>
                      </td>
                      <td style="text-align:right; white-space:nowrap;">
                        <a href="aulas.php?id=<?php echo $aula['id']; ?>" target="_blank" class="btn btn-outline btn-sm" title="Prévia da aula como o aluno enxerga">
                          <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"></circle><polygon points="10 8 16 12 10 16 10 8"></polygon></svg>
                          <span>Ver Aula</span>
                        </a>

                        <form method="POST" style="display:inline-block; margin-left:4px;" onsubmit="return confirm('Deseja realmente remover esta aula? Todos os acessos a ela serão revogados.');">
                          <input type="hidden" name="acao" value="excluir_aula">
                          <input type="hidden" name="id_aula" value="<?php echo $aula['id']; ?>">
                          <button type="submit" class="btn btn-outline btn-sm" style="color:var(--danger); border-color:#FECACA;" title="Excluir aula">
                            <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="3 6 5 6 21 6"></polyline><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path></svg>
                          </button>
                        </form>
                      </td>
                    </tr>
                  <?php endforeach; ?>
                </tbody>
              </table>
            </div>
          <?php else: ?>
            <div class="empty-state">
              <div class="empty-state-icon">📚</div>
              <h4>Nenhuma aula cadastrada</h4>
              <p>Comece preenchendo o formulário acima para adicionar o primeiro módulo do curso.</p>
            </div>
          <?php endif; ?>
        </div>

      <!-- ========================================================
           ABA 4: LIBERAR AULAS (LIBERAR AULA)
      ======================================================== -->
      <?php elseif ($tab === 'liberar'): ?>

        <!-- PAINEL DE CONTROLE DE LIBERAÇÃO POR ALUNO -->
        <div class="section-card">
          <div class="section-header-title">
            <div>
              <h2>
                <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                  <rect x="3" y="11" width="18" height="11" rx="2" ry="2"></rect>
                  <path d="M7 11V7a5 5 0 0 1 9.9-1"></path>
                </svg>
                <span>Central de Liberação de Aulas por Aluno</span>
              </h2>
              <p>Selecione um aluno para visualizar e alternar o status de liberação de cada aula individualmente.</p>
            </div>

            <!-- Botão de Ação Global Rápida -->
            <?php if (!empty($filtro_aluno)): ?>
              <form method="POST" style="display:inline-block;" onsubmit="return confirm('Deseja realmente liberar TODAS as aulas cadastradas para este aluno?');">
                <input type="hidden" name="acao" value="liberar_todas_aluno">
                <input type="hidden" name="usuario_aluno" value="<?php echo htmlspecialchars($filtro_aluno); ?>">
                <button type="submit" class="btn btn-success">
                  <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="20 6 9 17 4 12"></polyline></svg>
                  <span>Liberar Todas as Aulas para este Aluno</span>
                </button>
              </form>
            <?php endif; ?>
          </div>

          <!-- SELETOR DE ALUNO -->
          <div style="background:#F8FAFC; border:1px solid var(--border-light); border-radius:var(--radius-sm); padding:16px; margin-bottom:24px;">
            <label class="form-label" for="selectAluno">Selecione o Aluno para Gerenciar:</label>
            <div style="display:flex; gap:12px; align-items:center; flex-wrap:wrap;">
              <select id="selectAluno" class="form-select" style="max-width:420px;" onchange="window.location.href='?tab=liberar&aluno=' + encodeURIComponent(this.value)">
                <?php if (empty($alunos_lista)): ?>
                  <option value="">Nenhum aluno cadastrado no momento</option>
                <?php else: ?>
                  <?php foreach ($alunos_lista as $al): ?>
                    <option value="<?php echo htmlspecialchars($al['nome']); ?>" <?php echo $filtro_aluno === $al['nome'] ? 'selected' : ''; ?>>
                      👤 <?php echo htmlspecialchars($al['nome']); ?> (<?php echo htmlspecialchars($al['email']); ?>)
                    </option>
                  <?php endforeach; ?>
                <?php endif; ?>
              </select>

              <?php if (!empty($filtro_aluno)): ?>
                <span class="status-badge" style="background:#E0F2FE; color:#0369A1; font-size:0.85rem; padding:8px 16px;">
                  Aluno Selecionado: <strong><?php echo htmlspecialchars($filtro_aluno); ?></strong>
                </span>
              <?php endif; ?>
            </div>
          </div>

          <!-- GRADE DE AULAS COM STATUS DE LIBERAÇÃO -->
          <?php if (!empty($filtro_aluno)): ?>
            <?php
            // Buscar liberações deste aluno
            $u_safe = $conn->real_escape_string($filtro_aluno);
            $res_prog = $conn->query("SELECT modulo_id, liberado, concluido FROM progresso_aluno WHERE usuario = '$u_safe'");
            $liberacoes = [];
            while ($p = $res_prog->fetch_assoc()) {
                $liberacoes[$p['modulo_id']] = [
                    'liberado' => intval($p['liberado']),
                    'concluido' => intval($p['concluido'])
                ];
            }
            ?>

            <h3 style="font-size:1.05rem; font-weight:600; color:var(--primary); margin-bottom:14px;">
              Grade Curricular e Status de Acesso
            </h3>

            <?php if (count($aulas_lista) > 0): ?>
              <div class="aulas-matrix">
                <?php foreach ($aulas_lista as $aula): ?>
                  <?php
                  $is_liberado = isset($liberacoes[$aula['id']]) && $liberacoes[$aula['id']]['liberado'] === 1;
                  $is_concluido = isset($liberacoes[$aula['id']]) && $liberacoes[$aula['id']]['concluido'] === 1;
                  ?>
                  <div class="aula-card-item <?php echo $is_liberado ? 'liberada' : 'bloqueada'; ?>">
                    <div>
                      <div class="aula-card-top">
                        <span class="aula-card-num">Módulo #<?php echo $aula['ordem']; ?></span>
                        
                        <?php if ($is_concluido): ?>
                          <span class="status-badge" style="background:#DBEAFE; color:#1E40AF;">
                            🎓 Concluída
                          </span>
                        <?php elseif ($is_liberado): ?>
                          <span class="status-badge badge-liberado">
                            🔓 Liberada
                          </span>
                        <?php else: ?>
                          <span class="status-badge badge-bloqueado">
                            🔒 Bloqueada
                          </span>
                        <?php endif; ?>
                      </div>

                      <div class="aula-card-title"><?php echo htmlspecialchars($aula['titulo']); ?></div>
                    </div>

                    <div style="margin-top:14px; border-top:1px solid rgba(0,0,0,0.06); padding-top:12px;">
                      <form method="POST">
                        <input type="hidden" name="acao" value="alterar_liberacao">
                        <input type="hidden" name="usuario_aluno" value="<?php echo htmlspecialchars($filtro_aluno); ?>">
                        <input type="hidden" name="modulo_id" value="<?php echo $aula['id']; ?>">

                        <?php if ($is_liberado): ?>
                          <input type="hidden" name="status" value="0">
                          <button type="submit" class="btn btn-outline btn-sm" style="width:100%; color:var(--danger); border-color:#FECACA;">
                            <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="11" width="18" height="11" rx="2" ry="2"></rect><path d="M7 11V7a5 5 0 0 1 10 0v4"></path></svg>
                            <span>Bloquear Acesso</span>
                          </button>
                        <?php else: ?>
                          <input type="hidden" name="status" value="1">
                          <button type="submit" class="btn btn-success btn-sm" style="width:100%;">
                            <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="20 6 9 17 4 12"></polyline></svg>
                            <span>Liberar Esta Aula</span>
                          </button>
                        <?php endif; ?>
                      </form>
                    </div>
                  </div>
                <?php endforeach; ?>
              </div>
            <?php else: ?>
              <div class="empty-state">
                <div class="empty-state-icon">📚</div>
                <h4>Nenhuma aula cadastrada ainda</h4>
                <p>Cadastre as aulas na aba "Adicionar Aula" para poder liberá-las aos alunos.</p>
              </div>
            <?php endif; ?>

          <?php else: ?>
            <div class="empty-state">
              <div class="empty-state-icon">👤</div>
              <h4>Nenhum aluno cadastrado no sistema</h4>
              <p>Cadastre alunos primeiro na aba "Adicionar Aluno" para poder gerenciar as liberações de aulas.</p>
            </div>
          <?php endif; ?>
        </div>

        <!-- LIBERAÇÃO COLETIVA / EM MASSA -->
        <?php if (count($aulas_lista) > 0 && count($alunos_lista) > 0): ?>
          <div class="section-card">
            <div class="section-header-title">
              <h2>
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                  <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path>
                  <circle cx="9" cy="7" r="4"></circle>
                  <path d="M23 21v-2a4 4 0 0 0-3-3.87"></path>
                  <path d="M16 3.13a4 4 0 0 1 0 7.75"></path>
                </svg>
                <span>Liberação Coletiva (Para Toda a Turma)</span>
              </h2>
              <p>Libere uma aula específica de uma só vez para todos os alunos cadastrados no IBEM.</p>
            </div>

            <form method="POST" onsubmit="return confirm('Deseja realmente liberar esta aula para TODOS os alunos cadastrados?');" style="display:flex; gap:12px; align-items:center; flex-wrap:wrap;">
              <input type="hidden" name="acao" value="liberar_aula_todos">
              
              <select name="modulo_id" class="form-select" style="max-width:460px;" required>
                <option value="">Selecione a aula para liberar a todos...</option>
                <?php foreach ($aulas_lista as $m): ?>
                  <option value="<?php echo $m['id']; ?>">
                    #<?php echo $m['ordem']; ?> &bull; <?php echo htmlspecialchars($m['titulo']); ?>
                  </option>
                <?php endforeach; ?>
              </select>

              <button type="submit" class="btn btn-primary" style="padding:12px 20px;">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="20 6 9 17 4 12"></polyline></svg>
                <span>Liberar para Todos os Alunos</span>
              </button>
            </form>
          </div>
        <?php endif; ?>

      <?php endif; ?>

    </div>

  </main>

  <!-- ================= JAVASCRIPT DE APOIO ================= -->
  <script>
    // Gerar senha forte aleatória
    function gerarSenhaAleatoria() {
      const chars = "ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnpqrstuvwxyz23456789!@#$";
      let pass = "";
      for (let i = 0; i < 8; i++) {
        pass += chars.charAt(Math.floor(Math.random() * chars.length));
      }
      const campo = document.getElementById("alunoSenha");
      if (campo) {
        campo.value = pass;
        campo.type = "text";
      }
    }

    // Alternar visibilidade de senha
    function toggleSenha(inputId, btn) {
      const campo = document.getElementById(inputId);
      if (campo) {
        if (campo.type === "password") {
          campo.type = "text";
          btn.textContent = "🔒";
        } else {
          campo.type = "password";
          btn.textContent = "👁";
        }
      }
    }

    // Inserir formatação de texto rápida no textarea da aula
    function inserirFormatacao(prefixo, sufixo) {
      const textarea = document.getElementById("aulaTexto");
      if (!textarea) return;
      const start = textarea.selectionStart;
      const end = textarea.selectionEnd;
      const textoSelecionado = textarea.value.substring(start, end);
      const replacement = prefixo + (textoSelecionado || "texto") + sufixo;
      textarea.value = textarea.value.substring(0, start) + replacement + textarea.value.substring(end);
      textarea.focus();
      textarea.setSelectionRange(start + prefixo.length, start + prefixo.length + (textoSelecionado ? textoSelecionado.length : 5));
    }

    // Filtro instantâneo na tabela de alunos
    function filtrarTabelaAlunos() {
      const input = document.getElementById("filtroAlunosInput");
      const filtro = input.value.toLowerCase();
      const tabela = document.getElementById("tabelaAlunos");
      if (!tabela) return;
      const linhas = tabela.getElementsByTagName("tbody")[0].getElementsByTagName("tr");
      for (let i = 0; i < linhas.length; i++) {
        const textoLinha = linhas[i].textContent.toLowerCase();
        if (textoLinha.indexOf(filtro) > -1) {
          linhas[i].style.display = "";
        } else {
          linhas[i].style.display = "none";
        }
      }
    }
  </script>

</body>
</html>
