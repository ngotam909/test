<?php
use Inc\Access;
Access::requireRole('admin');

echo "Hello Admin!";