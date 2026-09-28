<?php
$pdo = new PDO("mysql:host=localhost;dbname=finanzasdb;charset=utf8mb4", "root", "");
$stmt = $pdo->query("SHOW COLUMNS FROM gastos_pendientes LIKE 'tarjeta_id'");
$col = $stmt->fetch();

if (!$col) {
    $pdo->exec("ALTER TABLE gastos_pendientes ADD COLUMN tarjeta_id INT NULL DEFAULT NULL AFTER deuda_id");
    echo "COLUMN_ADDED_GASTOS_PENDIENTES\n";
} else {
    echo "GASTOS_PENDIENTES_ALREADY_HAS_TARJETA_ID\n";
}

// Check plantillas as well
$stmt2 = $pdo->query("SHOW COLUMNS FROM gastos_fijos_plantillas LIKE 'tarjeta_id'");
$col2 = $stmt2->fetch();
if (!$col2) {
    $pdo->exec("ALTER TABLE gastos_fijos_plantillas ADD COLUMN tarjeta_id INT NULL DEFAULT NULL AFTER deuda_id");
    echo "COLUMN_ADDED_PLANTILLAS\n";
} else {
    echo "PLANTILLAS_ALREADY_HAS_TARJETA_ID\n";
}
