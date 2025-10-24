<?php

/**
 * APCuCacheManager
 * 
 * Gestor centralizado de caché usando APCu con fallback automático.
 * Proporciona una API simple para cachear datos con TTL configurable.
 * 
 * Características:
 * - Detección automática de APCu disponible
 * - Fallback transparente si APCu no está disponible
 * - Invalidación inteligente por patrones
 * - Estadísticas de uso
 * - Prefijos de keys para evitar colisiones
 */
class APCuCacheManager 
{
    private static $instance = null;
    private $enabled = false;
    private $prefix = 'gtk_';
    private $stats = [
        'hits' => 0,
        'misses' => 0,
        'stores' => 0,
        'deletes' => 0
    ];

    private function __construct() 
    {
        $this->enabled = function_exists('apcu_fetch') && 
                        function_exists('apcu_store') && 
                        ini_get('apc.enabled');
    }

    public static function getInstance() 
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Verifica si APCu está disponible y habilitado
     */
    public function isEnabled() 
    {
        return $this->enabled;
    }

    /**
     * Obtiene un valor del caché
     * 
     * @param string $key Clave del caché
     * @param mixed &$success Variable por referencia que indica si se encontró
     * @return mixed Valor cacheado o false si no existe
     */
    public function get($key, &$success = null) 
    {
        if (!$this->enabled) {
            $success = false;
            return false;
        }

        $fullKey = $this->prefix . $key;
        $value = apcu_fetch($fullKey, $success);

        if ($success) {
            $this->stats['hits']++;
        } else {
            $this->stats['misses']++;
        }

        return $value;
    }

    /**
     * Guarda un valor en el caché
     * 
     * @param string $key Clave del caché
     * @param mixed $value Valor a cachear
     * @param int $ttl Tiempo de vida en segundos (default: 300 = 5 min)
     * @return bool True si se guardó correctamente
     */
    public function set($key, $value, $ttl = 300) 
    {
        if (!$this->enabled) {
            return false;
        }

        $fullKey = $this->prefix . $key;
        $result = apcu_store($fullKey, $value, $ttl);

        if ($result) {
            $this->stats['stores']++;
        }

        return $result;
    }

    /**
     * Elimina un valor del caché
     * 
     * @param string $key Clave a eliminar
     * @return bool True si se eliminó
     */
    public function delete($key) 
    {
        if (!$this->enabled) {
            return false;
        }

        $fullKey = $this->prefix . $key;
        $result = apcu_delete($fullKey);

        if ($result) {
            $this->stats['deletes']++;
        }

        return $result;
    }

    /**
     * Elimina múltiples valores por patrón
     * 
     * @param string $pattern Patrón regex para las keys (sin el prefijo)
     * @return int Número de keys eliminadas
     */
    public function deletePattern($pattern) 
    {
        if (!$this->enabled) {
            return 0;
        }

        $deleted = 0;
        $iterator = new APCUIterator('/^' . preg_quote($this->prefix, '/') . $pattern . '/');
        
        foreach ($iterator as $entry) {
            if (apcu_delete($entry['key'])) {
                $deleted++;
                $this->stats['deletes']++;
            }
        }

        return $deleted;
    }

    /**
     * Limpia todo el caché con nuestro prefijo
     * 
     * @return int Número de entradas eliminadas
     */
    public function clear() 
    {
        if (!$this->enabled) {
            return 0;
        }

        return $this->deletePattern('.*');
    }

    /**
     * Verifica si una key existe en el caché
     * 
     * @param string $key Clave a verificar
     * @return bool True si existe
     */
    public function exists($key) 
    {
        if (!$this->enabled) {
            return false;
        }

        $fullKey = $this->prefix . $key;
        return apcu_exists($fullKey);
    }

    /**
     * Obtiene estadísticas de uso del caché
     * 
     * @return array Estadísticas de hits, misses, stores, deletes
     */
    public function getStats() 
    {
        $stats = $this->stats;
        $stats['hit_rate'] = $stats['hits'] > 0 
            ? round(($stats['hits'] / ($stats['hits'] + $stats['misses'])) * 100, 2) 
            : 0;
        $stats['enabled'] = $this->enabled;

        if ($this->enabled) {
            $info = apcu_cache_info();
            $stats['memory_size'] = isset($info['mem_size']) ? $info['mem_size'] : 0;
            $stats['memory_available'] = isset($info['avail_mem']) ? $info['avail_mem'] : 0;
            $stats['memory_used'] = $stats['memory_size'] - $stats['memory_available'];
            $stats['num_entries'] = isset($info['num_entries']) ? $info['num_entries'] : 0;
        }

        return $stats;
    }

    /**
     * Obtiene información detallada del caché APCu
     * 
     * @return array|null Info del caché o null si no está disponible
     */
    public function getCacheInfo() 
    {
        if (!$this->enabled) {
            return null;
        }

        return apcu_cache_info();
    }

    /**
     * Obtiene o establece un valor con callback
     * Patrón "cache-aside": si no existe, ejecuta el callback y cachea el resultado
     * 
     * @param string $key Clave del caché
     * @param callable $callback Función que retorna el valor si no está en caché
     * @param int $ttl Tiempo de vida en segundos
     * @return mixed Valor cacheado o resultado del callback
     */
    public function remember($key, $callback, $ttl = 300) 
    {
        $value = $this->get($key, $success);

        if ($success) {
            return $value;
        }

        // No está en caché, ejecutar callback
        $value = $callback();

        // Cachear el resultado
        $this->set($key, $value, $ttl);

        return $value;
    }

    /**
     * Incrementa un contador en el caché
     * 
     * @param string $key Clave del contador
     * @param int $step Incremento (default: 1)
     * @return int|false Nuevo valor o false si falla
     */
    public function increment($key, $step = 1) 
    {
        if (!$this->enabled) {
            return false;
        }

        $fullKey = $this->prefix . $key;
        return apcu_inc($fullKey, $step);
    }

    /**
     * Decrementa un contador en el caché
     * 
     * @param string $key Clave del contador
     * @param int $step Decremento (default: 1)
     * @return int|false Nuevo valor o false si falla
     */
    public function decrement($key, $step = 1) 
    {
        if (!$this->enabled) {
            return false;
        }

        $fullKey = $this->prefix . $key;
        return apcu_dec($fullKey, $step);
    }

    /**
     * Obtiene múltiples valores a la vez
     * 
     * @param array $keys Array de claves
     * @return array Array asociativo con los valores encontrados
     */
    public function getMultiple($keys) 
    {
        if (!$this->enabled) {
            return [];
        }

        $fullKeys = array_map(function($key) {
            return $this->prefix . $key;
        }, $keys);

        $results = [];
        foreach ($fullKeys as $i => $fullKey) {
            $success = false;
            $value = apcu_fetch($fullKey, $success);
            if ($success) {
                $results[$keys[$i]] = $value;
                $this->stats['hits']++;
            } else {
                $this->stats['misses']++;
            }
        }

        return $results;
    }

    /**
     * Guarda múltiples valores a la vez
     * 
     * @param array $values Array asociativo key => value
     * @param int $ttl Tiempo de vida en segundos
     * @return bool True si todos se guardaron
     */
    public function setMultiple($values, $ttl = 300) 
    {
        if (!$this->enabled) {
            return false;
        }

        $fullValues = [];
        foreach ($values as $key => $value) {
            $fullValues[$this->prefix . $key] = $value;
        }

        $errors = apcu_store($fullValues, null, $ttl);
        
        $stored = count($fullValues) - (is_array($errors) ? count($errors) : 0);
        $this->stats['stores'] += $stored;

        return empty($errors);
    }
}

