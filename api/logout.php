<?php
require __DIR__.'/lib.php';
session_destroy();
out(['ok'=>true]);
