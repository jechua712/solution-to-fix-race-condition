<?php
// Configuración de conexión a la base de datos
$user = "juan";
$password = "upy";
$database = "bank";
$table_cuentas = "cuentas";
$table_transacciones = "transacciones";

// Obtener la información del formulario
$cuentaId = $_POST['cuenta_id'] ?? null;
$montoRetiro = $_POST['monto'] ?? null;

if ($cuentaId !== null && $montoRetiro !== null) {
    try {
        // Conectar a la base de datos usando PDO
        $db = new PDO("mysql:host=localhost;dbname=$database", $user, $password);
        $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        // Iniciar transacción
        $db->beginTransaction();

        // Bloquear la fila de la cuenta para evitar condiciones de carrera
        $stmt = $db->prepare("SELECT saldo FROM $table_cuentas WHERE id = :id FOR UPDATE");
        $stmt->execute(['id' => $cuentaId]);
        $cuenta = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($cuenta && $cuenta['saldo'] >= $montoRetiro) {
            // Actualizar el saldo de la cuenta
            $nuevoSaldo = $cuenta['saldo'] - $montoRetiro;
            $stmt = $db->prepare("UPDATE $table_cuentas SET saldo = :saldo WHERE id = :id");
            $stmt->execute(['saldo' => $nuevoSaldo, 'id' => $cuentaId]);

            // Registrar la transacción
            $stmt = $db->prepare("INSERT INTO $table_transacciones (cuenta_id, tipo, monto) VALUES (:cuenta_id, :tipo, :monto)");
            $stmt->execute(['cuenta_id' => $cuentaId, 'tipo' => 'retiro', 'monto' => $montoRetiro]);

            // Confirmar transacción
            $db->commit();

            echo "Retiro exitoso. Monto retirado: $" . htmlspecialchars($montoRetiro) . ". Nuevo saldo: $" . htmlspecialchars($nuevoSaldo);
        } else {
            // Deshacer transacción si el saldo es insuficiente o la cuenta no se encuentra
            $db->rollBack();
            echo "Error: Saldo insuficiente o cuenta no encontrada.";
        }
    } catch (PDOException $e) {
        // Deshacer transacción en caso de error
        if ($db->inTransaction()) {
            $db->rollBack();
        }
        echo "Error!: " . $e->getMessage() . "<br/>";
    }
} else {
    echo "Error: Datos incompletos.";
}
?>
