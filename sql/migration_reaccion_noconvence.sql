-- Migración: Agregar columna para reacción "No me convenció"
-- Ejecutar una sola vez en la base de datos

ALTER TABLE blog_articulos
ADD COLUMN reaccion_noconvence INT DEFAULT 0 AFTER reaccion_importante;
