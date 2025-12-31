<?php
require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

try {
    echo "Iniciando creación de usuario de prueba...\n";

    // 1. Asegurar rol Director
    $rol = App\Models\Rol::firstOrCreate(['rol' => 'Director']);
    echo "Rol Director ID: " . $rol->id_rol . "\n";

    // 2. Crear Miembro
    $miembro = App\Models\Miembro::create([
        'nombres' => 'DEBUG',
        'apellidos' => 'USER',
        'ci' => '9999999',
        'fecha' => '2000-01-01',
        'direccion' => 'Calle Falsa 123',
        'celular' => '60000000',
        'id_rol' => $rol->id_rol,
        'estado' => true,
        'latitud' => 0,
        'longitud' => 0
    ]);
    echo "Miembro creado ID: " . $miembro->id_miembro . "\n";

    // 3. Crear Usuario
    $user = App\Models\User::create([
        'user' => 'admin_debug',
        'password' => '123456', // El modelo tiene cast 'hashed' o mutator? Revisaremos.
        'estado' => true,
        'id_miembro' => $miembro->id_miembro
    ]);

    // Forzar password hasheado si no se hizo automático
    if (strlen($user->password) < 20) {
        $user->password = Illuminate\Support\Facades\Hash::make('123456');
        $user->save();
        echo "Password hasheado manualmente.\n";
    }

    echo "\n============================================\n";
    echo "✅ USUARIO CREADO EXITOSAMENTE\n";
    echo "User: admin_debug\n";
    echo "Pass: 123456\n";
    echo "============================================\n";

} catch (\Exception $e) {
    echo "\n❌ ERROR FATAL:\n";
    echo $e->getMessage() . "\n";
    echo "File: " . $e->getFile() . " Line: " . $e->getLine() . "\n";
}
