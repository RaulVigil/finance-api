<?php

namespace App\Controllers;

use App\Controllers\BaseController;
use CodeIgniter\HTTP\ResponseInterface;
use CodeIgniter\API\ResponseTrait;
use CodeIgniter\RESTful\ResourceController;
use App\Models\UsersModel;
use App\Models\ClientModel;
use App\Models\CompanyModel;
use Config\Services;
use App\Models\CountryModel;

class AuthController extends BaseController
{
    use ResponseTrait;
    protected $session;
    protected $usersModel;
    protected $clientModel;
    protected $companyModel;
    protected $countryModel;

    public function __construct()
    {
        $this->session = \Config\Services::session();

    }

public function test()
{
    return $this->respond([
        'status' => 200,
        'message' => 'Finance API test endpoint working correctly',
        'data' => [
            'user_id' => 1,
            'user_type' => 'admin',
            'email' => 'admin@test.com',
            'name' => 'Test Admin',
            'is_verified' => true,
            'tripias_balance' => 100,
            'created_at' => date('Y-m-d H:i:s'),
        ]
    ], 200);
}






}
