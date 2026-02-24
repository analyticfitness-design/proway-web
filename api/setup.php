<?php
/**
 * Setup script DESHABILITADO en produccion.
 * Para ejecutar: renombrar a setup-run.php y usar con secret.
 * O ejecutar localmente contra la DB.
 */
http_response_code(403);
header('Content-Type: application/json');
echo json_encode(['error' => 'Setup deshabilitado en produccion. Ver api/setup/ para scripts de migracion.']);
