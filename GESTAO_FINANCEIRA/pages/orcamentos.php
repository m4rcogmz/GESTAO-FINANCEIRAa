<?php
// pages/orcamentos.php
// Gestão de orçamentos mensais por categoria:
// - Definir/alterar limite mensal por categoria
// - Ver percentagem já utilizada (com base nas despesas do mês)
// - Aviso visual quando ultrapassar o limite

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_login();

$pdo = getPDO();
$utilizadorId = $_SESSION['utilizador_id'];

// Mês e ano atuais (apenas mês atual)
$mesAtual = (int)date('m');
$anoAtual = (int)date('Y');

// 1) Carregar categorias
$stmt = $pdo->query('SELECT id, nome FROM categorias ORDER BY nome');
$categorias = $stmt->fetchAll();

$mensagemSucesso = '';
$mensagemErro = '';

// 2) Processar submissão de limites (um por categoria)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    foreach ($categorias as $cat) {
        $catId = (int)$cat['id'];
        $campoNome = 'limite_' . $catId;

        if (!isset($_POST[$campoNome])) {
            continue;
        }

        $valorInput = trim($_POST[$campoNome]);
        // Permitimos vazio (sem orçamento definido)
        if ($valorInput === '') {
            // Apagar orçamento existente para esta categoria/mes/ano
            $stmt = $pdo->prepare('
                DELETE FROM orcamentos
                WHERE utilizador_id = :uid AND categoria_id = :cid AND mes = :mes AND ano = :ano
            ');
            $stmt->execute([
                'uid' => $utilizadorId,
                'cid' => $catId,
                'mes' => $mesAtual,
                'ano' => $anoAtual,
            ]);
            continue;
        }

        $limite = (float)str_replace(',', '.', $valorInput);
        if ($limite <= 0) {
            continue;
        }

        // Verificar se já existe orçamento para esta combinação
        $stmt = $pdo->prepare('
            SELECT id FROM orcamentos
            WHERE utilizador_id = :uid AND categoria_id = :cid AND mes = :mes AND ano = :ano
        ');
        $stmt->execute([
            'uid' => $utilizadorId,
            'cid' => $catId,
            'mes' => $mesAtual,
            'ano' => $anoAtual,
        ]);
        $orcamentoExistente = $stmt->fetch();

        if ($orcamentoExistente) {
            // Atualizar
            $stmt = $pdo->prepare('
                UPDATE orcamentos
                SET limite = :limite
                WHERE id = :id
            ');
            $stmt->execute([
                'limite' => $limite,
                'id' => $orcamentoExistente['id'],
            ]);
        } else {
            // Inserir
            $stmt = $pdo->prepare('
                INSERT INTO orcamentos (utilizador_id, categoria_id, limite, mes, ano)
                VALUES (:uid, :cid, :limite, :mes, :ano)
            ');
            $stmt->execute([
                'uid' => $utilizadorId,
                'cid' => $catId,
                'limite' => $limite,
                'mes' => $mesAtual,
                'ano' => $anoAtual,
            ]);
        }
    }

    $mensagemSucesso = 'Orçamentos guardados com sucesso para ' . $mesAtual . '/' . $anoAtual . '.';
}

// 3) Obter orçamentos e despesas usadas por categoria para o mês/ano selecionado
$stmt = $pdo->prepare('
    SELECT 
        c.id AS categoria_id,
        c.nome AS categoria_nome,
        o.limite,
        COALESCE(SUM(d.valor), 0) AS gasto
    FROM categorias c
    LEFT JOIN orcamentos o
        ON o.categoria_id = c.id
       AND o.utilizador_id = :uid
       AND o.mes = :mes
       AND o.ano = :ano
    LEFT JOIN despesas d
        ON d.categoria_id = c.id
       AND d.utilizador_id = :uid
       AND MONTH(d.data) = :mes
       AND YEAR(d.data) = :ano
    GROUP BY c.id, c.nome, o.limite
    ORDER BY c.nome
');
$stmt->execute([
    'uid' => $utilizadorId,
    'mes' => $mesAtual,
    'ano' => $anoAtual,
]);
$dadosOrcamentos = $stmt->fetchAll();

// Função auxiliar para obter percentagem usada
function calcular_percentagem($gasto, $limite)
{
    if ($limite <= 0) {
        return null; // Sem limite definido
    }
    return ($gasto / $limite) * 100;
}

$appTopBarTitle = 'Orçamentos';
?>
<?php include __DIR__ . '/../includes/header.php'; ?>

<div class="ds-page-header">
    <h1>Orçamentos</h1>
    <p>Limites mensais por categoria e acompanhamento do que já gastaste.</p>
</div>


<?php if ($mensagemSucesso): ?>
    <div class="alert alert-success"><?php echo htmlspecialchars($mensagemSucesso); ?></div>
<?php endif; ?>

<?php if ($mensagemErro): ?>
    <div class="alert alert-danger"><?php echo htmlspecialchars($mensagemErro); ?></div>
<?php endif; ?>

<form method="post">
    <div class="card shadow-sm">
        <div class="card-body">
            <h5 class="card-title mb-3">
                Orçamentos mensais (<?php echo $mesAtual . '/' . $anoAtual; ?>)
            </h5>
            <div class="table-responsive ds-table-wrap">
                <table class="table align-middle">
                    <thead>
                    <tr>
                        <th>Categoria</th>
                        <th style="width: 180px;">Limite (€)</th>
                        <th style="width: 180px;">Gasto (€)</th>
                        <th>Progresso</th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($dadosOrcamentos as $linha): ?>
                        <?php
                        $limite = $linha['limite'] !== null ? (float)$linha['limite'] : 0;
                        $gasto = (float)$linha['gasto'];
                        $percentagem = calcular_percentagem($gasto, $limite);

                        // Cores do progresso:
                        // - >= 100%: vermelho
                        // - >= 50%: amarelo
                        // - < 50%: verde
                        $corBarra = 'var(--success-color)';
                        if ($percentagem !== null) {
                            if ($percentagem >= 100) {
                                $corBarra = 'var(--danger-color)';
                            } elseif ($percentagem >= 50) {
                                $corBarra = 'var(--warning-color)';
                            }
                        }
                        ?>
                        <tr>
                            <td><?php echo htmlspecialchars($linha['categoria_nome']); ?></td>
                            <td>
                                <input type="number" step="0.01" min="0" class="form-control"
                                       name="limite_<?php echo (int)$linha['categoria_id']; ?>"
                                       value="<?php echo $limite > 0 ? htmlspecialchars($limite) : ''; ?>"
                                       placeholder="Sem limite">
                            </td>
                            <td style="color: var(--danger-color); font-weight: 600;">
                                <?php echo number_format($gasto, 2, ',', ' '); ?> €
                            </td>
                            <td>
                                <?php if ($percentagem === null): ?>
                                    <span class="text-muted small">Sem limite definido</span>
                                <?php else: ?>
                                    <div class="progress">
                                        <div class="progress-bar"
                                             role="progressbar"
                                             style="width: <?php echo min(100, $percentagem); ?>%; background-color: <?php echo $corBarra; ?>;"
                                             aria-valuenow="<?php echo (int)$percentagem; ?>"
                                             aria-valuemin="0"
                                             aria-valuemax="100">
                                            <span class="small">
                                                <?php echo number_format($percentagem, 0); ?>%
                                            </span>
                                        </div>
                                    </div>
                                    <?php if ($percentagem >= 100): ?>
                                        <span class="small" style="color: var(--danger-color); font-weight: 600;">Limite ultrapassado!</span>
                                    <?php elseif ($percentagem >= 80): ?>
                                        <span class="small" style="color: var(--warning-color); font-weight: 600;">Quase a atingir o limite.</span>
                                    <?php endif; ?>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <button type="submit" class="btn btn-primary mt-3">Guardar orçamentos</button>
        </div>
    </div>
</form>

<?php include __DIR__ . '/../includes/footer.php'; ?>

