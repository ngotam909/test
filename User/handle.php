<?php
use Inc\DB;
use Inc\UserService;
use Middleware\Access;

Access::requireRole('user', 'admin');
$con = new DB();
$con->connect();
$user = new UserService($con);
// 