-- ═══════════════════════════════════════════════════════════
-- PostgreSQL Initialization Script
-- Optimizaciones de rendimiento y extensiones
-- ═══════════════════════════════════════════════════════════

-- Crear extensión para búsqueda de texto (trigram)
-- Necesario para búsquedas rápidas con ILIKE
CREATE EXTENSION IF NOT EXISTS pg_trgm;

-- Extensión para funciones adicionales
CREATE EXTENSION IF NOT EXISTS "uuid-ossp";

-- Extensión para estadísticas
CREATE EXTENSION IF NOT EXISTS pg_stat_statements;

-- Configurar zona horaria
SET timezone = 'America/La_Paz';

-- Log de inicialización
DO $$
BEGIN
    RAISE NOTICE 'PostgreSQL optimizado para Monster Band inicializado correctamente';
END $$;
