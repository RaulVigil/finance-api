<?php

namespace App\Controllers;

use CodeIgniter\RESTful\ResourceController;
use CodeIgniter\API\ResponseTrait;
use App\Models\TarjetasCreditoModel;
use App\Models\TransaccionesModel;
use App\Models\UsuariosModel;
use App\Models\DeudasModel;

class TarjetasController extends ResourceController
{
    use ResponseTrait;

    protected $tarjetasModel;
    protected $transaccionesModel;
    protected $usuariosModel;
    protected $deudasModel;
    protected $db;

    public function __construct()
    {
        $this->tarjetasModel      = new TarjetasCreditoModel();
        $this->transaccionesModel = new TransaccionesModel();
        $this->usuariosModel      = new UsuariosModel();
        $this->deudasModel        = new DeudasModel();
        $this->db                 = \Config\Database::connect();
    }

    // ─────────────────────────────────────────────────────────
    // TARJETAS
    // ─────────────────────────────────────────────────────────

    /** GET /api/tarjetas — Lista tarjetas del usuario */
    public function index()
    {
        $authUser = $this->request->getServer('auth_user');
        if (!$authUser || !isset($authUser['usuario_id'])) {
            return $this->failUnauthorized('Usuario no autenticado');
        }

        $usuarioId = $authUser['usuario_id'];

        try {
            $tarjetas = $this->tarjetasModel
                ->where('usuario_id', $usuarioId)
                ->orderBy('nombre', 'ASC')
                ->findAll();

            return $this->respond([
                'status'  => 200,
                'message' => 'Tarjetas del usuario',
                'data'    => $tarjetas,
            ]);
        } catch (\Exception $e) {
            return $this->failServerError($e->getMessage());
        }
    }

    /** POST /api/tarjetas — Crear tarjeta */
    public function crear()
    {
        $authUser = $this->request->getServer('auth_user');
        if (!$authUser || !isset($authUser['usuario_id'])) {
            return $this->failUnauthorized('Usuario no autenticado');
        }

        $usuarioId = $authUser['usuario_id'];
        $json      = $this->request->getJSON();

        if (empty($json->nombre) || empty($json->limite_credito)) {
            return $this->failValidationErrors('nombre y limite_credito son obligatorios');
        }

        $limite = (float) $json->limite_credito;
        if ($limite <= 0) {
            return $this->failValidationErrors('El límite de crédito debe ser mayor a 0');
        }

        try {
            $data = [
                'usuario_id'       => $usuarioId,
                'nombre'           => $json->nombre,
                'banco'            => $json->banco ?? null,
                'limite_credito'   => $limite,
                'saldo_disponible' => $limite,  // Empieza en el límite completo
                'saldo_utilizado'  => 0,
                'dia_corte'        => isset($json->dia_corte)  ? (int) $json->dia_corte  : null,
                'dia_pago'         => isset($json->dia_pago)   ? (int) $json->dia_pago   : null,
                'estado'           => 'Activa',
            ];

            $this->tarjetasModel->insert($data);

            return $this->respondCreated([
                'status'  => 201,
                'message' => 'Tarjeta creada correctamente',
            ]);
        } catch (\Exception $e) {
            return $this->failServerError($e->getMessage());
        }
    }

    /** GET /api/tarjetas/{id} — Detalle de tarjeta con transacciones */
    public function detalle($tarjetaId)
    {
        $authUser = $this->request->getServer('auth_user');
        if (!$authUser || !isset($authUser['usuario_id'])) {
            return $this->failUnauthorized('Usuario no autenticado');
        }

        $usuarioId = $authUser['usuario_id'];
        $tarjeta   = $this->tarjetasModel->find($tarjetaId);

        if (!$tarjeta || $tarjeta['usuario_id'] != $usuarioId) {
            return $this->failNotFound('Tarjeta no encontrada');
        }

        try {
            // Deudas (tasa 0) asociadas a esta tarjeta
            $deudas = $this->deudasModel
                ->where('tarjeta_id', $tarjetaId)
                ->where('usuario_id', $usuarioId)
                ->orderBy('estado', 'ASC') // Activas primero
                ->orderBy('deuda_id', 'DESC')
                ->findAll();

            $deudaIds = array_column($deudas, 'deuda_id');

            // Movimientos asociados a esta tarjeta (consumos y abonos/pagos de cuotas)
            $builder = $this->transaccionesModel
                ->select('transacciones.transaccion_id,
                          transacciones.fecha,
                          transacciones.tipo,
                          transacciones.monto,
                          transacciones.estado,
                          transacciones.saldo_despues,
                          transacciones.descripcion,
                          transacciones.deuda_id,
                          transacciones.tarjeta_id,
                          categorias.nombre AS categoria')
                ->join('categorias', 'categorias.categoria_id = transacciones.categoria_id', 'left')
                ->where('transacciones.usuario_id', $usuarioId);

            if (!empty($deudaIds)) {
                $builder->groupStart()
                        ->where('transacciones.tarjeta_id', $tarjetaId)
                        ->orWhereIn('transacciones.deuda_id', $deudaIds)
                        ->groupEnd();
            } else {
                $builder->where('transacciones.tarjeta_id', $tarjetaId);
            }

            $rawTransacciones = $builder
                ->orderBy('transacciones.fecha', 'DESC')
                ->orderBy('transacciones.transaccion_id', 'DESC')
                ->findAll();

            // Mapeamos para clarificar visualmente si es Cargo (compra) o Abono (pago a la tarjeta o cuota)
            $transacciones = array_map(function ($tx) {
                // Es abono si tiene saldo_despues (se pagó desde la cuenta corriente para amortizar)
                // o si la descripción indica abono/pago de cuota
                $esAbono = ($tx['saldo_despues'] !== null);
                return [
                    'transaccion_id' => $tx['transaccion_id'],
                    'fecha'          => $tx['fecha'],
                    'tipo'           => $tx['tipo'],
                    'monto'          => (float) $tx['monto'],
                    'es_abono'       => $esAbono,
                    'descripcion'    => $tx['descripcion'],
                    'categoria'      => $tx['categoria'],
                ];
            }, $rawTransacciones);

            return $this->respond([
                'status'        => 200,
                'message'       => 'Detalle de tarjeta',
                'tarjeta'       => $tarjeta,
                'transacciones' => $transacciones,
                'deudas_cuotas' => $deudas,
            ]);
        } catch (\Exception $e) {
            return $this->failServerError($e->getMessage());
        }
    }

    /**
     * POST /api/tarjetas/{id}/gasto
     * Registrar un gasto con la tarjeta de crédito.
     * - Reduce saldo_disponible / Aumenta saldo_utilizado de la tarjeta
     * - Crea una transacción Egreso identificada con tarjeta_id
     * - NO descuenta del saldo_actual (es crédito)
     */
    public function registrarGasto($tarjetaId)
    {
        $authUser = $this->request->getServer('auth_user');
        if (!$authUser || !isset($authUser['usuario_id'])) {
            return $this->failUnauthorized('Usuario no autenticado');
        }

        $usuarioId = $authUser['usuario_id'];
        $tarjeta   = $this->tarjetasModel->find($tarjetaId);

        if (!$tarjeta || $tarjeta['usuario_id'] != $usuarioId) {
            return $this->failNotFound('Tarjeta no encontrada');
        }

        if ($tarjeta['estado'] !== 'Activa') {
            return $this->failValidationErrors('La tarjeta no está activa');
        }

        $json = $this->request->getJSON();

        if (empty($json->monto) || (float) $json->monto <= 0) {
            return $this->failValidationErrors('El monto debe ser mayor a 0');
        }

        if (empty($json->categoria_id)) {
            return $this->failValidationErrors('categoria_id es obligatorio');
        }

        $monto = (float) $json->monto;

        if ($monto > (float) $tarjeta['saldo_disponible']) {
            return $this->failValidationErrors('Monto supera el saldo disponible de la tarjeta');
        }

        $this->db->transStart();

        try {
            $nuevoDisponible = (float) $tarjeta['saldo_disponible'] - $monto;
            $nuevoUtilizado  = (float) $tarjeta['saldo_utilizado']  + $monto;

            // Actualizar saldo de la tarjeta
            $this->tarjetasModel->update($tarjetaId, [
                'saldo_disponible' => $nuevoDisponible,
                'saldo_utilizado'  => $nuevoUtilizado,
            ]);

            // Crear transacción Egreso con tarjeta_id
            // saldo_despues es null — no afecta el saldo de cuenta corriente
            $this->transaccionesModel->insert([
                'usuario_id'    => $usuarioId,
                'fecha'         => $json->fecha ?? date('Y-m-d'),
                'tipo'          => 'Egreso',
                'monto'         => $monto,
                'estado'        => 'pagado',
                'saldo_despues' => null,
                'descripcion'   => $json->descripcion ?? '',
                'categoria_id'  => $json->categoria_id,
                'deuda_id'      => null,
                'tarjeta_id'    => (int) $tarjetaId,
            ]);

            $this->db->transComplete();

            if ($this->db->transStatus() === false) {
                return $this->failServerError('Error al registrar el gasto');
            }

            return $this->respondCreated([
                'status'           => 201,
                'message'          => 'Gasto con tarjeta registrado correctamente',
                'saldo_disponible' => $nuevoDisponible,
                'saldo_utilizado'  => $nuevoUtilizado,
            ]);
        } catch (\Exception $e) {
            $this->db->transRollback();
            return $this->failServerError($e->getMessage());
        }
    }

    /**
     * POST /api/tarjetas/{id}/pagar
     * Pagar la tarjeta (total o parcial).
     * - Descuenta del saldo_actual del usuario
     * - Abona al saldo_disponible / reduce saldo_utilizado de la tarjeta
     * - Crea transacción Egreso en la cuenta corriente
     */
    public function pagarTarjeta($tarjetaId)
    {
        $authUser = $this->request->getServer('auth_user');
        if (!$authUser || !isset($authUser['usuario_id'])) {
            return $this->failUnauthorized('Usuario no autenticado');
        }

        $usuarioId = $authUser['usuario_id'];
        $tarjeta   = $this->tarjetasModel->find($tarjetaId);

        if (!$tarjeta || $tarjeta['usuario_id'] != $usuarioId) {
            return $this->failNotFound('Tarjeta no encontrada');
        }

        $json = $this->request->getJSON();

        // Si no se especifica monto, paga el saldo utilizado completo
        $montoPago = isset($json->monto) && (float) $json->monto > 0
            ? (float) $json->monto
            : (float) $tarjeta['saldo_utilizado'];

        if ($montoPago <= 0) {
            return $this->failValidationErrors('No hay saldo utilizado para pagar');
        }

        if ($montoPago > (float) $tarjeta['saldo_utilizado']) {
            return $this->failValidationErrors('El monto supera el saldo utilizado de la tarjeta');
        }

        $this->db->transStart();

        try {
            $usuario     = $this->usuariosModel->find($usuarioId);
            $saldoActual = (float) $usuario['saldo_actual'];
            $nuevoSaldo  = $saldoActual - $montoPago;

            $nuevoDisponible = (float) $tarjeta['saldo_disponible'] + $montoPago;
            $nuevoUtilizado  = (float) $tarjeta['saldo_utilizado']  - $montoPago;
            if ($nuevoUtilizado < 0) $nuevoUtilizado = 0;
            if ($nuevoDisponible > (float) $tarjeta['limite_credito']) {
                $nuevoDisponible = (float) $tarjeta['limite_credito'];
            }

            // Actualizar saldo de la tarjeta
            $this->tarjetasModel->update($tarjetaId, [
                'saldo_disponible' => $nuevoDisponible,
                'saldo_utilizado'  => $nuevoUtilizado,
            ]);

            // Actualizar saldo_actual del usuario
            $this->usuariosModel->update($usuarioId, ['saldo_actual' => $nuevoSaldo]);

            // Crear transacción Egreso en cuenta corriente
            $descripcion = 'Pago tarjeta: ' . $tarjeta['nombre'];
            if ($json->descripcion ?? '') {
                $descripcion .= ' — ' . $json->descripcion;
            }

            $this->transaccionesModel->insert([
                'usuario_id'    => $usuarioId,
                'fecha'         => $json->fecha ?? date('Y-m-d'),
                'tipo'          => 'Egreso',
                'monto'         => $montoPago,
                'estado'        => 'pagado',
                'saldo_despues' => $nuevoSaldo,
                'descripcion'   => $descripcion,
                'categoria_id'  => $json->categoria_id ?? 12, // "Pago de Deudas" por defecto
                'deuda_id'      => null,
                'tarjeta_id'    => null, // No linkeamos al pago en sí
            ]);

            $this->db->transComplete();

            if ($this->db->transStatus() === false) {
                return $this->failServerError('Error al procesar el pago de tarjeta');
            }

            return $this->respond([
                'status'              => 200,
                'message'             => 'Pago de tarjeta registrado correctamente',
                'nuevo_saldo_usuario' => $nuevoSaldo,
                'saldo_disponible'    => $nuevoDisponible,
                'saldo_utilizado'     => $nuevoUtilizado,
            ]);
        } catch (\Exception $e) {
            $this->db->transRollback();
            return $this->failServerError($e->getMessage());
        }
    }

    /**
     * POST /api/tarjetas/{id}/cuota
     * Registrar una compra a tasa 0 / cuotas (crea una Deuda vinculada a la tarjeta)
     */
    public function registrarCuota($tarjetaId)
    {
        $authUser = $this->request->getServer('auth_user');
        if (!$authUser || !isset($authUser['usuario_id'])) {
            return $this->failUnauthorized('Usuario no autenticado');
        }

        $usuarioId = $authUser['usuario_id'];
        $tarjeta   = $this->tarjetasModel->find($tarjetaId);

        if (!$tarjeta || $tarjeta['usuario_id'] != $usuarioId) {
            return $this->failNotFound('Tarjeta no encontrada');
        }

        $json = $this->request->getJSON();

        if (empty($json->nombre_deuda) || empty($json->monto_total_inicial) || empty($json->cuota_mensual)) {
            return $this->failValidationErrors('nombre_deuda, monto_total_inicial y cuota_mensual son obligatorios');
        }

        $montoTotal = (float) $json->monto_total_inicial;
        $cuota      = (float) $json->cuota_mensual;

        if ($montoTotal <= 0 || $cuota <= 0) {
            return $this->failValidationErrors('Monto total y cuota deben ser mayores a 0');
        }

        if ($montoTotal > (float) $tarjeta['saldo_disponible']) {
            return $this->failValidationErrors('El monto supera el saldo disponible de la tarjeta');
        }

        $this->db->transStart();

        try {
            // Reducir saldo disponible de la tarjeta
            $nuevoDisponible = (float) $tarjeta['saldo_disponible'] - $montoTotal;
            $nuevoUtilizado  = (float) $tarjeta['saldo_utilizado']  + $montoTotal;

            $this->tarjetasModel->update($tarjetaId, [
                'saldo_disponible' => $nuevoDisponible,
                'saldo_utilizado'  => $nuevoUtilizado,
            ]);

            // Crear Deuda tipo Pagar vinculada a la tarjeta
            $this->deudasModel->insert([
                'usuario_id'          => $usuarioId,
                'nombre_deuda'        => $json->nombre_deuda,
                'tipo_deuda'          => 'Pagar',
                'monto_total_inicial' => $montoTotal,
                'saldo_pendiente'     => $montoTotal,
                'cuota_mensual'       => $cuota,
                'fecha_inicio'        => $json->fecha_inicio ?? date('Y-m-d'),
                'fecha_vencimiento'   => $json->fecha_vencimiento ?? null,
                'estado'              => 'Activa',
                'tarjeta_id'          => (int) $tarjetaId,
            ]);

            $deudaId = $this->deudasModel->getInsertID();

            // Registrar movimiento de gasto en la tarjeta (crédito: saldo_despues es null)
            $this->transaccionesModel->insert([
                'usuario_id'    => $usuarioId,
                'fecha'         => $json->fecha_inicio ?? date('Y-m-d'),
                'tipo'          => 'Egreso',
                'monto'         => $montoTotal,
                'estado'        => 'pagado',
                'saldo_despues' => null,
                'descripcion'   => 'Compra a cuotas: ' . $json->nombre_deuda,
                'categoria_id'  => !empty($json->categoria_id) ? (int) $json->categoria_id : 12,
                'deuda_id'      => $deudaId,
                'tarjeta_id'    => (int) $tarjetaId,
            ]);

            $this->db->transComplete();

            if ($this->db->transStatus() === false) {
                return $this->failServerError('Error al registrar la cuota');
            }

            return $this->respondCreated([
                'status'           => 201,
                'message'          => 'Compra a cuotas registrada correctamente',
                'saldo_disponible' => $nuevoDisponible,
                'saldo_utilizado'  => $nuevoUtilizado,
            ]);
        } catch (\Exception $e) {
            $this->db->transRollback();
            return $this->failServerError($e->getMessage());
        }
    }

    /** PUT /api/tarjetas/{id} — Actualizar datos de tarjeta */
    public function actualizar($tarjetaId)
    {
        $authUser = $this->request->getServer('auth_user');
        if (!$authUser || !isset($authUser['usuario_id'])) {
            return $this->failUnauthorized('Usuario no autenticado');
        }

        $usuarioId = $authUser['usuario_id'];
        $tarjeta   = $this->tarjetasModel->find($tarjetaId);

        if (!$tarjeta || $tarjeta['usuario_id'] != $usuarioId) {
            return $this->failNotFound('Tarjeta no encontrada');
        }

        $json = $this->request->getJSON();

        try {
            $data = [];
            if (isset($json->nombre))    $data['nombre']    = $json->nombre;
            if (isset($json->banco))     $data['banco']     = $json->banco;
            if (isset($json->dia_corte)) $data['dia_corte'] = (int) $json->dia_corte;
            if (isset($json->dia_pago))  $data['dia_pago']  = (int) $json->dia_pago;
            if (isset($json->estado))    $data['estado']    = $json->estado;

            $this->tarjetasModel->update($tarjetaId, $data);

            return $this->respond([
                'status'  => 200,
                'message' => 'Tarjeta actualizada correctamente',
            ]);
        } catch (\Exception $e) {
            return $this->failServerError($e->getMessage());
        }
    }
}
