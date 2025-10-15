<?php
namespace App\Controllers;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

class HelloController
{
    public function index(Request $request, Response $response): Response
    {
        $response->getBody()->write("Hello from HelloController!");
        return $response;
    }
}
