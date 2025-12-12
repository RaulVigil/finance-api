<?php

namespace App\Controllers;

use CodeIgniter\RESTful\ResourceController;
use CodeIgniter\API\ResponseTrait;
use App\Models\TransaccionesModel;
use App\Models\UsuariosModel;
use App\Models\DeudasModel;

class TransaccionesController extends ResourceController
{
    use ResponseTrait;

    protected $transaccionesModel;
    protected $usuariosModel;
    protected $deudasModel;
    protected $db;

    public function __construct()
    {
        $this->transaccionesModel = new TransaccionesModel();
        $this->usuariosModel      = new UsuariosModel();
        $this->deudasModel        = new DeudasModel();
        $this->db                 = \Config\Database::connect();
    }

    //Función para crear una nueva transacción
    public function create()
    {
        $json = $this->request->getJSON();

        //Validar datos mínimos
        if (!isset($json->usuario_id, $json->tipo, $json->monto, $json->categoria_id)) {
            return $this->failValidationErrors(
                'Faltan datos requeridos: usuario_id, tipo, monto, categoria_id'
            );
        }

        $usuarioId = $json->usuario_id;
        $monto     = (float) $json->monto;
        $tipo      = $json->tipo; // 'Ingreso' o 'Egreso'

        //INICIAR TRANSACCIÓN DE BASE DE DATOS
        $this->db->transStart();

        try {
            //Obtener Saldo Actual del Usuario
            $usuario = $this->usuariosModel->find($usuarioId);
            if (!$usuario) {
                return $this->failNotFound('Usuario no encontrado');
            }

            $saldoActual = (float) $usuario['saldo_actual'];

            //Calcular nuevo saldo
            if ($tipo === 'Ingreso') {
                $nuevoSaldo = $saldoActual + $monto;
            } else {
                $nuevoSaldo = $saldoActual - $monto;
            }

            //Preparar datos para insertar Transacción
            $dataTransaccion = [
                'usuario_id'    => $usuarioId,
                'fecha'         => $json->fecha ?? date('Y-m-d'), // Si no envían fecha, usa hoy
                'tipo'          => $tipo,
                'monto'         => $monto,
                'saldo_despues' => $nuevoSaldo, 
                'descripcion'   => $json->descripcion ?? '',
                'categoria_id'  => $json->categoria_id,
                'deuda_id'      => $json->deuda_id ?? null,
            ];

            $this->transaccionesModel->insert($dataTransaccion);

            //Actualizar Saldo en tabla Usuarios
            $this->usuariosModel->update($usuarioId, ['saldo_actual' => $nuevoSaldo]);

            //Lógica Especial: Si es pago de deuda (Tasa 0)
            if ($tipo === 'Egreso' && !empty($json->deuda_id)) {
                $deuda = $this->deudasModel->find($json->deuda_id);
                if ($deuda) {
                    $nuevoSaldoDeuda = (float)$deuda['saldo_pendiente'] - $monto;
                    // Evitar saldos negativos en deuda
                    if ($nuevoSaldoDeuda < 0) $nuevoSaldoDeuda = 0;

                    $estado = ($nuevoSaldoDeuda == 0) ? 'Pagada' : 'Activa';

                    $this->deudasModel->update($json->deuda_id, [
                        'saldo_pendiente' => $nuevoSaldoDeuda,
                        'estado'          => $estado
                    ]);
                }
            }

            //COMPLETAR TRANSACCIÓN DB
            $this->db->transComplete();

            if ($this->db->transStatus() === false) {
                return $this->failServerError('Error al procesar la transacción en la base de datos.');
            }

            return $this->respondCreated([
                'status' => 201,
                'message' => 'Transacción registrada correctamente',
                'nuevo_saldo_usuario' => $nuevoSaldo
            ]);
        } catch (\Exception $e) {
            return $this->failServerError($e->getMessage());
        }
    }

public function mesActual()
{
    // 1. Usuario autenticado desde JWT
    $authUser = $this->request->getServer('auth_user');

    if (!$authUser || !isset($authUser['usuario_id'])) {
        return $this->failUnauthorized('Usuario no autenticado');
    }

    $usuarioId = $authUser['usuario_id'];

    // 2. Rango de fechas del mes actual
    $inicioMes = date('Y-m-01');
    $finMes    = date('Y-m-t');

    // 3. Query con JOIN a categorias
    $transacciones = $this->transaccionesModel
        ->select('
            transacciones.transaccion_id,
            transacciones.fecha,
            transacciones.tipo,
            transacciones.monto,
            transacciones.saldo_despues,
            transacciones.descripcion,
            categorias.nombre AS categoria,
            transacciones.deuda_id
        ')
        ->join('categorias', 'categorias.categoria_id = transacciones.categoria_id')
        ->where('transacciones.usuario_id', $usuarioId)
        ->where('transacciones.fecha >=', $inicioMes)
        ->where('transacciones.fecha <=', $finMes)
        ->orderBy('transacciones.fecha', 'DESC')
        ->findAll();

    return $this->respond([
        'status'  => 200,
        'message' => 'Transacciones del mes actual',
        'mes'     => date('m'),
        'anio'    => date('Y'),
        'total'   => count($transacciones),
        'data'    => $transacciones
    ]);
}



}
