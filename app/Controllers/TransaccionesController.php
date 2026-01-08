<?php

namespace App\Controllers;

use CodeIgniter\RESTful\ResourceController;
use CodeIgniter\API\ResponseTrait;
use App\Models\TransaccionesModel;
use App\Models\UsuariosModel;
use App\Models\DeudasModel;
use App\Models\CategoriasModel;

class TransaccionesController extends ResourceController
{
    use ResponseTrait;

    protected $transaccionesModel;
    protected $usuariosModel;
    protected $deudasModel;
    protected $categoriasModel;
    protected $db;

    public function __construct()
    {
        $this->transaccionesModel = new TransaccionesModel();
        $this->usuariosModel      = new UsuariosModel();
        $this->deudasModel        = new DeudasModel();
        $this->categoriasModel    = new CategoriasModel();
        $this->db                 = \Config\Database::connect();
    }

    //Función para crear una nueva transacción
    public function create()
    {
        $json = $this->request->getJSON();

        // Validar datos mínimos
        if (!isset($json->usuario_id, $json->tipo, $json->monto, $json->categoria_id)) {
            return $this->failValidationErrors(
                'Faltan datos requeridos: usuario_id, tipo, monto, categoria_id'
            );
        }

        $usuarioId = $json->usuario_id;
        $monto     = (float) $json->monto;
        $tipo      = $json->tipo; // Ingreso | Egreso
        $estado    = $json->estado ?? 'pendiente';

        // 🔒 Regla: ingreso siempre pagado
        if ($tipo === 'Ingreso') {
            $estado = 'pagado';
        }

        $this->db->transStart();

        try {
            // Usuario
            $usuario = $this->usuariosModel->find($usuarioId);
            if (!$usuario) {
                return $this->failNotFound('Usuario no encontrado');
            }

            $saldoActual = (float) $usuario['saldo_actual'];
            $nuevoSaldo  = $saldoActual;

            // Obtener deuda si existe
            $deuda = null;
            if (!empty($json->deuda_id)) {
                $deuda = $this->deudasModel->find($json->deuda_id);
            }

            // 🔑 Calcular saldo SOLO si está pagado
            if ($estado === 'pagado') {

                // Caso normal (sin deuda)
                if (!$deuda) {
                    $nuevoSaldo = ($tipo === 'Ingreso')
                        ? $saldoActual + $monto
                        : $saldoActual - $monto;
                }

                // Caso con deuda
                if ($deuda) {
                    if ($deuda['tipo_deuda'] === 'Cobrar') {
                        // 💰 Me pagan → suma saldo
                        $nuevoSaldo = $saldoActual + $monto;
                    } else {
                        // 💸 Pago deuda → resta saldo
                        $nuevoSaldo = $saldoActual - $monto;
                    }
                }
            }

            // Guardar transacción
            $dataTransaccion = [
                'usuario_id'    => $usuarioId,
                'fecha'         => $json->fecha ?? date('Y-m-d'),
                'tipo'          => $tipo,
                'monto'         => $monto,
                'estado'        => $estado,
                'saldo_despues' => ($estado === 'pagado') ? $nuevoSaldo : null,
                'descripcion'   => $json->descripcion ?? '',
                'categoria_id'  => $json->categoria_id,
                'deuda_id'      => $json->deuda_id ?? null,
            ];

            $this->transaccionesModel->insert($dataTransaccion);

            // Actualizar saldo usuario
            if ($estado === 'pagado') {
                $this->usuariosModel->update($usuarioId, [
                    'saldo_actual' => $nuevoSaldo
                ]);
            }

            // Actualizar deuda
            if ($estado === 'pagado' && $deuda) {

                if ($deuda['tipo_deuda'] === 'Pagar') {
                    $nuevoSaldoDeuda = (float) $deuda['saldo_pendiente'] - $monto;
                } else {
                    // Cobrar → me deben menos
                    $nuevoSaldoDeuda = (float) $deuda['saldo_pendiente'] - $monto;
                }

                if ($nuevoSaldoDeuda < 0) $nuevoSaldoDeuda = 0;

                $estadoDeuda = ($nuevoSaldoDeuda == 0) ? 'Pagada' : 'Activa';

                $this->deudasModel->update($json->deuda_id, [
                    'saldo_pendiente' => $nuevoSaldoDeuda,
                    'estado'          => $estadoDeuda
                ]);
            }

            $this->db->transComplete();

            if ($this->db->transStatus() === false) {
                return $this->failServerError('Error al procesar la transacción.');
            }

            return $this->respondCreated([
                'status' => 201,
                'message' => 'Transacción registrada correctamente',
                'estado' => $estado,
                'nuevo_saldo_usuario' => ($estado === 'pagado') ? $nuevoSaldo : null
            ]);
        } catch (\Exception $e) {
            $this->db->transRollback();
            return $this->failServerError($e->getMessage());
        }
    }


    //Función para pagar una transacción pendiente
    public function pagar($transaccionId)
    {
        // Iniciar transacción DB
        $this->db->transStart();

        try {
            // Buscar transacción
            $transaccion = $this->transaccionesModel->find($transaccionId);

            if (!$transaccion) {
                return $this->failNotFound('Transacción no encontrada');
            }

            // Validar estado
            if ($transaccion['estado'] !== 'pendiente') {
                return $this->failValidationErrors(
                    'Solo se pueden pagar transacciones en estado pendiente'
                );
            }

            // Obtener usuario
            $usuario = $this->usuariosModel->find($transaccion['usuario_id']);
            if (!$usuario) {
                return $this->failNotFound('Usuario no encontrado');
            }

            $saldoActual = (float) $usuario['saldo_actual'];
            $monto       = (float) $transaccion['monto'];
            $tipo        = $transaccion['tipo'];

            // Calcular nuevo saldo
            if ($tipo === 'Ingreso') {
                $nuevoSaldo = $saldoActual + $monto;
            } else {
                $nuevoSaldo = $saldoActual - $monto;
            }

            // Actualizar transacción
            $this->transaccionesModel->update($transaccionId, [
                'estado'        => 'pagado',
                'saldo_despues' => $nuevoSaldo
            ]);

            // Actualizar saldo del usuario
            $this->usuariosModel->update($usuario['usuario_id'], [
                'saldo_actual' => $nuevoSaldo
            ]);

            // Si es pago de deuda
            if ($tipo === 'Egreso' && !empty($transaccion['deuda_id'])) {
                $deuda = $this->deudasModel->find($transaccion['deuda_id']);

                if ($deuda) {
                    $nuevoSaldoDeuda = (float) $deuda['saldo_pendiente'] - $monto;
                    if ($nuevoSaldoDeuda < 0) $nuevoSaldoDeuda = 0;

                    $estadoDeuda = ($nuevoSaldoDeuda == 0) ? 'Pagada' : 'Activa';

                    $this->deudasModel->update($transaccion['deuda_id'], [
                        'saldo_pendiente' => $nuevoSaldoDeuda,
                        'estado'          => $estadoDeuda
                    ]);
                }
            }

            $this->db->transComplete();

            if ($this->db->transStatus() === false) {
                return $this->failServerError('Error al pagar la transacción');
            }

            return $this->respond([
                'status' => 200,
                'message' => 'Transacción pagada correctamente',
                'nuevo_saldo_usuario' => $nuevoSaldo
            ]);
        } catch (\Exception $e) {
            $this->db->transRollback();
            return $this->failServerError($e->getMessage());
        }
    }


    // function para obtener las transacciones del mes actual
    public function mesActual()
    {
        // 1. Usuario autenticado desde JWT
        $authUser = $this->request->getServer('auth_user');

        if (!$authUser || !isset($authUser['usuario_id'])) {
            return $this->failUnauthorized('Usuario no autenticado');
        }

        $usuarioId = $authUser['usuario_id'];

        // 2. Rango del mes actual
        $inicioMes = date('Y-m-01');
        $finMes    = date('Y-m-t');

        // 3. Transacciones del mes actual
        $transacciones = $this->transaccionesModel
            ->select('
            transacciones.transaccion_id,
            transacciones.fecha,
            transacciones.tipo,
            transacciones.monto,
            transacciones.estado,
            transacciones.saldo_despues,
            transacciones.descripcion,
            categorias.nombre AS categoria,
            transacciones.deuda_id
        ')
            ->join('categorias', 'categorias.categoria_id = transacciones.categoria_id', 'left')
            ->where('transacciones.usuario_id', $usuarioId)
            ->where('transacciones.fecha >=', $inicioMes)
            ->where('transacciones.fecha <=', $finMes)
            ->orderBy('transacciones.fecha', 'DESC')
            ->findAll();

        // 4. Separar ingresos y egresos del mes
        $ingresos = [];
        $egresos  = [];

        foreach ($transacciones as $t) {
            if ($t['tipo'] === 'Ingreso') {
                $ingresos[] = $t;
            } else {
                $egresos[] = $t;
            }
        }

        // 5. Totales POR MONTO del mes (solo pagados)
        $totalesMes = $this->transaccionesModel
            ->select('
            tipo,
            SUM(monto) as total
        ')
            ->where('usuario_id', $usuarioId)
            ->where('estado', 'pagado')
            ->where('fecha >=', $inicioMes)
            ->where('fecha <=', $finMes)
            ->groupBy('tipo')
            ->findAll();

        $totalIngresosMes = 0;
        $totalEgresosMes  = 0;

        foreach ($totalesMes as $row) {
            if ($row['tipo'] === 'Ingreso') {
                $totalIngresosMes = (float) $row['total'];
            }

            if ($row['tipo'] === 'Egreso') {
                $totalEgresosMes = (float) $row['total'];
            }
        }

        // 6. Saldo actual
        $saldoActual = $this->usuariosModel
            ->select('saldo_actual')
            ->find($usuarioId)['saldo_actual'] ?? 0;

        return $this->respond([
            'status'  => 200,
            'message' => 'Transacciones del mes actual',
            'mes'     => date('m'),
            'anio'    => date('Y'),
            'saldo_actual' => (float) $saldoActual,

            // TOTALES 
            'totales' => [
                'ingresos_mes' => round($totalIngresosMes, 2),
                'egresos_mes'  => round($totalEgresosMes, 2),
            ],

            // DATA SOLO PARA LISTADO
            'data' => [
                'ingresos' => $ingresos,
                'egresos'  => $egresos,
            ]
        ]);
    }



    public function categorias()
    {
        try {
            $categorias = $this->categoriasModel
                ->select('categoria_id, nombre')
                ->orderBy('nombre', 'ASC')
                ->findAll();

            return $this->respond([
                'status'  => 200,
                'message' => 'Listado de categorías',
                'data'    => $categorias
            ]);
        } catch (\Exception $e) {
            return $this->failServerError($e->getMessage());
        }
    }


    public function deudasList()
    {
        // Usuario autenticado desde JWT
        $authUser = $this->request->getServer('auth_user');

        if (!$authUser || !isset($authUser['usuario_id'])) {
            return $this->failUnauthorized('Usuario no autenticado');
        }

        $usuarioId = $authUser['usuario_id'];

        try {
            $deudas = $this->deudasModel
                ->select('deuda_id, nombre_deuda, tipo_deuda')
                ->where('usuario_id', $usuarioId)
                ->orderBy('nombre_deuda', 'ASC')
                ->findAll();

            return $this->respond([
                'status'  => 200,
                'message' => 'Listado de deudas del usuario',
                'data'    => $deudas
            ]);
        } catch (\Exception $e) {
            return $this->failServerError($e->getMessage());
        }
    }

    public function allTransacciones()
    {
        // 1. Usuario autenticado desde JWT
        $authUser = $this->request->getServer('auth_user');

        if (!$authUser || !isset($authUser['usuario_id'])) {
            return $this->failUnauthorized('Usuario no autenticado');
        }

        $usuarioId = $authUser['usuario_id'];

        // 2. Obtener todas las transacciones del usuario
        $transacciones = $this->transaccionesModel
            ->select('
            transacciones.transaccion_id,
            transacciones.fecha,
            transacciones.tipo,
            transacciones.monto,
            transacciones.estado,
            transacciones.saldo_despues,
            transacciones.descripcion,
            categorias.nombre AS categoria,
            transacciones.deuda_id
        ')
            ->join('categorias', 'categorias.categoria_id = transacciones.categoria_id', 'left')
            ->where('transacciones.usuario_id', $usuarioId)
            ->orderBy('transacciones.fecha', 'DESC')
            ->findAll();

        // 3. Separar ingresos y egresos
        $ingresos = [];
        $egresos  = [];

        foreach ($transacciones as $t) {
            if ($t['tipo'] === 'Ingreso') {
                $ingresos[] = $t;
            } else {
                $egresos[] = $t;
            }
        }

        // 4. Saldo actual del usuario
        $saldoActual = $this->usuariosModel
            ->select('saldo_actual')
            ->find($usuarioId)['saldo_actual'] ?? 0;

        return $this->respond([
            'status'  => 200,
            'message' => 'Todas las transacciones del usuario',
            'saldo_actual' => (float) $saldoActual,
            'totales' => [
                'ingresos' => count($ingresos),
                'egresos'  => count($egresos),
            ],
            'data' => [
                'ingresos' => $ingresos,
                'egresos'  => $egresos,
            ]
        ]);
    }

    public function deudasDetalle()
    {
        // 1. Usuario autenticado
        $authUser = $this->request->getServer('auth_user');

        if (!$authUser || !isset($authUser['usuario_id'])) {
            return $this->failUnauthorized('Usuario no autenticado');
        }

        $usuarioId = $authUser['usuario_id'];

        try {
            // 2. Obtener TODAS las deudas del usuario
            $deudas = $this->deudasModel
                ->where('usuario_id', $usuarioId)
                ->orderBy('fecha_inicio', 'DESC')
                ->findAll();

            $deudasCobrar = [];
            $deudasPagar  = [];
            $totalCobrar = 0;
            $totalPagar  = 0;


            // 3. Para cada deuda, traer sus transacciones
            foreach ($deudas as $deuda) {

                $transacciones = $this->transaccionesModel
                    ->select('
                    transaccion_id,
                    fecha,
                    tipo,
                    monto,
                    estado,
                    saldo_despues,
                    descripcion
                ')
                    ->where('deuda_id', $deuda['deuda_id'])
                    ->orderBy('fecha', 'ASC')
                    ->findAll();

                $deuda['transacciones'] = $transacciones;


                $saldoPendiente = (float) ($deuda['saldo_pendiente'] ?? 0);

                if ($deuda['tipo_deuda'] === 'Cobrar') {
                    $totalCobrar += $saldoPendiente;
                } else {
                    $totalPagar += $saldoPendiente;
                }


                // 4. Separar por tipo_deuda
                if ($deuda['tipo_deuda'] === 'Cobrar') {
                    $deudasCobrar[] = $deuda;
                } else {
                    $deudasPagar[] = $deuda;
                }
            }

            return $this->respond([
                'status'  => 200,
                'message' => 'Detalle de deudas del usuario',
                'totales' => [
                    'cobrar' => round($totalCobrar, 2),
                    'pagar'  => round($totalPagar, 2),
                ],
                'data'    => [
                    'cobrar' => $deudasCobrar,
                    'pagar'  => $deudasPagar
                ]
            ]);
        } catch (\Exception $e) {
            return $this->failServerError($e->getMessage());
        }
    }


    public function createDeuda()
    {
        // 1. Usuario autenticado
        $authUser = $this->request->getServer('auth_user');

        if (!$authUser || !isset($authUser['usuario_id'])) {
            return $this->failUnauthorized('Usuario no autenticado');
        }

        $usuarioId = $authUser['usuario_id'];
        $json = $this->request->getJSON();

        // 2. Validaciones mínimas
        if (
            empty($json->nombre_deuda) ||
            empty($json->tipo_deuda) ||
            empty($json->monto_total_inicial)
        ) {
            return $this->failValidationErrors(
                'nombre_deuda, tipo_deuda y monto_total_inicial son obligatorios'
            );
        }

        if (!in_array($json->tipo_deuda, ['Pagar', 'Cobrar'])) {
            return $this->failValidationErrors('tipo_deuda debe ser Pagar o Cobrar');
        }

        $montoTotal = (float) $json->monto_total_inicial;

        if ($montoTotal <= 0) {
            return $this->failValidationErrors('El monto debe ser mayor a 0');
        }

        // 3. Preparar datos
        $data = [
            'usuario_id'           => $usuarioId,
            'nombre_deuda'         => $json->nombre_deuda,
            'tipo_deuda'           => $json->tipo_deuda,
            'monto_total_inicial'  => $montoTotal,
            'saldo_pendiente'      => $montoTotal,
            'cuota_mensual' => ($json->tipo_deuda === 'Pagar')
                ? ($json->cuota_mensual ?? 0)
                : 0,

            'fecha_inicio'         => $json->fecha_inicio ?? date('Y-m-d'),
            'fecha_vencimiento'    => $json->fecha_vencimiento ?? null,
            'estado'               => 'Activa'
        ];

        try {
            $this->deudasModel->insert($data);

            return $this->respondCreated([
                'status'  => 201,
                'message' => 'Deuda creada correctamente'
            ]);
        } catch (\Exception $e) {
            return $this->failServerError($e->getMessage());
        }
    }
}
