<?php
require_once __DIR__ . '/../services/AuthService.php';

class AdminController
{
    private $db;
    private $authService;

    public function __construct()
    {
        $this->db = (new Database())->connect();
        $this->authService = new AuthService();
    }

    public function dashboard(){

    }

    public function users(){

    }

    
}


?>