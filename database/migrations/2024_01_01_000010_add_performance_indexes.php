<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * ═══════════════════════════════════════════════════════════
     * ÍNDICES DE RENDIMIENTO - Optimización de consultas
     * ═══════════════════════════════════════════════════════════
     */
    public function up(): void
    {
        // Función helper para crear índice si no existe
        $createIfNotExists = function($table, $column, $indexName = null) {
            $name = $indexName ?? "{$table}_{$column}_index";
            $columnStr = is_array($column) ? implode(', ', $column) : $column;
            DB::statement("CREATE INDEX IF NOT EXISTS {$name} ON {$table} ({$columnStr})");
        };

        // ─── ÍNDICES PARA USERS ───
        $createIfNotExists('users', 'activo');

        // ─── ÍNDICES PARA MIEMBROS ───
        $createIfNotExists('miembros', 'deleted_at');

        // ─── ÍNDICES PARA ASISTENCIAS ───
        $createIfNotExists('asistencias', 'estado');
        DB::statement("CREATE INDEX IF NOT EXISTS asistencias_miembro_estado_idx ON asistencias (miembro_id, estado)");
        DB::statement("CREATE INDEX IF NOT EXISTS asistencias_evento_estado_idx ON asistencias (evento_id, estado)");

        // ─── ÍNDICES PARA EVENTOS ───
        $createIfNotExists('eventos', 'fecha');
        $createIfNotExists('eventos', 'tipo');
        $createIfNotExists('eventos', 'estado');
        DB::statement("CREATE INDEX IF NOT EXISTS eventos_fecha_estado_idx ON eventos (fecha, estado)");
        DB::statement("CREATE INDEX IF NOT EXISTS eventos_tipo_fecha_idx ON eventos (tipo, fecha)");

        // ─── ÍNDICES PARA LIQUIDACIONES ───
        $createIfNotExists('liquidaciones', 'estado');

        // ─── ÍNDICES PARA PAGOS ───
        $createIfNotExists('pagos', 'estado');
        $createIfNotExists('pagos', 'fecha_pago');

        // ─── ÍNDICES PARA SECCIONES ───
        $createIfNotExists('secciones', 'activo');
        $createIfNotExists('secciones', 'orden');

        // ─── ÍNDICES PARA CATEGORIAS ───
        $createIfNotExists('categorias_salariales', 'activo');
        $createIfNotExists('categorias_salariales', 'orden');

        // ─── ÍNDICES PARA ROLES ───
        $createIfNotExists('roles', 'activo');
        $createIfNotExists('roles', 'nivel');

        // ─── ÍNDICE GIN PARA BÚSQUEDA DE TEXTO (PostgreSQL) ───
        // Primero verificar si la extensión pg_trgm existe
        try {
            DB::statement('CREATE EXTENSION IF NOT EXISTS pg_trgm');
            DB::statement('CREATE INDEX IF NOT EXISTS miembros_nombres_trgm_idx ON miembros USING gin (nombres gin_trgm_ops)');
            DB::statement('CREATE INDEX IF NOT EXISTS miembros_apellidos_trgm_idx ON miembros USING gin (apellidos gin_trgm_ops)');
            DB::statement('CREATE INDEX IF NOT EXISTS miembros_ci_numero_trgm_idx ON miembros USING gin (ci_numero gin_trgm_ops)');
        } catch (\Exception $e) {
            // Si falla la creación de índices GIN, continuamos sin ellos
            // Las búsquedas seguirán funcionando, solo más lentas
            \Log::warning('No se pudieron crear índices GIN: ' . $e->getMessage());
        }
    }

    public function down(): void
    {
        // Eliminar índices GIN
        DB::statement('DROP INDEX IF EXISTS miembros_nombres_trgm_idx');
        DB::statement('DROP INDEX IF EXISTS miembros_apellidos_trgm_idx');
        DB::statement('DROP INDEX IF EXISTS miembros_ci_numero_trgm_idx');

        // Eliminar índices compuestos
        DB::statement('DROP INDEX IF EXISTS asistencias_miembro_estado_idx');
        DB::statement('DROP INDEX IF EXISTS asistencias_evento_estado_idx');
        DB::statement('DROP INDEX IF EXISTS eventos_fecha_estado_idx');
        DB::statement('DROP INDEX IF EXISTS eventos_tipo_fecha_idx');

        // Eliminar índices simples
        DB::statement('DROP INDEX IF EXISTS users_activo_index');
        DB::statement('DROP INDEX IF EXISTS miembros_deleted_at_index');
        DB::statement('DROP INDEX IF EXISTS asistencias_estado_index');
        DB::statement('DROP INDEX IF EXISTS eventos_fecha_index');
        DB::statement('DROP INDEX IF EXISTS eventos_tipo_index');
        DB::statement('DROP INDEX IF EXISTS eventos_estado_index');
        DB::statement('DROP INDEX IF EXISTS liquidaciones_estado_index');
        DB::statement('DROP INDEX IF EXISTS pagos_estado_index');
        DB::statement('DROP INDEX IF EXISTS pagos_fecha_pago_index');
        DB::statement('DROP INDEX IF EXISTS secciones_activo_index');
        DB::statement('DROP INDEX IF EXISTS secciones_orden_index');
        DB::statement('DROP INDEX IF EXISTS categorias_salariales_activo_index');
        DB::statement('DROP INDEX IF EXISTS categorias_salariales_orden_index');
        DB::statement('DROP INDEX IF EXISTS roles_activo_index');
        DB::statement('DROP INDEX IF EXISTS roles_nivel_index');
    }
};
