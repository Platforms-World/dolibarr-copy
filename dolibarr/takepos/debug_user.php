<?php
require '../main.inc.php';
echo "login: " . $user->login . "<br>";
echo "statut: " . $user->statut . "<br>";
echo "admin: " . $user->admin . "<br>";
echo "takepos run: " . $user->hasRight('takepos', 'run') . "<br>";
echo "socid: " . $user->socid . "<br>";
echo "fk_user: " . $user->fk_user . "<br>";
?>