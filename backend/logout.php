<?php
session_start();
session_destroy();
header("Location: ../pages/login.php?status=success&msg=Berhasil%20logout");
exit();
