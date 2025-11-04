<?php
use Inc\DB;
use Inc\UserService;
use Middleware\Access;

Access::requireRole('admin');
$con = new DB();
$con->connect();
$user = new UserService($con);

$user->create('user1', 'user1@example.com', 'test123!', 'user');