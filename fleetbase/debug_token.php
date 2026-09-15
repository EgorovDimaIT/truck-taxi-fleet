<?php
$u = \Fleetbase\Models\User::find('4f55d21d-688b-4384-bb3e-63728e8bb9f3');
echo $u->email . "\n";
echo $u->company_uuid . "\n";
$t = $u->createToken('debug')->plainTextToken;
echo $t . "\n";
