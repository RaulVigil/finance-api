<?php

namespace App\Controllers;

use CodeIgniter\RESTful\ResourceController;
use CodeIgniter\API\ResponseTrait;
use App\Models\GastosFijosPlantillasModel;
use App\Models\GastosPendientesModel;
use App\Models\TransaccionesModel;
use App\Models\UsuariosModel;
use App\Models\DeudasModel;
use App\Models\TarjetasCreditoModel;

class GastosFijosController extends ResourceController
{
    use ResponseTrait;

    protected $plantillasModel;
    protected $pendientesModel;
    protected $transaccionesModel;
    protected $usuariosModel;
    protected $deudasModel;
    protected $tarjetasModel;
    protected $db;

    public function __construct()
    {
        $this->plantillasModel    = new GastosFijosPlantillasModel();
        $this->pendientesModel    = new GastosPendientesModel();
        $this->transaccionesModel = new TransaccionesModel();
        $this->usuariosModel      = new UsuariosModel();
        $this->deudasModel        = new DeudasModel();
        $this->tarjetasModel      = new TarjetasCreditoModel();
        $this->db                 = \Config\Database::connect();
    }

    // ─────────────────────────────────────────────────────────
    // PLANTILLAS
    // ─────────────────────────────────────────────────────────

    /** GET /api/gastos-fijos/plantillas — Lista de plantillas del usuario */
    public function plantillas()
    {
        $authUser = $this->request->getServer('auth_user');
        if (!$authUser || !isset($authUser['usuario_id'])) {
            return $this->failUnauthorized('Usuario no autenticado');
        }

        $usuarioId = $authUser['usuario_id'];

        try {
            $plantillas = $this->plantillasModel
                ->select('gastos_fijos_plantillas.plantilla_id,
                          gastos_fijos_plantillas.nombre,
                          gastos_fijos_plantillas.descripcion,
                          gastos_fijos_plantillas.monto_estimado,
                          gastos_fijos_plantillas.activa,
                          gastos_fijos_plantillas.categoria_id,
                          gastos_fijos_plantillas.deuda_id,
                          gastos_fijos_plantillas.tarjeta_id,
                          categorias.nombre AS categoria,
                          deudas.nombre_deuda,
                          deudas.cuota_mensual,
                          deudas.saldo_pendiente,
                          deudas.estado AS estado_deuda,
                          COALESCE(tarjetas_directas.nombre, tarjetas_credito.nombre) AS tarjeta_nombre')
                ->join('categorias', 'categorias.categoria_id = gastos_fijos_plantillas.categoria_id', 'left')
                ->join('deudas', 'deudas.deuda_id = gastos_fijos_plantillas.deuda_id', 'left')
                ->join('tarjetas_credito AS tarjetas_directas', 'tarjetas_directas.tarjeta_id = gastos_fijos_plantillas.tarjeta_id', 'left')
                ->join('tarjetas_credito', 'tarjetas_credito.tarjeta_id = deudas.tarjeta_id', 'left')
                ->where('gastos_fijos_plantillas.usuario_id', $usuarioId)
                ->orderBy('gastos_fijos_plantillas.nombre', 'ASC')
                ->findAll();

            return $this->respond([
                'status'  => 200,
                'message' => 'Plantillas de gastos fijos',
                'data'    => $plantillas,
            ]);
        } catch (\Exception $e) {
            return $this->failServerError($e->getMessage());
        }
    }

    /** POST /api/gastos-fijos/plantillas — Crear plantilla */
    public function crearPlantilla()
    {
        $authUser = $this->request->getServer('auth_user');
        if (!$authUser || !isset($authUser['usuario_id'])) {
            return $this->failUnauthorized('Usuario no autenticado');
        }

        $usuarioId = $authUser['usuario_id'];
        $json      = $this->request->getJSON();

        if (empty($json->nombre) || empty($json->categoria_id)) {
            return $this->failValidationErrors('nombre y categoria_id son obligatorios');
        }

        try {
            $data = [
                'usuario_id'     => $usuarioId,
                'nombre'         => $json->nombre,
                'descripcion'    => $json->descripcion ?? null,
                'categoria_id'   => $json->categoria_id,
                'deuda_id'       => !empty($json->deuda_id) ? (int) $json->deuda_id : null,
                'tarjeta_id'     => !empty($json->tarjeta_id) ? (int) $json->tarjeta_id : null,
                'monto_estimado' => (float) ($json->monto_estimado ?? 0),
                'activa'         => 1,
            ];

            $this->plantillasModel->insert($data);

            return $this->respondCreated([
                'status'  => 201,
                'message' => 'Plantilla creada correctamente',
            ]);
        } catch (\Exception $e) {
            return $this->failServerError($e->getMessage());
        }
    }

    /** PUT /api/gastos-fijos/plantillas/{id} — Actualizar plantilla */
    public function actualizarPlantilla($plantillaId)
    {
        $authUser = $this->request->getServer('auth_user');
        if (!$authUser || !isset($authUser['usuario_id'])) {
            return $this->failUnauthorized('Usuario no autenticado');
        }

        $usuarioId = $authUser['usuario_id'];
        $plantilla = $this->plantillasModel->find($plantillaId);

        if (!$plantilla || $plantilla['usuario_id'] != $usuarioId) {
            return $this->failNotFound('Plantilla no encontrada');
        }

        $json = $this->request->getJSON();

        try {
            $data = [];
            if (isset($json->nombre))         $data['nombre']         = $json->nombre;
            if (isset($json->descripcion))    $data['descripcion']    = $json->descripcion;
            if (isset($json->categoria_id))   $data['categoria_id']   = $json->categoria_id;
            if (isset($json->monto_estimado)) $data['monto_estimado'] = (float) $json->monto_estimado;
            if (isset($json->activa))         $data['activa']         = (int) $json->activa;
            if (property_exists($json, 'deuda_id')) {
                $data['deuda_id'] = !empty($json->deuda_id) ? (int) $json->deuda_id : null;
            }
            if (property_exists($json, 'tarjeta_id')) {
                $data['tarjeta_id'] = !empty($json->tarjeta_id) ? (int) $json->tarjeta_id : null;
            }

            $this->plantillasModel->update($plantillaId, $data);

            return $this->respond([
                'status'  => 200,
                'message' => 'Plantilla actualizada correctamente',
            ]);
        } catch (\Exception $e) {
            return $this->failServerError($e->getMessage());
        }
    }

    /** DELETE /api/gastos-fijos/plantillas/{id} — Desactivar/eliminar plantilla */
    public function eliminarPlantilla($plantillaId)
    {
        $authUser = $this->request->getServer('auth_user');
        if (!$authUser || !isset($authUser['usuario_id'])) {
            return $this->failUnauthorized('Usuario no autenticado');
        }

        $usuarioId = $authUser['usuario_id'];
        $plantilla = $this->plantillasModel->find($plantillaId);

        if (!$plantilla || $plantilla['usuario_id'] != $usuarioId) {
            return $this->failNotFound('Plantilla no encontrada');
        }

        try {
            // Desactivar en lugar de borrar (preserva historial)
            $this->plantillasModel->update($plantillaId, ['activa' => 0]);

            return $this->respond([
                'status'  => 200,
                'message' => 'Plantilla desactivada correctamente',
            ]);
        } catch (\Exception $e) {
            return $this->failServerError($e->getMessage());
        }
    }

    // ─────────────────────────────────────────────────────────
    // GASTOS PENDIENTES (instancias mensuales)
    // ─────────────────────────────────────────────────────────

    /**
     * GET /api/gastos-pendientes?mes=9&anio=2026
     * Lista los gastos pendientes del mes/año indicado (o mes actual por defecto)
     */
    public function pendientes()
    {
        $authUser = $this->request->getServer('auth_user');
        if (!$authUser || !isset($authUser['usuario_id'])) {
            return $this->failUnauthorized('Usuario no autenticado');
        }

        $usuarioId = $authUser['usuario_id'];
        $mes       = (int) ($this->request->getGet('mes')  ?? date('n'));
        $anio      = (int) ($this->request->getGet('anio') ?? date('Y'));

        try {
            $gastos = $this->pendientesModel
                ->select('gastos_pendientes.gasto_id,
                          gastos_pendientes.plantilla_id,
                          gastos_pendientes.deuda_id,
                          gastos_pendientes.tarjeta_id,
                          gastos_pendientes.nombre,
                          gastos_pendientes.descripcion,
                          gastos_pendientes.monto,
                          gastos_pendientes.mes,
                          gastos_pendientes.anio,
                          gastos_pendientes.estado,
                          gastos_pendientes.fecha_pago,
                          gastos_pendientes.transaccion_id,
                          categorias.nombre AS categoria,
                          gastos_pendientes.categoria_id,
                          deudas.nombre_deuda,
                          deudas.cuota_mensual,
                          deudas.saldo_pendiente AS deuda_saldo_pendiente,
                          deudas.estado AS deuda_estado,
                          COALESCE(gastos_pendientes.tarjeta_id, deudas.tarjeta_id) AS tarjeta_vinculada_id,
                          COALESCE(tarjetas_directas.nombre, tarjetas_credito.nombre) AS tarjeta_nombre')
                ->join('categorias', 'categorias.categoria_id = gastos_pendientes.categoria_id', 'left')
                ->join('deudas', 'deudas.deuda_id = gastos_pendientes.deuda_id', 'left')
                ->join('tarjetas_credito AS tarjetas_directas', 'tarjetas_directas.tarjeta_id = gastos_pendientes.tarjeta_id', 'left')
                ->join('tarjetas_credito', 'tarjetas_credito.tarjeta_id = deudas.tarjeta_id', 'left')
                ->where('gastos_pendientes.usuario_id', $usuarioId)
                ->where('gastos_pendientes.mes', $mes)
                ->where('gastos_pendientes.anio', $anio)
                ->orderBy('gastos_pendientes.estado', 'ASC')  // pendientes primero
                ->orderBy('gastos_pendientes.nombre', 'ASC')
                ->findAll();

            // Totales
            $totalPendiente = 0;
            $totalPagado    = 0;
            foreach ($gastos as $g) {
                if ($g['estado'] === 'pagado') {
                    $totalPagado += (float) ($g['monto'] ?? 0);
                } else {
                    $totalPendiente += (float) ($g['monto'] ?? 0);
                }
            }

            return $this->respond([
                'status'  => 200,
                'message' => "Gastos pendientes $mes/$anio",
                'mes'     => $mes,
                'anio'    => $anio,
                'totales' => [
                    'pendiente' => round($totalPendiente, 2),
                    'pagado'    => round($totalPagado, 2),
                ],
                'data' => $gastos,
            ]);
        } catch (\Exception $e) {
            return $this->failServerError($e->getMessage());
        }
    }

    /**
     * POST /api/gastos-pendientes/generar-mes
     * Genera instancias del mes actual a partir de las plantillas activas
     */
    public function generarMes()
    {
        $authUser = $this->request->getServer('auth_user');
        if (!$authUser || !isset($authUser['usuario_id'])) {
            return $this->failUnauthorized('Usuario no autenticado');
        }

        $usuarioId = $authUser['usuario_id'];
        $json      = $this->request->getJSON();

        // Null-safe: si el body viene vacío usamos fecha actual
        $mes  = (int) (($json && isset($json->mes))  ? $json->mes  : date('n'));
        $anio = (int) (($json && isset($json->anio)) ? $json->anio : date('Y'));

        try {
            // Verificar cuáles plantillas ya tienen instancia este mes
            $existentes = $this->pendientesModel
                ->where('usuario_id', $usuarioId)
                ->where('mes', $mes)
                ->where('anio', $anio)
                ->where('plantilla_id IS NOT NULL', null, false)
                ->findAll();

            $plantillasConInstancia = array_column($existentes, 'plantilla_id');

            // Plantillas activas sin instancia este mes
            $plantillas = $this->plantillasModel
                ->where('usuario_id', $usuarioId)
                ->where('activa', 1)
                ->findAll();

            $creadas = 0;
            foreach ($plantillas as $p) {
                if (in_array($p['plantilla_id'], $plantillasConInstancia)) {
                    continue; // Ya existe este mes
                }

                // Si la plantilla está vinculada a una deuda y ya fue pagada por completo, omitirla
                if (!empty($p['deuda_id'])) {
                    $deudaCheck = $this->deudasModel->find($p['deuda_id']);
                    if ($deudaCheck && $deudaCheck['estado'] === 'Pagada') {
                        continue;
                    }
                }

                $this->pendientesModel->insert([
                    'usuario_id'   => $usuarioId,
                    'plantilla_id' => $p['plantilla_id'],
                    'deuda_id'     => !empty($p['deuda_id']) ? $p['deuda_id'] : null,
                    'tarjeta_id'   => !empty($p['tarjeta_id']) ? $p['tarjeta_id'] : null,
                    'nombre'       => $p['nombre'],
                    'descripcion'  => $p['descripcion'],
                    'categoria_id' => $p['categoria_id'],
                    'monto'        => ($p['monto_estimado'] > 0) ? $p['monto_estimado'] : null,
                    'mes'          => $mes,
                    'anio'         => $anio,
                    'estado'       => 'pendiente',
                ]);
                $creadas++;
            }

            return $this->respond([
                'status'  => 200,
                'message' => "$creadas gasto(s) generado(s) para $mes/$anio",
                'creados' => $creadas,
            ]);
        } catch (\Throwable $e) {
            return $this->failServerError('Error: ' . $e->getMessage() . ' en línea ' . $e->getLine());
        }
    }

    /** POST /api/gastos-pendientes — Crear gasto esporádico manual */
    public function crearPendiente()
    {
        $authUser = $this->request->getServer('auth_user');
        if (!$authUser || !isset($authUser['usuario_id'])) {
            return $this->failUnauthorized('Usuario no autenticado');
        }

        $usuarioId = $authUser['usuario_id'];
        $json      = $this->request->getJSON();

        if (empty($json->nombre) || empty($json->categoria_id)) {
            return $this->failValidationErrors('nombre y categoria_id son obligatorios');
        }

        try {
            $tarjetaId = !empty($json->tarjeta_id) ? (int) $json->tarjeta_id : null;
            $monto     = isset($json->monto) && $json->monto > 0 ? (float) $json->monto : null;

            $this->db->transStart();

            // Si es un nuevo consumo corriente con tarjeta (no una cuota de deuda/tasa 0 ya registrada), registrar el consumo en la tarjeta
            if ($tarjetaId && $monto > 0 && empty($json->deuda_id)) {
                $tarjeta = $this->tarjetasModel->find($tarjetaId);
                if ($tarjeta && $tarjeta['usuario_id'] == $usuarioId) {
                    $nuevoDisponible = max(0, (float) $tarjeta['saldo_disponible'] - $monto);
                    $nuevoUtilizado  = (float) $tarjeta['saldo_utilizado'] + $monto;

                    $this->tarjetasModel->update($tarjetaId, [
                        'saldo_disponible' => $nuevoDisponible,
                        'saldo_utilizado'  => $nuevoUtilizado,
                    ]);

                    // Registrar transacción de compra con tarjeta en historial de tarjeta
                    $this->transaccionesModel->insert([
                        'usuario_id'    => $usuarioId,
                        'fecha'         => date('Y-m-d'),
                        'tipo'          => 'Egreso',
                        'monto'         => $monto,
                        'estado'        => 'pagado',
                        'saldo_despues' => null, // Crédito, no debita cuenta corriente
                        'descripcion'   => $json->nombre . ($json->descripcion ? ' — ' . $json->descripcion : ''),
                        'categoria_id'  => $json->categoria_id,
                        'deuda_id'      => null,
                        'tarjeta_id'    => $tarjetaId,
                    ]);
                }
            }

            $data = [
                'usuario_id'   => $usuarioId,
                'plantilla_id' => null,
                'deuda_id'     => !empty($json->deuda_id) ? (int) $json->deuda_id : null,
                'tarjeta_id'   => $tarjetaId,
                'nombre'       => $json->nombre,
                'descripcion'  => $json->descripcion ?? null,
                'categoria_id' => $json->categoria_id,
                'monto'        => $monto,
                'mes'          => (int) ($json->mes  ?? date('n')),
                'anio'         => (int) ($json->anio ?? date('Y')),
                'estado'       => 'pendiente',
            ];

            $this->pendientesModel->insert($data);

            $this->db->transComplete();

            if ($this->db->transStatus() === false) {
                return $this->failServerError('Error al crear el gasto pendiente');
            }

            return $this->respondCreated([
                'status'  => 201,
                'message' => 'Gasto pendiente creado correctamente',
            ]);
        } catch (\Exception $e) {
            $this->db->transRollback();
            return $this->failServerError($e->getMessage());
        }
    }

    /** PATCH /api/gastos-pendientes/{id}/monto — Actualizar monto de un gasto pendiente */
    public function actualizarMonto($gastoId)
    {
        $authUser = $this->request->getServer('auth_user');
        if (!$authUser || !isset($authUser['usuario_id'])) {
            return $this->failUnauthorized('Usuario no autenticado');
        }

        $usuarioId = $authUser['usuario_id'];
        $gasto     = $this->pendientesModel->find($gastoId);

        if (!$gasto || $gasto['usuario_id'] != $usuarioId) {
            return $this->failNotFound('Gasto no encontrado');
        }

        if ($gasto['estado'] === 'pagado') {
            return $this->failValidationErrors('No se puede editar un gasto ya pagado');
        }

        $json = $this->request->getJSON();

        if (!isset($json->monto) || (float) $json->monto <= 0) {
            return $this->failValidationErrors('El monto debe ser mayor a 0');
        }

        try {
            $this->pendientesModel->update($gastoId, ['monto' => (float) $json->monto]);

            return $this->respond([
                'status'  => 200,
                'message' => 'Monto actualizado correctamente',
            ]);
        } catch (\Exception $e) {
            return $this->failServerError($e->getMessage());
        }
    }

    /**
     * POST /api/gastos-pendientes/{id}/pagar
     * Marca el gasto como pagado, descuenta del saldo_actual y crea la transacción
     */
    public function pagarGasto($gastoId)
    {
        $authUser = $this->request->getServer('auth_user');
        if (!$authUser || !isset($authUser['usuario_id'])) {
            return $this->failUnauthorized('Usuario no autenticado');
        }

        $usuarioId = $authUser['usuario_id'];
        $gasto     = $this->pendientesModel->find($gastoId);

        if (!$gasto || $gasto['usuario_id'] != $usuarioId) {
            return $this->failNotFound('Gasto no encontrado');
        }

        if ($gasto['estado'] === 'pagado') {
            return $this->failValidationErrors('Este gasto ya fue marcado como pagado');
        }

        if (empty($gasto['monto']) || (float) $gasto['monto'] <= 0) {
            return $this->failValidationErrors('Debes definir el monto antes de pagar');
        }

        $this->db->transStart();

        try {
            $usuario     = $this->usuariosModel->find($usuarioId);
            $saldoActual = (float) $usuario['saldo_actual'];
            $monto       = (float) $gasto['monto'];
            $nuevoSaldo  = $saldoActual - $monto;

            // Manejo de deuda y tarjeta asociada
            $deudaId   = !empty($gasto['deuda_id']) ? (int) $gasto['deuda_id'] : null;
            $tarjetaId = !empty($gasto['tarjeta_id']) ? (int) $gasto['tarjeta_id'] : null;

            if ($deudaId) {
                $deuda = $this->deudasModel->find($deudaId);
                if ($deuda) {
                    $nuevoSaldoDeuda = max(0, (float) $deuda['saldo_pendiente'] - $monto);
                    $estadoDeuda = ($nuevoSaldoDeuda <= 0) ? 'Pagada' : 'Activa';

                    $this->deudasModel->update($deudaId, [
                        'saldo_pendiente' => $nuevoSaldoDeuda,
                        'estado'          => $estadoDeuda,
                    ]);

                    // Si la deuda proviene de una tarjeta de crédito (ej. Tasa 0)
                    if (!empty($deuda['tarjeta_id'])) {
                        $tarjetaId = (int) $deuda['tarjeta_id'];
                    }
                }
            }

            // Si hay tarjeta vinculada (directa o via deuda), reponer/liberar saldo en la tarjeta
            if ($tarjetaId) {
                $tarjeta = $this->tarjetasModel->find($tarjetaId);
                if ($tarjeta) {
                    $nuevoUtilizado  = max(0, (float) $tarjeta['saldo_utilizado'] - $monto);
                    $nuevoDisponible = min((float) $tarjeta['limite_credito'], (float) $tarjeta['saldo_disponible'] + $monto);

                    $this->tarjetasModel->update($tarjetaId, [
                        'saldo_disponible' => $nuevoDisponible,
                        'saldo_utilizado'  => $nuevoUtilizado,
                    ]);
                }
            }

            // Crear transacción Egreso
            $transaccionData = [
                'usuario_id'    => $usuarioId,
                'fecha'         => date('Y-m-d'),
                'tipo'          => 'Egreso',
                'monto'         => $monto,
                'estado'        => 'pagado',
                'saldo_despues' => $nuevoSaldo,
                'descripcion'   => $gasto['nombre'] . ($gasto['descripcion'] ? ' — ' . $gasto['descripcion'] : ''),
                'categoria_id'  => $gasto['categoria_id'],
                'deuda_id'      => $deudaId,
                'tarjeta_id'    => null, // Sale de cuenta bancaria para abonar a la deuda
            ];

            $this->transaccionesModel->insert($transaccionData);
            $transaccionId = $this->transaccionesModel->getInsertID();

            // Actualizar saldo usuario
            $this->usuariosModel->update($usuarioId, ['saldo_actual' => $nuevoSaldo]);

            // Marcar gasto como pagado
            $this->pendientesModel->update($gastoId, [
                'estado'         => 'pagado',
                'fecha_pago'     => date('Y-m-d'),
                'transaccion_id' => $transaccionId,
            ]);

            $this->db->transComplete();

            if ($this->db->transStatus() === false) {
                return $this->failServerError('Error al procesar el pago');
            }

            return $this->respond([
                'status'              => 200,
                'message'             => 'Gasto pagado correctamente',
                'nuevo_saldo_usuario' => $nuevoSaldo,
            ]);
        } catch (\Exception $e) {
            $this->db->transRollback();
            return $this->failServerError($e->getMessage());
        }
    }

    /** DELETE /api/gastos-pendientes/{id} — Eliminar gasto pendiente (solo si no está pagado) */
    public function eliminarPendiente($gastoId)
    {
        $authUser = $this->request->getServer('auth_user');
        if (!$authUser || !isset($authUser['usuario_id'])) {
            return $this->failUnauthorized('Usuario no autenticado');
        }

        $usuarioId = $authUser['usuario_id'];
        $gasto     = $this->pendientesModel->find($gastoId);

        if (!$gasto || $gasto['usuario_id'] != $usuarioId) {
            return $this->failNotFound('Gasto no encontrado');
        }

        if ($gasto['estado'] === 'pagado') {
            return $this->failValidationErrors('No se puede eliminar un gasto ya pagado');
        }

        try {
            $this->db->transStart();

            // Si estaba vinculado directamente a una tarjeta y no se pagó, revertir el saldo consumido
            if (!empty($gasto['tarjeta_id']) && !empty($gasto['monto'])) {
                $tarjeta = $this->tarjetasModel->find($gasto['tarjeta_id']);
                if ($tarjeta) {
                    $monto = (float) $gasto['monto'];
                    $nuevoUtilizado  = max(0, (float) $tarjeta['saldo_utilizado'] - $monto);
                    $nuevoDisponible = min((float) $tarjeta['limite_credito'], (float) $tarjeta['saldo_disponible'] + $monto);

                    $this->tarjetasModel->update($gasto['tarjeta_id'], [
                        'saldo_disponible' => $nuevoDisponible,
                        'saldo_utilizado'  => $nuevoUtilizado,
                    ]);
                }
            }

            $this->pendientesModel->delete($gastoId);

            $this->db->transComplete();

            if ($this->db->transStatus() === false) {
                return $this->failServerError('Error al eliminar el gasto');
            }

            return $this->respond([
                'status'  => 200,
                'message' => 'Gasto eliminado correctamente',
            ]);
        } catch (\Exception $e) {
            $this->db->transRollback();
            return $this->failServerError($e->getMessage());
        }
    }
}
