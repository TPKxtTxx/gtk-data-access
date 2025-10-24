<?php

/**
 * CacheStatsPage
 * 
 * Página de monitoreo del sistema de caché (APCu + Sesión)
 * Muestra estadísticas en tiempo real y permite gestionar el caché
 */
class CacheStatsPage extends GTKHTMLPage
{
    public function renderBody()
    {
        $user = DataAccessManager::get("session")->getCurrentUser();
        
        if (!$user) {
            return '<div class="alert alert-danger">Debe iniciar sesión para ver esta página.</div>';
        }

        // Solo administradores pueden ver estadísticas
        if (!DataAccessManager::get("persona")->isInGroup($user, ['DEV', 'SOFTWARE_ADMIN'])) {
            return '<div class="alert alert-danger">No tiene permisos para ver esta página.</div>';
        }

        // Manejar acciones
        if (isset($_POST['action'])) {
            $this->handleAction($_POST['action']);
        }

        ob_start();
        ?>
        <style>
            .stats-container {
                max-width: 1200px;
                margin: 20px auto;
                padding: 20px;
            }
            .stats-card {
                background: white;
                border-radius: 8px;
                padding: 20px;
                margin-bottom: 20px;
                box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            }
            .stats-card h2 {
                margin-top: 0;
                color: #333;
                border-bottom: 2px solid #4CAF50;
                padding-bottom: 10px;
            }
            .stat-row {
                display: flex;
                justify-content: space-between;
                padding: 10px;
                border-bottom: 1px solid #eee;
            }
            .stat-row:last-child {
                border-bottom: none;
            }
            .stat-label {
                font-weight: bold;
                color: #666;
            }
            .stat-value {
                color: #333;
            }
            .stat-good {
                color: #4CAF50;
                font-weight: bold;
            }
            .stat-warning {
                color: #FF9800;
                font-weight: bold;
            }
            .stat-error {
                color: #f44336;
                font-weight: bold;
            }
            .btn {
                padding: 10px 20px;
                margin: 5px;
                border: none;
                border-radius: 4px;
                cursor: pointer;
                font-size: 14px;
            }
            .btn-primary {
                background: #2196F3;
                color: white;
            }
            .btn-danger {
                background: #f44336;
                color: white;
            }
            .btn-success {
                background: #4CAF50;
                color: white;
            }
            .btn:hover {
                opacity: 0.9;
            }
            .progress-bar {
                width: 100%;
                height: 20px;
                background: #eee;
                border-radius: 10px;
                overflow: hidden;
                margin-top: 5px;
            }
            .progress-fill {
                height: 100%;
                background: #4CAF50;
                transition: width 0.3s;
            }
        </style>

        <div class="stats-container">
            <h1>📊 Sistema de Caché - Monitoreo</h1>
            
            <?php echo $this->renderCacheStats(); ?>
            <?php echo $this->renderUserCacheInfo($user); ?>
            <?php echo $this->renderAPCuDetails(); ?>
            <?php echo $this->renderActions(); ?>
        </div>
        <?php
        return ob_get_clean();
    }

    private function renderCacheStats()
    {
        $stats = DataAccessManager::get("session")->getCacheStats();
        
        ob_start();
        ?>
        <div class="stats-card">
            <h2>🎯 Estado General del Sistema</h2>
            
            <div class="stat-row">
                <span class="stat-label">APCu Habilitado:</span>
                <span class="stat-value <?php echo $stats['apcu_enabled'] ? 'stat-good' : 'stat-error'; ?>">
                    <?php echo $stats['apcu_enabled'] ? '✓ SÍ' : '✗ NO'; ?>
                </span>
            </div>

            <div class="stat-row">
                <span class="stat-label">Sesión Activa:</span>
                <span class="stat-value <?php echo $stats['session_active'] ? 'stat-good' : 'stat-error'; ?>">
                    <?php echo $stats['session_active'] ? '✓ SÍ' : '✗ NO'; ?>
                </span>
            </div>

            <?php if ($stats['session_active']): ?>
            <div class="stat-row">
                <span class="stat-label">Session ID:</span>
                <span class="stat-value"><?php echo htmlspecialchars($stats['session_id']); ?></span>
            </div>
            <?php endif; ?>

            <?php if ($stats['apcu_enabled'] && isset($stats['apcu_stats'])): ?>
                <?php $apcu = $stats['apcu_stats']; ?>
                
                <div class="stat-row">
                    <span class="stat-label">Hit Rate (APCu):</span>
                    <span class="stat-value <?php 
                        echo $apcu['hit_rate'] > 80 ? 'stat-good' : 
                            ($apcu['hit_rate'] > 50 ? 'stat-warning' : 'stat-error'); 
                    ?>">
                        <?php echo $apcu['hit_rate']; ?>%
                    </span>
                </div>

                <div class="stat-row">
                    <span class="stat-label">Hits / Misses:</span>
                    <span class="stat-value"><?php echo $apcu['hits']; ?> / <?php echo $apcu['misses']; ?></span>
                </div>

                <div class="stat-row">
                    <span class="stat-label">Entradas Cacheadas:</span>
                    <span class="stat-value"><?php echo isset($apcu['num_entries']) ? $apcu['num_entries'] : 'N/A'; ?></span>
                </div>

                <?php if (isset($apcu['memory_size'])): ?>
                <div class="stat-row">
                    <span class="stat-label">Memoria APCu:</span>
                    <span class="stat-value">
                        <?php 
                        $usedMB = round($apcu['memory_used'] / 1024 / 1024, 2);
                        $totalMB = round($apcu['memory_size'] / 1024 / 1024, 2);
                        $percent = round(($apcu['memory_used'] / $apcu['memory_size']) * 100, 2);
                        echo "{$usedMB} MB / {$totalMB} MB ({$percent}%)";
                        ?>
                    </span>
                </div>
                <div class="progress-bar">
                    <div class="progress-fill" style="width: <?php echo $percent; ?>%"></div>
                </div>
                <?php endif; ?>
            <?php endif; ?>
        </div>
        <?php
        return ob_get_clean();
    }

    private function renderUserCacheInfo($user)
    {
        $user_id = DataAccessManager::get("persona")->valueForKey("id", $user);
        $info = DataAccessManager::get("session")->getUserCacheInfo($user_id);
        
        ob_start();
        ?>
        <div class="stats-card">
            <h2>👤 Caché del Usuario Actual (ID: <?php echo $user_id; ?>)</h2>
            
            <div class="stat-row">
                <span class="stat-label">En APCu:</span>
                <span class="stat-value <?php echo $info['apcu']['exists'] ? 'stat-good' : 'stat-warning'; ?>">
                    <?php echo $info['apcu']['exists'] ? '✓ SÍ (Ultra rápido)' : '✗ NO'; ?>
                </span>
            </div>

            <div class="stat-row">
                <span class="stat-label">En Sesión:</span>
                <span class="stat-value <?php echo $info['session']['exists'] ? 'stat-good' : 'stat-warning'; ?>">
                    <?php echo $info['session']['exists'] ? '✓ SÍ (Rápido)' : '✗ NO'; ?>
                </span>
            </div>

            <?php if ($info['session']['exists']): ?>
            <div class="stat-row">
                <span class="stat-label">Edad del Caché:</span>
                <span class="stat-value"><?php echo $info['session']['age_seconds']; ?> segundos</span>
            </div>

            <div class="stat-row">
                <span class="stat-label">Creado:</span>
                <span class="stat-value"><?php echo $info['session']['created_at']; ?></span>
            </div>
            <?php endif; ?>

            <?php if (!empty($info['cache_keys'])): ?>
            <div class="stat-row">
                <span class="stat-label">Datos Cacheados:</span>
                <span class="stat-value"><?php echo implode(', ', $info['cache_keys']); ?></span>
            </div>
            <?php endif; ?>

            <div class="stat-row">
                <span class="stat-label">Estado:</span>
                <span class="stat-value">
                    <?php 
                    if ($info['apcu']['exists']) {
                        echo '<span class="stat-good">⚡ Óptimo (APCu)</span>';
                    } elseif ($info['session']['exists']) {
                        echo '<span class="stat-warning">✓ Bueno (Sesión)</span>';
                    } else {
                        echo '<span class="stat-error">⚠ Sin caché</span>';
                    }
                    ?>
                </span>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }

    private function renderAPCuDetails()
    {
        $apcu = APCuCacheManager::getInstance();
        
        if (!$apcu->isEnabled()) {
            return '';
        }

        $stats = $apcu->getStats();
        
        ob_start();
        ?>
        <div class="stats-card">
            <h2>🚀 Estadísticas Detalladas de APCu</h2>
            
            <div class="stat-row">
                <span class="stat-label">Total Hits:</span>
                <span class="stat-value stat-good"><?php echo $stats['hits']; ?></span>
            </div>

            <div class="stat-row">
                <span class="stat-label">Total Misses:</span>
                <span class="stat-value stat-warning"><?php echo $stats['misses']; ?></span>
            </div>

            <div class="stat-row">
                <span class="stat-label">Total Stores:</span>
                <span class="stat-value"><?php echo $stats['stores']; ?></span>
            </div>

            <div class="stat-row">
                <span class="stat-label">Total Deletes:</span>
                <span class="stat-value"><?php echo $stats['deletes']; ?></span>
            </div>

            <div class="stat-row">
                <span class="stat-label">Hit Rate:</span>
                <span class="stat-value">
                    <strong><?php echo $stats['hit_rate']; ?>%</strong>
                    <?php if ($stats['hit_rate'] > 80): ?>
                        <span class="stat-good">🎉 Excelente</span>
                    <?php elseif ($stats['hit_rate'] > 50): ?>
                        <span class="stat-warning">✓ Bueno</span>
                    <?php else: ?>
                        <span class="stat-error">⚠ Mejorable</span>
                    <?php endif; ?>
                </span>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }

    private function renderActions()
    {
        ob_start();
        ?>
        <div class="stats-card">
            <h2>⚙️ Acciones</h2>
            
            <form method="POST" style="display: inline;">
                <input type="hidden" name="action" value="refresh">
                <button type="submit" class="btn btn-primary">🔄 Refrescar Página</button>
            </form>

            <form method="POST" style="display: inline;" onsubmit="return confirm('¿Limpiar el caché del usuario actual?');">
                <input type="hidden" name="action" value="clear_user">
                <button type="submit" class="btn btn-danger">🗑️ Limpiar Mi Caché</button>
            </form>

            <form method="POST" style="display: inline;" onsubmit="return confirm('¿Limpiar TODOS los cachés de usuarios? Esta acción afectará a todos los usuarios.');">
                <input type="hidden" name="action" value="clear_all">
                <button type="submit" class="btn btn-danger">💣 Limpiar Todo</button>
            </form>

            <p style="margin-top: 20px; color: #666; font-size: 14px;">
                <strong>Nota:</strong> El caché se refresca automáticamente cada 5 minutos o al modificar roles/permisos.
            </p>
        </div>
        <?php
        return ob_get_clean();
    }

    private function handleAction($action)
    {
        switch ($action) {
            case 'refresh':
                // Solo refrescar página
                header("Location: " . $_SERVER['PHP_SELF']);
                exit;
                
            case 'clear_user':
                DataAccessManager::get("session")->invalidateUserCache();
                echo '<div class="alert alert-success" style="margin: 20px; padding: 15px; background: #d4edda; color: #155724; border-radius: 4px;">✓ Caché del usuario limpiado</div>';
                break;
                
            case 'clear_all':
                DataAccessManager::get("session")->clearAllUserCaches();
                echo '<div class="alert alert-success" style="margin: 20px; padding: 15px; background: #d4edda; color: #155724; border-radius: 4px;">✓ Todos los cachés limpiados</div>';
                break;
        }
    }
}

