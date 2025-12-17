<?php

namespace App\Controllers;

use App\Controllers\BaseController;
use CodeIgniter\HTTP\ResponseInterface;
use CodeIgniter\API\ResponseTrait;
use CodeIgniter\RESTful\ResourceController;
use Config\Services;
use App\Models\UsuariosModel;

class AuthController extends BaseController
{
    use ResponseTrait;
    protected $session;

    protected $usuariosModel;
    public function __construct()
    {
        $this->session = \Config\Services::session();
        $this->usuariosModel = new UsuariosModel();
    }

    public function test()
    {
        $user = $this->request->getServer('auth_user'); // ✅ aquí está

        return $this->respond([
            'status'  => 200,
            'message' => 'Authorized',
            'user'    => $user,
        ]);
    }



    public function login()
    {
        // Validación
        $rules = [
            'email' => 'required|valid_email',
            'password' => 'required',
        ];

        if (!$this->validate($rules)) {
            return $this->respond([
                'status' => 400,
                'error' => $this->validator->getErrors(),
            ], 400);
        }

        $email = $this->request->getVar('email');
        $password = $this->request->getVar('password');

        // Buscar usuario
        $user = $this->usuariosModel
            ->where('email', $email)
            ->first();

        if (!$user || !password_verify($password, $user['contrasena'])) {
            return $this->respond([
                'status' => 401,
                'message' => 'Correo o contraseña incorrectos.',
            ], 401);
        }

        if (!$user['esta_verificado']) {
            return $this->respond([
                'status' => 403,
                'message' => 'Cuenta no verificada.',
            ], 403);
        }

        if (strtolower($user['estado']) !== 'activo') {
            return $this->respond([
                'status' => 403,
                'message' => 'Cuenta inactiva.',
            ], 403);
        }


        helper('jwt_helper');

        $payload = [
            'usuario_id'   => $user['usuario_id'],
            'email'        => $user['email'],
            'nombre'       => $user['nombre'],
            'tipo_usuario' => $user['tipo_usuario'],
            'saldo_actual' => $user['saldo_actual'],
        ];

        $token = createJWT($payload);

        return $this->respond([
            'status' => 200,
            'message' => 'Login successful',
            'data' => [
                'usuario_id'   => $user['usuario_id'],
                'nombre'       => $user['nombre'],
                'email'        => $user['email'],
                'tipo_usuario' => $user['tipo_usuario'],
                'saldo_actual' => $user['saldo_actual'],
                'token'        => $token,
            ],
        ], 200);
    }

    public function register()
    {
        $rules = [
            'nombre'   => 'required|min_length[3]',
            'email'    => 'required|valid_email|is_unique[usuarios.email]',
            'password' => 'required|min_length[6]',
        ];

        if (!$this->validate($rules)) {
            return $this->respond([
                'status' => 400,
                'errors' => $this->validator->getErrors(),
            ], 400);
        }

        $todayStart = date('Y-m-d 00:00:00');
        $todayEnd   = date('Y-m-d 23:59:59');

        $email = $this->request->getVar('email');

        // Solo 1 registro por día por email
        $alreadyRegisteredToday = $this->usuariosModel
            ->where('email', $email)
            ->where('fecha_creacion >=', $todayStart)
            ->where('fecha_creacion <=', $todayEnd)
            ->countAllResults();

        if ($alreadyRegisteredToday > 0) {
            return $this->respond([
                'status' => 429,
                'message' => 'Solo se permite registrar un usuario por día con este correo.',
            ], 429);
        }

        $data = [
            'nombre'           => $this->request->getVar('nombre'),
            'email'            => $email,
            'contrasena'       => password_hash(
                $this->request->getVar('password'),
                PASSWORD_DEFAULT
            ),
            'saldo_actual'     => 0,
            'tipo_usuario'     => 'Usuario',
            'estado'           => 'Activo',
            'esta_verificado'  => 1,
            'fecha_creacion'   => date('Y-m-d H:i:s'),
            'fecha_actualizacion' => date('Y-m-d H:i:s'),
        ];

        try {
            $this->usuariosModel->insert($data);

            return $this->respond([
                'status'  => 201,
                'message' => 'Usuario creado correctamente',
                'data'    => [
                    'usuario_id' => $this->usuariosModel->getInsertID(),
                    'nombre'     => $data['nombre'],
                    'email'      => $data['email'],
                ],
            ], 201);
        } catch (\Throwable $e) {
            return $this->respond([
                'status' => 500,
                'message' => 'Error al crear el usuario',
            ], 500);
        }
    }
}
