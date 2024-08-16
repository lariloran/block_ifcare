<?php
// get_aluno.php
require __DIR__ . '/../db/db.php'; // Caminho relativo para a conexão com o banco de dados
header('Content-Type: application/json');

$sql = 'SELECT * FROM mdl_ifcare_aluno';
$stmt = $pdo->query($sql);

$data = $stmt->fetchAll(PDO::FETCH_ASSOC);
echo json_encode($data);
?>
