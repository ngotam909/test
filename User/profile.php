<?php
use Middleware\Access;
Access::requireRole('user', 'admin');

echo "Hello user!";