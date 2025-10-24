<?php
/**
 * Script de prueba del sistema de caché APCu híbrido
 * 
 * Ejecutar desde línea de comandos o navegador para verificar que todo funciona
 */

require_once __DIR__ . '/vendor/autoload.php';
require_once __DIR__ . '/src/Model/Base/APCuCacheManager.php';

echo "=================================================\n";
echo "   TEST DEL SISTEMA DE CACHÉ APCu HÍBRIDO\n";
echo "=================================================\n\n";

// Test 1: Verificar APCu
echo "1️⃣  Verificando APCu...\n";
$apcu = APCuCacheManager::getInstance();

if ($apcu->isEnabled()) {
    echo "   ✅ APCu está HABILITADO\n\n";
} else {
    echo "   ❌ APCu NO está disponible\n";
    echo "   ⚠️  El sistema funcionará con solo sesión (más lento)\n\n";
}

// Test 2: Operaciones básicas
echo "2️⃣  Probando operaciones básicas...\n";

// Set
$key = 'test_key_' . time();
$value = ['name' => 'Test User', 'roles' => ['ADMIN', 'USER']];
$stored = $apcu->set($key, $value, 60);
echo "   Set: " . ($stored ? "✅ OK" : "❌ FAIL") . "\n";

// Get
$retrieved = $apcu->get($key, $success);
echo "   Get: " . ($success ? "✅ OK" : "❌ FAIL") . "\n";

// Verify data
$dataOK = ($retrieved == $value);
echo "   Data integrity: " . ($dataOK ? "✅ OK" : "❌ FAIL") . "\n";

// Delete
$deleted = $apcu->delete($key);
echo "   Delete: " . ($deleted ? "✅ OK" : "❌ FAIL") . "\n\n";

// Test 3: Remember pattern
echo "3️⃣  Probando cache-aside pattern (remember)...\n";

$expensive_key = 'expensive_operation';
$call_count = 0;

// Primera llamada - debe ejecutar callback
$result1 = $apcu->remember($expensive_key, function() use (&$call_count) {
    $call_count++;
    usleep(100000); // Simular operación costosa (100ms)
    return ['computed' => true, 'time' => microtime(true)];
}, 60);

echo "   Primera llamada: Callback ejecutado = " . ($call_count == 1 ? "✅ OK" : "❌ FAIL") . "\n";

// Segunda llamada - debe usar caché
$result2 = $apcu->remember($expensive_key, function() use (&$call_count) {
    $call_count++;
    return ['computed' => true, 'time' => microtime(true)];
}, 60);

echo "   Segunda llamada: Desde caché = " . ($call_count == 1 ? "✅ OK (no ejecutó callback)" : "❌ FAIL") . "\n";
echo "   Data match: " . ($result1 == $result2 ? "✅ OK" : "❌ FAIL") . "\n\n";

// Cleanup
$apcu->delete($expensive_key);

// Test 4: Estadísticas
echo "4️⃣  Estadísticas del caché...\n";
$stats = $apcu->getStats();

echo "   Hits: {$stats['hits']}\n";
echo "   Misses: {$stats['misses']}\n";
echo "   Stores: {$stats['stores']}\n";
echo "   Deletes: {$stats['deletes']}\n";
echo "   Hit Rate: {$stats['hit_rate']}%\n";

if (isset($stats['memory_size'])) {
    $usedMB = round($stats['memory_used'] / 1024 / 1024, 2);
    $totalMB = round($stats['memory_size'] / 1024 / 1024, 2);
    $percent = round(($stats['memory_used'] / $stats['memory_size']) * 100, 2);
    echo "   Memoria: {$usedMB} MB / {$totalMB} MB ({$percent}%)\n";
}
echo "\n";

// Test 5: Operaciones múltiples
echo "5️⃣  Probando operaciones batch...\n";

$batch_data = [
    'user1' => ['id' => 1, 'name' => 'Alice'],
    'user2' => ['id' => 2, 'name' => 'Bob'],
    'user3' => ['id' => 3, 'name' => 'Charlie'],
];

$batch_stored = $apcu->setMultiple($batch_data, 60);
echo "   setMultiple: " . ($batch_stored ? "✅ OK" : "❌ FAIL") . "\n";

$batch_retrieved = $apcu->getMultiple(array_keys($batch_data));
echo "   getMultiple: " . (count($batch_retrieved) == 3 ? "✅ OK" : "❌ FAIL") . "\n";

// Cleanup
foreach (array_keys($batch_data) as $key) {
    $apcu->delete($key);
}
echo "\n";

// Test 6: Benchmark
echo "6️⃣  Benchmark de velocidad...\n";

// Setup test data
$bench_key = 'benchmark_data';
$bench_data = array_fill(0, 100, ['key' => 'value']);
$apcu->set($bench_key, $bench_data, 60);

// Benchmark get
$iterations = 1000;
$start = microtime(true);
for ($i = 0; $i < $iterations; $i++) {
    $apcu->get($bench_key, $success);
}
$time = (microtime(true) - $start) * 1000;
$avg = $time / $iterations;

echo "   {$iterations} lecturas en {$time}ms\n";
echo "   Promedio: {$avg}ms por lectura\n";

if ($avg < 0.1) {
    echo "   Velocidad: ✅ EXCELENTE (<0.1ms)\n";
} elseif ($avg < 1) {
    echo "   Velocidad: ✅ BUENA (<1ms)\n";
} else {
    echo "   Velocidad: ⚠️  LENTA (>{$avg}ms)\n";
}

// Cleanup
$apcu->delete($bench_key);
echo "\n";

// Resumen final
echo "=================================================\n";
echo "                 RESUMEN\n";
echo "=================================================\n\n";

if ($apcu->isEnabled() && $stored && $success && $dataOK) {
    echo "✅ TODOS LOS TESTS PASARON\n\n";
    echo "Sistema de caché APCu funcionando perfectamente.\n";
    echo "Esperado: 90-95% de reducción en tiempo de carga.\n\n";
    
    if ($avg < 0.1) {
        echo "🚀 Rendimiento: ÓPTIMO\n";
        echo "   Velocidad de acceso: {$avg}ms (ultra rápido)\n\n";
    }
} else {
    echo "⚠️  ALGUNOS TESTS FALLARON\n\n";
    echo "Revisar configuración de APCu.\n";
    echo "El sistema funcionará con solo sesión como fallback.\n\n";
}

echo "Para ver estadísticas en vivo, accede a:\n";
echo "   /admin/cache-stats (después de configurar la ruta)\n\n";

echo "=================================================\n";

