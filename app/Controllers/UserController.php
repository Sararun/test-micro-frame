<?php

namespace App\Controllers;

use App\UserRepository;
use Src\Router\Request;
use Src\Router\Response;

class UserController
{
    public function index(Request $request, UserRepository $userRepository)
    {

        $users = [
            new class
            {
                public string $name = 'John';
            },
            new class
            {
                public string $name = 'Jane';
            }
        ];
        return $users;
    }

    public function create(Request $request, string $id, UserRepository $userRepository)
    {
        return new Response()->setStatusCode(201)->json(['name' => 'John']);
    }
}